<?php
/**
 * Standalone validation cases for seo_quick_update.
 *
 * Run inside WordPress with:
 * wp eval-file tests/seo-quick-update-cases.php --allow-root
 */

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

foreach ( $cases as $name => $case ) {
	$input     = $case['input'];
	$forbidden = webo_mcp_rank_math_quick_update_forbidden_fields( $input );
	$updates   = webo_mcp_rank_math_extract_quick_meta_updates( $input );
	$status    = empty( $forbidden ) && ! empty( $updates ) ? 'PASS' : 'REJECT';

	$results[ $name ] = array(
		'expect'           => $case['expect'],
		'actual'           => $status,
		'matches_expected' => $status === $case['expect'],
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
