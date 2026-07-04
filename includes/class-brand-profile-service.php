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
			// 1. Validate.
			$valid = WeboMcpRankMath_BrandProfileValidator::validate_brand_profile( $input );
			if ( is_wp_error( $valid ) ) {
				return new WP_Error(
					'webo_mcp_brand_profile_validation',
					implode( ' | ', WeboMcpRankMath_BrandProfileValidator::flatten_errors( $valid ) ),
					array( 'errors' => WeboMcpRankMath_BrandProfileValidator::flatten_errors( $valid ) )
				);
			}

			$input   = WeboMcpRankMath_BrandProfileMapper::normalize_input( $input );
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
