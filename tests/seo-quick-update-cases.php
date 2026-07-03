<?php
/**
 * Standalone validation cases for seo_quick_update.
 *
 * Run inside WordPress with:
 * wp eval-file tests/seo-quick-update-cases.php --allow-root
 */

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return rtrim( dirname( $file ), '/\\' ) . DIRECTORY_SEPARATOR;
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		unset( $file );
		return 'https://example.test/wp-content/plugins/webo-mcp-rank-math/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( $file );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		unset( $file, $callback );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'webo_mcp_rank_math_extract_quick_meta_updates' ) ) {
	require dirname( __DIR__ ) . '/webo-mcp-rank-math.php';
}

if ( ! function_exists( 'webo_mcp_rank_math_extract_quick_meta_updates' ) || ! function_exists( 'webo_mcp_rank_math_quick_update_forbidden_fields' ) ) {
	echo wp_json_encode(
		array(
			'error' => 'Rank Math quick update helpers are not loaded.',
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	);
	return;
}

$cases = array(
	'case_1' => array(
		'input'  => array(
			'post_id' => 10602,
			'title'   => 'New Title',
		),
		'expect' => 'PASS',
	),
	'case_2' => array(
		'input'  => array(
			'post_id'      => 10602,
			'description'  => 'New Description',
		),
		'expect' => 'PASS',
	),
	'case_3' => array(
		'input'  => array(
			'post_id'       => 10602,
			'focus_keyword' => 'AI Agent Ready WordPress',
		),
		'expect' => 'PASS',
	),
	'case_4' => array(
		'input'  => array(
			'post_id'   => 10602,
			'canonical' => 'https://example.com/forbidden',
		),
		'expect' => 'REJECT',
	),
);

$results = array();
$failed  = false;

foreach ( $cases as $name => $case ) {
	$input     = $case['input'];
	$forbidden = webo_mcp_rank_math_quick_update_forbidden_fields( $input );
	$updates   = webo_mcp_rank_math_extract_quick_meta_updates( $input );
	$status    = empty( $forbidden ) && ! empty( $updates ) ? 'PASS' : 'REJECT';
	$matches   = $status === $case['expect'];
	$failed    = $failed || ! $matches;

	$results[ $name ] = array(
		'expect'           => $case['expect'],
		'actual'           => $status,
		'matches_expected' => $matches,
		'forbidden_fields' => $forbidden,
		'mapped_updates'   => $updates,
	);
}

echo wp_json_encode(
	array(
		'test'    => 'seo_quick_update',
		'results' => $results,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);

if ( $failed ) {
	exit( 1 );
}
