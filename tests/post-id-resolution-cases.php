<?php
/**
 * Standalone checks for post_id resolution in the post SEO mutate path.
 *
 * Reproduces the websitedanang.vn issue: a write sent with post_id=0 (the client
 * could not resolve an id) was hard-rejected by the ability schema (minimum:1)
 * before the handler could resolve the post by slug. The schema now allows
 * post_id=0 and the handler returns a clear error when nothing resolves.
 *
 * Run: php tests/post-id-resolution-cases.php
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return abs( (int) $v );
	}
}

// Load only webo_rank_math_resolve_post_id() from helpers.php.
$src = file_get_contents( dirname( __DIR__ ) . '/abilities/helpers.php' );
eval( preg_replace( '/^<\?php/', '', $src ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged

$failures = 0;
$assert   = static function ( $cond, $msg ) use ( &$failures ) {
	echo ( $cond ? 'PASS: ' : 'FAIL: ' ) . $msg . PHP_EOL;
	if ( ! $cond ) {
		++$failures;
	}
};

// resolve_post_id treats post_id=0 / missing as "resolve elsewhere" (null), so
// the schema no longer needs minimum:1 and the handler guard can return a clear
// error instead of a cryptic "must be >= 1" schema rejection.
$assert( null === webo_rank_math_resolve_post_id( array( 'post_id' => 0 ) ), 'post_id=0 resolves to null (falls through to slug)' );
$assert( null === webo_rank_math_resolve_post_id( array() ), 'missing post_id resolves to null' );
$assert( 5 === webo_rank_math_resolve_post_id( array( 'post_id' => 5 ) ), 'valid post_id passes through' );
$assert( 7 === webo_rank_math_resolve_post_id( array( 'post_id' => '7' ) ), 'numeric-string post_id is coerced' );

echo ( 0 === $failures ? 'All post_id resolution cases passed.' : $failures . ' case(s) failed.' ) . PHP_EOL;
exit( 0 === $failures ? 0 : 1 );
