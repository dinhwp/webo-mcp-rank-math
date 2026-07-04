<?php
/**
 * MigrationService — orchestrates the migrate-brand semantic action.
 *
 * Scans all readable Rank Math option groups for occurrences of the old brand string,
 * builds a diff, and replaces them safely if dry_run=false.
 *
 * Workflow:
 *  1. Validate input (BrandProfileValidator).
 *  2. Read ALL Rank Math option groups (OptionsRepository).
 *  3. Deep-scan for old brand string.
 *  4. Build replacement patch and diff.
 *  5. Create snapshot.
 *  6. If dry_run=true → return diff only.
 *  7. If dry_run=false → write patch, flush cache, verify, rollback on error.
 *  8. Return standardised mutation response.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WeboMcpRankMath_MigrationService' ) ) {

	/**
	 * Service for migrating a brand name across all Rank Math options.
	 */
	class WeboMcpRankMath_MigrationService {

		/**
		 * Execute the migrate-brand action.
		 *
		 * @param array<string,mixed> $input  Tool input: from, to, dry_run.
		 * @return array<string,mixed>|\WP_Error
		 */
		public static function migrate( $input ) {
			// 1. Validate.
			$valid = WeboMcpRankMath_BrandProfileValidator::validate_migrate_brand( $input );
			if ( is_wp_error( $valid ) ) {
				return new WP_Error(
					'webo_mcp_migrate_brand_validation',
					implode( ' | ', WeboMcpRankMath_BrandProfileValidator::flatten_errors( $valid ) ),
					array( 'errors' => WeboMcpRankMath_BrandProfileValidator::flatten_errors( $valid ) )
				);
			}

			$from    = (string) $input['from'];
			$to      = (string) $input['to'];
			$dry_run = isset( $input['dry_run'] ) ? webo_mcp_is_truthy( $input['dry_run'] ) : true;

			// 2. Read all option groups.
			$all_groups  = WeboMcpRankMath_OptionsRepository::option_group_map();
			$current_all = array();
			foreach ( $all_groups as $short => $wp_name ) {
				$current_all[ $short ] = WeboMcpRankMath_OptionsRepository::get_group( $short );
			}

			// 3 & 4. Deep-scan and build patch.
			$patch          = array();
			$diff           = array();
			$changed_fields = array();

			foreach ( $current_all as $group => $options ) {
				$patched_group = self::replace_in_value( $options, $from, $to );
				$group_diff    = self::diff_values( $options, $patched_group, $group );
				if ( ! empty( $group_diff ) ) {
					$patch[ $group ] = $patched_group;
					$diff            = array_merge( $diff, $group_diff );
					foreach ( $group_diff as $item ) {
						if ( ! empty( $item['changed'] ) ) {
							$changed_fields[] = $group . '.' . $item['key'];
						}
					}
				}
			}

			$planned_count = count( $changed_fields );

			// 5. Dry run.
			if ( $dry_run ) {
				return webo_mcp_mutation_response( array(
					'dry_run'       => true,
					'would_change'  => $planned_count > 0,
					'planned_count' => $planned_count,
					'diff'          => $diff,
					'context'       => array(
						'action'         => 'migrate-brand',
						'from'           => $from,
						'to'             => $to,
						'changed_fields' => $changed_fields,
						'warnings'       => $planned_count === 0
							? array( "No Rank Math option values contain '{$from}'." )
							: array(),
					),
				) );
			}

			if ( $planned_count === 0 ) {
				return webo_mcp_mutation_response( array(
					'dry_run'       => false,
					'changed'       => false,
					'changed_count' => 0,
					'diff'          => array(),
					'context'       => array(
						'action'   => 'migrate-brand',
						'from'     => $from,
						'to'       => $to,
						'warnings' => array( "No Rank Math option values contain '{$from}'. Nothing changed." ),
					),
				) );
			}

			// 6. Create snapshot.
			$option_names = array();
			foreach ( array_keys( $patch ) as $group ) {
				$map = WeboMcpRankMath_OptionsRepository::option_group_map();
				if ( isset( $map[ $group ] ) ) {
					$option_names[] = $map[ $group ];
				}
			}
			$snapshot = WeboMcpRankMath_SnapshotService::create( "migrate-brand:{$from}->{$to}", $option_names );

			// 7. Write.
			$write_error = null;
			try {
				foreach ( $patch as $group => $patched_options ) {
					$wp_name = $all_groups[ $group ] ?? null;
					if ( null === $wp_name ) {
						continue;
					}
					$success = WeboMcpRankMath_OptionsRepository::set( $wp_name, $patched_options );
					if ( ! $success ) {
						throw new RuntimeException( "Failed to write option group: {$group}" );
					}
				}
			} catch ( Exception $e ) {
				$write_error = $e->getMessage();
			}

			if ( null !== $write_error ) {
				$rollback = WeboMcpRankMath_SnapshotService::rollback( $snapshot['snapshot_id'] );
				return new WP_Error(
					'webo_mcp_migrate_brand_write_failed',
					$write_error,
					array(
						'rollback'    => $rollback,
						'snapshot_id' => $snapshot['snapshot_id'],
					)
				);
			}

			// Flush sitemap cache.
			webo_rank_math_flush_sitemap_cache();

			// 8. Return.
			return webo_mcp_mutation_response( array(
				'dry_run'       => false,
				'changed'       => true,
				'changed_count' => $planned_count,
				'diff'          => $diff,
				'context'       => array(
					'action'         => 'migrate-brand',
					'from'           => $from,
					'to'             => $to,
					'changed_fields' => $changed_fields,
					'snapshot_id'    => $snapshot['snapshot_id'],
					'warnings'       => array(),
				),
			) );
		}


		/**
		 * Execute a multi-value brand cleanup across all Rank Math option groups.
		 *
		 * @param array<string,mixed> $input Tool input: replacements, old_brand, brand_name, old_url, url, old_logo, logo, dry_run.
		 * @return array<string,mixed>|\WP_Error
		 */
		public static function cleanup( $input ) {
			$replacements = self::build_replacements( $input );
			if ( empty( $replacements ) ) {
				return new WP_Error(
					'webo_mcp_brand_cleanup_empty',
					'Provide replacements, old_values, old_brand + brand_name, old_url + url, old_logo + logo, or old_social_profiles + social.'
				);
			}

			$dry_run = isset( $input['dry_run'] ) ? webo_mcp_is_truthy( $input['dry_run'] ) : true;

			$all_groups  = WeboMcpRankMath_OptionsRepository::option_group_map();
			$current_all = array();
			foreach ( $all_groups as $short => $wp_name ) {
				$current_all[ $short ] = WeboMcpRankMath_OptionsRepository::get_group( $short );
			}

			$patch          = array();
			$diff           = array();
			$changed_fields = array();

			foreach ( $current_all as $group => $options ) {
				$patched_group = self::replace_many_in_value( $options, $replacements );
				$group_diff    = self::diff_values( $options, $patched_group, $group );
				if ( ! empty( $group_diff ) ) {
					$patch[ $group ] = $patched_group;
					$diff            = array_merge( $diff, $group_diff );
					foreach ( $group_diff as $item ) {
						if ( ! empty( $item['changed'] ) ) {
							$changed_fields[] = $group . '.' . $item['key'];
						}
					}
				}
			}

			$planned_count = count( $changed_fields );
			$context       = array(
				'action'            => 'brand-cleanup',
				'replacement_count' => count( $replacements ),
				'changed_fields'    => $changed_fields,
			);

			if ( $dry_run ) {
				return webo_mcp_mutation_response( array(
					'dry_run'       => true,
					'would_change'  => $planned_count > 0,
					'planned_count' => $planned_count,
					'diff'          => $diff,
					'context'       => $context,
				) );
			}

			if ( 0 === $planned_count ) {
				$context['warnings'] = array( 'No Rank Math option values matched the provided cleanup replacements.' );
				return webo_mcp_mutation_response( array(
					'dry_run'       => false,
					'changed'       => false,
					'changed_count' => 0,
					'diff'          => array(),
					'context'       => $context,
				) );
			}

			$option_names = array();
			foreach ( array_keys( $patch ) as $group ) {
				if ( isset( $all_groups[ $group ] ) ) {
					$option_names[] = $all_groups[ $group ];
				}
			}
			$snapshot = WeboMcpRankMath_SnapshotService::create( 'brand-cleanup', $option_names );

			$write_error = null;
			try {
				foreach ( $patch as $group => $patched_options ) {
					$wp_name = $all_groups[ $group ] ?? null;
					if ( null === $wp_name ) {
						continue;
					}
					$success = WeboMcpRankMath_OptionsRepository::set( $wp_name, $patched_options );
					if ( ! $success ) {
						throw new RuntimeException( "Failed to write option group: {$group}" );
					}
				}
			} catch ( Exception $e ) {
				$write_error = $e->getMessage();
			}

			if ( null !== $write_error ) {
				$rollback = WeboMcpRankMath_SnapshotService::rollback( $snapshot['snapshot_id'] );
				return new WP_Error(
					'webo_mcp_brand_cleanup_write_failed',
					$write_error,
					array(
						'rollback'    => $rollback,
						'snapshot_id' => $snapshot['snapshot_id'],
					)
				);
			}

			webo_rank_math_flush_sitemap_cache();
			$context['snapshot_id'] = $snapshot['snapshot_id'];

			return webo_mcp_mutation_response( array(
				'dry_run'       => false,
				'changed'       => true,
				'changed_count' => $planned_count,
				'diff'          => $diff,
				'context'       => $context,
			) );
		}

		// -------------------------------------------------------------------------
		// Private helpers
		// -------------------------------------------------------------------------


		/**
		 * Build an old=>new replacement map from generic cleanup input.
		 *
		 * @param array<string,mixed> $input Tool input.
		 * @return array<string,string>
		 */
		private static function build_replacements( $input ) {
			$input = (array) $input;
			if ( isset( $input['brand_cleanup'] ) && is_array( $input['brand_cleanup'] ) ) {
				$input = array_merge( $input['brand_cleanup'], $input );
				unset( $input['brand_cleanup'] );
			}

			$replacements = array();
			if ( isset( $input['replacements'] ) && is_array( $input['replacements'] ) ) {
				foreach ( $input['replacements'] as $from => $to ) {
					self::add_replacement( $replacements, $from, $to );
				}
			}

			if ( isset( $input['old_values'] ) && is_array( $input['old_values'] ) ) {
				foreach ( $input['old_values'] as $from => $to ) {
					if ( is_int( $from ) ) {
						$to = self::replacement_target_for_old_value( (string) $to, $input );
						self::add_replacement( $replacements, $input['old_values'][ $from ], $to );
					} else {
						self::add_replacement( $replacements, $from, $to );
					}
				}
			}

			self::add_replacement( $replacements, $input['old_brand'] ?? ( $input['from'] ?? null ), $input['brand_name'] ?? ( $input['to'] ?? null ) );
			self::add_replacement( $replacements, $input['old_organization_name'] ?? null, $input['brand_name'] ?? null );
			self::add_replacement( $replacements, $input['old_company_name'] ?? null, $input['brand_name'] ?? null );
			self::add_replacement( $replacements, $input['old_person_name'] ?? null, $input['person_name'] ?? ( $input['brand_name'] ?? null ) );
			self::add_replacement( $replacements, $input['old_publisher_name'] ?? null, $input['publisher_name'] ?? ( $input['brand_name'] ?? null ) );
			self::add_replacement( $replacements, $input['old_url'] ?? null, $input['url'] ?? null );
			self::add_replacement( $replacements, $input['old_logo'] ?? null, $input['logo'] ?? null );
			self::add_replacement( $replacements, $input['old_open_graph_image'] ?? null, $input['open_graph_image'] ?? ( $input['logo'] ?? null ) );
			self::add_replacement( $replacements, $input['old_publisher_logo'] ?? null, $input['publisher_logo'] ?? ( $input['logo'] ?? null ) );
			self::add_replacement( $replacements, $input['old_email_report_logo'] ?? null, $input['email_report_logo'] ?? ( $input['logo'] ?? null ) );
			self::add_replacement( $replacements, $input['old_email'] ?? null, $input['email'] ?? ( $input['contact_email'] ?? null ) );
			self::add_replacement( $replacements, $input['old_contact_email'] ?? null, $input['contact_email'] ?? ( $input['email'] ?? null ) );

			self::add_nested_replacements( $replacements, $input['old_organization'] ?? null, $input['organization'] ?? null, array(
				'name' => 'brand_name',
				'url'  => 'url',
				'logo' => 'logo',
			), $input );
			self::add_nested_replacements( $replacements, $input['old_person'] ?? null, $input['person'] ?? null, array(
				'name' => 'person_name',
				'url'  => 'url',
				'image'=> 'logo',
			), $input );
			self::add_nested_replacements( $replacements, $input['old_publisher'] ?? null, $input['publisher'] ?? null, array(
				'name' => 'publisher_name',
				'logo' => 'publisher_logo',
				'image'=> 'publisher_logo',
			), $input );
			self::add_nested_replacements( $replacements, $input['old_contact'] ?? null, $input['contact'] ?? null, array(
				'email' => 'contact_email',
				'phone' => 'phone',
			), $input );

			$old_social = isset( $input['old_social_profiles'] ) && is_array( $input['old_social_profiles'] ) ? $input['old_social_profiles'] : array();
			$new_social = isset( $input['social'] ) && is_array( $input['social'] ) ? $input['social'] : array();
			foreach ( array( 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'github', 'pinterest' ) as $field ) {
				self::add_replacement( $replacements, $input[ "old_{$field}" ] ?? ( $old_social[ $field ] ?? null ), $input[ $field ] ?? ( $new_social[ $field ] ?? null ) );
			}
			self::add_pairwise_replacements( $replacements, $input['old_same_as'] ?? null, $input['same_as'] ?? ( $input['sameAs'] ?? null ) );

			return $replacements;
		}

		/**
		 * Add replacements from matching keys in old/new nested objects.
		 *
		 * @param array<string,string> $replacements Replacement map.
		 * @param mixed                $old          Old nested object.
		 * @param mixed                $new          New nested object.
		 * @param array<string,string> $fallbacks    old key => root input fallback key.
		 * @param array<string,mixed>  $input        Root input.
		 */
		private static function add_nested_replacements( &$replacements, $old, $new, $fallbacks, $input ) {
			if ( ! is_array( $old ) ) {
				return;
			}
			$new = is_array( $new ) ? $new : array();
			foreach ( $old as $key => $old_value ) {
				$fallback_key = $fallbacks[ $key ] ?? $key;
				$new_value    = $new[ $key ] ?? ( $input[ $fallback_key ] ?? null );
				self::add_replacement( $replacements, $old_value, $new_value );
			}
		}

		/**
		 * Add replacements from two same-length lists, used for sameAs/profile URL arrays.
		 *
		 * @param array<string,string> $replacements Replacement map.
		 * @param mixed                $old_values   Old values.
		 * @param mixed                $new_values   New values.
		 */
		private static function add_pairwise_replacements( &$replacements, $old_values, $new_values ) {
			if ( ! is_array( $old_values ) || ! is_array( $new_values ) ) {
				return;
			}
			$new_values = array_values( $new_values );
			foreach ( array_values( $old_values ) as $index => $old_value ) {
				self::add_replacement( $replacements, $old_value, $new_values[ $index ] ?? null );
			}
		}

		/**
		 * Add one string replacement if it is valid.
		 *
		 * @param array<string,string> $replacements Replacement map.
		 * @param mixed                $from         Old value.
		 * @param mixed                $to           New value.
		 */
		private static function add_replacement( &$replacements, $from, $to ) {
			$from = is_scalar( $from ) ? (string) $from : '';
			$to   = is_scalar( $to ) ? (string) $to : '';
			if ( '' === $from || $from === $to ) {
				return;
			}
			$replacements[ self::normalize_replacement_value( $from ) ] = self::normalize_replacement_value( $to );
		}

		/**
		 * Normalize URL-like replacement values so cleanup does not introduce accidental //.
		 *
		 * @param string $value Replacement value.
		 * @return string
		 */
		private static function normalize_replacement_value( $value ) {
			$value = trim( (string) $value );
			if ( preg_match( '#^https?://#i', $value ) ) {
				$value = preg_replace( '#(?<!:)/{2,}#', '/', $value );
				return esc_url_raw( $value );
			}
			return $value;
		}

		/**
		 * Guess a target for old_values list entries when no explicit replacement is provided.
		 *
		 * @param string              $old_value Old value.
		 * @param array<string,mixed> $input     Tool input.
		 * @return string
		 */
		private static function replacement_target_for_old_value( $old_value, $input ) {
			if ( filter_var( $old_value, FILTER_VALIDATE_URL ) && ! empty( $input['url'] ) ) {
				return (string) $input['url'];
			}
			if ( ! empty( $input['brand_name'] ) ) {
				return (string) $input['brand_name'];
			}
			return '';
		}

		/**
		 * Recursively apply multiple string replacements to a value.
		 *
		 * @param mixed                $value        Input value.
		 * @param array<string,string> $replacements Replacement map.
		 * @return mixed
		 */
		private static function replace_many_in_value( $value, $replacements ) {
			foreach ( $replacements as $from => $to ) {
				$value = self::replace_in_value( $value, $from, $to );
			}
			return $value;
		}

		/**
		 * Recursively replace all string occurrences of $from with $to in a value.
		 *
		 * Handles: string, array, nested arrays.  Skips non-scalar leaf types.
		 *
		 * @param mixed  $value  Input value.
		 * @param string $from   String to find.
		 * @param string $to     Replacement string.
		 * @return mixed  Replaced value (same type as input).
		 */
		private static function replace_in_value( $value, $from, $to ) {
			if ( is_string( $value ) ) {
				if ( self::should_replace_with_token_boundaries( $from ) ) {
					return preg_replace( '/(?<![A-Za-z0-9])' . preg_quote( $from, '/' ) . '(?![A-Za-z0-9])/', $to, $value );
				}

				return str_replace( $from, $to, $value );
			}

			if ( is_array( $value ) ) {
				$result = array();
				foreach ( $value as $k => $v ) {
					$result[ $k ] = self::replace_in_value( $v, $from, $to );
				}
				return $result;
			}

			return $value;
		}

		/**
		 * Plain brand names should not rewrite usernames/handles such as Webovn.
		 *
		 * @param string $from String to find.
		 * @return bool
		 */
		private static function should_replace_with_token_boundaries( $from ) {
			return (bool) preg_match( '/^[A-Za-z0-9][A-Za-z0-9 _.-]*[A-Za-z0-9]$|^[A-Za-z0-9]$/', $from )
				&& false === strpos( $from, '://' )
				&& false === strpos( $from, '/' )
				&& false === strpos( $from, '@' );
		}

		/**
		 * Produce a flat diff for top-level keys in two versions of an option group.
		 *
		 * For nested arrays it compares the serialised representation.
		 *
		 * @param array  $before       Original option group.
		 * @param array  $after        Replaced option group.
		 * @param string $group        Short group name for labelling.
		 * @return array<string,mixed>[]
		 */
		private static function diff_values( $before, $after, $group ) {
			$diff = array();
			$all_keys = array_unique( array_merge( array_keys( (array) $before ), array_keys( (array) $after ) ) );

			foreach ( $all_keys as $key ) {
				$old = $before[ $key ] ?? null;
				$new = $after[ $key ] ?? null;

				// Compare serialised forms to detect deep changes in arrays.
				$old_str = is_array( $old ) ? serialize( $old ) : (string) $old;
				$new_str = is_array( $new ) ? serialize( $new ) : (string) $new;

				if ( $old_str !== $new_str ) {
					$diff[] = array(
						'option_group' => $group,
						'key'          => $key,
						'before'       => $old,
						'after'        => $new,
						'changed'      => true,
					);
				}
			}

			return $diff;
		}
	}
}
