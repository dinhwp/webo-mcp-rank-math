<?php
/**
 * @wordpress-plugin
 *
 * Plugin Name: WEBO MCP - Rank Math Addon
 * Plugin URI: https://webomcp.com
 * Description: Rank Math SEO management abilities addon for WEBO MCP.
 * Version: 1.0.16
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: webo-mcp, seo-by-rank-math
 * Author: Dinh WP
 * Author URI: https://webomcp.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: webo-mcp-rank-math
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBO_MCP_RANK_MATH_PATH', plugin_dir_path( __FILE__ ) );
define( 'WEBO_MCP_RANK_MATH_URL', plugin_dir_url( __FILE__ ) );
define( 'WEBO_MCP_RANK_MATH_VERSION', '1.0.16' );
if ( ! defined( 'WEBO_MCP_LICENSE_STORE_URL' ) ) {
	define( 'WEBO_MCP_LICENSE_STORE_URL', 'https://webomcp.com' );
}
define( 'WEBO_MCP_RANK_MATH_ITEM_ID', 4888 );
define( 'WEBO_MCP_RANK_MATH_ITEM_NAME', 'WEBO MCP Rank Math SEO Addon' );

require_once WEBO_MCP_RANK_MATH_PATH . 'includes/mutation-contract.php';
require_once WEBO_MCP_RANK_MATH_PATH . 'includes/license-client.php';

/**
 * Loads addon translations.
 *
 * @return void
 */
function webo_mcp_rank_math_load_textdomain() {
	load_plugin_textdomain( 'webo-mcp-rank-math', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Ability names surfaced as public MCP tools (unified dispatchers only).
 *
 * Granular abilities (e.g. list-redirections) stay registered for REST but are MCP-internal.
 *
 * @return string[]
 */
function webo_mcp_rank_math_public_mcp_ability_names() {
	return array(
		'webo-rank-math/config-query',
		'webo-rank-math/config-mutate',
		'webo-rank-math/post-seo-query',
		'webo-rank-math/post-seo-mutate',
		'webo-rank-math/term-seo-query',
		'webo-rank-math/term-seo-mutate',
		'webo-rank-math/user-seo-query',
		'webo-rank-math/user-seo-mutate',
		'webo-rank-math/redirect-query',
		'webo-rank-math/redirect-mutate',
		'webo-rank-math/schema-mutate',
	);
}

/**
 * Public AI-safe SEO aliases exposed by seo_quick_update.
 *
 * @return array<string,string>
 */
function webo_mcp_rank_math_quick_post_meta_aliases() {
	return array(
		'title'                => 'rank_math_title',
		'description'          => 'rank_math_description',
		'focus_keyword'        => 'rank_math_focus_keyword',
		'rank_math_title'      => 'rank_math_title',
		'rank_math_description'=> 'rank_math_description',
		'rank_math_focus_keyword' => 'rank_math_focus_keyword',
	);
}

/**
 * Advanced SEO aliases kept out of public MCP discovery by default.
 *
 * @return array<string,string>
 */
function webo_mcp_rank_math_advanced_post_meta_aliases() {
	return array(
		'canonical'            => 'rank_math_canonical_url',
		'canonical_url'        => 'rank_math_canonical_url',
		'robots'               => 'rank_math_robots',
		'facebook_title'       => 'rank_math_facebook_title',
		'facebook_description' => 'rank_math_facebook_description',
		'twitter_title'        => 'rank_math_twitter_title',
		'twitter_description'  => 'rank_math_twitter_description',
	);
}

/**
 * Rank Math post meta keys that the public MCP write tool can update.
 *
 * @return string[]
 */
function webo_mcp_rank_math_public_post_meta_keys() {
	return array(
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_canonical_url',
		'rank_math_robots',
		'rank_math_facebook_title',
		'rank_math_facebook_description',
		'rank_math_twitter_title',
		'rank_math_twitter_description',
	);
}

/**
 * Short SEO meta aliases accepted by public MCP tools.
 *
 * @return array<string,string>
 */
function webo_mcp_rank_math_public_post_meta_aliases() {
	return array_merge(
		webo_mcp_rank_math_quick_post_meta_aliases(),
		webo_mcp_rank_math_advanced_post_meta_aliases()
	);
}

/**
 * Shared site selector schema for multisite-aware Rank Math tools.
 *
 * @return array<string,array<string,mixed>>
 */
function webo_mcp_rank_math_site_context_arguments() {
	return array(
		'site_id' => array(
			'type'     => 'integer',
			'required' => false,
			'min'      => 1,
		),
		'blog_id' => array(
			'type'     => 'integer',
			'required' => false,
			'min'      => 1,
		),
		'domain'  => array(
			'type'     => 'string',
			'required' => false,
		),
		'url'     => array(
			'type'     => 'string',
			'required' => false,
		),
	);
}

/**
 * ToolRegistry argument schema for AI-safe quick SEO updates.
 *
 * @return array<string,array<string,mixed>>
 */
function webo_mcp_rank_math_quick_update_tool_arguments() {
	return array(
		'post_id' => array(
			'type'     => 'integer',
			'required' => false,
			'min'      => 0,
		),
		'id' => array(
			'type'     => 'integer',
			'required' => false,
			'min'      => 0,
		),
		'slug' => array(
			'type'     => 'string',
			'required' => false,
		),
		'post_type' => array(
			'type'     => 'string',
			'required' => false,
			'default'  => 'post',
		),
	) + webo_mcp_rank_math_site_context_arguments() + array(
		'title' => array(
			'type'     => 'string',
			'required' => false,
		),
		'description' => array(
			'type'     => 'string',
			'required' => false,
		),
		'focus_keyword' => array(
			'type'     => 'string',
			'required' => false,
		),
	);
}

/**
 * ToolRegistry argument schema for internal/admin SEO updates.
 *
 * @return array<string,array<string,mixed>>
 */
function webo_mcp_rank_math_advanced_update_tool_arguments() {
	$arguments = webo_mcp_rank_math_quick_update_tool_arguments();
	$arguments['canonical'] = array(
		'type'     => 'string',
		'required' => false,
	);
	$arguments['canonical_url'] = array(
		'type'     => 'string',
		'required' => false,
	);
	$arguments['robots'] = array(
		'type'     => 'array',
		'required' => false,
	);
	$arguments['facebook_title'] = array(
		'type'     => 'string',
		'required' => false,
	);
	$arguments['facebook_description'] = array(
		'type'     => 'string',
		'required' => false,
	);
	$arguments['twitter_title'] = array(
		'type'     => 'string',
		'required' => false,
	);
	$arguments['twitter_description'] = array(
		'type'     => 'string',
		'required' => false,
	);
	$arguments['schema'] = array(
		'type'     => 'object',
		'required' => false,
	);
	$arguments['schemas'] = array(
		'type'     => 'object',
		'required' => false,
	);
	$arguments['schema_key'] = array(
		'type'     => 'string',
		'required' => false,
	);
	$arguments['schema_type'] = array(
		'type'     => 'string',
		'required' => false,
	);
	$arguments['dry_run'] = array(
		'type'     => 'boolean',
		'required' => false,
		'default'  => true,
	);
	$arguments['force'] = array(
		'type'     => 'boolean',
		'required' => false,
		'default'  => false,
	);
	return $arguments;
}

/**
 * ToolRegistry argument schema for the public post meta tools.
 *
 * @param bool $include_update_fields Whether update fields should be included.
 * @param bool $include_action        Whether action and seo_meta wrapper fields should be included.
 * @return array<string,array<string,mixed>>
 */
function webo_mcp_rank_math_post_meta_tool_arguments( $include_update_fields = false, $include_action = false ) {
	$arguments = array(
		'post_id' => array(
			'type'     => 'integer',
			'required' => false,
			'min'      => 1,
		),
		'id' => array(
			'type'     => 'integer',
			'required' => false,
			'min'      => 1,
		),
		'slug' => array(
			'type'     => 'string',
			'required' => false,
		),
		'post_type' => array(
			'type'     => 'string',
			'required' => false,
			'default'  => 'post',
		),
	) + webo_mcp_rank_math_site_context_arguments();

	if ( ! $include_update_fields ) {
		$arguments['keys'] = array(
			'type'     => 'array',
			'required' => false,
		);
		return $arguments;
	}

	if ( $include_action ) {
		$arguments['action'] = array(
			'type'     => 'string',
			'required' => false,
			'default'  => 'update',
		);
		$arguments['seo_meta'] = array(
			'type'     => 'object',
			'required' => false,
		);
		$arguments['posts'] = array(
			'type'     => 'array',
			'required' => false,
		);
		$arguments['skip_missing'] = array(
			'type'     => 'boolean',
			'required' => false,
			'default'  => false,
		);
	}

	foreach ( webo_mcp_rank_math_public_post_meta_keys() as $key ) {
		$arguments[ $key ] = array(
			'type'     => 'rank_math_robots' === $key ? 'array' : 'string',
			'required' => false,
		);
	}

	foreach ( webo_mcp_rank_math_public_post_meta_aliases() as $alias => $key ) {
		$arguments[ $alias ] = array(
			'type'     => 'rank_math_robots' === $key ? 'array' : 'string',
			'required' => false,
		);
	}

	$arguments['dry_run'] = array(
		'type'     => 'boolean',
		'required' => false,
		'default'  => true,
	);
	$arguments['force'] = array(
		'type'     => 'boolean',
		'required' => false,
		'default'  => false,
	);

	return $arguments;
}

/**
 * Resolve post ID from ToolRegistry arguments.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return int|\WP_Error
 */
function webo_mcp_rank_math_resolve_tool_post_id( $arguments ) {
	$post_id = 0;
	if ( ! empty( $arguments['post_id'] ) ) {
		$post_id = absint( $arguments['post_id'] );
	} elseif ( ! empty( $arguments['id'] ) ) {
		$post_id = absint( $arguments['id'] );
	}

	if ( $post_id < 1 && ! empty( $arguments['slug'] ) ) {
		$post_type = ! empty( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : 'post';
		$post      = get_page_by_path( sanitize_title( (string) $arguments['slug'] ), OBJECT, $post_type );
		if ( $post ) {
			$post_id = (int) $post->ID;
		}
	}

	if ( $post_id < 1 ) {
		return new WP_Error( 'webo_mcp_rank_math_missing_post_id', 'post_id or id is required.', array( 'status' => 400 ) );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'webo_mcp_rank_math_post_not_found', 'Post not found.', array( 'status' => 404 ) );
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'webo_mcp_rank_math_forbidden', 'Permission denied for this post.', array( 'status' => 403 ) );
	}

	return $post_id;
}

/**
 * Sanitize one allowed Rank Math meta value.
 *
 * @param string $key   Meta key.
 * @param mixed  $value Raw value.
 * @return mixed
 */
function webo_mcp_rank_math_sanitize_public_meta_value( $key, $value ) {
	if ( 'rank_math_canonical_url' === $key ) {
		return esc_url_raw( trim( (string) $value ) );
	}

	if ( 'rank_math_robots' === $key ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\s*,\s*/', $value );
		}
		$robots = array();
		foreach ( (array) $value as $item ) {
			$item = sanitize_key( (string) $item );
			if ( '' !== $item ) {
				$robots[] = $item;
			}
		}
		return array_values( array_unique( $robots ) );
	}

	return sanitize_text_field( (string) $value );
}

