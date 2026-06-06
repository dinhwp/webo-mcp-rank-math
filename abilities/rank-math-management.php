<?php
/**
 * WEBO MCP - Rank Math abilities loader.
 *
 * Loads helpers, category, and all ability modules. Keep this file as single entry point.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$abilities_path = dirname( __FILE__ ) . '/';

// Unified dispatchers handle all abilities via 10 unified tools
// require_once $abilities_path . 'helpers.php';  // Loaded separately in bootstrap
require_once $abilities_path . 'category.php';
// Individual ability files disabled in favor of unified dispatchers:
// require_once $abilities_path . 'ability-plugin-status.php';
// require_once $abilities_path . 'ability-post-meta.php';
// require_once $abilities_path . 'ability-term-meta.php';
// require_once $abilities_path . 'ability-user-meta.php';
// require_once $abilities_path . 'ability-options.php';
// require_once $abilities_path . 'redirections.php';  // Unified in dispatchers
