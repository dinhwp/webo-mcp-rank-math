<?php
/**
 * WEBO MCP - Rank Math get-plugin-status ability.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_abilities_api_init', function () {
	wp_register_ability( 'webo-rank-math/get-plugin-status', array(
		'label'       => 'Get Rank Math Plugin Status',
		'description' => 'Get current status of Rank Math plugin and key options/modules.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'site_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Target site ID in multisite (optional).',
				),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () {
				$active = (array) get_option( 'active_plugins', array() );
				return array(
					'rank_math_active'  => in_array( 'seo-by-rank-math/rank-math.php', $active, true ) || defined( 'RANK_MATH_VERSION' ),
					'rank_math_version' => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : null,
					'rank_math_modules' => (array) get_option( 'rank_math_modules', array() ),
					'options_available' => array_values( array_filter( webo_rank_math_default_option_names(), function ( $name ) {
						return get_option( $name, null ) !== null;
					} ) ),
				);
			} );
		},
		'permission_callback' => function ( $input ) {
			if ( function_exists( 'webo_mcp_multisite_current_user_can_for_site' ) ) {
				return webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 );
			}
			return current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );
} );
