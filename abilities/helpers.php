<?php
/**
 * WEBO MCP - Rank Math helpers (meta keys, option names, resolve/collect/update).
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return string[] */
function webo_rank_math_meta_keys() {
	return array(
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_canonical_url',
		'rank_math_robots',
		'rank_math_facebook_title',
		'rank_math_facebook_description',
		'rank_math_facebook_image',
		'rank_math_twitter_title',
		'rank_math_twitter_description',
		'rank_math_twitter_image',
		'rank_math_twitter_card_type',
		'rank_math_schema_type',
		'rank_math_schema_Article',
		'rank_math_schema_Product',
		'rank_math_pillar_content',
		'rank_math_seo_score',
		'rank_math_primary_category',
	);
}

/** @return string[] */
function webo_rank_math_default_option_names() {
	return array(
		'rank_math_modules',
		'rank-math-options-general',
		'rank-math-options-titles',
		'rank-math-options-sitemap',
		'rank-math-options-social',
		'rank-math-options-instant-indexing',
		'rank_math_options_general',
		'rank_math_options_titles',
		'rank_math_options_sitemap',
		'rank_math_options_social',
		'rank_math_options_instant_indexing',
		'rank_math_google_analytic_options',
		'rank_math_google_analytic_profile',
		'rank_math_analytics_installed',
	);
}

/**
 * Check whether a Rank Math option name is safe for MCP updates.
 *
 * Rank Math stores active modules and a few analytics flags as rank_math_* options,
 * while its main option groups use rank-math-options-* names.
 *
 * @param string $name Sanitized option name.
 * @return bool
 */
function webo_rank_math_is_allowed_option_name( $name ) {
	return 0 === strpos( $name, 'rank_math' ) || 0 === strpos( $name, 'rank-math-options-' );
}

/** @return string[] */
function webo_rank_math_user_meta_keys() {
	return array(
		'rank_math_title',
		'rank_math_description',
		'rank_math_robots',
		'rank_math_canonical_url',
	);
}

/**
 * Resolve post ID from input (post_id or slug + post_type).
 *
 * @param array $input
 * @return int|null
 */
function webo_rank_math_resolve_post_id( $input ) {
	if ( ! empty( $input['post_id'] ) ) {
		$post_id = absint( $input['post_id'] );
		return $post_id > 0 ? $post_id : null;
	}
	if ( ! empty( $input['slug'] ) ) {
		$post_type = ! empty( $input['post_type'] ) ? $input['post_type'] : 'post';
		$query     = new WP_Query( array(
			'name'           => sanitize_title( $input['slug'] ),
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0];
		}
	}
	return null;
}

/**
 * Run callback with optional multisite switch; restore in finally.
 * Returns WP_Error from switch or callback result.
 *
 * @param int    $site_id   Site ID (0 = current).
 * @param callable $callback function() { return result; }
 * @return mixed|WP_Error
 */
function webo_rank_math_with_site( $site_id, $callback ) {
	$context = function_exists( 'webo_mcp_multisite_switch_to_site' )
		? webo_mcp_multisite_switch_to_site( $site_id )
		: array( 'switched' => false );

	if ( is_wp_error( $context ) ) {
		return $context;
	}
	try {
		return $callback();
	} finally {
		if ( ! empty( $context['switched'] ) ) {
			restore_current_blog();
		}
	}
}

function webo_rank_math_collect_post_meta( $post_id, $keys = null ) {
	$keys   = is_array( $keys ) && ! empty( $keys ) ? $keys : webo_rank_math_meta_keys();
	$result = array();
	foreach ( $keys as $key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( $value !== '' && $value !== null ) {
			$result[ $key ] = maybe_unserialize( $value );
		}
	}
	return $result;
}

function webo_rank_math_collect_term_meta( $term_id, $keys = null ) {
	$keys   = is_array( $keys ) && ! empty( $keys ) ? $keys : webo_rank_math_meta_keys();
	$result = array();
	foreach ( $keys as $key ) {
		$value = get_term_meta( $term_id, $key, true );
		if ( $value !== '' && $value !== null ) {
			$result[ $key ] = maybe_unserialize( $value );
		}
	}
	return $result;
}

function webo_rank_math_collect_user_meta( $user_id, $keys = null ) {
	$keys   = is_array( $keys ) && ! empty( $keys ) ? $keys : webo_rank_math_user_meta_keys();
	$result = array();
	foreach ( $keys as $key ) {
		$value = get_user_meta( $user_id, $key, true );
		if ( $value !== '' && $value !== null ) {
			$result[ $key ] = maybe_unserialize( $value );
		}
	}
	return $result;
}

function webo_rank_math_update_post_meta_map( $post_id, $meta ) {
	foreach ( (array) $meta as $key => $value ) {
		$k = sanitize_key( $key );
		if ( $k === '' ) {
			continue;
		}
		if ( $value === null ) {
			delete_post_meta( $post_id, $k );
		} else {
			update_post_meta( $post_id, $k, $value );
		}
	}
}

