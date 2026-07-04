<?php
/**
 * WEBO MCP - Rank Math unified ability dispatchers.
 *
 * Provides unified query and mutation abilities for Rank Math features.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================================
// Handler Functions (extracted from individual abilities)
// ============================================================================

/**
 * Get Rank Math plugin status.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_get_plugin_status( $input = array() ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () {
		$active = (array) get_option( 'active_plugins', array() );
		return array(
			'rank_math_active'  => in_array( 'seo-by-rank-math/rank-math.php', $active, true ) || defined( 'RANK_MATH_VERSION' ),
			'rank_math_version' => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : null,
			'rank_math_modules' => (array) get_option( 'rank_math_modules', array() ),
			'options_available' => array_values( array_filter( webo_rank_math_default_option_names(), function ( $name ) {
				return get_option( $name, null ) !== null;
			} ) ),
		);
	} );
}

/**
 * Get Rank Math options.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_get_options( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$names   = ! empty( $input['option_names'] )
			? array_values( array_filter( array_map( 'sanitize_key', (array) $input['option_names'] ) ) )
			: webo_rank_math_default_option_names();
		$options = array();
		foreach ( $names as $name ) {
			$value = get_option( $name, null );
			if ( $value !== null ) {
				$options[ $name ] = $value;
			}
		}
		return array( 'count' => count( $options ), 'options' => $options );
	} );
}

/**
 * Update Rank Math options.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_update_options( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$options = webo_rank_math_normalize_option_updates( $input );
		if ( empty( $options ) ) {
			return new WP_Error(
				'webo_mcp_rank_math_no_options',
				'No allowed Rank Math option updates were provided. Send options keyed by rank-math-options-* or groups such as general, titles, sitemap, social.'
			);
		}

		$dry_run = webo_rank_math_resolve_dry_run( $input, false );
		$updated = array();
		$diff    = array();
		foreach ( $options as $name => $value ) {
			$k = sanitize_key( $name );
			if ( $k === '' || ! webo_rank_math_is_allowed_option_name( $k ) ) {
				continue;
			}
			$before = get_option( $k, null );
			if ( ! $dry_run ) {
				update_option( $k, $value );
			}
			$after       = $dry_run ? $value : get_option( $k );
			$updated[ $k ] = $after;
			$diff[ $k ]    = array(
				'before'  => $before,
				'after'   => $after,
				'changed' => $before !== $after,
			);
		}

		if ( empty( $updated ) ) {
			return new WP_Error(
				'webo_mcp_rank_math_no_allowed_options',
				'Rank Math option updates were present, but none matched the allowed option names.'
			);
		}

		return array(
			'success'       => true,
			'dry_run'       => $dry_run,
			'executed'      => ! $dry_run,
			'would_change'  => $dry_run && count( $updated ) > 0,
			'planned_count' => $dry_run ? count( $updated ) : 0,
			'changed'       => ! $dry_run && count( $updated ) > 0,
			'changed_count' => $dry_run ? 0 : count( $updated ),
			'updated_count' => $dry_run ? 0 : count( $updated ),
			'options'       => $updated,
			'diff'          => $diff,
		);
	} );
}

/**
 * Normalize flexible MCP payloads into real Rank Math option names.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>
 */
function webo_rank_math_normalize_option_updates( $input ) {
	$input = (array) $input;
	if ( isset( $input['parameters'] ) && is_array( $input['parameters'] ) ) {
		$input = array_merge( $input['parameters'], $input );
		unset( $input['parameters'] );
	}

	$raw = array();
	foreach ( array( 'options', 'option_updates', 'settings', 'groups' ) as $key ) {
		if ( isset( $input[ $key ] ) && is_array( $input[ $key ] ) ) {
			$raw = array_merge( $raw, $input[ $key ] );
		}
	}

	foreach ( $input as $key => $value ) {
		if ( in_array( $key, array( 'action', 'site_id', 'blog_id', 'dry_run', 'force' ), true ) ) {
			continue;
		}
		if ( is_array( $value ) && ( webo_rank_math_option_group_to_name( $key ) || webo_rank_math_is_allowed_option_name( sanitize_key( $key ) ) ) ) {
			$raw[ $key ] = $value;
		}
	}

	$options = array();
	foreach ( $raw as $name => $value ) {
		$option_name = webo_rank_math_option_group_to_name( (string) $name ) ?: sanitize_key( (string) $name );
		if ( $option_name === '' || ! webo_rank_math_is_allowed_option_name( $option_name ) ) {
			continue;
		}

		if ( is_array( $value ) && webo_rank_math_option_group_to_name( (string) $name ) ) {
			$current = get_option( $option_name, array() );
			$options[ $option_name ] = array_merge( is_array( $current ) ? $current : array(), $value );
			continue;
		}

		$options[ $option_name ] = $value;
	}

	return $options;
}

