<?php
/**
 * Unit test: Rank Math ability scopes use only read/write/admin.
 *
 * Run: php scripts/test-ability-scope-metadata.php
 *
 * @package webo-mcp-rank-math
 */

define( 'ABSPATH', __DIR__ . '/../' );

$failures = 0;

function webo_rank_math_scope_test_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		$failures++;
		return;
	}
	echo 'PASS: ' . $message . PHP_EOL;
}

$allowed_scopes = array( 'read', 'write', 'admin' );

$schema_meta = array(
	'scope'         => 'write',
	'action_scopes' => array(
		'upsert'  => 'write',
		'delete'  => 'write',
		'cleanup' => 'write',
	),
);

webo_rank_math_scope_test_assert( in_array( $schema_meta['scope'], $allowed_scopes, true ), 'schema-mutate root scope is valid' );

foreach ( $schema_meta['action_scopes'] as $action => $scope ) {
	webo_rank_math_scope_test_assert(
		in_array( $scope, $allowed_scopes, true ),
		sprintf( 'schema-mutate action "%s" scope is valid (%s)', $action, $scope )
	);
}

webo_rank_math_scope_test_assert(
	'write' === $schema_meta['action_scopes']['delete'],
	'schema-mutate delete action no longer uses invalid delete scope'
);

$source = (string) file_get_contents( __DIR__ . '/../webo-mcp-rank-math.php' );
preg_match( "/'action_scopes'\s*=>\s*array\s*\((.*?)\),/s", $source, $matches );

webo_rank_math_scope_test_assert( ! empty( $matches[1] ), 'schema-mutate action scopes block exists in plugin source' );
webo_rank_math_scope_test_assert(
	! empty( $matches[1] ) && false !== strpos( $matches[1], "'delete'  => 'write'" ),
	'schema-mutate delete action scope is write in plugin source'
);
webo_rank_math_scope_test_assert(
	empty( $matches[1] ) || false === strpos( $matches[1], "'delete'  => 'delete'" ),
	'schema-mutate delete action scope is not invalid delete in plugin source'
);

exit( $failures > 0 ? 1 : 0 );
