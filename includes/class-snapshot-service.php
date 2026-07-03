<?php
/**
 * SnapshotService — creates, reads, and rolls back point-in-time snapshots of Rank Math options.
 *
 * Snapshots are stored as WordPress transients (prefixed webo_rm_snap_) and expire after 24 hours
 * by default. They are created automatically before every large semantic mutation so the user can
 * roll back without data loss.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WeboMcpRankMath_SnapshotService' ) ) {

	/**
	 * Manages point-in-time snapshots of Rank Math WordPress options.
	 */
	class WeboMcpRankMath_SnapshotService {

		/**
		 * Transient prefix for all snapshots.
		 */
		const PREFIX = 'webo_rm_snap_';

		/**
		 * Default TTL for stored snapshots (seconds).  24 hours.
		 */
		const DEFAULT_TTL = 86400;

		/**
		 * Create a snapshot of the specified Rank Math option names.
		 *
		 * @param string   $label        Human-readable label for the snapshot.
		 * @param string[] $option_names WP option names to capture. NULL = all default groups.
		 * @param int      $ttl          Seconds before the snapshot expires. 0 = DEFAULT_TTL.
		 * @return array{snapshot_id:string,label:string,captured_at:string,option_names:string[]}
		 */
		public static function create( $label = 'semantic-mutation', $option_names = null, $ttl = 0 ) {
			if ( null === $option_names ) {
				$option_names = array_merge(
					array( 'rank_math_modules' ),
					array_values( WeboMcpRankMath_OptionsRepository::option_group_map() )
				);
			}

			$data    = array();
			foreach ( $option_names as $name ) {
				$data[ $name ] = get_option( $name, null );
			}

			$id          = self::PREFIX . substr( md5( microtime( true ) . wp_rand() ), 0, 12 );
			$captured_at = current_time( 'c' );
			$payload     = array(
				'snapshot_id'  => $id,
				'label'        => sanitize_text_field( (string) $label ),
				'captured_at'  => $captured_at,
				'option_names' => array_values( $option_names ),
				'data'         => $data,
			);

			set_transient( $id, $payload, $ttl > 0 ? (int) $ttl : self::DEFAULT_TTL );

			// Return a public envelope without the raw data (keep it internal).
			return array(
				'snapshot_id'  => $id,
				'label'        => $payload['label'],
				'captured_at'  => $captured_at,
				'option_names' => $payload['option_names'],
			);
		}

		/**
		 * Retrieve a snapshot payload by ID.
		 *
		 * @param string $snapshot_id  Snapshot transient key.
		 * @return array|null  Full payload including 'data', or null if not found / expired.
		 */
		public static function get( $snapshot_id ) {
			$payload = get_transient( (string) $snapshot_id );
			return is_array( $payload ) ? $payload : null;
		}

		/**
		 * Roll back all option values captured in a snapshot.
		 *
		 * @param string $snapshot_id  Snapshot transient key.
		 * @param bool   $delete       Whether to delete the snapshot after a successful rollback.
		 * @return array{success:bool,rolled_back:string[],snapshot_id:string,error?:string}
		 */
		public static function rollback( $snapshot_id, $delete = true ) {
			$payload = self::get( $snapshot_id );

			if ( null === $payload ) {
				return array(
					'success'     => false,
					'snapshot_id' => (string) $snapshot_id,
					'error'       => 'Snapshot not found or expired.',
				);
			}

			$data        = $payload['data'] ?? array();
			$rolled_back = array();

			foreach ( $data as $name => $value ) {
				if ( null === $value ) {
					delete_option( $name );
				} else {
					update_option( $name, $value );
				}
				$rolled_back[] = $name;
			}

			if ( $delete ) {
				delete_transient( $snapshot_id );
			}

			return array(
				'success'      => true,
				'snapshot_id'  => $snapshot_id,
				'rolled_back'  => $rolled_back,
				'captured_at'  => $payload['captured_at'] ?? null,
				'label'        => $payload['label'] ?? '',
			);
		}

		/**
		 * Delete a snapshot without rolling it back.
		 *
		 * @param string $snapshot_id
		 * @return bool
		 */
		public static function delete( $snapshot_id ) {
			return delete_transient( (string) $snapshot_id );
		}
	}
}
