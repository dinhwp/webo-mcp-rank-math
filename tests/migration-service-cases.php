<?php
/**
 * Unit tests for MigrationService — migrate-brand dry_run and replacement logic.
 *
 * Run:  php tests/migration-service-cases.php
 *
 * @package webo-mcp-rank-math
 */

// ---------------------------------------------------------------------------
// Bootstrap (same stubs as brand-profile-service-cases.php)
// ---------------------------------------------------------------------------
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'WEBO_MCP_CONTRACT_TEST' ) ) {
	define( 'WEBO_MCP_CONTRACT_TEST', true );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { // phpcs:ignore
		private $codes = array(); private $messages = array();
		public function __construct( $c = '', $m = '' ) { if ( $c ) $this->add( $c, $m ); }
		public function add( $c, $m ) { $this->codes[] = $c; $this->messages[$c][] = $m; }
		public function has_errors() { return !empty($this->codes); }
		public function get_error_codes() { return $this->codes; }
		public function get_error_messages( $c ) { return $this->messages[$c] ?? []; }
		public function get_error_code() { return $this->codes[0] ?? ''; }
		public function get_error_message() { return ($this->messages[$this->codes[0] ?? ''] ?? [])[0] ?? ''; }
		public function get_error_data() { return null; }
	}
	function is_wp_error( $v ) { return $v instanceof WP_Error; } // phpcs:ignore
}

if ( ! function_exists( 'sanitize_text_field' ) )    { function sanitize_text_field( $v ) { return trim( strip_tags( (string)$v ) ); } } // phpcs:ignore
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $v ) { return trim( (string)$v ); } } // phpcs:ignore
if ( ! function_exists( 'sanitize_key' ) )            { function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower((string)$v) ) ); } } // phpcs:ignore
if ( ! function_exists( 'esc_url_raw' ) )             { function esc_url_raw( $v ) { return filter_var( trim($v), FILTER_SANITIZE_URL ) ?: ''; } } // phpcs:ignore
if ( ! function_exists( 'current_time' ) )            { function current_time( $type ) { return date('c'); } } // phpcs:ignore
if ( ! function_exists( 'wp_rand' ) )                 { function wp_rand() { return rand(); } } // phpcs:ignore
if ( ! function_exists( 'set_transient' ) )           { function set_transient( $k, $v, $ttl ) {} } // phpcs:ignore
if ( ! function_exists( 'get_transient' ) )           { function get_transient( $k ) { return false; } } // phpcs:ignore
if ( ! function_exists( 'delete_transient' ) )        { function delete_transient( $k ) {} } // phpcs:ignore
if ( ! function_exists( 'update_option' ) )           { function update_option( $k, $v, $a = null ) {} } // phpcs:ignore
if ( ! function_exists( 'delete_option' ) )           { function delete_option( $k ) {} } // phpcs:ignore
if ( ! function_exists( 'webo_rank_math_flush_sitemap_cache' ) ) {
	function webo_rank_math_flush_sitemap_cache( $input = array() ) { return array( 'flushed' => true ); } // phpcs:ignore
}
if ( ! function_exists( 'webo_rank_math_with_site' ) ) {
	function webo_rank_math_with_site( $site, $cb ) { return $cb(); } // phpcs:ignore
}

// Configurable get_option stub — populated per-test below.
$GLOBALS['webo_test_options'] = array();
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $k, $default = null ) { // phpcs:ignore
		return $GLOBALS['webo_test_options'][ $k ] ?? $default;
	}
}

// ---------------------------------------------------------------------------
// Load production code
// ---------------------------------------------------------------------------
$root = dirname( __DIR__ );
require_once $root . '/includes/mutation-contract.php';
require_once $root . '/includes/class-rank-math-options-repository.php';
require_once $root . '/includes/class-snapshot-service.php';
require_once $root . '/includes/class-brand-profile-validator.php';
require_once $root . '/includes/class-brand-profile-mapper.php';
require_once $root . '/includes/class-migration-service.php';

// ---------------------------------------------------------------------------
// Test runner
// ---------------------------------------------------------------------------
$failures = 0;
$assert   = static function ( $cond, $msg ) use ( &$failures ) {
	echo ( $cond ? 'PASS: ' : 'FAIL: ' ) . $msg . PHP_EOL;
	if ( ! $cond ) { ++$failures; }
};

// ---------------------------------------------------------------------------
// Validation tests
// ---------------------------------------------------------------------------
echo PHP_EOL . '=== MigrationService — Validation ===' . PHP_EOL;

$result = WeboMcpRankMath_BrandProfileValidator::validate_migrate_brand( array( 'from' => 'Webo', 'to' => 'DinhWP' ) );
$assert( true === $result, 'Valid migrate input passes' );