/**
 * Normalize canonical and short SEO meta keys into whitelisted Rank Math keys.
 *
 * @param array<string,mixed> $meta Raw meta map.
 * @return array<string,mixed>
 */
function webo_mcp_rank_math_normalize_public_meta_updates( $meta ) {
	$updates = array();
	$allowed = webo_mcp_rank_math_public_post_meta_keys();
	$aliases = webo_mcp_rank_math_public_post_meta_aliases();

	foreach ( (array) $meta as $raw_key => $value ) {
		$key = sanitize_key( (string) $raw_key );
		if ( isset( $aliases[ $key ] ) ) {
			$key = $aliases[ $key ];
		}

		if ( ! in_array( $key, $allowed, true ) ) {
			continue;
		}

		$updates[ $key ] = webo_mcp_rank_math_sanitize_public_meta_value( $key, $value );
	}

	return $updates;
}

/**
 * Extract whitelisted Rank Math meta updates from tool arguments.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return array<string,mixed>
 */
function webo_mcp_rank_math_extract_public_meta_updates( $arguments ) {
	$updates = array();

	if ( isset( $arguments['seo_meta'] ) && is_array( $arguments['seo_meta'] ) ) {
		$updates = webo_mcp_rank_math_normalize_public_meta_updates( $arguments['seo_meta'] );
	}

	return array_merge( $updates, webo_mcp_rank_math_normalize_public_meta_updates( $arguments ) );
}

/**
 * Extract only AI-safe quick SEO updates.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return array<string,mixed>
 */
function webo_mcp_rank_math_extract_quick_meta_updates( $arguments ) {
	$updates = array();
	$aliases = webo_mcp_rank_math_quick_post_meta_aliases();

	if ( isset( $arguments['seo_meta'] ) && is_array( $arguments['seo_meta'] ) ) {
		foreach ( $arguments['seo_meta'] as $raw_key => $value ) {
			$key = sanitize_key( (string) $raw_key );
			if ( ! isset( $aliases[ $key ] ) ) {
				continue;
			}
			$mapped            = $aliases[ $key ];
			$updates[ $mapped ] = webo_mcp_rank_math_sanitize_public_meta_value( $mapped, $value );
		}
	}

	foreach ( $arguments as $raw_key => $value ) {
		$key = sanitize_key( (string) $raw_key );
		if ( ! isset( $aliases[ $key ] ) ) {
			continue;
		}
		$mapped            = $aliases[ $key ];
		$updates[ $mapped ] = webo_mcp_rank_math_sanitize_public_meta_value( $mapped, $value );
	}

	return $updates;
}

/**
 * Return user-facing quick-update fields that are explicitly forbidden.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return string[]
 */
