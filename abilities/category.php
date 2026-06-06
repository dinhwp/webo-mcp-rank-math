<?php
/**
 * WEBO MCP - Rank Math ability category.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_abilities_api_categories_init', function () {
	if ( ! wp_has_ability_category( 'webo-rank-math' ) ) {
		wp_register_ability_category( 'webo-rank-math', array(
			'label'       => 'WEBO Rank Math',
			'description' => 'Rank Math SEO: status, options, modules, post/term/user meta, redirections.',
		) );
	}
} );
