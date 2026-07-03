<?php
/**
 * Unit tests for semantic schema and sitemap profile actions.
 *
 * Run: php tests/semantic-sitemap-schema-cases.php
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'WEBO_MCP_CONTRACT_TEST' ) ) {
	define( 'WEBO_MCP_CONTRACT_TEST', true );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { // phpcs:ignore
		private $codes = array();
		private $messages = array();

		public function __construct( $code = '', $message = '' ) {
			if ( $code ) {
				$this->add( $code, $message );
			}
		}

		public function add( $code, $message ) {
			$this->codes[]              = $code;
			$this->messages[ $code ][] = $message;
		}

		public function get_error_code() {
			return $this->codes[0] ?? '';
		}

		public function get_error_message() {
			return ( $this->messages[ $this->codes[0] ?? '' ] ?? array() )[0] ?? '';
		}
	}

	function is_wp_error( $value ) { // phpcs:ignore
		return $value instanceof WP_Error;
	}
}

$GLOBALS['webo_rm_test_options']    = array(
	'rank-math-options-titles'  => array(
		'pt_post_default_rich_snippet' => 'article',
	),
	'rank-math-options-sitemap' => array(
		'pt_post_sitemap' => 'on',
		'tax_post_tag_sitemap' => 'on',
	),
);
$GLOBALS['webo_rm_test_transients'] = array();
$GLOBALS['webo_rm_flushed']         = 0;

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) { // phpcs:ignore
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { // phpcs:ignore
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) { // phpcs:ignore
		return trim( (string) $value );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) { // phpcs:ignore
		return filter_var( trim( (string) $value ), FILTER_SANITIZE_URL ) ?: '';
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = null ) { // phpcs:ignore
		return array_key_exists( $key, $GLOBALS['webo_rm_test_options'] ) ? $GLOBALS['webo_rm_test_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) { // phpcs:ignore
		unset( $autoload );
		$GLOBALS['webo_rm_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) { // phpcs:ignore
		unset( $GLOBALS['webo_rm_test_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl ) { // phpcs:ignore
		unset( $ttl );
		$GLOBALS['webo_rm_test_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) { // phpcs:ignore
		return $GLOBALS['webo_rm_test_transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) { // phpcs:ignore
		unset( $GLOBALS['webo_rm_test_transients'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) { // phpcs:ignore
		unset( $type );
		return date( 'c' );
	}
}
if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand() { // phpcs:ignore
		return rand();
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $name, $args ) { // phpcs:ignore
		unset( $name, $args );
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) { // phpcs:ignore
		unset( $capability );
		return true;
	}
}
if ( ! function_exists( 'webo_rank_math_with_site' ) ) {
	function webo_rank_math_with_site( $site_id, $callback ) { // phpcs:ignore
		unset( $site_id );
		return $callback();
	}
}
if ( ! function_exists( 'webo_rank_math_flush_sitemap_cache' ) ) {
	function webo_rank_math_flush_sitemap_cache( $input = array() ) { // phpcs:ignore
		unset( $input );
		++$GLOBALS['webo_rm_flushed'];
		return array( 'flushed' => true );
	}
}

$root = dirname( __DIR__ );
require_once $root . '/includes/mutation-contract.php';
require_once $root . '/includes/class-rank-math-options-repository.php';
require_once $root . '/includes/class-snapshot-service.php';
require_once $root . '/includes/class-brand-profile-validator.php';
require_once $root . '/includes/class-brand-profile-mapper.php';
require_once $root . '/includes/class-brand-profile-service.php';
require_once $root . '/includes/class-migration-service.php';
require_once $root . '/abilities/semantic-actions.php';

$failures = 0;
$assert   = static function ( $condition, $message ) use ( &$failures ) {
	echo ( $condition ? 'PASS: ' : 'FAIL: ' ) . $message . PHP_EOL;
	if ( ! $condition ) {
		++$failures;
	}
};

echo PHP_EOL . '=== configure-schema-defaults ===' . PHP_EOL;

$before_titles = $GLOBALS['webo_rm_test_options']['rank-math-options-titles'];
$result        = webo_rank_math_action_configure_schema_defaults(
	array(
		'post_types' => array(
			'post'     => 'BlogPosting',
			'tutorial' => 'HowTo',
			'plugin'   => 'SoftwareApplication',
		),
		'dry_run'    => true,
	)
);

$assert( ! is_wp_error( $result ), 'schema defaults dry_run returns payload' );
$assert( true === $result['dry_run'], 'schema defaults dry_run=true' );
$assert( false === $result['executed'], 'schema defaults dry_run does not execute' );
$assert( 3 === $result['planned_count'], 'schema defaults dry_run reports planned changes' );
$assert( $before_titles === $GLOBALS['webo_rm_test_options']['rank-math-options-titles'], 'schema defaults dry_run does not write options' );
$assert( in_array( 'titles.pt_plugin_default_rich_snippet', $result['changed_fields'], true ), 'schema defaults includes plugin schema key' );

$result = webo_rank_math_action_configure_schema_defaults(
	array(
		'post_types' => array(
			'product' => 'Product',
			'faq'     => 'FAQPage',
		),
		'dry_run'    => false,
	)
);

$titles = $GLOBALS['webo_rm_test_options']['rank-math-options-titles'];
$assert( ! is_wp_error( $result ), 'schema defaults execute returns payload' );
$assert( false === $result['dry_run'] && true === $result['executed'], 'schema defaults execute marks mutation' );
$assert( isset( $result['snapshot_id'] ) && 0 === strpos( $result['snapshot_id'], 'webo_rm_snap_' ), 'schema defaults execute creates snapshot' );
$assert( 'product' === $titles['pt_product_default_rich_snippet'], 'schema defaults writes product schema' );
$assert( 'faqpage' === $titles['pt_faq_default_rich_snippet'], 'schema defaults writes faq schema' );

echo PHP_EOL . '=== configure-sitemap-profile ===' . PHP_EOL;

$before_sitemap = $GLOBALS['webo_rm_test_options']['rank-math-options-sitemap'];
$result         = webo_rank_math_action_configure_sitemap_profile(
	array(
		'include_post_types' => array( 'docs' ),
		'exclude_post_types' => array( 'product' ),
		'include_taxonomies' => array( 'category' ),
		'exclude_taxonomies' => array( 'post_tag' ),
		'dry_run'           => true,
	)
);

$assert( ! is_wp_error( $result ), 'sitemap profile dry_run returns payload' );
$assert( true === $result['dry_run'], 'sitemap profile dry_run=true' );
$assert( 4 === $result['planned_count'], 'sitemap profile dry_run reports planned changes' );
$assert( $before_sitemap === $GLOBALS['webo_rm_test_options']['rank-math-options-sitemap'], 'sitemap profile dry_run does not write options' );
$assert( in_array( 'sitemap.tax_post_tag_sitemap', $result['changed_fields'], true ), 'sitemap profile includes taxonomy exclusion key' );

$result = webo_rank_math_action_configure_sitemap_profile(
	array(
		'include_post_types' => array( 'docs' ),
		'exclude_post_types' => array( 'product' ),
		'include_taxonomies' => array( 'category' ),
		'exclude_taxonomies' => array( 'post_tag' ),
		'dry_run'           => false,
	)
);

$sitemap = $GLOBALS['webo_rm_test_options']['rank-math-options-sitemap'];
$assert( ! is_wp_error( $result ), 'sitemap profile execute returns payload' );
$assert( false === $result['dry_run'] && true === $result['executed'], 'sitemap profile execute marks mutation' );
$assert( 'on' === $sitemap['pt_docs_sitemap'], 'sitemap profile includes docs post type' );
$assert( 'off' === $sitemap['pt_product_sitemap'], 'sitemap profile excludes product post type' );
$assert( 'on' === $sitemap['tax_category_sitemap'], 'sitemap profile includes category taxonomy' );
$assert( 'off' === $sitemap['tax_post_tag_sitemap'], 'sitemap profile excludes post_tag taxonomy' );
$assert( $GLOBALS['webo_rm_flushed'] > 0, 'sitemap profile execute flushes sitemap cache' );

$empty = webo_rank_math_action_configure_sitemap_profile( array( 'dry_run' => true ) );
$assert( is_wp_error( $empty ) && 'webo_mcp_configure_sitemap_empty' === $empty->get_error_code(), 'sitemap profile rejects empty input' );

echo PHP_EOL . ( 0 === $failures ? 'All semantic sitemap/schema cases passed.' : $failures . ' case(s) FAILED.' ) . PHP_EOL;
exit( $failures > 0 ? 1 : 0 );