function webo_mcp_rank_math_quick_update_forbidden_fields( $arguments ) {
	$forbidden = array(
		'canonical',
		'canonical_url',
		'robots',
		'schema',
		'schemas',
		'schema_key',
		'schema_type',
		'noindex',
		'facebook_title',
		'facebook_description',
		'twitter_title',
		'twitter_description',
		'og',
		'og_title',
		'og_description',
		'redirect',
		'rank_math_canonical_url',
		'rank_math_robots',
		'rank_math_facebook_title',
		'rank_math_facebook_description',
		'rank_math_twitter_title',
		'rank_math_twitter_description',
	);
	$present = array();

	foreach ( $forbidden as $key ) {
		if ( array_key_exists( $key, $arguments ) && null !== $arguments[ $key ] && '' !== $arguments[ $key ] ) {
			$present[] = $key;
		}
	}

	if ( isset( $arguments['seo_meta'] ) && is_array( $arguments['seo_meta'] ) ) {
		foreach ( $forbidden as $key ) {
			if ( array_key_exists( $key, $arguments['seo_meta'] ) && null !== $arguments['seo_meta'][ $key ] && '' !== $arguments['seo_meta'][ $key ] ) {
				$present[] = $key;
			}
		}
	}

	return array_values( array_unique( $present ) );
}

/**
 * Whether a legacy SEO mutate payload can be safely downgraded to seo_quick_update.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return bool
 */
function webo_mcp_rank_math_is_quick_only_payload( $arguments ) {
	if ( ! empty( webo_mcp_rank_math_quick_update_forbidden_fields( $arguments ) ) ) {
		return false;
	}

	if ( ! empty( $arguments['posts'] ) || ! empty( $arguments['skip_missing'] ) ) {
		return false;
	}

	return ! empty( webo_mcp_rank_math_extract_quick_meta_updates( $arguments ) );
}

/**
 * Build before/after diff for selected meta keys.
 *
 * @param array<string,mixed> $before Before values.
 * @param array<string,mixed> $after  After values.
 * @param string[]            $keys   Keys to compare.
 * @return array<string,array<string,mixed>>
 */
function webo_mcp_rank_math_build_meta_diff( $before, $after, $keys ) {
	$diff = array();
	foreach ( $keys as $key ) {
		$old = array_key_exists( $key, $before ) ? $before[ $key ] : null;
		$new = array_key_exists( $key, $after ) ? $after[ $key ] : null;
		$diff[ $key ] = array(
			'before'  => $old,
			'after'   => $new,
			'changed' => $old !== $new,
		);
	}
	return $diff;
}

/**
 * ToolRegistry argument schema for public Rank Math schema mutations.
 *
 * @return array<string,array<string,mixed>>
 */
function webo_mcp_rank_math_schema_mutate_tool_arguments() {
	return array(
		'action' => array(
			'type'     => 'string',
			'required' => false,
			'default'  => 'upsert',
		),
		'post_id' => array(
			'type'     => 'integer',
			'required' => false,
			'min'      => 1,
		),
		'id' => array(
			'type'     => 'integer',
			'required' => false,
			'min'      => 1,
		),
		'slug' => array(
			'type'     => 'string',
			'required' => false,
		),
		'post_type' => array(
			'type'     => 'string',
			'required' => false,
			'default'  => 'post',
		),
		'site_id' => array(
			'type'     => 'integer',
			'required' => false,
			'min'      => 1,
		),
		'schema_key' => array(
			'type'     => 'string',
			'required' => false,
		),
		'schema_type' => array(
			'type'     => 'string',
			'required' => false,
		),
		'schema' => array(
			'type'     => 'object',
			'required' => false,
		),
		'schemas' => array(
			'type'     => 'object',
			'required' => false,
		),
		'delete_all' => array(
			'type'     => 'boolean',
			'required' => false,
			'default'  => false,
		),
		'dry_run' => array(
			'type'     => 'boolean',
			'required' => false,
			'default'  => true,
		),
		'force' => array(
			'type'     => 'boolean',
			'required' => false,
			'default'  => false,
		),
	);
}

/**
 * Normalize a Rank Math schema meta key.
 *
 * @param string $schema_key  Requested meta key.
 * @param mixed  $schema      Schema payload.
 * @param string $schema_type Optional schema type fallback.
 * @return string|\WP_Error
 */
function webo_mcp_rank_math_normalize_schema_meta_key( $schema_key, $schema = null, $schema_type = '' ) {
	$schema_key = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $schema_key );

	if ( '' === $schema_key ) {
		if ( is_array( $schema ) && ! empty( $schema['@type'] ) ) {
			$schema_key = 'rank_math_schema_' . preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $schema['@type'] );
		} elseif ( '' !== trim( (string) $schema_type ) ) {
			$schema_key = 'rank_math_schema_' . preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $schema_type );
		}
	}

	if ( '' === $schema_key ) {
		return new WP_Error( 'webo_mcp_rank_math_missing_schema_key', 'schema_key or schema @type is required.', array( 'status' => 400 ) );
	}

	if ( 0 !== strpos( $schema_key, 'rank_math_schema_' ) ) {
		$schema_key = 'rank_math_schema_' . $schema_key;
	}

	if ( 'rank_math_schema_' === $schema_key ) {
		return new WP_Error( 'webo_mcp_rank_math_invalid_schema_key', 'Invalid Rank Math schema meta key.', array( 'status' => 400 ) );
	}

	return $schema_key;
}

/**
 * Validate a Rank Math schema payload.
 *
 * @param mixed $schema Schema payload.
 * @return array<string,mixed>|\WP_Error
 */
function webo_mcp_rank_math_validate_schema_payload( $schema ) {
	if ( is_object( $schema ) ) {
		$schema = (array) $schema;
	}

	if ( ! is_array( $schema ) || empty( $schema ) ) {
		return new WP_Error( 'webo_mcp_rank_math_invalid_schema', 'schema must be a non-empty object.', array( 'status' => 400 ) );
	}

	if ( empty( $schema['@type'] ) || ! is_scalar( $schema['@type'] ) ) {
		return new WP_Error( 'webo_mcp_rank_math_invalid_schema', 'schema must include a scalar @type.', array( 'status' => 400 ) );
	}

	return $schema;
}

/**
 * Extract schema updates from schema/schemas arguments.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return array<string,array<string,mixed>>|\WP_Error
 */
function webo_mcp_rank_math_extract_schema_updates( $arguments ) {
	$updates = array();

	if ( isset( $arguments['schema'] ) && is_array( $arguments['schema'] ) ) {
		$schema = webo_mcp_rank_math_validate_schema_payload( $arguments['schema'] );
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		$key = webo_mcp_rank_math_normalize_schema_meta_key( $arguments['schema_key'] ?? '', $schema, $arguments['schema_type'] ?? '' );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$updates[ $key ] = $schema;
	}

	if ( isset( $arguments['schemas'] ) && is_array( $arguments['schemas'] ) ) {
		foreach ( $arguments['schemas'] as $raw_key => $raw_schema ) {
			$schema = webo_mcp_rank_math_validate_schema_payload( $raw_schema );
			if ( is_wp_error( $schema ) ) {
				return $schema;
			}

			$key = webo_mcp_rank_math_normalize_schema_meta_key( (string) $raw_key, $schema, '' );
			if ( is_wp_error( $key ) ) {
				return $key;
			}
			$updates[ $key ] = $schema;
		}
	}

	if ( empty( $updates ) ) {
		return new WP_Error( 'webo_mcp_rank_math_no_schema_fields', 'schema or schemas is required for this action.', array( 'status' => 400 ) );
	}

	return $updates;
}

