<?php
/**
 * Unit tests for BrandProfileService — apply-brand-profile dry_run and validation paths.
 *
 * These tests run WITHOUT a live WordPress database. We stub the WordPress/OptionsRepository
 * calls so the Service, Mapper, Validator and mutation-contract are all exercised in isolation.
 *
 * Run:  php tests/brand-profile-service-cases.php
 *
 * @package webo-mcp-rank-math
 */

// ---------------------------------------------------------------------------
// Bootstrap: minimal stubs so the classes can load without WordPress
// ---------------------------------------------------------------------------
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'WEBO_MCP_CONTRACT_TEST' ) ) {
	define( 'WEBO_MCP_CONTRACT_TEST', true );
}

// WP stubs
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { // phpcs:ignore
		private $codes    = array();
		private $messages = array();
		public function __construct( $code = '', $message = '' ) {
			if ( $code ) { $this->add( $code, $message ); }
		}
		public function add( $code, $msg ) {
			$this->codes[]            = $code;
			$this->messages[ $code ][] = $msg;
		}
		public function has_errors() { return ! empty( $this->codes ); }
		public function get_error_codes() { return $this->codes; }
		public function get_error_messages( $code ) { return $this->messages[ $code ] ?? array(); }
		public function get_error_code() { return $this->codes[0] ?? ''; }
		public function get_error_message() { return ( $this->messages[ $this->codes[0] ?? '' ] ?? array() )[0] ?? ''; }
		public function get_error_data() { return null; }
	}
	function is_wp_error( $v ) { return $v instanceof WP_Error; } // phpcs:ignore
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); } // phpcs:ignore
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $v ) { return trim( (string) $v ); } // phpcs:ignore
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ) ); } // phpcs:ignore
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $v ) { return filter_var( trim( (string) $v ), FILTER_SANITIZE_EMAIL ); } // phpcs:ignore
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $v ) { return filter_var( trim( $v ), FILTER_SANITIZE_URL ) ?: ''; } // phpcs:ignore
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) { return date( 'c' ); } // phpcs:ignore
}
if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand() { return rand(); } // phpcs:ignore
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $k, $v, $ttl ) {} // phpcs:ignore
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $k ) { return false; } // phpcs:ignore
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $k ) {} // phpcs:ignore
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $k, $default = null ) { return $default; } // phpcs:ignore
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $k, $v, $autoload = null ) {} // phpcs:ignore
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $k ) {} // phpcs:ignore
}

// Stub webo_rank_math_flush_sitemap_cache so service calls don't blow up.
if ( ! function_exists( 'webo_rank_math_flush_sitemap_cache' ) ) {
	function webo_rank_math_flush_sitemap_cache( $input = array() ) { return array( 'flushed' => true ); } // phpcs:ignore
}
if ( ! function_exists( 'webo_rank_math_with_site' ) ) {
	function webo_rank_math_with_site( $site, $cb ) { return $cb(); } // phpcs:ignore
}

// ---------------------------------------------------------------------------
// Load production code (mutation-contract + classes under test)
// ---------------------------------------------------------------------------
$root = dirname( __DIR__ );
require_once $root . '/includes/mutation-contract.php';
require_once $root . '/includes/class-rank-math-options-repository.php';
require_once $root . '/includes/class-snapshot-service.php';
require_once $root . '/includes/class-brand-profile-validator.php';
require_once $root . '/includes/class-brand-profile-mapper.php';
require_once $root . '/includes/class-brand-profile-service.php';

// ---------------------------------------------------------------------------
// Test runner
// ---------------------------------------------------------------------------
$failures = 0;
$assert   = static function ( $cond, $msg ) use ( &$failures ) {
	echo ( $cond ? 'PASS: ' : 'FAIL: ' ) . $msg . PHP_EOL;
	if ( ! $cond ) {
		++$failures;
	}
};

// ---------------------------------------------------------------------------
// BrandProfileValidator tests
// ---------------------------------------------------------------------------
echo PHP_EOL . '=== BrandProfileValidator ===' . PHP_EOL;

