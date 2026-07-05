<?php
/**
 * Shared mutation write-mode guard.
 *
 * @package webo-mcp-rank-math
 */

namespace WeboMCP\Core;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WEBO_MCP_CONTRACT_TEST' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\MutationGuard' ) ) {
	/**
	 * Centralizes dry_run/force parsing for mutation tools.
	 */
	final class MutationGuard {
		/**
		 * @param mixed $value Raw value.
		 * @param bool  $default Default value.
		 * @return bool
		 */
		public static function parse_bool( $value, bool $default = false ): bool {
			if ( null === $value ) {
				return $default;
			}

			$parsed = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			return null === $parsed ? $default : (bool) $parsed;
		}

		/**
		 * @param bool $dry_run Whether request is preview-only.
		 * @param bool $force Whether guarded operations were confirmed.
		 * @return bool
		 */
		public static function canWrite( bool $dry_run, bool $force = false ): bool {
			unset( $force );
			return false === $dry_run;
		}

		/**
		 * @param array<string,mixed> $args Arguments.
		 * @param bool $is_dangerous Whether action is destructive.
		 * @return array{dry_run:bool,force:bool,blocked:bool,reason:string}
		 */
		public static function mode( array $args, bool $is_dangerous = false ): array {
			$dry_run       = self::parse_bool( $args['dry_run'] ?? null, true );
			$force         = self::parse_bool( $args['force'] ?? null, false );
			$has_checkpoint = ! empty( $args['checkpoint_id'] ) || ! empty( $args['checkpoint'] ) || ! empty( $args['snapshot_id'] );
			$blocked       = false;
			$reason        = '';

			if ( $is_dangerous && self::canWrite( $dry_run, $force ) && ! $force && ! $has_checkpoint ) {
				$dry_run = true;
				$blocked = true;
				$reason  = 'This action is destructive. Re-run with dry_run=false and force=true (or supply a checkpoint_id) to execute. A dry-run preview was returned instead.';
			}

			return array(
				'dry_run' => $dry_run,
				'force'   => $force,
				'blocked' => $blocked,
				'reason'  => $reason,
			);
		}
	}
}
