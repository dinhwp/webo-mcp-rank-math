<?php
/**
 * Public frontend fallback for sites where Rank Math meta is stored but the
 * Rank Math frontend head does not render.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the public post ID whose Rank Math meta should be used.
 *
 * @return int
 */
function webo_rank_math_frontend_post_id() {
	if ( is_front_page() ) {
		$page_on_front = (int) get_option( 'page_on_front' );
		if ( $page_on_front > 0 ) {
			return $page_on_front;
		}
	}

	if ( is_singular() ) {
		return (int) get_queried_object_id();
	}

	return 0;
}

/**
 * Apply a small subset of Rank Math variable replacements for fallback output.
 *
 * @param string $value   Raw meta value.
 * @param int    $post_id Post ID.
 * @return string
 */
function webo_rank_math_frontend_replace_vars( $value, $post_id ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return '';
	}

	$post        = $post_id ? get_post( $post_id ) : null;
	$title       = $post ? get_the_title( $post ) : get_bloginfo( 'name' );
	$description = $post ? get_post_meta( $post_id, 'rank_math_description', true ) : '';
	if ( '' === $description ) {
		$description = $post ? wp_strip_all_tags( get_the_excerpt( $post ), true ) : get_bloginfo( 'description' );
	}

	$replacements = array(
		'%title%'           => $title,
		'%sitename%'        => get_bloginfo( 'name' ),
		'%sitedesc%'        => get_bloginfo( 'description' ),
		'%sep%'             => '-',
		'%excerpt%'         => $post ? wp_strip_all_tags( get_the_excerpt( $post ), true ) : '',
		'%seo_title%'       => $title,
		'%seo_description%' => $description,
	);

	return trim( strtr( $value, $replacements ) );
}

/**
 * Get fallback title/description/canonical values for the current request.
 *
 * @return array
 */
function webo_rank_math_frontend_values() {
	$post_id = webo_rank_math_frontend_post_id();
	$values  = array(
		'post_id'     => $post_id,
		'title'       => '',
		'description' => '',
		'canonical'   => '',
		'robots'      => array(),
	);

	if ( $post_id > 0 ) {
		$values['title']       = webo_rank_math_frontend_replace_vars( get_post_meta( $post_id, 'rank_math_title', true ), $post_id );
		$values['description'] = webo_rank_math_frontend_replace_vars( get_post_meta( $post_id, 'rank_math_description', true ), $post_id );
		$values['canonical']   = (string) get_post_meta( $post_id, 'rank_math_canonical_url', true );
		$values['robots']      = webo_rank_math_frontend_normalize_robots( get_post_meta( $post_id, 'rank_math_robots', true ) );

		if ( '' === $values['canonical'] ) {
			$values['canonical'] = get_permalink( $post_id );
		}
	}

	if ( is_front_page() ) {
		$options = get_option( 'rank-math-options-titles', array() );
		if ( is_array( $options ) ) {
			if ( '' === $values['title'] && ! empty( $options['homepage_title'] ) ) {
				$values['title'] = webo_rank_math_frontend_replace_vars( $options['homepage_title'], $post_id );
			}
			if ( '' === $values['description'] && ! empty( $options['homepage_description'] ) ) {
				$values['description'] = webo_rank_math_frontend_replace_vars( $options['homepage_description'], $post_id );
			}
		}
	}

	$values['title']       = wp_strip_all_tags( $values['title'], true );
	$values['description'] = wp_strip_all_tags( $values['description'], true );

	return $values;
}

/**
 * Normalize Rank Math robots post meta into a public meta content list.
 *
 * @param mixed $robots Raw post meta.
 * @return string[]
 */
function webo_rank_math_frontend_normalize_robots( $robots ) {
	if ( is_string( $robots ) ) {
		$decoded = json_decode( $robots, true );
		if ( is_array( $decoded ) ) {
			$robots = $decoded;
		} else {
			$robots = preg_split( '/[\s,]+/', $robots );
		}
	}

	if ( ! is_array( $robots ) ) {
		return array();
	}

	$allowed = array(
		'index',
		'noindex',
		'follow',
		'nofollow',
		'noarchive',
		'noimageindex',
		'nosnippet',
	);
	$values  = array();
	foreach ( $robots as $robot ) {
		$robot = strtolower( trim( (string) $robot ) );
		if ( in_array( $robot, $allowed, true ) ) {
			$values[] = $robot;
		}
	}

	return array_values( array_unique( $values ) );
}

