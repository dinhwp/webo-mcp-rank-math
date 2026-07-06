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

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		$GLOBALS['webo_rm_quick_actions'][] = array(
			'hook' => $hook_name,
			'args' => $args,
		);
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

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $value ) {
		return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $value ), '-' ) );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '', $data = array() ) {
			unset( $data );
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

$GLOBALS['webo_rm_quick_posts'] = array(
	4048 => (object) array(
		'ID'        => 4048,
		'post_type' => 'post',
		'post_name' => 'test-post',
	),
);
$GLOBALS['webo_rm_quick_meta']  = array(
	4048 => array(
		'rank_math_title'         => 'Old Title',
		'rank_math_description'   => 'Old Description',
		'rank_math_focus_keyword' => 'Old Keyword',
	),
);

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		return $GLOBALS['webo_rm_quick_posts'][ (int) $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $slug, $output = OBJECT, $post_type = 'post' ) {
		unset( $output, $post_type );
		foreach ( $GLOBALS['webo_rm_quick_posts'] as $post ) {
			if ( $post->post_name === $slug ) {
				return $post;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'url_to_postid' ) ) {
	function url_to_postid( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$slug = $path ? basename( trim( (string) $path, '/' ) ) : '';
		foreach ( $GLOBALS['webo_rm_quick_posts'] as $post ) {
			if ( $post->post_name === $slug ) {
				return (int) $post->ID;
			}
		}
		return 0;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		unset( $capability, $args );
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		unset( $single );
		$meta = $GLOBALS['webo_rm_quick_meta'][ (int) $post_id ] ?? array();
		return '' === $key ? $meta : ( $meta[ $key ] ?? '' );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['webo_rm_quick_meta'][ (int) $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['webo_rm_quick_meta'][ (int) $post_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists( $type, $object_id, $meta_key ) {
		unset( $type );
		return array_key_exists( (string) $meta_key, $GLOBALS['webo_rm_quick_meta'][ (int) $object_id ] ?? array() );
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand() {
		return 12345;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		unset( $type );
		return '2026-07-06T00:00:00+07:00';
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		unset( $expiration );
		$GLOBALS['webo_rm_quick_transients'][ (string) $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return $GLOBALS['webo_rm_quick_transients'][ (string) $transient ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['webo_rm_quick_transients'][ (string) $transient ] );
		return true;
	}
}

if ( ! function_exists( 'clean_post_cache' ) ) {
	function clean_post_cache( $post_id ) {
		unset( $post_id );
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {
		unset( $key, $group );
		return true;
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'webo_mcp_rank_math_extract_quick_meta_updates' ) ) {
	require dirname( __DIR__ ) . '/webo-mcp-rank-math.php';
	require dirname( __DIR__ ) . '/abilities/helpers.php';
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

$execute_cases = array(
	'bool_false'   => false,
	'string_false' => 'false',
	'string_zero'  => '0',
	'int_zero'     => 0,
);

foreach ( $execute_cases as $name => $dry_run_value ) {
	$GLOBALS['webo_rm_quick_meta'][4048] = array(
		'rank_math_title'         => 'Old Title',
		'rank_math_description'   => 'Old Description',
		'rank_math_focus_keyword' => 'Old Keyword',
	);

	$result = webo_mcp_rank_math_execute_quick_update_tool(
		array(
			'post_id'       => 4048,
			'title'         => 'Test',
			'description'   => 'Test',
			'focus_keyword' => 'SEO',
			'dry_run'       => $dry_run_value,
		)
	);

	$matches = ! is_wp_error( $result )
		&& false === ( $result['dry_run'] ?? null )
		&& true === ( $result['executed'] ?? null )
		&& 'Test' === $GLOBALS['webo_rm_quick_meta'][4048]['rank_math_title']
		&& 'Test' === $GLOBALS['webo_rm_quick_meta'][4048]['rank_math_description']
		&& 'SEO' === $GLOBALS['webo_rm_quick_meta'][4048]['rank_math_focus_keyword'];
	$failed = $failed || ! $matches;

	$results[ 'execute_' . $name ] = array(
		'expect'           => 'EXECUTE',
		'actual'           => is_wp_error( $result ) ? $result->get_error_code() : ( ( $result['executed'] ?? false ) ? 'EXECUTE' : 'PREVIEW' ),
		'matches_expected' => $matches,
		'dry_run'          => $dry_run_value,
		'meta'             => $GLOBALS['webo_rm_quick_meta'][4048],
	);
}

$GLOBALS['webo_rm_quick_meta'][4048] = array(
	'rank_math_title'         => 'Old Title',
	'rank_math_description'   => 'Old Description',
	'rank_math_focus_keyword' => 'Old Keyword',
);
$checkpoint_result = webo_mcp_rank_math_execute_quick_update_tool(
	array(
		'post_id'       => 4048,
		'title'         => 'Checkpoint Title',
		'description'   => 'Checkpoint Description',
		'focus_keyword' => 'Checkpoint Keyword',
		'dry_run'       => false,
	)
);
$checkpoint_id = is_wp_error( $checkpoint_result ) ? '' : (string) ( $checkpoint_result['checkpoint_id'] ?? '' );
$rollback_result = $checkpoint_id ? webo_mcp_rank_math_execute_rollback_checkpoint_tool( array( 'checkpoint_id' => $checkpoint_id ) ) : null;
$checkpoint_matches = ! is_wp_error( $checkpoint_result )
	&& '' !== $checkpoint_id
	&& ! is_wp_error( $rollback_result )
	&& true === ( $rollback_result['success'] ?? null )
	&& 'Old Title' === $GLOBALS['webo_rm_quick_meta'][4048]['rank_math_title']
	&& 'Old Description' === $GLOBALS['webo_rm_quick_meta'][4048]['rank_math_description']
	&& 'Old Keyword' === $GLOBALS['webo_rm_quick_meta'][4048]['rank_math_focus_keyword'];
$failed = $failed || ! $checkpoint_matches;
$results['quick_checkpoint_rollback'] = array(
	'expect'           => 'ROLLBACK',
	'actual'           => $checkpoint_matches ? 'ROLLBACK' : 'FAIL',
	'matches_expected' => $checkpoint_matches,
	'checkpoint_id'    => $checkpoint_id,
	'meta'             => $GLOBALS['webo_rm_quick_meta'][4048],
);

$GLOBALS['webo_rm_quick_meta'][4048] = array( 'rank_math_title' => 'Old Bulk Title' );
$GLOBALS['webo_rm_quick_actions'] = array();
$bulk_result = webo_mcp_rank_math_execute_bulk_update_tool(
	array(
		'items' => array(
			array(
				'url'         => 'https://example.test/test-post/',
				'title'       => 'Bulk Title',
				'description' => 'Bulk Description',
			),
			array(
				'post_id'   => 4048,
				'canonical' => 'https://example.test/forbidden',
			),
			array(
				'post_id' => 9999,
				'title'   => 'Missing post',
			),
		),
		'dry_run' => false,
	)
);
$bulk_checkpoint_id = is_wp_error( $bulk_result ) ? '' : (string) ( $bulk_result['checkpoint_id'] ?? '' );
$bulk_rollback = $bulk_checkpoint_id ? webo_mcp_rank_math_execute_rollback_checkpoint_tool( array( 'checkpoint_id' => $bulk_checkpoint_id ) ) : null;
$bulk_matches = ! is_wp_error( $bulk_result )
	&& false === ( $bulk_result['dry_run'] ?? null )
	&& true === ( $bulk_result['executed'] ?? null )
	&& '' !== $bulk_checkpoint_id
	&& ! is_wp_error( $bulk_rollback )
	&& true === ( $bulk_rollback['success'] ?? null )
	&& 1 === (int) ( $bulk_result['success_count'] ?? 0 )
	&& 2 === (int) ( $bulk_result['failure_count'] ?? 0 )
	&& true === ( $bulk_result['cache_flushed'] ?? null )
	&& true === ( $bulk_result['sitemap_regenerated'] ?? null )
	&& 'Old Bulk Title' === $GLOBALS['webo_rm_quick_meta'][4048]['rank_math_title']
	&& ! array_key_exists( 'rank_math_description', $GLOBALS['webo_rm_quick_meta'][4048] )
	&& in_array( 'rank_math/sitemap/flush_cache', array_column( $GLOBALS['webo_rm_quick_actions'], 'hook' ), true )
	&& in_array( 'rank_math/sitemap/build_cache', array_column( $GLOBALS['webo_rm_quick_actions'], 'hook' ), true );
$failed = $failed || ! $bulk_matches;
$results['seo_bulk_update_checkpoint_rollback'] = array(
	'expect'           => 'ROLLBACK',
	'actual'           => $bulk_matches ? 'ROLLBACK' : 'FAIL',
	'matches_expected' => $bulk_matches,
	'checkpoint_id'    => $bulk_checkpoint_id,
	'meta'             => $GLOBALS['webo_rm_quick_meta'][4048],
);

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
