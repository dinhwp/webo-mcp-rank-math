<?php
/**
 * WEBO MCP - Rank Math term SEO meta abilities.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_abilities_api_init', function () {
	wp_register_ability( 'webo-rank-math/get-term-seo-meta', array(
		'label'       => 'Get Term SEO Meta (Rank Math)',
		'description' => 'Get Rank Math SEO metadata for a taxonomy term.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'term_id' ),
			'properties' => array(
				'term_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'keys'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'site_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$term = get_term( $input['term_id'] );
				if ( ! $term || is_wp_error( $term ) ) {
					return new WP_Error( 'term_not_found', 'Term not found.', array( 'status' => 404 ) );
				}
				return array(
					'term_id'   => (int) $term->term_id,
					'taxonomy'   => $term->taxonomy,
					'slug'      => $term->slug,
					'seo_meta'  => webo_rank_math_collect_term_meta( (int) $term->term_id, $input['keys'] ?? null ),
				);
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_categories', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_categories' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/update-term-seo-meta', array(
		'label'       => 'Update Term SEO Meta (Rank Math)',
		'description' => 'Update Rank Math SEO metadata for one term. Set value to null to delete key.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'term_id', 'seo_meta' ),
			'properties' => array(
				'term_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
				'seo_meta' => array( 'type' => 'object', 'additionalProperties' => true ),
				'site_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$term = get_term( $input['term_id'] );
				if ( ! $term || is_wp_error( $term ) ) {
					return new WP_Error( 'term_not_found', 'Term not found.', array( 'status' => 404 ) );
				}
				$term_id = (int) $term->term_id;
				webo_rank_math_update_term_meta_map( $term_id, $input['seo_meta'] );
				return array(
					'updated'  => true,
					'term_id'  => $term_id,
					'taxonomy' => $term->taxonomy,
					'seo_meta' => webo_rank_math_collect_term_meta( $term_id ),
				);
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_categories', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_categories' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );
} );