/**
 * Execute public Rank Math schema mutate tool.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return array<string,mixed>|\WP_Error
 */
function webo_mcp_rank_math_execute_schema_mutate_tool( $arguments ) {
	return webo_rank_math_with_site( $arguments, function () use ( $arguments ) {
		$post_id = webo_mcp_rank_math_resolve_tool_post_id( $arguments );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post    = get_post( $post_id );
		$action  = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : 'upsert';

		// delete and cleanup remove schema meta and are destructive: they require
		// force=true (or a checkpoint) to run for real, not merely dry_run=false.
		$is_dangerous   = in_array( $action, array( 'delete', 'cleanup' ), true );
		$mode           = webo_mcp_resolve_mutation_mode( $arguments, $is_dangerous );
		$dry_run        = $mode['dry_run'];
		$force_required = $mode['blocked'];
		$block_reason   = $mode['reason'];

		if ( 'cleanup' === $action ) {
			if ( $dry_run ) {
				$audit = webo_rank_math_audit_schema_meta(
					array(
						'post_types' => $post ? array( $post->post_type ) : array(),
						'statuses'   => $post ? array( $post->post_status ) : array(),
						'limit'      => 2000,
						'offset'     => 0,
					)
				);
				$would_delete = array();
				foreach ( (array) ( $audit['malformed'] ?? array() ) as $item ) {
					if ( isset( $item['post_id'] ) && (int) $item['post_id'] === (int) $post_id ) {
						$would_delete[] = $item;
					}
				}
				if ( ! empty( $arguments['delete_all'] ) ) {
					foreach ( (array) ( $audit['valid'] ?? array() ) as $item ) {
						if ( isset( $item['post_id'] ) && (int) $item['post_id'] === (int) $post_id ) {
							$would_delete[] = $item;
						}
					}
				}

				return webo_mcp_mutation_response(
					array(
						'dry_run'       => true,
						'would_change'  => count( $would_delete ) > 0,
						'planned_count' => count( $would_delete ),
						'diff'          => $would_delete,
						'context'       => array(
							'tool'               => 'webo-rank-math/schema-mutate',
							'post_id'            => $post_id,
							'post_type'          => $post ? $post->post_type : null,
							'slug'               => $post ? $post->post_name : null,
							'action'             => $action,
							'delete_all'         => ! empty( $arguments['delete_all'] ),
							'would_delete_count' => count( $would_delete ),
							'would_delete'       => $would_delete,
							'force_required'     => $force_required,
							'reason'             => $block_reason,
						),
					)
				);
			}

			$preview = webo_rank_math_cleanup_post_schema_meta( $post_id, ! empty( $arguments['delete_all'] ) );
			webo_mcp_rank_math_clear_post_meta_caches( $post_id );
			$deleted_count = (int) ( $preview['deleted_count'] ?? ( isset( $preview['deleted'] ) && is_array( $preview['deleted'] ) ? count( $preview['deleted'] ) : 0 ) );
			return webo_mcp_mutation_response(
				array(
					'dry_run'       => false,
					'changed'       => $deleted_count > 0,
					'changed_count' => $deleted_count,
					'context'       => array_merge(
						array(
							'tool'      => 'webo-rank-math/schema-mutate',
							'post_id'   => $post_id,
							'post_type' => $post ? $post->post_type : null,
							'slug'      => $post ? $post->post_name : null,
							'action'    => $action,
							'updated'   => true,
						),
						$preview
					),
				)
			);
		}

		if ( ! in_array( $action, array( 'upsert', 'delete' ), true ) ) {
			return new WP_Error( 'webo_mcp_rank_math_invalid_action', sprintf( 'Unsupported schema-mutate action: %s', $action ), array( 'status' => 400 ) );
		}

		if ( 'delete' === $action ) {
			$key = webo_mcp_rank_math_normalize_schema_meta_key( $arguments['schema_key'] ?? '', null, $arguments['schema_type'] ?? '' );
			if ( is_wp_error( $key ) ) {
				return $key;
			}
			$updates = array( $key => null );
		} else {
			$updates = webo_mcp_rank_math_extract_schema_updates( $arguments );
			if ( is_wp_error( $updates ) ) {
				return $updates;
			}
		}

		$keys    = array_keys( $updates );
		$before  = webo_rank_math_collect_post_meta( $post_id, $keys );
		$planned = $before;

		foreach ( $updates as $key => $value ) {
			if ( null === $value ) {
				unset( $planned[ $key ] );
			} else {
				$planned[ $key ] = $value;
			}
		}

		if ( ! $dry_run ) {
			foreach ( $updates as $key => $value ) {
				if ( null === $value ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $value );
				}
			}
			webo_mcp_rank_math_clear_post_meta_caches( $post_id );
		}

		$after = $dry_run ? $planned : webo_rank_math_collect_post_meta( $post_id, $keys );
		$diff  = webo_mcp_rank_math_build_meta_diff( $before, $after, $keys );

		$changed_count = count( array_filter( $diff, static function ( $item ) {
			return ! empty( $item['changed'] );
		} ) );

		return webo_mcp_mutation_response(
			array(
				'dry_run'       => $dry_run,
				'would_change'  => $changed_count > 0,
				'planned_count' => $changed_count,
				'changed'       => ! $dry_run && $changed_count > 0,
				'changed_count' => $dry_run ? 0 : $changed_count,
				'diff'          => $diff,
				'context'       => array(
					'tool'           => 'webo-rank-math/schema-mutate',
					'post_id'        => $post_id,
					'post_type'      => $post ? $post->post_type : null,
					'slug'           => $post ? $post->post_name : null,
					'action'         => $action,
					'keys'           => $keys,
					'updated'        => ! $dry_run,
					'updated_count'  => $dry_run ? 0 : $changed_count,
					'force_required' => $force_required,
					'reason'         => $block_reason,
				),
			)
		);
	} );
}

/**
 * Log a safe audit event for public Rank Math post meta tools.
 *
 * @param string              $event   Event name.
 * @param array<string,mixed> $payload Event payload.
 * @return void
 */
function webo_mcp_rank_math_audit_public_post_meta( $event, $payload ) {
	$payload = array_merge(
		array(
			'event'   => $event,
			'user_id' => get_current_user_id(),
			'blog_id' => function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0,
		),
		$payload
	);

	do_action( 'webo_mcp_rank_math_post_meta_audit', $event, $payload );

	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( 'WEBO MCP Rank Math audit: ' . wp_json_encode( $payload ) );
	}
}

