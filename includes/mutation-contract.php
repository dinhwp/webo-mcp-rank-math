<?php
/**
 * Standardized safety contract for WEBO MCP mutation tools.
 *
 * Every mutation tool that supports dry_run MUST shape its response through
 * webo_mcp_mutation_response() so that AI clients can never confuse a preview
 * with a real write. The contract is:
 *
 *  dry_run = true  -> { dry_run:true,  executed:false, would_change, planned_count, diff }
 *  dry_run = false -> { dry_run:false, executed:true,  changed,       changed_count, diff }
 *
 * On a preview (executed:false) the misleading "it happened" keys
 * (success, updated, updated_count, deleted, deleted_count, purged, created)
 * are stripped, so a client can never read success:true for work that was
 * never performed.
 *
 * These helpers are intentionally free of WordPress dependencies so they can be
 * unit tested in isolation and reused by the pro add-on plugins. They are guarded
 * with function_exists() because every WEBO MCP add-on ships a copy; whichever
 * plugin loads first wins.
 *
 * @package WeboMcp
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WEBO_MCP_CONTRACT_TEST' ) ) {
	exit;
}

if ( ! function_exists( 'webo_mcp_is_truthy' ) ) {
	/**
	 * Parse a boolean-ish argument without treating the string "false"/"0" as true.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	function webo_mcp_is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 !== (int) $value;
		}

		if ( is_string( $value ) ) {
			$normalized = strtolower( trim( $value ) );
			return ! in_array( $normalized, array( '', '0', 'false', 'no', 'off', 'null' ), true );
		}

		return ! empty( $value );
	}
}

if ( ! function_exists( 'webo_mcp_mutation_misleading_keys' ) ) {
	/**
	 * Keys that wrongly imply a write happened. They must never appear on a preview.
	 *
	 * @return string[]
	 */
	function webo_mcp_mutation_misleading_keys(): array {
		return array(
			'success',
			'updated',
			'updated_count',
			'deleted',
			'deleted_count',
			'purged',
			'created',
			'restored',
			'cleaned',
		);
	}
}

if ( ! function_exists( 'webo_mcp_mutation_response' ) ) {
	/**
	 * Build a standardized, safety-first mutation response.
	 *
	 * @param array<string,mixed> $args {
	 *     @type bool                 $dry_run       Whether this was a preview (no writes performed).
	 *     @type bool                 $would_change  (preview) Whether executing would change state.
	 *                                                Defaults to planned_count>0 || diff not empty.
	 *     @type bool                 $changed       (executed) Whether state actually changed.
	 *                                                Defaults to changed_count>0 || diff not empty.
	 *     @type int                  $planned_count (preview) Number of items that would change.
	 *     @type int                  $changed_count (executed) Number of items that changed.
	 *     @type array<string,mixed>  $diff          Field/item level diff.
	 *     @type array<string,mixed>  $context       Extra response keys (post_id, action, ...).
	 *                                                Misleading keys are stripped on a preview.
	 * }
	 * @return array<string,mixed>
	 */
	function webo_mcp_mutation_response( array $args ): array {
		$dry_run = ! empty( $args['dry_run'] );
		$context = ( isset( $args['context'] ) && is_array( $args['context'] ) ) ? $args['context'] : array();
		$diff    = ( isset( $args['diff'] ) && is_array( $args['diff'] ) ) ? $args['diff'] : array();

		if ( $dry_run ) {
			foreach ( webo_mcp_mutation_misleading_keys() as $key ) {
				unset( $context[ $key ] );
			}

			$would_change = array_key_exists( 'would_change', $args )
				? (bool) $args['would_change']
				: ( ! empty( $args['planned_count'] ) || ! empty( $diff ) );

			return array_merge(
				$context,
				array(
					'dry_run'       => true,
					'executed'      => false,
					'would_change'  => $would_change,
					'planned_count' => (int) ( $args['planned_count'] ?? 0 ),
					'diff'          => $diff,
				)
			);
		}

		$changed = array_key_exists( 'changed', $args )
			? (bool) $args['changed']
			: ( ! empty( $args['changed_count'] ) || ! empty( $diff ) );

		$envelope = array(
			'dry_run'  => false,
			'executed' => true,
			'changed'  => $changed,
			'diff'     => $diff,
		);

		if ( array_key_exists( 'changed_count', $args ) ) {
			$envelope['changed_count'] = (int) $args['changed_count'];
		}

		// On a real execution legacy keys (success/updated/...) are accurate, so the
		// caller's context is kept; canonical keys win on any conflict.
		return array_merge( $context, $envelope );
	}
}

if ( ! function_exists( 'webo_mcp_resolve_mutation_mode' ) ) {
	/**
	 * Decide whether a mutation is allowed to execute for real.
	 *
	 * Safety rules:
	 *  - Default is a preview (dry_run = true) unless the caller opts in.
	 *  - A normal action executes when force=true OR dry_run is explicitly false.
	 *  - A dangerous action executes ONLY when force=true (or a prior checkpoint
	 *    is supplied). A bare dry_run=false is downgraded to a preview and flagged
	 *    blocked=true so the caller can surface a "pass force=true" notice.
	 *
	 * @param array<string,mixed> $args         Tool arguments.
	 * @param bool                $is_dangerous Whether this action is destructive/irreversible.
	 * @return array{dry_run:bool,force:bool,blocked:bool,reason:string}
	 */
	function webo_mcp_resolve_mutation_mode( array $args, bool $is_dangerous = false ): array {
		$force = ! empty( $args['force'] ) && webo_mcp_is_truthy( $args['force'] );

		$dry_run = $force
			? false
			: ( ! array_key_exists( 'dry_run', $args ) || webo_mcp_is_truthy( $args['dry_run'] ) );

		$has_checkpoint = ! empty( $args['checkpoint_id'] ) || ! empty( $args['checkpoint'] );

		$blocked = false;
		$reason  = '';

		if ( $is_dangerous && ! $dry_run && ! $force && ! $has_checkpoint ) {
			// Caller asked to execute a dangerous action without authorization.
			$dry_run = true;
			$blocked = true;
			$reason  = 'This action is destructive. Re-run with force=true (or supply a checkpoint_id) to execute. A dry-run preview was returned instead.';
		}

		return array(
			'dry_run' => $dry_run,
			'force'   => $force,
			'blocked' => $blocked,
			'reason'  => $reason,
		);
	}
}