function webo_rank_math_option_group_to_name( $group ) {
	$key = sanitize_key( str_replace( ' ', '-', (string) $group ) );
	$map = array(
		'general'          => 'rank-math-options-general',
		'titles'           => 'rank-math-options-titles',
		'titles-meta'      => 'rank-math-options-titles',
		'sitemap'          => 'rank-math-options-sitemap',
		'sitemaps'         => 'rank-math-options-sitemap',
		'social'           => 'rank-math-options-social',
		'instant-indexing' => 'rank-math-options-instant-indexing',
		'instant_indexing' => 'rank-math-options-instant-indexing',
	);

	return $map[ $key ] ?? null;
}

/**
 * Get Rank Math modules.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_get_modules( $input = array() ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () {
		$modules = array_values( array_filter( (array) get_option( 'rank_math_modules', array() ) ) );
		sort( $modules );
		return array( 'count' => count( $modules ), 'modules' => $modules );
	} );
}

/**
 * Update Rank Math modules.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_update_modules( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$raw_modules = $input['modules'] ?? ( $input['active_modules'] ?? array() );
		if ( isset( $input['parameters']['modules'] ) && is_array( $input['parameters']['modules'] ) ) {
			$raw_modules = $input['parameters']['modules'];
		}
		$modules = array_values( array_unique( array_filter( array_map( function ( $v ) {
			return sanitize_key( (string) $v );
		}, (array) $raw_modules ) ) ) );
		sort( $modules );
		update_option( 'rank_math_modules', $modules );
		webo_rank_math_create_module_tables( $modules );
		return array( 'updated' => true, 'count' => count( $modules ), 'modules' => $modules );
	} );
}

function webo_rank_math_create_module_tables( $modules ) {
	if ( class_exists( '\RankMath\Installer' ) && method_exists( '\RankMath\Installer', 'create_tables' ) ) {
		\RankMath\Installer::create_tables( (array) $modules );
	}
}

function webo_rank_math_apply_basic_seo_settings( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$modules = array_values( array_unique( array_merge(
			(array) get_option( 'rank_math_modules', array() ),
			array( 'seo-analysis', 'sitemap', 'rich-snippet', 'instant-indexing', 'redirections', '404-monitor', 'link-counter' )
		) ) );
		sort( $modules );
		$dry_run = webo_rank_math_resolve_dry_run( $input, false );
		if ( ! $dry_run ) {
			webo_rank_math_create_module_tables( $modules );
		}

		$general = (array) get_option( 'rank-math-options-general', array() );
		$titles  = (array) get_option( 'rank-math-options-titles', array() );
		$sitemap = (array) get_option( 'rank-math-options-sitemap', array() );
		$social  = (array) get_option( 'rank-math-options-social', array() );

		$updates = array(
			'rank_math_modules'           => $modules,
			'rank-math-options-general'   => array_merge( $general, array(
				'attachment_redirect_urls'            => 'on',
				'new_window_external_links'           => 'on',
				'404_monitor_mode'                    => 'simple',
				'404_monitor_ignore_query_parameters' => 'on',
				'redirections_header_code'            => '301',
				'redirections_debug'                  => 'off',
			) ),
			'rank-math-options-titles'    => array_merge( $titles, array(
				'author_custom_robots'       => 'on',
				'author_robots'              => array( 'noindex' ),
				'disable_date_archives'      => 'on',
				'date_archive_robots'        => array( 'noindex' ),
				'noindex_search'             => 'on',
				'noindex_empty_taxonomies'   => 'on',
				'noindex_password_protected' => 'off',
				'twitter_card_type'          => 'summary_large_image',
				'homepage_custom_robots'     => 'off',
			) ),
			'rank-math-options-sitemap'   => array_merge( $sitemap, array(
				'items_per_page'         => 200,
				'include_images'         => 'on',
				'include_featured_image' => 'on',
				'authors_sitemap'        => 'off',
			) ),
			'rank-math-options-social'    => array_merge( $social, array(
				'open_graph_image' => $social['open_graph_image'] ?? '',
			) ),
		);

		$input = array_merge( $input, array( 'options' => $updates ) );
		$result = webo_rank_math_update_options( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['action']  = 'seo-baseline';
		$result['profile'] = isset( $input['profile'] ) ? sanitize_key( (string) $input['profile'] ) : 'basic';

		if ( ! $dry_run ) {
			$flush = webo_rank_math_flush_sitemap_cache( $input );
			if ( ! is_wp_error( $flush ) ) {
				$result['sitemap_cache'] = $flush;
			}
		}

		return $result;
	} );
}

/**
 * Flush Rank Math sitemap cache/transients.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_flush_sitemap_cache( $input = array() ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () {
		global $wpdb;

		do_action( 'rank_math/sitemap/flush_cache' );

		if ( class_exists( '\RankMath\Sitemap\Cache' ) && method_exists( '\RankMath\Sitemap\Cache', 'invalidate_storage' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}

		$deleted = 0;
		$patterns = array(
			'_transient_rank_math_sitemap_%',
			'_transient_timeout_rank_math_sitemap_%',
			'_site_transient_rank_math_sitemap_%',
			'_site_transient_timeout_rank_math_sitemap_%',
			'_transient_rank_math%_sitemap%',
			'_transient_timeout_rank_math%_sitemap%',
			'_site_transient_rank_math%_sitemap%',
			'_site_transient_timeout_rank_math%_sitemap%',
		);

		foreach ( $patterns as $pattern ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
		}

		wp_cache_flush();

		return array(
			'flushed'         => true,
			'deleted_options' => $deleted,
		);
	} );
}

/**
 * Get post SEO metadata.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_get_post_seo_meta( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$post_id = webo_rank_math_resolve_post_id( $input );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		return array( 'post_id' => $post_id, 'seo_meta' => webo_rank_math_collect_post_meta( $post_id, $input['keys'] ?? null ) );
	} );
}

/**
 * Update post SEO metadata.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_update_post_seo_meta( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$post_id = webo_rank_math_resolve_post_id( $input );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		if ( empty( $post_id ) ) {
			return new \WP_Error(
				'webo_mcp_rank_math_post_not_found',
				'Provide a valid post_id (>= 1) or a resolvable slug; no target post could be resolved.',
				array( 'status' => 400 )
			);
		}
		$seo_meta = $input['seo_meta'] ?? array();
		$dry_run  = webo_rank_math_resolve_dry_run( $input, false );
		$result   = webo_rank_math_update_post_meta_map( $post_id, $seo_meta, $dry_run );
		return array_merge( array( 'post_id' => $post_id ), $result );
	} );
}

/**
 * Bulk upsert post SEO metadata.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_bulk_upsert_post_seo_meta( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$posts         = $input['posts'] ?? ( $input['items'] ?? array() );
		$skip_missing  = $input['skip_missing'] ?? false;
		$dry_run       = webo_rank_math_resolve_dry_run( $input, false );
		$results       = array();
		$planned_count = 0;

		foreach ( (array) $posts as $post_data ) {
			$post_id = isset( $post_data['post_id'] ) ? intval( $post_data['post_id'] ) : null;
			if ( ! $post_id && ! empty( $post_data['slug'] ) ) {
				$post = get_page_by_path( $post_data['slug'], OBJECT, $post_data['post_type'] ?? 'post' );
				$post_id = $post ? $post->ID : null;
			}

			if ( ! $post_id ) {
				if ( $skip_missing ) {
					continue;
				}
				$results[] = array( 'post_id' => null, 'error' => 'post_not_found' );
				continue;
			}

			$seo_meta = $post_data['seo_meta'] ?? array();
			$result   = webo_rank_math_update_post_meta_map( $post_id, $seo_meta, $dry_run );
			$planned_count += isset( $result['planned_count'] ) ? (int) $result['planned_count'] : 0;
			$results[] = array_merge( array( 'post_id' => $post_id ), $result );
		}

		return webo_mcp_mutation_response(
			array(
				'dry_run'       => $dry_run,
				'would_change'  => $planned_count > 0,
				'planned_count' => $planned_count,
				'changed'       => ! $dry_run && $planned_count > 0,
				'changed_count' => $dry_run ? 0 : $planned_count,
				'diff'          => $results,
				'context'       => array(
					'action' => 'bulk-upsert',
					'count'  => count( $results ),
				),
			)
		);
	} );
}

/**
 * Cleanup post schema metadata.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_cleanup_post_schema_meta_handler( $input ) {
	$args = isset( $input['post_id'] ) ? array_merge( $input, array( 'post_id' => intval( $input['post_id'] ) ) ) : $input;
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input, $args ) {
		$dry_run = webo_rank_math_resolve_dry_run( $input, ! empty( $args['delete_all'] ) );
		$cleanup = webo_rank_math_cleanup_post_schema_meta( $args['post_id'] ?? 0, $args['delete_all'] ?? false, $dry_run );
		return webo_mcp_mutation_response(
			array(
				'dry_run'       => $dry_run,
				'would_change'  => ! empty( $cleanup['would_change'] ),
				'planned_count' => isset( $cleanup['planned_count'] ) ? (int) $cleanup['planned_count'] : 0,
				'changed'       => ! $dry_run && ! empty( $cleanup['deleted_count'] ),
				'changed_count' => $dry_run ? 0 : ( isset( $cleanup['deleted_count'] ) ? (int) $cleanup['deleted_count'] : 0 ),
				'diff'          => $cleanup['deleted'] ?? array(),
				'context'       => array_merge( array( 'action' => 'cleanup' ), $cleanup ),
			)
		);
	} );
}

/**
 * Audit schema metadata.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_audit_schema_meta_handler( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		return webo_rank_math_audit_schema_meta( $input );
	} );
}

/**
 * Get term SEO metadata.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_get_term_seo_meta( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$term_id = isset( $input['term_id'] ) ? intval( $input['term_id'] ) : 0;
		if ( ! $term_id ) {
			return new WP_Error( 'missing_term_id', 'term_id is required' );
		}
		return array( 'term_id' => $term_id, 'seo_meta' => webo_rank_math_collect_term_meta( $term_id, $input['keys'] ?? null ) );
	} );
}

/**
 * Update term SEO metadata.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_update_term_seo_meta( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$term_id = isset( $input['term_id'] ) ? intval( $input['term_id'] ) : 0;
		if ( ! $term_id ) {
			return new WP_Error( 'missing_term_id', 'term_id is required' );
		}
		$seo_meta = $input['seo_meta'] ?? array();
		$updated  = webo_rank_math_update_term_meta_map( $term_id, $seo_meta );
		return array( 'term_id' => $term_id, 'updated' => $updated );
	} );
}

/**
 * Get user SEO metadata.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_get_user_seo_meta( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$user_id = isset( $input['user_id'] ) ? intval( $input['user_id'] ) : 0;
		if ( ! $user_id ) {
			return new WP_Error( 'missing_user_id', 'user_id is required' );
		}
		return array( 'user_id' => $user_id, 'seo_meta' => webo_rank_math_collect_user_meta( $user_id, $input['keys'] ?? null ) );
	} );
}

/**
 * Update user SEO metadata.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_update_user_seo_meta( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$user_id = isset( $input['user_id'] ) ? intval( $input['user_id'] ) : 0;
		if ( ! $user_id ) {
			return new WP_Error( 'missing_user_id', 'user_id is required' );
		}
		$seo_meta = $input['seo_meta'] ?? array();
		$updated  = webo_rank_math_update_user_meta_map( $user_id, $seo_meta );
		return array( 'user_id' => $user_id, 'updated' => $updated );
	} );
}

/**
 * List redirections.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_list_redirections( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		$page = isset( $input['page'] ) ? max( 1, intval( $input['page'] ) ) : 1;
		$per_page = isset( $input['per_page'] ) ? max( 1, intval( $input['per_page'] ) ) : 50;
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$redirections = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

		return array(
			'redirections' => $redirections ?? array(),
			'page'         => $page,
			'per_page'     => $per_page,
			'total'        => intval( $total ),
		);
	} );
}

/**
 * Get a single redirection.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_get_redirection( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$id = isset( $input['id'] ) ? intval( $input['id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_id', 'id is required' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$redirection = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$id
			)
		);

		if ( ! $redirection ) {
			return new WP_Error( 'not_found', 'Redirection not found' );
		}

		return array( 'id' => $id, 'redirection' => $redirection );
	} );
}

/**
 * Create a redirection.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_create_redirection( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		$now   = current_time( 'mysql' );
		$source_url = isset( $input['source_url'] ) ? trim( (string) $input['source_url'] ) : '';
		$target_url = isset( $input['target_url'] ) ? trim( (string) $input['target_url'] ) : '';

		if ( '' === $source_url || '' === $target_url ) {
			return new WP_Error( 'missing_fields', 'source_url and target_url are required' );
		}

		$source_url = preg_replace( '#^https?://[^/]+/#i', '', $source_url );
		$source_url = ltrim( (string) $source_url, '/' );
		$source = array(
			array(
				'ignore'     => '',
				'pattern'    => $source_url,
				'comparison' => 'exact',
			),
		);

		$data = array(
			'sources'     => maybe_serialize( $source ),
			'url_to'      => $target_url,
			'header_code' => intval( $input['header_code'] ?? 301 ),
			'status'      => 'active',
			'created'     => $now,
			'updated'     => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $data );

		if ( ! $result ) {
			return new WP_Error( 'insert_failed', 'Failed to create redirection', array( 'db_error' => $wpdb->last_error ) );
		}

		return array( 'id' => $wpdb->insert_id, 'redirection' => (object) array_merge( $data, array( 'id' => $wpdb->insert_id ) ) );
	} );
}

/**
 * Update a redirection.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_update_redirection( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		$id = isset( $input['id'] ) ? intval( $input['id'] ) : 0;

		if ( ! $id ) {
			return new WP_Error( 'missing_id', 'id is required' );
		}

		$data = array();
		if ( isset( $input['source_url'] ) ) {
			$data['source_url'] = $input['source_url'];
		}
		if ( isset( $input['target_url'] ) ) {
			$data['target_url'] = $input['target_url'];
		}
		if ( isset( $input['header_code'] ) ) {
			$data['header_code'] = intval( $input['header_code'] );
		}
		$data['updated'] = current_time( 'mysql' );

		if ( empty( $data ) ) {
			return new WP_Error( 'no_fields', 'At least one field to update is required' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update( $table, $data, array( 'id' => $id ) );

		if ( $result === false ) {
			return new WP_Error( 'update_failed', 'Failed to update redirection' );
		}

		return array( 'id' => $id, 'updated' => $result > 0 );
	} );
}

/**
 * Delete a redirection.
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_delete_redirection( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		$id = isset( $input['id'] ) ? intval( $input['id'] ) : 0;

		if ( ! $id ) {
			return new WP_Error( 'missing_id', 'id is required' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete( $table, array( 'id' => $id ) );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', 'Failed to delete redirection' );
		}

		return array( 'id' => $id, 'deleted' => true );
	} );
}

/**
 * Dispatch config queries (plugin-status, get-options, get-modules).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_config_query( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';

	if ( '' === $action ) {
		return new WP_Error( 'webo_mcp_missing_action', 'action is required' );
	}

	return match ( $action ) {
		'plugin-status'   => webo_rank_math_get_plugin_status(),
		'get-options'     => webo_rank_math_get_options( $input ),
		'get-modules'     => webo_rank_math_get_modules( $input ),
		default          => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown config-query action: %s', $action ) ),
	};
}

/**
 * Dispatch config mutations (update-options, update-modules, flush-sitemap-cache).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_config_mutate( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';

	if ( '' === $action ) {
		return new WP_Error( 'webo_mcp_missing_action', 'action is required' );
	}

	if ( isset( $input['options'] ) && is_array( $input['options'] ) ) {
		foreach ( $input['options'] as $key => $value ) {
			if ( ! array_key_exists( $key, $input ) ) {
				$input[ $key ] = $value;
			}
		}
	}

	if ( 'apply-profile' === $action && function_exists( 'webo_mcp_rank_math_apply_profile_tool' ) ) {
		return webo_mcp_rank_math_apply_profile_tool( $input );
	}

	// Semantic (AI-first) actions are also reachable via config-mutate for backward compat.
	$semantic_actions = array(
		'apply-brand-profile', 'migrate-brand', 'configure-homepage', 'configure-social',
		'configure-schema-defaults', 'configure-sitemap-profile', 'audit-brand-seo', 'fix-brand-seo',
	);
	if ( in_array( $action, $semantic_actions, true ) && function_exists( 'webo_rank_math_semantic_action' ) ) {
		return webo_rank_math_semantic_action( $input );
	}

	return match ( $action ) {
		'update-options'       => webo_rank_math_update_options( $input ),
		'update-modules'       => webo_rank_math_update_modules( $input ),
		'flush-sitemap-cache'  => webo_rank_math_flush_sitemap_cache( $input ),
		'apply-basic-seo',
		'optimize-basic',
		'optimize-basic-settings',
		'seo-baseline'         => webo_rank_math_apply_basic_seo_settings( $input ),
		default               => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown config-mutate action: %s. Semantic actions (apply-brand-profile, migrate-brand, etc.) are available via webo-rank-math/semantic-action.', $action ) ),
	};
}

/**
 * Dispatch post SEO queries (get, audit).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_post_seo_query( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';

	if ( '' === $action ) {
		return new WP_Error( 'webo_mcp_missing_action', 'action is required' );
	}

	return match ( $action ) {
		'get'   => webo_rank_math_get_post_seo_meta( $input ),
		'audit' => webo_rank_math_audit_schema_meta_handler( $input ),
		default => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown post-seo-query action: %s', $action ) ),
	};
}

/**
 * Dispatch post SEO mutations (update, bulk-upsert, cleanup).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_post_seo_mutate( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';

	if ( '' === $action ) {
		return new WP_Error( 'webo_mcp_missing_action', 'action is required' );
	}

	return match ( $action ) {
		'update'       => webo_rank_math_update_post_seo_meta( $input ),
		'bulk-upsert'  => webo_rank_math_bulk_upsert_post_seo_meta( $input ),
		'cleanup'      => webo_rank_math_cleanup_post_schema_meta_handler( $input ),
		default       => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown post-seo-mutate action: %s', $action ) ),
	};
}

/**
 * Dispatch term SEO queries (get).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_term_seo_query( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : 'get';
	return match ( $action ) {
		'get'   => webo_rank_math_get_term_seo_meta( $input ),
		default => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown term-seo-query action: %s', $action ) ),
	};
}

/**
 * Dispatch term SEO mutations (update).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_term_seo_mutate( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';

	if ( '' === $action ) {
		return new WP_Error( 'webo_mcp_missing_action', 'action is required' );
	}

	return match ( $action ) {
		'update' => webo_rank_math_update_term_seo_meta( $input ),
		default => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown term-seo-mutate action: %s', $action ) ),
	};
}

/**
 * Dispatch user SEO queries (get).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_user_seo_query( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : 'get';
	return match ( $action ) {
		'get'   => webo_rank_math_get_user_seo_meta( $input ),
		default => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown user-seo-query action: %s', $action ) ),
	};
}

/**
 * Dispatch user SEO mutations (update).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_user_seo_mutate( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';

	if ( '' === $action ) {
		return new WP_Error( 'webo_mcp_missing_action', 'action is required' );
	}

	return match ( $action ) {
		'update' => webo_rank_math_update_user_seo_meta( $input ),
		default => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown user-seo-mutate action: %s', $action ) ),
	};
}

/**
 * Dispatch redirect queries (list, get).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_redirect_query( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : 'list';

	return match ( $action ) {
		'list' => webo_rank_math_list_redirections( $input ),
		'get'  => webo_rank_math_get_redirection( $input ),
		default => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown redirect-query action: %s', $action ) ),
	};
}

/**
 * Dispatch redirect mutations (create, update, delete).
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed>|\WP_Error
 */