/**
 * Clear Rank Math/post/object caches after a post meta write.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function webo_mcp_rank_math_clear_post_meta_caches( $post_id ) {
	clean_post_cache( $post_id );
	wp_cache_delete( $post_id, 'post_meta' );
	wp_cache_delete( $post_id, 'posts' );
	do_action( 'rank_math/clear_post_cache', $post_id );
	do_action( 'rank_math/frontend/clear_cache', $post_id );
}

/**
 * Execute public get/update Rank Math post meta tools with optional multisite context.
 *
 * @param string              $tool_name Tool name.
 * @param array<string,mixed> $arguments Tool arguments.
 * @return array<string,mixed>|\WP_Error
 */
function webo_mcp_rank_math_execute_public_post_meta_tool( $tool_name, $arguments ) {
	return webo_rank_math_with_site( $arguments, function () use ( $tool_name, $arguments ) {
		$post_id = webo_mcp_rank_math_resolve_tool_post_id( $arguments );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post = get_post( $post_id );
		$allowed_keys = webo_mcp_rank_math_public_post_meta_keys();

		if ( 'rankmath_get_post_meta' === $tool_name || 'webo-rank-math/post-seo-query' === $tool_name ) {
			$keys = ! empty( $arguments['keys'] ) && is_array( $arguments['keys'] )
				? array_values( array_intersect( array_map( 'sanitize_key', $arguments['keys'] ), $allowed_keys ) )
				: $allowed_keys;

			return array(
				'post_id'   => $post_id,
				'post_type' => $post ? $post->post_type : null,
				'slug'      => $post ? $post->post_name : null,
				'meta'      => webo_rank_math_collect_post_meta( $post_id, $keys ),
				'keys'      => $keys,
			);
		}

		$updates = webo_mcp_rank_math_extract_public_meta_updates( $arguments );
		if ( empty( $updates ) ) {
			return new WP_Error( 'webo_mcp_rank_math_no_meta_fields', 'No whitelisted Rank Math meta fields were provided.', array( 'status' => 400 ) );
		}

		$keys    = array_keys( $updates );
		$before  = webo_rank_math_collect_post_meta( $post_id, $keys );
		$dry_run = ! empty( $arguments['force'] ) ? false : ( ! array_key_exists( 'dry_run', $arguments ) || filter_var( $arguments['dry_run'], FILTER_VALIDATE_BOOLEAN ) );
		$planned = array_merge( $before, $updates );

		webo_mcp_rank_math_audit_public_post_meta(
			'before_update',
			array(
				'post_id' => $post_id,
				'dry_run' => $dry_run,
				'keys'    => $keys,
				'before'  => $before,
				'planned' => $planned,
			)
		);

		if ( ! $dry_run ) {
			foreach ( $updates as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
			webo_mcp_rank_math_clear_post_meta_caches( $post_id );
		}

		$after = $dry_run ? $planned : webo_rank_math_collect_post_meta( $post_id, $keys );
		$diff  = webo_mcp_rank_math_build_meta_diff( $before, $after, $keys );

		webo_mcp_rank_math_audit_public_post_meta(
			'after_update',
			array(
				'post_id' => $post_id,
				'dry_run' => $dry_run,
				'keys'    => $keys,
				'after'   => $after,
			)
		);

		$changed_count = count( array_filter( $diff, static function ( $item ) {
			return ! empty( $item['changed'] );
		} ) );

		return webo_mcp_mutation_response(
			array(
				'dry_run'       => $dry_run,
				'would_change'  => $changed_count > 0,
				'planned_count' => $changed_count,
				'changed'       => ! $dry_run && $changed_count > 0,
				'changed_count' => $dry_run ? 0 : $changed_count,
				'diff'          => $diff,
				'context'       => array(
					'tool'          => 'webo-rank-math/post-seo-mutate',
					'post_id'       => $post_id,
					'post_type'     => $post ? $post->post_type : null,
					'slug'          => $post ? $post->post_name : null,
					'keys'          => $keys,
					'updated'       => ! $dry_run,
					'updated_count' => $dry_run ? 0 : $changed_count,
				),
			)
		);
	} );
}

/**
 * Execute the public post-seo-mutate dispatcher as a direct MCP tool.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return array<string,mixed>|\WP_Error
 */
function webo_mcp_rank_math_execute_post_seo_mutate_tool( $arguments ) {
	$action = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : 'update';

	if ( 'update' === $action ) {
		if ( webo_mcp_rank_math_is_quick_only_payload( $arguments ) ) {
			return webo_mcp_rank_math_execute_quick_update_tool( $arguments );
		}
		return webo_mcp_rank_math_execute_public_post_meta_tool( 'webo-rank-math/post-seo-mutate', $arguments );
	}

	if ( function_exists( 'wp_get_ability' ) ) {
		$ability = wp_get_ability( 'webo-rank-math/post-seo-mutate' );
		if ( $ability && method_exists( $ability, 'execute' ) ) {
			return $ability->execute( $arguments );
		}
	}

	return new WP_Error( 'webo_mcp_rank_math_handler_not_found', 'No handler available for webo-rank-math/post-seo-mutate.' );
}

/**
 * Execute the public AI-safe quick SEO update tool.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return array<string,mixed>|\WP_Error
 */
function webo_mcp_rank_math_execute_quick_update_tool( $arguments ) {
	$forbidden = webo_mcp_rank_math_quick_update_forbidden_fields( $arguments );
	if ( ! empty( $forbidden ) ) {
		return new WP_Error(
			'webo_mcp_rank_math_quick_update_forbidden_field',
			sprintf( 'seo_quick_update only accepts title, description, and focus_keyword. Forbidden fields: %s', implode( ', ', $forbidden ) ),
			array( 'status' => 400, 'fields' => $forbidden )
		);
	}

	return webo_rank_math_with_site( $arguments, function () use ( $arguments ) {
		$post_id = webo_mcp_rank_math_resolve_tool_post_id( $arguments );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post    = get_post( $post_id );
		$updates = webo_mcp_rank_math_extract_quick_meta_updates( $arguments );
		if ( empty( $updates ) ) {
			return new WP_Error( 'webo_mcp_rank_math_no_quick_fields', 'At least one of title, description, or focus_keyword is required.', array( 'status' => 400 ) );
		}

		$mode    = webo_mcp_resolve_mutation_mode( $arguments, false );
		$dry_run = ! empty( $mode['dry_run'] );
		$keys    = array_keys( $updates );
		$before  = webo_rank_math_collect_post_meta( $post_id, $keys );
		$planned = array_merge( $before, $updates );
		$diff    = webo_mcp_rank_math_build_meta_diff( $before, $planned, $keys );
		$planned_count = count(
			array_filter(
				$diff,
				static function ( $item ) {
					return ! empty( $item['changed'] );
				}
			)
		);

		if ( ! $dry_run ) {
			foreach ( $updates as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
			webo_mcp_rank_math_clear_post_meta_caches( $post_id );
			$after = webo_rank_math_collect_post_meta( $post_id, $keys );
			$diff  = webo_mcp_rank_math_build_meta_diff( $before, $after, $keys );
		}

		$changed_count = count(
			array_filter(
				$diff,
				static function ( $item ) {
					return ! empty( $item['changed'] );
				}
			)
		);

		return webo_mcp_mutation_response(
			array(
				'dry_run'       => $dry_run,
				'would_change'  => $planned_count > 0,
				'planned_count' => $planned_count,
				'changed'       => ! $dry_run && $changed_count > 0,
				'changed_count' => $dry_run ? 0 : $changed_count,
				'diff'          => $diff,
				'context'       => array(
					'tool'          => 'seo_quick_update',
					'post_id'       => $post_id,
					'post_type'     => $post ? $post->post_type : null,
					'slug'          => $post ? $post->post_name : null,
					'keys'          => $keys,
					'updated'       => ! $dry_run && $changed_count > 0,
					'updated_count' => $dry_run ? 0 : $changed_count,
				),
			)
		);
	} );
}

/**
 * Execute internal/admin-only advanced SEO updates.
 *
 * @param array<string,mixed> $arguments Tool arguments.
 * @return array<string,mixed>|\WP_Error
 */
function webo_mcp_rank_math_execute_advanced_update_tool( $arguments ) {
	$meta_updates = webo_mcp_rank_math_extract_public_meta_updates( $arguments );
	$schema_args  = array_intersect_key(
		$arguments,
		array(
			'action'      => true,
			'post_id'     => true,
			'id'          => true,
			'slug'        => true,
			'post_type'   => true,
			'site_id'     => true,
			'schema'      => true,
			'schemas'     => true,
			'schema_key'  => true,
			'schema_type' => true,
			'dry_run'     => true,
			'force'       => true,
			'delete_all'  => true,
		)
	);
	$schema_handled = false;

	if ( isset( $schema_args['schema'] ) || isset( $schema_args['schemas'] ) ) {
		$schema_args['action'] = isset( $schema_args['action'] ) ? $schema_args['action'] : 'upsert';
		$schema_result         = webo_mcp_rank_math_execute_schema_mutate_tool( $schema_args );
		if ( is_wp_error( $schema_result ) ) {
			return $schema_result;
		}
		$schema_handled = true;
	}

	if ( empty( $meta_updates ) && ! $schema_handled ) {
		return new WP_Error(
			'webo_mcp_rank_math_no_advanced_fields',
			'seo_advanced_update requires canonical, robots, schema, or social meta fields.',
			array( 'status' => 400 )
		);
	}

	if ( empty( $meta_updates ) ) {
		return array(
			'updated' => true,
			'context' => array(
				'tool' => 'seo_advanced_update',
				'mode' => 'schema-only',
			),
		);
	}

	return webo_mcp_rank_math_execute_public_post_meta_tool(
		'webo-rank-math/post-seo-mutate',
		array_merge(
			$arguments,
			array(
				'action' => isset( $arguments['action'] ) ? $arguments['action'] : 'update',
				'force'  => isset( $arguments['force'] ) ? $arguments['force'] : true,
			)
		)
	);
}

/**
 * Register short public Rank Math post meta tools in WEBO MCP ToolRegistry.
 *
 * @return void
 */

function webo_mcp_rank_math_tool_scope_risk( $tool_name ) {
	$map = array(
		'seo_quick_update'              => array( 'write', 'medium' ),
		'seo_advanced_update'           => array( 'admin', 'critical' ),
		'webo-rank-math/post-seo-query'  => array( 'read', 'low' ),
		'webo-rank-math/post-seo-mutate' => array( 'write', 'medium' ),
		'webo-rank-math/schema-mutate'   => array( 'write', 'high' ),
		'rankmath_get_post_meta'         => array( 'read', 'low' ),
		'rankmath_update_post_meta'      => array( 'write', 'medium' ),
	);
	return isset( $map[ $tool_name ] ) ? $map[ $tool_name ] : array( 'write', 'medium' );
}

function webo_mcp_rank_math_register_post_meta_tools_to_core_registry() {
	if ( ! class_exists( '\WeboMCP\Core\Registry\ToolRegistry' ) ) {
		return;
	}

	webo_mcp_rank_math_bootstrap();

	$tools = array(
		'seo_quick_update' => array(
			'description' => 'Update SEO title, meta description and focus keyword for a WordPress post.',
			'arguments'   => webo_mcp_rank_math_quick_update_tool_arguments(),
			'callback'    => static function ( array $arguments ) {
				return webo_mcp_rank_math_execute_quick_update_tool( $arguments );
			},
		),
		'seo_advanced_update' => array(
			'description' => 'Update advanced SEO metadata for a WordPress post.',
			'arguments'   => webo_mcp_rank_math_advanced_update_tool_arguments(),
			'callback'    => static function ( array $arguments ) {
				return webo_mcp_rank_math_execute_advanced_update_tool( $arguments );
			},
			'visibility'  => 'internal',
			'permission'  => 'manage_options',
		),
		'webo-rank-math/post-seo-query' => array(
			'description' => 'Rank Math post SEO query dispatcher. action: get, audit.',
			'arguments'   => webo_mcp_rank_math_post_meta_tool_arguments( false ),
			'callback'    => static function ( array $arguments ) {
				if ( function_exists( 'wp_get_ability' ) ) {
					$ability = wp_get_ability( 'webo-rank-math/post-seo-query' );
					if ( $ability && method_exists( $ability, 'execute' ) ) {
						return $ability->execute( array_merge( array( 'action' => 'get' ), $arguments ) );
					}
				}
				return webo_mcp_rank_math_execute_public_post_meta_tool( 'webo-rank-math/post-seo-query', $arguments );
			},
		),
		'webo-rank-math/post-seo-mutate' => array(
			'description' => '@deprecated Use seo_quick_update for title, description, and focus_keyword updates. Rank Math post SEO mutation dispatcher. action: update, bulk-upsert, cleanup.',
			'arguments'   => webo_mcp_rank_math_post_meta_tool_arguments( true, true ),
			'callback'    => static function ( array $arguments ) {
				return webo_mcp_rank_math_execute_post_seo_mutate_tool( $arguments );
			},
		),
		'webo-rank-math/schema-mutate' => array(
			'description' => 'Rank Math schema mutation dispatcher. action: upsert, delete, cleanup. Defaults to dry_run=true; set dry_run=false or force=true to write rank_math_schema_* meta.',
			'arguments'   => webo_mcp_rank_math_schema_mutate_tool_arguments(),
			'callback'    => static function ( array $arguments ) {
				return webo_mcp_rank_math_execute_schema_mutate_tool( $arguments );
			},
		),
		'rankmath_get_post_meta' => array(
			'description' => 'Get whitelisted Rank Math SEO post meta for one post.',
			'arguments'   => webo_mcp_rank_math_post_meta_tool_arguments( false ),
			'callback'    => static function ( array $arguments ) {
				return webo_mcp_rank_math_execute_public_post_meta_tool( 'rankmath_get_post_meta', $arguments );
			},
		),
		'rankmath_update_post_meta' => array(
			'description' => '@deprecated Use seo_quick_update for title, description, and focus_keyword updates. Update whitelisted Rank Math SEO post meta for one post.',
			'arguments'   => webo_mcp_rank_math_post_meta_tool_arguments( true, true ),
			'callback'    => static function ( array $arguments ) {
				if ( webo_mcp_rank_math_is_quick_only_payload( $arguments ) ) {
					return webo_mcp_rank_math_execute_quick_update_tool( $arguments );
				}
				return webo_mcp_rank_math_execute_public_post_meta_tool( 'rankmath_update_post_meta', $arguments );
			},
		),
	);

	foreach ( $tools as $tool_name => $tool ) {
		if ( \WeboMCP\Core\Registry\ToolRegistry::get( $tool_name ) ) {
			continue;
		}

		$scope_risk = webo_mcp_rank_math_tool_scope_risk( (string) $tool_name );

		\WeboMCP\Core\Registry\ToolRegistry::register(
			array(
				'name'        => $tool_name,
				'description' => $tool['description'],
				'category'    => 'rank_math',
				'visibility'  => isset( $tool['visibility'] ) ? (string) $tool['visibility'] : 'public',
				'arguments'   => $tool['arguments'],
				'permission'  => isset( $tool['permission'] ) ? (string) $tool['permission'] : '',
				'meta'        => array(
					'schema_version' => WEBO_MCP_RANK_MATH_VERSION,
					'addon'          => 'webo-mcp-rank-math',
					'deprecated'     => in_array( $tool_name, array( 'rankmath_update_post_meta', 'webo-rank-math/post-seo-mutate' ), true ),
					'mcp'            => array(
						'public' => 'internal' !== ( isset( $tool['visibility'] ) ? (string) $tool['visibility'] : 'public' ),
						'type'   => 'tool',
					),
					'webo_mcp'       => array(
						'scope'    => $scope_risk[0],
						'risk'     => $scope_risk[1],
						'category' => 'rank_math',
					),
				),
				'callback'    => static function ( array $arguments ) use ( $tool_name, $tool ) {
					if ( ! function_exists( 'webo_rank_math_with_site' ) ) {
						return new WP_Error( 'webo_mcp_rank_math_helpers_unavailable', 'Rank Math MCP helpers are not available.' );
					}
					if ( isset( $tool['callback'] ) && is_callable( $tool['callback'] ) ) {
						return call_user_func( $tool['callback'], $arguments );
					}
					return webo_mcp_rank_math_execute_public_post_meta_tool( $tool_name, $arguments );
				},
			)
		);
	}
}

/**
 * Hide deprecated Rank Math mutation tools from public discovery while keeping
 * exact-name calls backward compatible.
 *
 * @param array<string,mixed> $payload          Tools/list payload.
 * @param bool                $include_internal Whether internal tools are included.
 * @return array<string,mixed>
 */
function webo_mcp_rank_math_filter_public_tools_list_payload( $payload, $include_internal ) {
	if ( ! is_array( $payload ) || ! empty( $include_internal ) || empty( $payload['tools'] ) || ! is_array( $payload['tools'] ) ) {
		return $payload;
	}

	$payload['tools'] = array_values(
		array_filter(
			$payload['tools'],
			static function ( $tool ) {
				if ( ! isset( $tool['name'] ) ) {
					return true;
				}

				$name = (string) $tool['name'];
				if ( 'seo_quick_update' === $name ) {
					return true;
				}

				if ( 0 === strpos( $name, 'webo-rank-math/' ) || 0 === strpos( $name, 'rankmath_' ) ) {
					return false;
				}

				return true;
			}
		)
	);

	return $payload;
}

/**
 * Bump WEBO MCP tools/list cache epoch when this addon changes registry shape.
 *
 * @return void
 */
function webo_mcp_rank_math_bump_tools_list_cache() {
	if ( function_exists( 'webo_mcp_bump_tools_list_cache' ) ) {
		webo_mcp_bump_tools_list_cache();
		return;
	}

	if ( function_exists( 'update_option' ) ) {
		update_option( 'webo_mcp_tools_list_cache_epoch', (string) time(), false );
	}
}

/**
 * Bootstrap Rank Math addon: register ability meta for MCP and load abilities.
 *
 * @return bool True if bootstrapped, false if dependencies missing.
 */
function webo_mcp_rank_math_bootstrap() {
	static $initialized = false;

	if ( $initialized ) {
		return true;
	}

	if ( ! function_exists( 'wp_register_ability' ) ) {
		return false;
	}

	add_filter( 'wp_register_ability_args', 'webo_mcp_rank_math_ability_args', 10, 2 );
	require_once WEBO_MCP_RANK_MATH_PATH . 'abilities/helpers.php';
	require_once WEBO_MCP_RANK_MATH_PATH . 'abilities/unified-dispatchers.php';
	require_once WEBO_MCP_RANK_MATH_PATH . 'abilities/rank-math-management.php';
	require_once WEBO_MCP_RANK_MATH_PATH . 'abilities/redirections.php';
	require_once WEBO_MCP_RANK_MATH_PATH . 'frontend-fallback.php';
	add_filter( 'rank_math/sitemap/exclude_taxonomy', 'webo_mcp_rank_math_exclude_disabled_sitemap_taxonomies', 20, 2 );
	add_filter( 'rank_math/sitemap/html_sitemap_taxonomies', 'webo_mcp_rank_math_filter_html_sitemap_taxonomies', 20, 1 );
	$initialized = true;

	return true;
}

function webo_mcp_rank_math_option_is_disabled( $value ) {
	if ( is_bool( $value ) ) {
		return ! $value;
	}

	if ( is_numeric( $value ) ) {
		return 0 === intval( $value );
	}

	$value = strtolower( trim( (string) $value ) );
	return in_array( $value, array( '', '0', 'false', 'off', 'no', 'disabled' ), true );
}

function webo_mcp_rank_math_exclude_disabled_sitemap_taxonomies( $exclude, $taxonomy ) {
	$options = get_option( 'rank-math-options-sitemap', array() );
	if ( ! is_array( $options ) ) {
		return $exclude;
	}

	$key = 'tax_' . sanitize_key( (string) $taxonomy ) . '_sitemap';
	if ( array_key_exists( $key, $options ) && webo_mcp_rank_math_option_is_disabled( $options[ $key ] ) ) {
		return true;
	}

	return $exclude;
}

function webo_mcp_rank_math_filter_html_sitemap_taxonomies( $taxonomies ) {
	$options = get_option( 'rank-math-options-sitemap', array() );
	if ( ! is_array( $taxonomies ) || ! is_array( $options ) ) {
		return $taxonomies;
	}

	foreach ( $taxonomies as $index => $taxonomy ) {
		$name = is_object( $taxonomy ) && isset( $taxonomy->name ) ? $taxonomy->name : (string) $taxonomy;
		$key  = 'tax_' . sanitize_key( $name ) . '_html_sitemap';
		if ( array_key_exists( $key, $options ) && webo_mcp_rank_math_option_is_disabled( $options[ $key ] ) ) {
			unset( $taxonomies[ $index ] );
		}
	}

	return array_values( $taxonomies );
}

/**
 * Filter ability args for webo-rank-math abilities: expose in REST and set MCP meta.
 *
 * @param array  $args Ability registration args.
 * @param string $name Ability name.
 * @return array
 */
function webo_mcp_rank_math_ability_args( $args, $name ) {
	if ( 0 !== strpos( (string) $name, 'webo-rank-math/' ) ) {
		return $args;
	}

	if ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
		$args['meta'] = array();
	}
	if ( ! isset( $args['meta']['mcp'] ) || ! is_array( $args['meta']['mcp'] ) ) {
		$args['meta']['mcp'] = array();
	}
	if ( ! isset( $args['meta']['webo_mcp'] ) || ! is_array( $args['meta']['webo_mcp'] ) ) {
		$args['meta']['webo_mcp'] = array();
	}

	$args['meta']['show_in_rest'] = true;
	$args['meta']['mcp']['public'] = in_array( (string) $name, webo_mcp_rank_math_public_mcp_ability_names(), true );
	if ( ! isset( $args['meta']['mcp']['type'] ) ) {
		$args['meta']['mcp']['type'] = 'tool';
	}
	if ( false !== strpos( (string) $name, '-query' ) || false !== strpos( (string) $name, '/get-' ) || false !== strpos( (string) $name, '/list-' ) ) {
		$args['meta']['webo_mcp']['scope'] = 'read';
		$args['meta']['webo_mcp']['risk']  = 'low';
	} elseif ( 'webo-rank-math/config-mutate' === (string) $name ) {
		$args['meta']['webo_mcp']['scope'] = 'admin';
		$args['meta']['webo_mcp']['risk']  = 'critical';
	} else {
		$args['meta']['webo_mcp']['scope'] = 'write';
		$args['meta']['webo_mcp']['risk']  = 'medium';
	}

	return $args;
}

/**
 * WEBO MCP denies ability names containing "bulk" by default; allow this addon's bulk upsert.
 *
 * @param bool   $allow        Whether bridging is allowed before this filter.
 * @param string $ability_name Registered ability name.
 * @return bool
 */
add_filter(
	'webo_mcp_should_bridge_ability',
	static function ( $allow, $ability_name ) {
		if ( 'webo-rank-math/bulk-upsert-post-seo-meta' === $ability_name ) {
			return true;
		}
		return $allow;
	},
	10,
	2
);

/**
 * Add Rank Math abilities to the WEBO WordPress MCP core bridge.
 *
 * The internal WEBO MCP abilities pack bridges only selected prefixes by default.
 * Without this prefix, the abilities are registered and can be used internally by
 * other analyzers, but `webo-rank-math/*` tools do not appear in MCP tools/list.
 *
 * @param array $prefixes Existing bridge prefixes.
 * @return array
 */
add_filter(
	'webo_wordpress_mcp_bridge_prefixes',
	static function ( $prefixes ) {
		if ( ! is_array( $prefixes ) ) {
			$prefixes = array();
		}

		$prefixes[] = 'webo-rank-math/';

		return array_values( array_unique( array_filter( array_map( 'strval', $prefixes ) ) ) );
	}
);

add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'webo-rank-math/schema-mutate',
			array(
				'label'       => 'Rank Math Schema Mutation',
				'description' => 'Safely upsert, delete, or cleanup Rank Math schema post meta. Defaults to dry_run=true.',
				'category'    => 'webo-rank-math',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'action'      => array(
							'type'        => 'string',
							'default'     => 'upsert',
							'description' => 'Mutation action: upsert, delete, cleanup.',
						),
						'post_id'     => array( 'type' => 'integer', 'minimum' => 1 ),
						'id'          => array( 'type' => 'integer', 'minimum' => 1 ),
						'slug'        => array( 'type' => 'string' ),
						'post_type'   => array( 'type' => 'string', 'default' => 'post' ),
						'site_id'     => array( 'type' => 'integer', 'minimum' => 1 ),
						'blog_id'     => array( 'type' => 'integer', 'minimum' => 1 ),
						'domain'      => array( 'type' => 'string' ),
						'url'         => array( 'type' => 'string' ),
						'schema_key'  => array(
							'type'        => 'string',
							'description' => 'Rank Math schema meta key, for example rank_math_schema_Article.',
						),
						'schema_type' => array(
							'type'        => 'string',
							'description' => 'Fallback schema type used to derive schema_key.',
						),
						'schema'      => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'schemas'     => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'delete_all'  => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'dry_run'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'force'       => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback' => static function ( $input ) {
					return webo_mcp_rank_math_execute_schema_mutate_tool( is_array( $input ) ? $input : array() );
				},
				'permission_callback' => static function ( $input ) {
					$site_id = is_array( $input ) ? ( $input['site_id'] ?? 0 ) : 0;
					return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
						? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $site_id )
						: current_user_can( 'edit_posts' );
				},
				'meta' => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
					'webo_mcp'     => array(
						'scope' => 'write',
						'risk'  => 'high',
						'action_scopes' => array(
							'upsert'  => 'write',
							'delete'  => 'write',
							'cleanup' => 'write',
						),
						'action_risks' => array(
							'upsert' => 'medium',
							'delete' => 'high',
							'cleanup' => 'high',
						),
					),
				),
			)
		);
	},
	25
);