$result = WeboMcpRankMath_BrandProfileValidator::validate_migrate_brand( array() );
$assert( is_wp_error( $result ), 'Empty input fails' );
$assert( in_array( 'missing_from', $result->get_error_codes(), true ), 'missing_from error' );
$assert( in_array( 'missing_to', $result->get_error_codes(), true ), 'missing_to error' );

// ---------------------------------------------------------------------------
// Internal replacement logic
// ---------------------------------------------------------------------------
echo PHP_EOL . '=== MigrationService — Replace logic ===' . PHP_EOL;

// Use reflection to call the private method for isolated testing.
$ref    = new ReflectionClass( 'WeboMcpRankMath_MigrationService' );
$method = $ref->getMethod( 'replace_in_value' );
$method->setAccessible( true );

$assert( 'DinhWP Blog' === $method->invoke( null, 'Webo Blog', 'Webo', 'DinhWP' ), 'String replacement works' );
$assert( array( 'name' => 'DinhWP' ) === $method->invoke( null, array( 'name' => 'Webo' ), 'Webo', 'DinhWP' ), 'Array replacement works' );
$assert( 42 === $method->invoke( null, 42, 'Webo', 'DinhWP' ), 'Non-string scalar unchanged' );

// Nested array
$nested = array( 'profile' => array( 'facebook' => 'https://facebook.com/webo' ) );
$result_nested = $method->invoke( null, $nested, 'webo', 'dinhwp' );
$assert( $result_nested['profile']['facebook'] === 'https://facebook.com/dinhwp', 'Nested array replacement works' );

// ---------------------------------------------------------------------------
// Dry run — no brand occurrences
// ---------------------------------------------------------------------------
echo PHP_EOL . '=== MigrationService — Dry run (no match) ===' . PHP_EOL;

// Empty options — nothing to replace.
$GLOBALS['webo_test_options'] = array();

$response = WeboMcpRankMath_MigrationService::migrate( array( 'from' => 'Webo', 'to' => 'DinhWP', 'dry_run' => true ) );

$assert( ! is_wp_error( $response ), 'No match dry run is not WP_Error' );
$assert( true === ( $response['dry_run'] ?? null ), 'dry_run=true in response' );
$assert( false === ( $response['would_change'] ?? null ), 'would_change=false when no match' );
$assert( 0 === ( $response['planned_count'] ?? -1 ), 'planned_count=0 when no match' );

// ---------------------------------------------------------------------------
// Dry run — brand found
// ---------------------------------------------------------------------------
echo PHP_EOL . '=== MigrationService — Dry run (match found) ===' . PHP_EOL;

$GLOBALS['webo_test_options'] = array(
	'rank-math-options-general' => array(
		'knowledgegraph_name' => 'Webo Company',
		'knowledgegraph_url'  => 'https://webo.dev',
	),
	'rank-math-options-titles' => array(
		'website_name' => 'Webo Solutions',
	),
	'rank-math-options-sitemap'          => array(),
	'rank-math-options-social'           => array(),
	'rank-math-options-instant-indexing' => array(),
);

// Re-define get_option to pick up globals after options are populated.
// (The stubs above already forward to $GLOBALS['webo_test_options'].)

$response = WeboMcpRankMath_MigrationService::migrate( array( 'from' => 'Webo', 'to' => 'DinhWP', 'dry_run' => true ) );

$assert( ! is_wp_error( $response ), 'Match dry run is not WP_Error' );
$assert( true === ( $response['dry_run'] ?? null ), 'dry_run=true' );
$assert( true === ( $response['would_change'] ?? null ), 'would_change=true when match found' );
$assert( ( $response['planned_count'] ?? 0 ) >= 2, 'planned_count >= 2 (general + titles changed)' );
$assert( is_array( $response['diff'] ?? null ), 'diff is array' );
// Ensure dry_run does NOT contain success/updated keys
$assert( ! array_key_exists( 'success', $response ), 'No success key on dry_run' );
$assert( ! array_key_exists( 'updated', $response ), 'No updated key on dry_run' );

// Verify diff entries contain correct before/after
$found_general = false;
foreach ( $response['diff'] as $item ) {
	if ( 'general' === $item['option_group'] && 'knowledgegraph_name' === $item['key'] ) {
		$found_general = true;
		$assert( $item['before'] === 'Webo Company', 'Diff before is "Webo Company"' );
		$assert( $item['after'] === 'DinhWP Company', 'Diff after is "DinhWP Company"' );
	}
}
$assert( $found_general, 'Diff contains general.knowledgegraph_name change' );

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo PHP_EOL . ( 0 === $failures ? 'All migration-service cases passed.' : $failures . ' case(s) FAILED.' ) . PHP_EOL;
exit( 0 === $failures ? 0 : 1 );
