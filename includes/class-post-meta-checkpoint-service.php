<?php
/**
 * Post meta checkpoints for Rank Math MCP writes.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WeboMcpRankMath_PostMetaCheckpointService' ) ) {
	/**
	 * Stores short-lived checkpoints for Rank Math post meta updates.
	 */
	class WeboMcpRankMath_PostMetaCheckpointService {
		const PREFIX      = 'webo_rm_meta_cp_';
		const DEFAULT_TTL = 86400;

		/**
		 * @param string                $label Checkpoint label.
		 * @param array<int,int|string> $post_keys Map of post_id => meta keys.
		 * @param int                   $ttl TTL in seconds.
		 * @return array<string,mixed>
		 */
		public static function create( $label, $post_keys, $ttl = 0 ) {
			$data = array();
			foreach ( (array) $post_keys as $post_id => $keys ) {
				$post_id = absint( $post_id );
				if ( $post_id < 1 ) {
					continue;
				}

				$data[ $post_id ] = array();
				foreach ( array_values( array_unique( array_filter( (array) $keys ) ) ) as $key ) {
					$key = (string) $key;
					if ( '' === $key ) {
						continue;
					}

					$exists = function_exists( 'metadata_exists' )
						? metadata_exists( 'post', $post_id, $key )
						: true;

					$data[ $post_id ][ $key ] = array(
						'exists' => (bool) $exists,
						'value'  => get_post_meta( $post_id, $key, true ),
					);
				}
			}

			$id          = self::PREFIX . substr( md5( microtime( true ) . wp_rand() ), 0, 12 );
			$captured_at = current_time( 'c' );
			$payload     = array(
				'checkpoint_id' => $id,
				'type'          => 'post_meta',
				'label'         => sanitize_text_field( (string) $label ),
				'captured_at'   => $captured_at,
				'data'          => $data,
			);

			set_transient( $id, $payload, $ttl > 0 ? (int) $ttl : self::DEFAULT_TTL );

			return array(
				'checkpoint_id' => $id,
				'snapshot_id'   => $id,
				'label'         => $payload['label'],
				'captured_at'   => $captured_at,
				'post_count'    => count( $data ),
			);
		}

		/**
		 * @param string $checkpoint_id Checkpoint ID.
		 * @return array<string,mixed>|null
		 */
		public static function get( $checkpoint_id ) {
			$payload = get_transient( (string) $checkpoint_id );
			return is_array( $payload ) ? $payload : null;
		}

		/**
		 * @param string $checkpoint_id Checkpoint ID.
		 * @param bool   $delete Delete after rollback.
		 * @return array<string,mixed>
		 */
		public static function rollback( $checkpoint_id, $delete = true ) {
			$payload = self::get( $checkpoint_id );
			if ( null === $payload ) {
				return array(
					'success'       => false,
					'ok'            => false,
					'checkpoint_id' => (string) $checkpoint_id,
					'error'         => 'Checkpoint not found or expired.',
				);
			}

			$rolled_back = array();
			foreach ( (array) ( $payload['data'] ?? array() ) as $post_id => $meta ) {
				$post_id = absint( $post_id );
				foreach ( (array) $meta as $key => $entry ) {
					if ( ! empty( $entry['exists'] ) ) {
						update_post_meta( $post_id, (string) $key, $entry['value'] ?? '' );
					} else {
						delete_post_meta( $post_id, (string) $key );
					}

					$rolled_back[] = array(
						'post_id' => $post_id,
						'key'     => (string) $key,
					);
				}

				if ( function_exists( 'webo_mcp_rank_math_clear_post_meta_caches' ) ) {
					webo_mcp_rank_math_clear_post_meta_caches( $post_id );
				}
			}

			if ( $delete ) {
				delete_transient( (string) $checkpoint_id );
			}

			return array(
				'success'       => true,
				'ok'            => true,
				'checkpoint_id' => (string) $checkpoint_id,
				'snapshot_id'   => (string) $checkpoint_id,
				'type'          => $payload['type'] ?? 'post_meta',
				'label'         => $payload['label'] ?? '',
				'captured_at'   => $payload['captured_at'] ?? null,
				'rolled_back'   => $rolled_back,
			);
		}
	}
}
