<?php
/**
 * BrandProfileService — orchestrates the apply-brand-profile semantic action.
 *
 * Workflow:
 *  1. Validate input (BrandProfileValidator).
 *  2. Read current Rank Math options (OptionsRepository).
 *  3. Create snapshot (SnapshotService).
 *  4. Build patch (BrandProfileMapper).
 *  5. If dry_run=true → return diff without writing.
 *  6. If dry_run=false → write options, flush sitemap/cache, verify, rollback on error.
 *  7. Return standardised mutation response.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WeboMcpRankMath_BrandProfileService' ) ) {

	/**
	 * Service for applying a brand profile to Rank Math options.
	 */
	class WeboMcpRankMath_BrandProfileService {

		/**
		 * Execute the apply-brand-profile action.
		 *
		 * @param array<string,mixed> $input  Tool input including optional dry_run flag.
		 * @return array<string,mixed>|\WP_Error
		 */
		public static function apply( $input ) {
			$input = WeboMcpRankMath_BrandProfileMapper::normalize_input( $input );

			// 1. Validate.
			$valid = WeboMcpRankMath_BrandProfileValidator::validate_brand_profile( $input );
			if ( is_wp_error( $valid ) ) {
				return new WP_Error(
					'webo_mcp_brand_profile_validation',
					implode( ' | ', WeboMcpRankMath_BrandProfileValidator::flatten_errors( $valid ) ),
					array( 'errors' => WeboMcpRankMath_BrandProfileValidator::flatten_errors( $valid ) )
				);
			}

			$dry_run = isset( $input['dry_run'] ) ? webo_mcp_is_truthy( $input['dry_run'] ) : true;

			// 2. Build patch (Mapper).
			$patch = WeboMcpRankMath_BrandProfileMapper::map( $input );

			// 3. Read current state for only the option groups this profile will touch.
			$groups_needed = array_keys( $patch );
			$current       = array();
			foreach ( $groups_needed as $group ) {
				$current[ $group ] = WeboMcpRankMath_OptionsRepository::get_group( $group );
			}

			// 4. Build diff.
			$diff           = WeboMcpRankMath_BrandProfileMapper::build_diff( $patch, $current );
			$changed_fields = WeboMcpRankMath_BrandProfileMapper::changed_fields( $diff );
			$planned_count  = count( $changed_fields );

			// 5. Dry run — return diff only.
			if ( $dry_run ) {
				return webo_mcp_mutation_response( array(
					'dry_run'       => true,
					'would_change'  => $planned_count > 0,
					'planned_count' => $planned_count,
					'diff'          => $diff,
					'context'       => array(
						'action'         => 'apply-brand-profile',
						'profile'        => $input['profile'] ?? 'organization',
						'brand_name'     => $input['brand_name'] ?? '',
						'changed_fields' => $changed_fields,
						'warnings'       => self::build_warnings( $input ),
					),
				) );
			}

			// 6a. Create snapshot before mutating.
			$option_names = array();
			foreach ( $groups_needed as $group ) {
				$map = WeboMcpRankMath_OptionsRepository::option_group_map();
				if ( isset( $map[ $group ] ) ) {
					$option_names[] = $map[ $group ];
				}
			}
			$snapshot = WeboMcpRankMath_SnapshotService::create( 'apply-brand-profile', $option_names );

			// 6b. Write options via Repository.
			$write_error = null;
			try {
				foreach ( $patch as $group => $keys ) {
					$success = WeboMcpRankMath_OptionsRepository::patch_group( $group, $keys );
					if ( ! $success ) {
						throw new RuntimeException( "Failed to write option group: {$group}" );
					}
				}
			} catch ( Exception $e ) {
				$write_error = $e->getMessage();
			}

			// 6c. Rollback on error.
			if ( null !== $write_error ) {
				$rollback = WeboMcpRankMath_SnapshotService::rollback( $snapshot['snapshot_id'] );
				return new WP_Error(
					'webo_mcp_brand_profile_write_failed',
					$write_error,
					array(
						'rollback'    => $rollback,
						'snapshot_id' => $snapshot['snapshot_id'],
					)
				);
			}

			// 6d. Flush sitemap cache.
			webo_rank_math_flush_sitemap_cache();

			// 6e. Verify by re-reading after write.
			$after = array();
			foreach ( $groups_needed as $group ) {
				$after[ $group ] = WeboMcpRankMath_OptionsRepository::get_group( $group );
			}

			// Build post-write diff for verification.
			$actual_diff    = WeboMcpRankMath_BrandProfileMapper::build_diff( $patch, $after );
			$still_pending  = WeboMcpRankMath_BrandProfileMapper::changed_fields( $actual_diff );

			if ( ! empty( $still_pending ) ) {
				// Some fields did not save — partial success; still report.
				$warnings[] = sprintf(
					'%d field(s) may not have saved correctly: %s',
					count( $still_pending ),
					implode( ', ', $still_pending )
				);
			} else {
				$warnings = array();
			}

			// 7. Return full response.
			return webo_mcp_mutation_response( array(
				'dry_run'       => false,
				'changed'       => $planned_count > 0,
				'changed_count' => $planned_count,
				'diff'          => $diff,
				'context'       => array(
					'action'         => 'apply-brand-profile',
					'profile'        => $input['profile'] ?? 'organization',
					'brand_name'     => $input['brand_name'] ?? '',
					'changed_fields' => $changed_fields,
					'snapshot_id'    => $snapshot['snapshot_id'],
					'warnings'       => array_merge( self::build_warnings( $input ), $warnings ?? array() ),
				),
			) );
		}

		/**
		 * Execute a complete brand profile: apply the canonical profile and clean up
		 * leftover old brand/entity/social/contact values across all Rank Math options.
		 *
		 * @param array<string,mixed> $input Tool input.
		 * @return array<string,mixed>|\WP_Error
		 */
		public static function complete( $input ) {
			$input   = WeboMcpRankMath_BrandProfileMapper::normalize_input( $input );
			$dry_run = isset( $input['dry_run'] ) ? webo_mcp_is_truthy( $input['dry_run'] ) : true;

			$profile_args            = $input;
			$profile_args['dry_run'] = $dry_run;
			$profile                 = self::apply( $profile_args );
			if ( is_wp_error( $profile ) ) {
				return $profile;
			}

			$cleanup_args            = self::build_complete_cleanup_args( $input );
			$cleanup_args['dry_run'] = $dry_run;
			$cleanup                 = null;
			$warnings                = array();

			if ( self::has_cleanup_signal( $cleanup_args ) ) {
				$cleanup = WeboMcpRankMath_MigrationService::cleanup( $cleanup_args );
				if ( is_wp_error( $cleanup ) ) {
					return $cleanup;
				}
			} else {
				$warnings[] = 'No old brand/entity values were provided for cleanup.';
			}

			$profile_changed = self::response_changed( $profile );
			$cleanup_changed = is_array( $cleanup ) ? self::response_changed( $cleanup ) : false;
			$planned_count   = self::response_count( $profile ) + ( is_array( $cleanup ) ? self::response_count( $cleanup ) : 0 );

			return webo_mcp_mutation_response( array(
				'dry_run'       => $dry_run,
				'would_change'  => $profile_changed || $cleanup_changed,
				'changed'       => $profile_changed || $cleanup_changed,
				'planned_count' => $planned_count,
				'changed_count' => $planned_count,
				'context'       => array(
					'action'         => 'complete-brand-profile',
					'profile'        => $input['profile'] ?? 'organization',
					'brand_name'     => $input['brand_name'] ?? '',
					'profile_result' => $profile,
					'cleanup_result' => $cleanup,
					'changed_fields' => array_values( array_unique( array_merge(
						self::response_changed_fields( $profile ),
						is_array( $cleanup ) ? self::response_changed_fields( $cleanup ) : array()
					) ) ),
					'warnings'       => $warnings,
				),
			) );
		}

		/**
		 * Build a cleanup payload from a complete profile request.
		 *
		 * @param array<string,mixed> $input Normalized input.
		 * @return array<string,mixed>
		 */
		private static function build_complete_cleanup_args( $input ) {
			$args = $input;
			if ( isset( $input['old_brand'] ) && ! isset( $args['from'] ) ) {
				$args['from'] = $input['old_brand'];
			}
			if ( isset( $input['brand_name'] ) && ! isset( $args['to'] ) ) {
				$args['to'] = $input['brand_name'];
			}
			if ( ! isset( $args['old_contact_email'] ) && isset( $input['old_contact']['email'] ) ) {
				$args['old_contact_email'] = $input['old_contact']['email'];
			}
			if ( ! isset( $args['old_email'] ) && isset( $input['old_contact']['email'] ) ) {
				$args['old_email'] = $input['old_contact']['email'];
			}
			if ( ! isset( $args['old_publisher_logo'] ) && isset( $input['old_publisher']['logo'] ) ) {
				$args['old_publisher_logo'] = $input['old_publisher']['logo'];
			}
			if ( ! isset( $args['old_publisher_name'] ) && isset( $input['old_publisher']['name'] ) ) {
				$args['old_publisher_name'] = $input['old_publisher']['name'];
			}
			return $args;
		}

		/**
		 * Check whether a payload has enough old values for cleanup.
		 *
		 * @param array<string,mixed> $input Cleanup input.
		 * @return bool
		 */
		private static function has_cleanup_signal( $input ) {
			foreach ( array(
				'brand_cleanup', 'replacements', 'old_values', 'old_brand', 'from', 'old_url',
				'old_logo', 'old_open_graph_image', 'old_publisher_logo', 'old_email_report_logo',
				'old_email', 'old_contact_email', 'old_social_profiles', 'old_same_as',
				'old_organization', 'old_person', 'old_publisher', 'old_contact',
			) as $key ) {
				if ( ! empty( $input[ $key ] ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Extract changed fields from a nested mutation response.
		 *
		 * @param array<string,mixed> $response Mutation response.
		 * @return string[]
		 */
		private static function response_changed_fields( $response ) {
			if ( ! is_array( $response ) ) {
				return array();
			}
			return isset( $response['changed_fields'] ) && is_array( $response['changed_fields'] )
				? $response['changed_fields']
				: array();
		}

		/**
		 * Determine if a nested mutation response changed or would change anything.
		 *
		 * @param array<string,mixed> $response Mutation response.
		 * @return bool
		 */
		private static function response_changed( $response ) {
			if ( ! is_array( $response ) ) {
				return false;
			}
			if ( isset( $response['would_change'] ) ) {
				return (bool) $response['would_change'];
			}
			if ( isset( $response['changed'] ) ) {
				return (bool) $response['changed'];
			}
			return self::response_count( $response ) > 0;
		}

		/**
		 * Extract planned/changed count from a nested mutation response.
		 *
		 * @param array<string,mixed> $response Mutation response.
		 * @return int
		 */
		private static function response_count( $response ) {
			if ( ! is_array( $response ) ) {
				return 0;
			}
			if ( isset( $response['planned_count'] ) ) {
				return (int) $response['planned_count'];
			}
			if ( isset( $response['changed_count'] ) ) {
				return (int) $response['changed_count'];
			}
			return count( self::response_changed_fields( $response ) );
		}

		/**
		 * Build context-aware warnings (missing recommended fields, etc.).
		 *
		 * @param array<string,mixed> $input
		 * @return string[]
		 */
		private static function build_warnings( $input ) {
			$warnings = array();
			if ( empty( $input['url'] ) ) {
				$warnings[] = 'url is not set — Knowledge Graph entity URL will be empty.';
			}
			if ( empty( $input['description'] ) ) {
				$warnings[] = 'description is not set — Knowledge Graph description will be empty.';
			}
			if ( empty( $input['facebook'] ) && empty( $input['twitter'] ) ) {
				$warnings[] = 'No social profile URLs provided — sameAs will be empty.';
			}
			return $warnings;
		}
	}
}
