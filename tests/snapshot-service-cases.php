<?php
/**
 * Unit tests for SnapshotService — create, get, rollback.
 *
 * Run:  php tests/snapshot-service-cases.php
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'WEBO_MCP_CONTRACT_TEST' ) ) {
	define( 'WEBO_MCP_CONTRACT_TEST', true );
}

// Minimal WP stubs
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { // phpcs:ignore
		private $codes = []; private $messages = [];
		public function __construct( $c='', $m='' ) { if ($c) $this->add($c,$m); }
		public function add($c,$m) { $this->codes[]=$c; $this->messages[$c][]=$m; }
		public function has_errors() { return !empty($this->codes); }
		public function get_error_codes() { return $this->codes; }
		public function get_error_messages($c) { return $this->messages[$c]??[]; }
		public function get_error_code() { return $this->codes[0]??''; }
		public function get_error_message() { return ($this->messages[$this->codes[0]??'']??[])[0]??''; }
	}
	function is_wp_error($v){return $v instanceof WP_Error;} // phpcs:ignore
}
if ( ! function_exists( 'sanitize_key' ) )    { function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)));} } // phpcs:ignore
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field($v){return trim(strip_tags((string)$v));} } // phpcs:ignore
if ( ! function_exists( 'current_time' ) )    { function current_time($t){return date('c');} } // phpcs:ignore
if ( ! function_exists( 'wp_rand' ) )         { function wp_rand(){return rand();} } // phpcs:ignore

// In-memory transient store for testing
$GLOBALS['webo_test_transients'] = array();
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['webo_test_transients'][$k] = $v; } // phpcs:ignore
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $k ) { return $GLOBALS['webo_test_transients'][$k] ?? false; } // phpcs:ignore
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $k ) { unset( $GLOBALS['webo_test_transients'][$k] ); return true; } // phpcs:ignore
}

// In-memory options store for testing
$GLOBALS['webo_test_options'] = array(
	'rank-math-options-general' => array( 'knowledgegraph_name' => 'OldBrand' ),
	'rank-math-options-titles'  => array( 'website_name' => 'OldBrand Site' ),
);
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $k, $default = null ) { return $GLOBALS['webo_test_options'][$k] ?? $default; } // phpcs:ignore
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $k, $v, $a = null ) { $GLOBALS['webo_test_options'][$k] = $v; } // phpcs:ignore
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $k ) { unset( $GLOBALS['webo_test_options'][$k] ); } // phpcs:ignore
}

require_once dirname( __DIR__ ) . '/includes/mutation-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-rank-math-options-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-snapshot-service.php';

// ---------------------------------------------------------------------------
$failures = 0;
$assert   = static function ( $cond, $msg ) use ( &$failures ) {
	echo ( $cond ? 'PASS: ' : 'FAIL: ' ) . $msg . PHP_EOL;
	if ( ! $cond ) { ++$failures; }
};

echo PHP_EOL . '=== SnapshotService ===' . PHP_EOL;

// Create
$snap = WeboMcpRankMath_SnapshotService::create( 'test', array( 'rank-math-options-general', 'rank-math-options-titles' ) );

$assert( ! empty( $snap['snapshot_id'] ), 'create() returns snapshot_id' );
$assert( 0 === strpos( $snap['snapshot_id'], WeboMcpRankMath_SnapshotService::PREFIX ), 'snapshot_id has correct prefix' );
$assert( $snap['label'] === 'test', 'create() returns correct label' );
$assert( ! empty( $snap['captured_at'] ), 'create() returns captured_at' );
$assert( count( $snap['option_names'] ) === 2, 'create() returns option_names list' );

// Get
$payload = WeboMcpRankMath_SnapshotService::get( $snap['snapshot_id'] );
$assert( is_array( $payload ), 'get() returns array' );
$assert( isset( $payload['data'] ), 'get() payload has data' );
$assert( is_array( $payload['data']['rank-math-options-general'] ?? null ), 'get() captured general options' );

// Simulate changes after snapshot
$GLOBALS['webo_test_options']['rank-math-options-general'] = array( 'knowledgegraph_name' => 'NewBrand' );
$GLOBALS['webo_test_options']['rank-math-options-titles']  = array( 'website_name' => 'NewBrand Site' );

$assert( get_option( 'rank-math-options-general' )['knowledgegraph_name'] === 'NewBrand', 'Pre-rollback: options have new values' );

// Rollback
$rb = WeboMcpRankMath_SnapshotService::rollback( $snap['snapshot_id'] );

$assert( true === $rb['success'], 'rollback() returns success=true' );
$assert( ! empty( $rb['rolled_back'] ), 'rollback() returns rolled_back list' );
$assert( get_option( 'rank-math-options-general' )['knowledgegraph_name'] === 'OldBrand', 'Post-rollback: general restored' );
$assert( get_option( 'rank-math-options-titles' )['website_name'] === 'OldBrand Site', 'Post-rollback: titles restored' );

// After rollback with delete=true, get() returns null
$assert( null === WeboMcpRankMath_SnapshotService::get( $snap['snapshot_id'] ), 'Snapshot deleted after rollback' );

// Rollback of expired/missing snapshot
$rb_missing = WeboMcpRankMath_SnapshotService::rollback( 'nonexistent_id' );
$assert( false === $rb_missing['success'], 'Missing snapshot rollback returns success=false' );
$assert( ! empty( $rb_missing['error'] ), 'Missing snapshot rollback has error message' );

echo PHP_EOL . ( 0 === $failures ? 'All snapshot-service cases passed.' : $failures . ' case(s) FAILED.' ) . PHP_EOL;
exit( 0 === $failures ? 0 : 1 );
