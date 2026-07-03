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
foreach ( $cases as $name => $result ) {
	$is_error = is_wp_error( $result );
	$has_misleading_keys = ! $is_error && (
		array_key_exists( 'success', (array) $result )
		|| array_key_exists( 'updated_count', (array) $result )
		|| array_key_exists( 'deleted_count', (array) $result )
		|| array_key_exists( 'deleted', (array) $result )
	);
	$results[ $name ] = array(
		'pass'   => ! $is_error && true === ( $result['dry_run'] ?? null ) && false === ( $result['executed'] ?? null ) && ! $has_misleading_keys,
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