$valid_input = array(
	'profile'    => 'personal',
	'brand_name' => 'DinhWP',
	'alternate_name' => 'DinhWP.com',
	'url'        => 'https://dinhwp.com',
	'logo'       => 'https://dinhwp.com//uploads/logo.png',
	'open_graph_image' => 'https://dinhwp.com//uploads/og.png',
	'publisher'  => array(
		'name' => 'DinhWP Publisher',
		'logo' => 'https://dinhwp.com//uploads/publisher.png',
	),
	'contact'    => array(
		'email' => 'hello@dinhwp.com',
		'phone' => '+84900000000',
	),
	'same_as'    => array(
		'https://www.youtube.com/@dinhwp',
		'https://www.linkedin.com/company/dinhwp',
	),
	'nofollow_external_links' => false,
	'social'     => array(
		'facebook' => 'https://facebook.com/dinhwp',
		'github'   => 'https://github.com/dinhwp',
	),
	'local'      => array(
		'business_type' => 'ProfessionalService',
		'address'       => array( 'streetAddress' => '123 Test St' ),
		'phone'         => '+84900000000',
	),
	'image_seo'  => array(
		'post_types' => array( 'post' => true, 'page' => false ),
	),
	'instant_indexing' => array(
		'bing_post_types' => array( 'post', 'page' ),
	),
);

$result = WeboMcpRankMath_BrandProfileValidator::validate_brand_profile( $valid_input );
$assert( true === $result, 'Valid input passes validation' );

$result = WeboMcpRankMath_BrandProfileValidator::validate_brand_profile( array() );
$assert( is_wp_error( $result ), 'Empty input fails validation' );
$assert( in_array( 'missing_profile', $result->get_error_codes(), true ), 'Empty input has missing_profile error' );
$assert( in_array( 'missing_brand_name', $result->get_error_codes(), true ), 'Empty input has missing_brand_name error' );

$result = WeboMcpRankMath_BrandProfileValidator::validate_brand_profile( array(
	'profile'    => 'invalid',
	'brand_name' => 'X',
) );
$assert( is_wp_error( $result ), 'Invalid profile type fails' );
$assert( in_array( 'invalid_profile', $result->get_error_codes(), true ), 'invalid_profile error present' );

$result = WeboMcpRankMath_BrandProfileValidator::validate_brand_profile( array(
	'profile'    => 'personal',
	'brand_name' => 'X',
	'url'        => 'not-a-url',
) );
$assert( is_wp_error( $result ), 'Invalid URL fails' );
$assert( in_array( 'invalid_url', $result->get_error_codes(), true ), 'invalid_url error present' );

// migrate-brand validation
$result = WeboMcpRankMath_BrandProfileValidator::validate_migrate_brand( array( 'from' => 'Webo', 'to' => 'DinhWP' ) );
$assert( true === $result, 'Valid migrate-brand input passes' );

$result = WeboMcpRankMath_BrandProfileValidator::validate_migrate_brand( array( 'from' => 'X', 'to' => 'X' ) );
$assert( is_wp_error( $result ), 'Same from/to fails' );
$assert( in_array( 'same_values', $result->get_error_codes(), true ), 'same_values error present' );

// ---------------------------------------------------------------------------
// BrandProfileMapper tests
// ---------------------------------------------------------------------------
echo PHP_EOL . '=== BrandProfileMapper ===' . PHP_EOL;

$patch = WeboMcpRankMath_BrandProfileMapper::map( $valid_input );