/**
 * Whether fallback output should run.
 *
 * @return bool
 */
function webo_rank_math_frontend_fallback_enabled() {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}

	return (bool) apply_filters( 'webo_rank_math_frontend_fallback_enabled', true );
}

add_filter(
	'pre_get_document_title',
	function ( $title ) {
		if ( ! webo_rank_math_frontend_fallback_enabled() ) {
			return $title;
		}

		$values = webo_rank_math_frontend_values();
		return '' !== $values['title'] ? $values['title'] : $title;
	},
	20
);

add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( ! webo_rank_math_frontend_fallback_enabled() ) {
			return $parts;
		}

		$values = webo_rank_math_frontend_values();
		if ( '' !== $values['title'] ) {
			$parts['title'] = $values['title'];
			unset( $parts['tagline'] );
		}

		return $parts;
	},
	20
);

add_action(
	'wp_head',
	function () {
		if ( ! webo_rank_math_frontend_fallback_enabled() || did_action( 'rank_math/head' ) ) {
			return;
		}

		$values = webo_rank_math_frontend_values();
		if ( '' === $values['description'] ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $values['description'] ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $values['description'] ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $values['description'] ) . '" />' . "\n";

		if ( '' !== $values['title'] ) {
			echo '<meta property="og:title" content="' . esc_attr( $values['title'] ) . '" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $values['title'] ) . '" />' . "\n";
		}
		if ( '' !== $values['canonical'] ) {
			echo '<meta property="og:url" content="' . esc_url( $values['canonical'] ) . '" />' . "\n";
		}
	},
	2
);

add_action(
	'wp_head',
	function () {
		if ( ! webo_rank_math_frontend_fallback_enabled() ) {
			return;
		}

		$values = webo_rank_math_frontend_values();
		if ( empty( $values['robots'] ) ) {
			return;
		}

		// Emit an explicit robots tag when stored Rank Math robots meta exists
		// but the active frontend stack renders an empty robots tag.
		echo '<meta name="robots" content="' . esc_attr( implode( ', ', $values['robots'] ) ) . '" />' . "\n";
	},
	99
);

/**
 * Run a minimal Rank Math redirection fallback when Rank Math frontend hooks are
 * not firing but redirections exist in the Rank Math table.
 *
 * @return void
 */
function webo_rank_math_frontend_redirect_fallback() {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! function_exists( 'webo_rank_math_redirections_ensure_tables' ) || ! webo_rank_math_redirections_ensure_tables() ) {
		return;
	}

	global $wpdb;

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = trim( (string) parse_url( $request_uri, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}

	$table = webo_rank_math_redirections_table_name();
	$rows  = $wpdb->get_results(
		"SELECT * FROM {$table} WHERE status = 'active' ORDER BY updated DESC, id DESC LIMIT 500",
		ARRAY_A
	);

	foreach ( (array) $rows as $row ) {
		$sources = isset( $row['sources'] ) ? maybe_unserialize( $row['sources'] ) : array();
		foreach ( (array) $sources as $source ) {
			$pattern    = isset( $source['pattern'] ) ? trim( (string) $source['pattern'], '/' ) : '';
			$comparison = isset( $source['comparison'] ) ? (string) $source['comparison'] : 'exact';
			if ( 'exact' !== $comparison || '' === $pattern || $pattern !== $path ) {
				continue;
			}

			$code = isset( $row['header_code'] ) ? (int) $row['header_code'] : 301;
			if ( in_array( $code, array( 410, 451 ), true ) ) {
				status_header( $code );
				exit;
			}

			$destination = isset( $row['url_to'] ) ? (string) $row['url_to'] : '';
			if ( '' === $destination ) {
				return;
			}
			if ( 0 !== strpos( $destination, 'http://' ) && 0 !== strpos( $destination, 'https://' ) ) {
				$destination = home_url( '/' . ltrim( $destination, '/' ) );
			}

			wp_redirect( $destination, in_array( $code, array( 301, 302, 307 ), true ) ? $code : 302 );
			exit;
		}
	}
}

add_action( 'template_redirect', 'webo_rank_math_frontend_redirect_fallback', 1 );
