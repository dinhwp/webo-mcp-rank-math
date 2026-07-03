<?php
/**
 * Standalone dry-run validation cases for Rank Math mutation handlers.
 *
 * Run inside WordPress with:
 * wp eval-file tests/rank-math-dry-run-cases.php --allow-root
 */

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WEBO_MCP_CONTRACT_TEST' ) ) {
	define( 'WEBO_MCP_CONTRACT_TEST', true );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { // phpcs:ignore
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
	function is_wp_error( $value ) { // phpcs:ignore
		return $value instanceof WP_Error;
	}
}

$GLOBALS['webo_rm_dry_run_posts'] = array(
	10509 => (object) array(
		'ID'        => 10509,
		'post_type' => 'post',
		'post_name' => 'dry-run-post',
	),
);
$GLOBALS['webo_rm_dry_run_meta'] = array(
	10509 => array(
		'rank_math_description' => 'Existing description',
		'rank_math_schema_Bad'  => 'not serialized schema',
	),
);

if ( ! isset( $GLOBALS['wpdb'] ) || ! is_object( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new class() {
		public $postmeta = 'wp_postmeta';

		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}

		public function prepare( $query, ...$args ) {
			unset( $args );
			return $query;
		}

		public function get_results( $query, $output = ARRAY_A ) {
			unset( $query, $output );
			return array(
				array(
					'meta_id'    => 1,
					'meta_key'   => 'rank_math_schema_Bad',
					'meta_value' => 'not serialized schema',
				),
			);
		}
	};
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) { // phpcs:ignore
		return rtrim( dirname( $file ), '/\\' ) . DIRECTORY_SEPARATOR;
	}
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) { // phpcs:ignore
		unset( $file );
		return 'https://example.test/wp-content/plugins/webo-mcp-rank-math/';
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) { // phpcs:ignore
		return basename( $file );
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
		unset( $hook_name, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
		unset( $hook_name, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) { // phpcs:ignore
		unset( $hook_name, $args );
	}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) { // phpcs:ignore
		unset( $file, $callback );
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) { // phpcs:ignore
		unset( $capability );
		return true;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { // phpcs:ignore
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { // phpcs:ignore
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { // phpcs:ignore
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { // phpcs:ignore
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) { // phpcs:ignore
		return $value;
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) { // phpcs:ignore
		return $GLOBALS['webo_rm_dry_run_posts'][ (int) $post_id ] ?? null;
	}
}
if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $slug, $output = OBJECT, $post_type = 'post' ) { // phpcs:ignore
		unset( $output );
		foreach ( $GLOBALS['webo_rm_dry_run_posts'] as $post ) {
			if ( $slug === $post->post_name && $post_type === $post->post_type ) {
				return $post;
			}
		}
		return null;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) { // phpcs:ignore
		unset( $single );
		$meta = $GLOBALS['webo_rm_dry_run_meta'][ (int) $post_id ] ?? array();
		if ( '' === $key ) {
			return $meta;
		}
		return $meta[ $key ] ?? '';
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) { // phpcs:ignore
		$GLOBALS['webo_rm_dry_run_meta'][ (int) $post_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $key ) { // phpcs:ignore
		unset( $GLOBALS['webo_rm_dry_run_meta'][ (int) $post_id ][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'delete_metadata_by_mid' ) ) {
	function delete_metadata_by_mid( $meta_type, $meta_id ) { // phpcs:ignore
		unset( $meta_type, $meta_id );
		return true;
	}
}
if ( ! function_exists( 'clean_post_cache' ) ) {
	function clean_post_cache( $post_id ) { // phpcs:ignore
		unset( $post_id );
	}
}
if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) { // phpcs:ignore
		unset( $key, $group );
		return true;
	}
}

if ( ! function_exists( 'webo_mcp_rank_math_execute_post_seo_mutate_tool' ) ) {
	require dirname( __DIR__ ) . '/webo-mcp-rank-math.php';
}
if ( ! function_exists( 'webo_rank_math_post_seo_mutate' ) ) {
	require dirname( __DIR__ ) . '/abilities/helpers.php';
	require dirname( __DIR__ ) . '/abilities/unified-dispatchers.php';
}

if ( ! function_exists( 'webo_mcp_rank_math_execute_post_seo_mutate_tool' ) || ! function_exists( 'webo_rank_math_post_seo_mutate' ) ) {
	echo wp_json_encode(
		array( 'error' => 'Rank Math mutation handlers are not loaded.' ),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	);
	return;
}

$post_id = 10509;
$cases   = array();

$cases['update_meta_dry_run'] = webo_mcp_rank_math_execute_post_seo_mutate_tool(
	array(
		'action'   => 'update',
		'post_id'  => $post_id,
		'seo_meta' => array( 'description' => 'Dry run test' ),
		'dry_run'  => true,
	)
);

$cases['bulk_upsert_dry_run'] = webo_rank_math_post_seo_mutate(
	array(
		'action'  => 'bulk-upsert',
		'posts'   => array(
			array(
				'post_id'  => $post_id,
				'seo_meta' => array( 'rank_math_description' => 'Bulk dry run test' ),
			),
		),
		'dry_run' => true,
	)
);

$cases['cleanup_dry_run'] = webo_rank_math_post_seo_mutate(
	array(
		'action'     => 'cleanup',
		'post_id'    => $post_id,
		'delete_all' => false,
		'dry_run'    => true,
	)
);

$results = array();
$failed  = false;
foreach ( $cases as $name => $result ) {
	$is_error = is_wp_error( $result );
	$has_misleading_keys = ! $is_error && (
		array_key_exists( 'success', (array) $result )
		|| array_key_exists( 'updated_count', (array) $result )
		|| array_key_exists( 'deleted_count', (array) $result )
		|| array_key_exists( 'deleted', (array) $result )
	);
	$pass = ! $is_error && true === ( $result['dry_run'] ?? null ) && false === ( $result['executed'] ?? null ) && ! $has_misleading_keys;
	$failed = $failed || ! $pass;
	$results[ $name ] = array(
		'pass'   => $pass,
		'result' => $is_error ? array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) : $result,
	);
}

echo wp_json_encode(
	array(
		'test'    => 'rank_math_dry_run_mutations',
		'post_id' => $post_id,
		'results' => $results,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);

if ( $failed ) {
	exit( 1 );
}