function webo_rank_math_redirect_mutate( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';

	if ( '' === $action ) {
		return new WP_Error( 'webo_mcp_missing_action', 'action is required' );
	}

	return match ( $action ) {
		'create' => webo_rank_math_create_redirection( $input ),
		'update' => webo_rank_math_update_redirection( $input ),
		'delete' => webo_rank_math_delete_redirection( $input ),
		default => new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown redirect-mutate action: %s', $action ) ),
	};
}

add_action( 'wp_abilities_api_init', function () {
	// Config query
	wp_register_ability( 'webo-rank-math/config-query', array(
		'label'       => 'Rank Math Config Query',
		'description' => 'Unified Rank Math configuration query. action: plugin-status, get-options, get-modules.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'required'             => array( 'action' ),
			'properties'           => array(
				'action'         => array( 'type' => 'string', 'description' => 'Query action.' ),
				'option_names'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'site_id'        => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_config_query',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	// Config mutate
	wp_register_ability( 'webo-rank-math/config-mutate', array(
		'label'       => 'Rank Math Config Mutation',
		'description' => 'Unified Rank Math configuration mutation. action: update-options, update-modules, flush-sitemap-cache, apply-basic-seo.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'required'             => array( 'action' ),
			'properties'           => array(
				'action'  => array( 'type' => 'string', 'description' => 'Mutation action.' ),
				'options' => array( 'type' => 'object', 'additionalProperties' => true ),
				'modules' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'site_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_config_mutate',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	// Post SEO query
	wp_register_ability( 'webo-rank-math/post-seo-query', array(
		'label'       => 'Rank Math Post SEO Query',
		'description' => 'Unified Rank Math post SEO query. action: get, audit.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'action'    => array( 'type' => 'string', 'description' => 'Query action (get or audit).' ),
				'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
				'slug'      => array( 'type' => 'string' ),
				'post_type' => array( 'type' => 'string', 'default' => 'post' ),
				'keys'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'site_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_post_seo_query',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	// Post SEO mutate
	wp_register_ability( 'webo-rank-math/post-seo-mutate', array(
		'label'       => 'Rank Math Post SEO Mutation',
		'description' => 'Unified Rank Math post SEO mutation. action: update, bulk-upsert, cleanup.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'action'       => array( 'type' => 'string', 'description' => 'Mutation action.' ),
				'post_id'      => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Target post id. Use 0 or omit to resolve the post by slug instead.' ),
				'slug'         => array( 'type' => 'string' ),
				'post_type'    => array( 'type' => 'string', 'default' => 'post' ),
				'seo_meta'     => array( 'type' => 'object', 'additionalProperties' => true ),
				'posts'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'skip_missing' => array( 'type' => 'boolean' ),
				'site_id'      => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_post_seo_mutate',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	// Term SEO query
	wp_register_ability( 'webo-rank-math/term-seo-query', array(
		'label'       => 'Rank Math Term SEO Query',
		'description' => 'Unified Rank Math term SEO query. action: get.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'action'    => array( 'type' => 'string', 'description' => 'Query action (always get).' ),
				'term_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
				'taxonomy'  => array( 'type' => 'string' ),
				'keys'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'site_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_term_seo_query',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	// Term SEO mutate
	wp_register_ability( 'webo-rank-math/term-seo-mutate', array(
		'label'       => 'Rank Math Term SEO Mutation',
		'description' => 'Unified Rank Math term SEO mutation. action: update.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'action'    => array( 'type' => 'string', 'description' => 'Mutation action (always update).' ),
				'term_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
				'taxonomy'  => array( 'type' => 'string' ),
				'seo_meta'  => array( 'type' => 'object', 'additionalProperties' => true ),
				'site_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_term_seo_mutate',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	// User SEO query
	wp_register_ability( 'webo-rank-math/user-seo-query', array(
		'label'       => 'Rank Math User SEO Query',
		'description' => 'Unified Rank Math user SEO query. action: get.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'action'  => array( 'type' => 'string', 'description' => 'Query action (always get).' ),
				'user_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'keys'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'site_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_user_seo_query',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	// User SEO mutate
	wp_register_ability( 'webo-rank-math/user-seo-mutate', array(
		'label'       => 'Rank Math User SEO Mutation',
		'description' => 'Unified Rank Math user SEO mutation. action: update.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'action'   => array( 'type' => 'string', 'description' => 'Mutation action (always update).' ),
				'user_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
				'seo_meta' => array( 'type' => 'object', 'additionalProperties' => true ),
				'site_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_user_seo_mutate',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	// Redirect query
	wp_register_ability( 'webo-rank-math/redirect-query', array(
		'label'       => 'Rank Math Redirect Query',
		'description' => 'Unified Rank Math redirection query. action: list, get.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'action'   => array( 'type' => 'string', 'description' => 'Query action (list or get).' ),
				'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
				'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'default' => 50 ),
				'site_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_redirect_query',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	// Redirect mutate
	wp_register_ability( 'webo-rank-math/redirect-mutate', array(
		'label'       => 'Rank Math Redirect Mutation',
		'description' => 'Unified Rank Math redirection mutation. action: create, update, delete.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'action'      => array( 'type' => 'string', 'description' => 'Mutation action.' ),
				'id'          => array( 'type' => 'integer', 'minimum' => 1 ),
				'source_url'  => array( 'type' => 'string' ),
				'target_url'  => array( 'type' => 'string' ),
				'header_code' => array( 'type' => 'integer' ),
				'site_id'     => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => 'webo_rank_math_redirect_mutate',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );
} );
