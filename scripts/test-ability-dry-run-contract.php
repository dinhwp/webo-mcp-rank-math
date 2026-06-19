<?php
/**
 * Unit test: Rank Math ability-layer dry_run responses avoid misleading keys.
 *
 * Run: php scripts/test-ability-dry-run-contract.php
 *
 * @package webo-mcp-rank-math
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'WEBO_MCP_CONTRACT_TEST', true );

require __DIR__ . '/../includes/mutation-contract.php';

$failures = 0;

function webo_rank_math_test_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		$failures++;
		return;
	}
	echo 'PASS: ' . $message . PHP_EOL;
}

function webo_rank_math_test_has_misleading_preview_keys( array $result ): bool {
	foreach ( webo_mcp_mutation_misleading_keys() as $key ) {
		if ( array_key_exists( $key, $result ) ) {
			return true;
		}
	}
	return false;
}

$preview = webo_mcp_mutation_response(
	array(
		'dry_run'       => true,
		'would_change'  => true,
		'planned_count' => 1,
		'diff'          => array( 'rank_math_title' => array( 'changed' => true ) ),
		'context'       => array(
			'updated'       => true,
			'updated_count' => 1,
			'success'       => true,
		),
	)
);

webo_rank_math_test_assert( true === $preview['dry_run'], 'contract preview dry_run=true' );
webo_rank_math_test_assert( false === $preview['executed'], 'contract preview executed=false' );
webo_rank_math_test_assert( ! webo_rank_math_test_has_misleading_preview_keys( $preview ), 'contract strips misleading keys on preview' );

$executed = webo_mcp_mutation_response(
	array(
		'dry_run'       => false,
		'changed'       => true,
		'changed_count' => 1,
		'diff'          => array(),
		'context'       => array(
			'success' => true,
			'updated' => true,
		),
	)
);

webo_rank_math_test_assert( true === ( $executed['success'] ?? null ), 'contract keeps success on execute' );

if ( $failures > 0 ) {
	fwrite( STDERR, $failures . " failure(s)\n" );
	exit( 1 );
}

echo "Rank Math ability dry-run contract tests completed.\n";