$assert( isset( $patch['general'] ), 'Patch has general group' );
$assert( isset( $patch['titles'] ), 'Patch has titles group' );
$assert( $patch['general']['knowledgegraph_name'] === 'DinhWP', 'KG name is set correctly' );
$assert( $patch['general']['knowledgegraph_type'] === 'person', 'KG type is person for personal profile' );
$assert( $patch['general']['knowledgegraph_url'] === 'https://dinhwp.com', 'KG url is set correctly' );
$assert( $patch['general']['knowledgegraph_logo'] === 'https://dinhwp.com/uploads/logo.png', 'KG logo URL is normalized' );
$assert( $patch['general']['publisher_name'] === 'DinhWP Publisher', 'Publisher name is mapped' );
$assert( $patch['general']['publisher_logo'] === 'https://dinhwp.com/uploads/publisher.png', 'Publisher logo URL is normalized' );
$assert( $patch['general']['console_email_logo'] === 'https://dinhwp.com/uploads/publisher.png', 'Email report logo follows normalized publisher logo' );
$assert( $patch['general']['404_monitor_exclude'][0]['exclude'] === 'https://dinhwp.com/', '404 exclude slash URL is normalized' );
$assert( $patch['general']['404_monitor_exclude'][1]['exclude'] === 'https://dinhwp.com', '404 exclude bare URL is normalized' );
$assert( $patch['general']['email'] === 'hello@dinhwp.com', 'Contact email is mapped' );
$assert( in_array( 'https://www.youtube.com/@dinhwp', $patch['general']['social_networks'], true ), 'Explicit same_as is included in social_networks' );
$assert( $patch['titles']['website_name'] === 'DinhWP', 'Website name set' );
$assert( $patch['titles']['website_alternate_name'] === 'DinhWP.com', 'Website alternate name set' );
$assert( $patch['titles']['breadcrumbs_home_label'] === 'DinhWP', 'Breadcrumb home label set' );
$assert( $patch['titles']['twitter_card_type'] === 'summary_large_image', 'Twitter card type set' );
$assert( $patch['titles']['nofollow_external_links'] === 'off', 'External nofollow can be disabled' );
$assert( $patch['social']['open_graph_image'] === 'https://dinhwp.com/uploads/og.png', 'Open Graph image is normalized and mapped' );
$assert( $patch['social']['twitter_image'] === 'https://dinhwp.com/uploads/og.png', 'Twitter image follows Open Graph image' );
$assert( in_array( 'https://github.com/dinhwp', $patch['social']['social_urls'], true ), 'GitHub is included in sameAs/social_urls' );
$assert( $patch['general']['local_business_type'] === 'ProfessionalService', 'Local SEO business type set' );
$assert( $patch['general']['phone_numbers'][0] === '+84900000000', 'Local SEO phone number set' );
$assert( $patch['titles']['pt_post_autogenerate_image'] === 'on', 'Image SEO can enable post image generation' );
$assert( $patch['titles']['pt_page_autogenerate_image'] === 'off', 'Image SEO can disable page image generation' );
$assert( $patch['instant-indexing']['bing_post_types'][0] === 'post', 'Instant indexing settings are mapped' );

$org_patch = WeboMcpRankMath_BrandProfileMapper::map( array_merge( $valid_input, array( 'profile' => 'organization' ) ) );
$assert( $org_patch['general']['knowledgegraph_type'] === 'organization', 'KG type is organization' );

// Diff builder
$diff = WeboMcpRankMath_BrandProfileMapper::build_diff(
	array( 'general' => array( 'knowledgegraph_name' => 'DinhWP' ) ),
	array( 'general' => array( 'knowledgegraph_name' => 'OldName' ) )
);
$assert( count( $diff ) === 1, 'Diff has one entry' );
$assert( $diff[0]['changed'] === true, 'Diff entry marked as changed' );
$assert( $diff[0]['before'] === 'OldName', 'Diff before is old value' );
$assert( $diff[0]['after'] === 'DinhWP', 'Diff after is new value' );

// changed_fields
$fields = WeboMcpRankMath_BrandProfileMapper::changed_fields( $diff );
$assert( count( $fields ) === 1, 'changed_fields returns one field' );
$assert( $fields[0] === 'general.knowledgegraph_name', 'changed_fields has correct field name' );

// ---------------------------------------------------------------------------
// BrandProfileService dry_run tests
// ---------------------------------------------------------------------------
echo PHP_EOL . '=== BrandProfileService (dry_run) ===' . PHP_EOL;

$input = array_merge( $valid_input, array(
	'description' => 'DinhWP chia sẻ WordPress, AI Automation, MCP, Plugin Development.',
	'alternate_name' => 'Đinh WP',
	'dry_run' => true,
) );

$response = WeboMcpRankMath_BrandProfileService::apply( $input );

$assert( ! is_wp_error( $response ), 'Dry run does not return WP_Error' );
$assert( true === ( $response['dry_run'] ?? null ), 'Response dry_run=true' );
$assert( false === ( $response['executed'] ?? null ), 'Response executed=false' );
$assert( isset( $response['planned_count'] ), 'Response has planned_count' );
$assert( is_array( $response['diff'] ?? null ), 'Response has diff array' );
$assert( isset( $response['would_change'] ), 'Response has would_change' );
$assert( ! array_key_exists( 'success', $response ), 'No misleading success key on dry_run' );
$assert( ! array_key_exists( 'updated', $response ), 'No misleading updated key on dry_run' );

// Validation failure path
$bad_input = array( 'brand_name' => 'X', 'dry_run' => true ); // missing profile
$response  = WeboMcpRankMath_BrandProfileService::apply( $bad_input );
$assert( is_wp_error( $response ), 'Validation failure returns WP_Error' );
$assert( 'webo_mcp_brand_profile_validation' === $response->get_error_code(), 'Error code is correct' );

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo PHP_EOL . ( 0 === $failures ? 'All brand-profile-service cases passed.' : $failures . ' case(s) FAILED.' ) . PHP_EOL;
exit( 0 === $failures ? 0 : 1 );