add_action( 'plugins_loaded', function () {
	webo_mcp_rank_math_load_textdomain();
	webo_mcp_rank_math_bootstrap();
}, 5 );

add_action( 'init', 'webo_mcp_rank_math_bootstrap', 1 );
add_action( 'webo_mcp_register_tools', 'webo_mcp_rank_math_register_post_meta_tools_to_core_registry', 30 );
add_action( 'wp_abilities_api_init', 'webo_mcp_rank_math_register_post_meta_tools_to_core_registry', 30 );
add_action( 'webo_mcp_loaded', 'webo_mcp_rank_math_bootstrap', 20 );
add_filter( 'webo_mcp_tools_list_payload', 'webo_mcp_rank_math_filter_public_tools_list_payload', 20, 2 );
register_activation_hook( __FILE__, 'webo_mcp_rank_math_bump_tools_list_cache' );

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! function_exists( 'wp_register_ability' ) ) {
		echo '<div class="notice notice-warning"><p><strong>WEBO MCP Rank Math:</strong> ' . esc_html__( 'Missing dependency webo-mcp (Abilities API not available).', 'webo-mcp-rank-math' ) . '</p></div>';
		return;
	}
	if ( ! defined( 'RANK_MATH_VERSION' ) && ! class_exists( 'RankMath\\Helper' ) ) {
		echo '<div class="notice notice-warning"><p><strong>WEBO MCP Rank Math:</strong> ' . esc_html__( 'Missing dependency seo-by-rank-math. Please install and activate Rank Math SEO.', 'webo-mcp-rank-math' ) . '</p></div>';
	}
} );
