<?php
/**
 * WEBO MCP - Rank Math user/author SEO meta abilities.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_abilities_api_init', function () {
	wp_register_ability( 'webo-rank-math/get-user-seo-meta', array(
		'label'       => 'Get User/Author SEO Meta (Rank Math)',
		'description' => 'Get Rank Math SEO metadata for a user (author archive).',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'keys'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'site_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$user_id = absint( $input['user_id'] );
				$user    = get_userdata( $user_id );
				if ( ! $user ) {
					return new WP_Error( 'user_not_found', 'User not found.', array( 'status' => 404 ) );
				}
				return array(
					'user_id'   => $user_id,
					'login'     => $user->user_login,
					'seo_meta'  => webo_rank_math_collect_user_meta( $user_id, $input['keys'] ?? null ),
				);
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_users', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_users' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/update-user-seo-meta', array(
		'label'       => 'Update User/Author SEO Meta (Rank Math)',
		'description' => 'Update Rank Math SEO metadata for one user. Set value to null to delete key.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id', 'seo_meta' ),
			'properties' => array(
				'user_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
				'seo_meta' => array( 'type' => 'object', 'additionalProperties' => true ),
				'site_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$user_id = absint( $input['user_id'] );
				$user    = get_userdata( $user_id );
				if ( ! $user ) {
					return new WP_Error( 'user_not_found', 'User not found.', array( 'status' => 404 ) );
				}
				webo_rank_math_update_user_meta_map( $user_id, $input['seo_meta'] );
				return array(
					'updated'  => true,
					'user_id'  => $user_id,
					'login'    => $user->user_login,
					'seo_meta' => webo_rank_math_collect_user_meta( $user_id ),
				);
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_users', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_users' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );
} );