function webo_rank_math_cleanup_post_schema_meta( $post_id, $delete_all = false ) {
	global $wpdb;

	$post_id = absint( $post_id );
	if ( $post_id < 1 ) {
		return array();
	}

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
			$post_id,
			$wpdb->esc_like( 'rank_math_schema_' ) . '%'
		),
		ARRAY_A
	);

	$deleted = array();
	$kept    = array();

	foreach ( (array) $rows as $row ) {
		$key   = (string) $row['meta_key'];
		$value = maybe_unserialize( $row['meta_value'] );
		$bad   = ! is_array( $value ) || empty( $value['@type'] );

		if ( $delete_all || $bad ) {
			delete_metadata_by_mid( 'post', (int) $row['meta_id'] );
			$deleted[] = array(
				'meta_id'    => (int) $row['meta_id'],
				'meta_key'   => $key,
				'value_type' => is_object( $value ) ? get_class( $value ) : gettype( $value ),
				'reason'     => $delete_all ? 'delete_all' : 'malformed',
			);
		} else {
			$kept[] = array(
				'meta_id'  => (int) $row['meta_id'],
				'meta_key' => $key,
				'type'     => $value['@type'],
			);
		}
	}

	return array(
		'found_count'   => count( (array) $rows ),
		'deleted_count' => count( $deleted ),
		'kept_count'    => count( $kept ),
		'deleted'       => $deleted,
		'kept'          => $kept,
	);
}

function webo_rank_math_audit_schema_meta( $args = array() ) {
	global $wpdb;

	$post_types = isset( $args['post_types'] ) && is_array( $args['post_types'] ) ? array_filter( array_map( 'sanitize_key', $args['post_types'] ) ) : array();
	if ( empty( $post_types ) ) {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		$post_types = array_values( $post_types );
	}

	$statuses = isset( $args['statuses'] ) && is_array( $args['statuses'] ) ? array_filter( array_map( 'sanitize_key', $args['statuses'] ) ) : array( 'publish' );
	$limit    = isset( $args['limit'] ) ? absint( $args['limit'] ) : 500;
	$limit    = max( 1, min( 2000, $limit ) );
	$offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

	$type_placeholders   = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
	$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	$query_args          = array_merge(
		array( $wpdb->esc_like( 'rank_math_schema_' ) . '%' ),
		$post_types,
		$statuses,
		array( $limit, $offset )
	);

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value, p.post_title, p.post_name, p.post_type, p.post_status
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key LIKE %s
			AND p.post_type IN ($type_placeholders)
			AND p.post_status IN ($status_placeholders)
			ORDER BY pm.post_id DESC, pm.meta_id ASC
			LIMIT %d OFFSET %d",
			$query_args
		),
		ARRAY_A
	);

	$malformed = array();
	$valid     = array();

	foreach ( (array) $rows as $row ) {
		$value      = maybe_unserialize( $row['meta_value'] );
		$value_type = is_object( $value ) ? get_class( $value ) : gettype( $value );
		$is_valid   = is_array( $value ) && ! empty( $value['@type'] );
		$item       = array(
			'meta_id'    => (int) $row['meta_id'],
			'post_id'    => (int) $row['post_id'],
			'post_type'  => $row['post_type'],
			'status'     => $row['post_status'],
			'slug'       => $row['post_name'],
			'title'      => get_the_title( (int) $row['post_id'] ),
			'permalink'  => get_permalink( (int) $row['post_id'] ),
			'meta_key'   => $row['meta_key'],
			'value_type' => $value_type,
		);

		if ( $is_valid ) {
			$item['schema_type'] = $value['@type'];
			$valid[]            = $item;
		} else {
			$item['reason']  = is_array( $value ) ? 'missing_type' : 'not_array';
			$item['preview'] = is_scalar( $value ) ? substr( (string) $value, 0, 120 ) : '';
			$malformed[]     = $item;
		}
	}

	return array(
		'scanned_meta_count' => count( (array) $rows ),
		'malformed_count'    => count( $malformed ),
		'valid_count'        => count( $valid ),
		'post_types'         => array_values( $post_types ),
		'statuses'           => array_values( $statuses ),
		'limit'              => $limit,
		'offset'             => $offset,
		'malformed'          => $malformed,
		'valid'              => $valid,
	);
}

function webo_rank_math_update_term_meta_map( $term_id, $meta ) {
	foreach ( (array) $meta as $key => $value ) {
		$k = sanitize_key( $key );
		if ( $k === '' ) {
			continue;
		}
		if ( $value === null ) {
			delete_term_meta( $term_id, $k );
		} else {
			update_term_meta( $term_id, $k, $value );
		}
	}
}

function webo_rank_math_update_user_meta_map( $user_id, $meta ) {
	foreach ( (array) $meta as $key => $value ) {
		$k = sanitize_key( $key );
		if ( $k === '' ) {
			continue;
		}
		if ( $value === null ) {
			delete_user_meta( $user_id, $k );
		} else {
			update_user_meta( $user_id, $k, $value );
		}
	}
}
