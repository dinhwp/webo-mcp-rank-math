<?php
/**
 * WEBO MCP - Rank Math options and modules abilities.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_abilities_api_init', function () {
	wp_register_ability( 'webo-rank-math/get-options', array(
		'label'       => 'Get Rank Math Options',
		'description' => 'Read Rank Math option groups.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'option_names' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Option names to read. Default: common Rank Math groups.',
				),
				'site_id'      => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$names   = ! empty( $input['option_names'] )
					? array_values( array_filter( array_map( 'sanitize_key', (array) $input['option_names'] ) ) )
					: webo_rank_math_default_option_names();
				$options = array();
				foreach ( $names as $name ) {
					$value = get_option( $name, null );
					if ( $value !== null ) {
						$options[ $name ] = $value;
					}
				}
				return array( 'count' => count( $options ), 'options' => $options );
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/update-options', array(
		'label'       => 'Update Rank Math Options',
		'description' => 'Update Rank Math options by name. Only rank_math* and rank-math-options-* options are allowed.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'options' ),
			'properties' => array(
				'options' => array( 'type' => 'object', 'additionalProperties' => true ),
				'site_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$updated = array();
				foreach ( (array) $input['options'] as $name => $value ) {
					$k = sanitize_key( $name );
					if ( $k === '' || ! webo_rank_math_is_allowed_option_name( $k ) ) {
						continue;
					}
					update_option( $k, $value );
					$updated[ $k ] = get_option( $k );
				}
				return array( 'updated_count' => count( $updated ), 'options' => $updated );
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/get-modules', array(
		'label'       => 'Get Rank Math Modules',
		'description' => 'Get active Rank Math modules list.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array( 'site_id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () {
				$modules = array_values( array_filter( (array) get_option( 'rank_math_modules', array() ) ) );
				sort( $modules );
				return array( 'count' => count( $modules ), 'modules' => $modules );
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/update-modules', array(
		'label'       => 'Update Rank Math Modules',
		'description' => 'Set active Rank Math modules (bulk).',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'modules' ),
			'properties' => array(
				'modules' => array(
					'type'     => 'array',
					'items'    => array( 'type' => 'string' ),
					'minItems' => 0,
				),
				'site_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$modules = array_values( array_unique( array_filter( array_map( function ( $v ) {
					return sanitize_key( (string) $v );
				}, (array) $input['modules'] ) ) ) );
				sort( $modules );
				update_option( 'rank_math_modules', $modules );
				return array( 'updated' => true, 'count' => count( $modules ), 'modules' => $modules );
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );
} );
