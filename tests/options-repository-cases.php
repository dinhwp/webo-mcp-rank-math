<?php
/**
 * Unit tests for WeboMcpRankMath_OptionsRepository.
 *
 * Run:  php tests/options-repository-cases.php
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

// Minimal WP stubs
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ) ); } // phpcs:ignore
}

// In-memory options store
$GLOBALS['webo_test_options'] = array(
	'rank-math-options-general' => array( 'knowledgegraph_name' => 'TestBrand', 'knowledgegraph_type' => 'organization' ),
	'rank-math-options-titles'  => array( 'website_name' => 'TestBrand Site', 'homepage_title' => 'Home | TestBrand' ),
	'rank-math-options-sitemap' => array( 'items_per_page' => 200 ),
	'rank_math_modules'         => array( 'sitemap', 'rich-snippet' ),
);

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $k, $default = null ) { return $GLOBALS['webo_test_options'][ $k ] ?? $default; } // phpcs:ignore
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $k, $v, $a = null ) { $GLOBALS['webo_test_options'][ $k ] = $v; return true; } // phpcs:ignore
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $k ) { unset( $GLOBALS['webo_test_options'][ $k ] ); return true; } // phpcs:ignore
}

require_once dirname( __DIR__ ) . '/includes/class-rank-math-options-repository.php';

// ---------------------------------------------------------------------------
$failures = 0;
$assert   = static function ( $cond, $msg ) use ( &$failures ) {
	echo ( $cond ? 'PASS: ' : 'FAIL: ' ) . $msg . PHP_EOL;
	if ( ! $cond ) { ++$failures; }
};

echo PHP_EOL . '=== OptionsRepository ===' . PHP_EOL;

// is_allowed
$assert( WeboMcpRankMath_OptionsRepository::is_allowed( 'rank-math-options-general' ), 'dash prefix allowed' );
$assert( WeboMcpRankMath_OptionsRepository::is_allowed( 'rank_math_modules' ), 'underscore prefix allowed' );
$assert( ! WeboMcpRankMath_OptionsRepository::is_allowed( 'siteurl' ), 'siteurl not allowed' );
$assert( ! WeboMcpRankMath_OptionsRepository::is_allowed( 'wp_options' ), 'wp_options not allowed' );

// option_group_map
$map = WeboMcpRankMath_OptionsRepository::option_group_map();
$assert( $map['general'] === 'rank-math-options-general', 'general maps correctly' );
$assert( $map['titles'] === 'rank-math-options-titles', 'titles maps correctly' );
$assert( $map['sitemap'] === 'rank-math-options-sitemap', 'sitemap maps correctly' );
$assert( $map['social'] === 'rank-math-options-social', 'social maps correctly' );
$assert( isset( $map['instant-indexing'] ), 'instant-indexing key exists' );

// resolve_option_name
$assert( WeboMcpRankMath_OptionsRepository::resolve_option_name( 'general' ) === 'rank-math-options-general', 'resolve general' );
$assert( WeboMcpRankMath_OptionsRepository::resolve_option_name( 'titles' ) === 'rank-math-options-titles', 'resolve titles' );
$assert( WeboMcpRankMath_OptionsRepository::resolve_option_name( 'rank-math-options-general' ) === 'rank-math-options-general', 'resolve full name passthrough' );
$assert( null === WeboMcpRankMath_OptionsRepository::resolve_option_name( 'unknown' ), 'resolve unknown returns null' );

// get
$general = WeboMcpRankMath_OptionsRepository::get( 'rank-math-options-general' );
$assert( is_array( $general ), 'get() returns array for known option' );
$assert( $general['knowledgegraph_name'] === 'TestBrand', 'get() returns correct value' );

$blocked = WeboMcpRankMath_OptionsRepository::get( 'siteurl', 'DEFAULT' );
$assert( $blocked === 'DEFAULT', 'get() returns default for blocked option' );

// get_group
$group = WeboMcpRankMath_OptionsRepository::get_group( 'general' );
$assert( $group['knowledgegraph_name'] === 'TestBrand', 'get_group() returns correct value' );

$empty = WeboMcpRankMath_OptionsRepository::get_group( 'nonexistent' );
$assert( $empty === array(), 'get_group() returns empty array for unknown group' );

// set
$result = WeboMcpRankMath_OptionsRepository::set( 'rank-math-options-general', array( 'knowledgegraph_name' => 'NewBrand' ) );
$assert( true === $result, 'set() returns true' );
$assert( $GLOBALS['webo_test_options']['rank-math-options-general']['knowledgegraph_name'] === 'NewBrand', 'set() persists value' );

$blocked_set = WeboMcpRankMath_OptionsRepository::set( 'siteurl', 'https://evil.com' );
$assert( false === $blocked_set, 'set() returns false for blocked option' );
$assert( ( $GLOBALS['webo_test_options']['siteurl'] ?? 'UNSET' ) === 'UNSET', 'set() does not write blocked option' );

// patch_group
$GLOBALS['webo_test_options']['rank-math-options-titles'] = array( 'website_name' => 'OldSite', 'homepage_title' => 'Old Home' );
$result = WeboMcpRankMath_OptionsRepository::patch_group( 'titles', array( 'homepage_title' => 'New Home | Brand' ) );
$assert( true === $result, 'patch_group() returns true' );
$updated_titles = $GLOBALS['webo_test_options']['rank-math-options-titles'];
$assert( $updated_titles['website_name'] === 'OldSite', 'patch_group() preserves untouched keys' );
$assert( $updated_titles['homepage_title'] === 'New Home | Brand', 'patch_group() updates target key' );

$blocked_patch = WeboMcpRankMath_OptionsRepository::patch_group( 'nonexistent', array( 'foo' => 'bar' ) );
$assert( false === $blocked_patch, 'patch_group() returns false for unknown group' );

// get_many
$many = WeboMcpRankMath_OptionsRepository::get_many( array( 'rank-math-options-general', 'rank_math_modules' ) );
$assert( isset( $many['rank-math-options-general'] ), 'get_many() includes general' );
$assert( isset( $many['rank_math_modules'] ), 'get_many() includes modules' );

// get_modules / set_modules
$modules = WeboMcpRankMath_OptionsRepository::get_modules();
$assert( is_array( $modules ), 'get_modules() returns array' );
$assert( in_array( 'sitemap', $modules, true ), 'get_modules() includes sitemap' );

WeboMcpRankMath_OptionsRepository::set_modules( array( 'sitemap', 'rich-snippet', 'redirections' ) );
$updated_modules = WeboMcpRankMath_OptionsRepository::get_modules();
$assert( in_array( 'redirections', $updated_modules, true ), 'set_modules() adds new module' );
$assert( count( $updated_modules ) === 3, 'set_modules() deduplicates and sorts' );
// Verify they are sorted
$sorted = $updated_modules;
sort( $sorted );
$assert( $sorted === $updated_modules, 'set_modules() stores sorted list' );

echo PHP_EOL . ( 0 === $failures ? 'All options-repository cases passed.' : $failures . ' case(s) FAILED.' ) . PHP_EOL;
exit( 0 === $failures ? 0 : 1 );
