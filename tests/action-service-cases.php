<?php
/**
 * Action service contract tests.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'WEBO_MCP_CONTRACT_TEST' ) ) {
	define( 'WEBO_MCP_CONTRACT_TEST', true );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

$GLOBALS['webo_rm_action_options'] = array(
	'active_plugins'             => array( 'seo-by-rank-math/rank-math.php' ),
	'rank_math_modules'          => array(),
	'rank-math-options-general'  => array( '404_monitor_mode' => '' ),
	'rank-math-options-titles'   => array( 'nofollow_external_links' => 'on' ),
	'rank-math-options-sitemap'  => array( 'attachment_sitemap' => 'on', 'tax_post_tag_sitemap' => 'on' ),
	'rank-math-options-social'   => array(),
);

function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['webo_rm_action_options'] ) ? $GLOBALS['webo_rm_action_options'][ $name ] : $default; }
function update_option( $name, $value, $autoload = null ) { unset( $autoload ); $GLOBALS['webo_rm_action_options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['webo_rm_action_options'][ $name ] ); return true; }
function set_transient( $key, $value, $ttl = 0 ) { unset( $ttl ); $GLOBALS['webo_rm_action_options'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['webo_rm_action_options'][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['webo_rm_action_options'][ $key ] ); return true; }
function current_time( $type ) { unset( $type ); return '2026-07-05T00:00:00+00:00'; }
function wp_rand() { return 12345; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function sanitize_email( $value ) { return trim( (string) $value ); }
function esc_url_raw( $value ) { return trim( (string) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_cache_flush() { return true; }
function do_action( $name ) { unset( $name ); }
function add_action( $hook, $callback, $priority = 10 ) { unset( $hook, $callback, $priority ); }
function get_current_user_id() { return 1; }
function get_post_types() { return array( 'post' => 'post', 'page' => 'page', 'attachment' => 'attachment' ); }
class WP_Error { public $code; public $message; public $data; public function __construct( $code, $message, $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; } }
class Webo_RM_Test_WPDB {
	public $options = 'wp_options';
	public function prepare( $query, ...$args ) { unset( $args ); return $query; }
	public function query( $query ) { unset( $query ); return 0; }
}
$GLOBALS['wpdb'] = new Webo_RM_Test_WPDB();

require_once dirname( __DIR__ ) . '/includes/mutation-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-rank-math-options-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-snapshot-service.php';
require_once dirname( __DIR__ ) . '/includes/class-brand-profile-mapper.php';
require_once dirname( __DIR__ ) . '/abilities/helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-action-service.php';
require_once dirname( __DIR__ ) . '/abilities/unified-dispatchers.php';

$assert = function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$before = $GLOBALS['webo_rm_action_options']['rank-math-options-titles'];
$result = WeboMcpRankMath_ActionService::dispatch( array( 'action' => 'optimize-settings', 'dryRun' => true ) );
$assert( ! is_wp_error( $result ), 'optimize-settings dryRun returns payload' );
$assert( true === $result['dry_run'], 'dryRun camelCase maps to dry_run=true' );
$assert( false === $result['executed'], 'dryRun does not execute' );
$assert( $before === $GLOBALS['webo_rm_action_options']['rank-math-options-titles'], 'dryRun does not write options' );

$result = WeboMcpRankMath_ActionService::dispatch( array( 'action' => 'optimize-settings', 'dryRun' => false ) );
$assert( true === $result['dry_run'], 'dryRun=false without force is downgraded' );
$assert( false === $result['executed'], 'downgraded mutation is not executed' );
$assert( ! empty( $result['warnings'] ), 'downgraded mutation returns warning' );

$result = WeboMcpRankMath_ActionService::dispatch( array( 'action' => 'optimize-settings', 'dryRun' => false, 'force' => true ) );
$assert( false === $result['dry_run'], 'force executes optimize-settings' );
$assert( 'off' === $GLOBALS['webo_rm_action_options']['rank-math-options-titles']['nofollow_external_links'], 'force writes allowed title option' );
$assert( ! empty( $result['backup_id'] ), 'execute returns backup ID' );

$result = WeboMcpRankMath_ActionService::dispatch( array( 'action' => 'fix-common-issues', 'dryRun' => true ) );
$assert( ! is_wp_error( $result ), 'fix-common-issues works' );
$assert( true === $result['dry_run'], 'fix-common-issues defaults preview' );

$result = webo_rank_math_config_mutate( array( 'action' => 'optimize-settings', 'dry_run' => true ) );
$assert( ! is_wp_error( $result ), 'config-mutate routes v2 optimize-settings' );

fwrite( STDOUT, "Action service cases passed.\n" );
