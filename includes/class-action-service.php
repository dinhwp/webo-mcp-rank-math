<?php
/**
 * ActionService - v2 action-level Rank Math MCP API.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WeboMcpRankMath_ActionService' ) ) {
	class WeboMcpRankMath_ActionService {
		const ACTIONS = array(
			'optimize-settings',
			'complete-brand-profile',
			'fix-common-issues',
			'flush-rankmath-cache',
			'ai-optimize-low-ctr-posts',
			'generate-faq-schema',
			'rebuild-internal-links',
			'sync-gsc',
		);

		const OPTION_GROUPS = array(
			'general' => 'rank-math-options-general',
			'titles'  => 'rank-math-options-titles',
			'sitemap' => 'rank-math-options-sitemap',
			'social'  => 'rank-math-options-social',
		);

		public static function dispatch( $input ) {
			$input  = self::normalize_input( $input );
			$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';

			if ( '' === $action ) {
				return new WP_Error( 'webo_mcp_missing_action', 'action is required.' );
			}

			if ( ! in_array( $action, self::ACTIONS, true ) ) {
				return new WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unsupported Rank Math MCP action: %s', $action ) );
			}

			$runner = static function () use ( $input, $action ) {
				if ( 'sync-gsc' !== $action && ! self::rank_math_is_active() ) {
					return new WP_Error( 'webo_mcp_rank_math_inactive', 'Rank Math SEO is not active on the selected site.' );
				}

				return match ( $action ) {
					'optimize-settings'         => self::optimize_settings( $input ),
					'complete-brand-profile'    => self::complete_brand_profile( $input ),
					'fix-common-issues'         => self::fix_common_issues( $input ),
					'flush-rankmath-cache'      => self::flush_cache( $input ),
					'ai-optimize-low-ctr-posts' => self::ai_optimize_low_ctr_posts( $input ),
					'generate-faq-schema'       => self::generate_faq_schema( $input ),
					'rebuild-internal-links'    => self::rebuild_internal_links( $input ),
					'sync-gsc'                  => self::sync_gsc( $input ),
				};
			};

			return function_exists( 'webo_rank_math_with_site' )
				? webo_rank_math_with_site( $input, $runner )
				: $runner();
		}

		public static function normalize_input( $input ) {
			$input = (array) $input;
			foreach ( array( 'parameters', 'options', 'profileData' ) as $container ) {
				if ( isset( $input[ $container ] ) && is_array( $input[ $container ] ) ) {
					$input = array_merge( $input[ $container ], $input );
					unset( $input[ $container ] );
				}
			}

			$map = array(
				'dryRun'                => 'dry_run',
				'brandName'             => 'brand_name',
				'alternateName'         => 'alternate_name',
				'personName'            => 'person_name',
				'homepageTitle'         => 'homepage_title',
				'homepageDescription'   => 'homepage_description',
				'openGraphImage'        => 'open_graph_image',
				'sameAs'                => 'same_as',
				'nofollowExternalLinks' => 'nofollow_external_links',
				'nofollowImageLinks'    => 'nofollow_image_links',
				'postIds'               => 'post_ids',
				'questionsPerPost'      => 'questions_per_post',
				'minImpressions'        => 'min_impressions',
				'maxCtr'                => 'max_ctr',
				'dateRange'             => 'date_range',
			);
			foreach ( $map as $from => $to ) {
				if ( array_key_exists( $from, $input ) && ! array_key_exists( $to, $input ) ) {
					$input[ $to ] = $input[ $from ];
				}
			}

			if ( ! isset( $input['dry_run'] ) ) {
				$input['dry_run'] = true;
			}

			return $input;
		}

		private static function rank_math_is_active() {
			if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) || class_exists( '\\RankMath\\Installer' ) ) {
				return true;
			}
			$active = (array) get_option( 'active_plugins', array() );
			return in_array( 'seo-by-rank-math/rank-math.php', $active, true );
		}

		private static function mutation_mode( $input ) {
			$mode = function_exists( 'webo_mcp_resolve_mutation_mode' )
				? webo_mcp_resolve_mutation_mode( (array) $input, true )
				: array(
					'dry_run' => ( empty( $input['force'] ) || ( array_key_exists( 'dry_run', $input ) && webo_mcp_is_truthy( $input['dry_run'] ) ) ),
					'force'   => ! empty( $input['force'] ),
					'blocked' => false,
					'reason'  => '',
				);

			if ( empty( $mode['dry_run'] ) && empty( $mode['force'] ) ) {
				$mode['dry_run'] = true;
				$mode['blocked'] = true;
				$mode['reason']  = 'Mutating Rank Math action requires force=true with dryRun=false. A dry-run preview was returned instead.';
			}

			return $mode;
		}

		private static function bool_to_on_off( $value ) {
			return webo_mcp_is_truthy( $value ) ? 'on' : 'off';
		}

		private static function public_post_types() {
			if ( ! function_exists( 'get_post_types' ) ) {
				return array( 'post', 'page' );
			}
			$types = get_post_types( array( 'public' => true ), 'names' );
			unset( $types['attachment'] );
			return array_values( $types );
		}

		private static function build_diff( $patch, $current = null ) {
			if ( null === $current ) {
				$current = array();
				foreach ( array_keys( $patch ) as $group ) {
					$current[ $group ] = WeboMcpRankMath_OptionsRepository::get_group( $group );
				}
			}
			return WeboMcpRankMath_BrandProfileMapper::build_diff( $patch, $current );
		}

		private static function changed_fields( $diff ) {
			return WeboMcpRankMath_BrandProfileMapper::changed_fields( $diff );
		}

		private static function option_names_for_patch( $patch ) {
			$names = array();
			foreach ( array_keys( $patch ) as $group ) {
				if ( isset( self::OPTION_GROUPS[ $group ] ) ) {
					$names[] = self::OPTION_GROUPS[ $group ];
				}
			}
			return $names;
		}

		private static function option_response( $action, $input, $patch, $warnings = array(), $extra = array() ) {
			$mode    = self::mutation_mode( $input );
			$dry_run = ! empty( $mode['dry_run'] );
			$diff    = self::build_diff( $patch );
			$changed = self::changed_fields( $diff );

			if ( ! empty( $mode['blocked'] ) && ! empty( $mode['reason'] ) ) {
				$warnings[] = $mode['reason'];
			}

			$context = array_merge(
				array(
					'ok'             => true,
					'action'         => $action,
					'changed_fields' => $changed,
					'warnings'       => array_values( array_filter( $warnings ) ),
					'nextActions'    => self::next_actions( $action ),
				),
				$extra
			);

			if ( $dry_run ) {
				self::log_action( $action, true, $changed, array() );
				return webo_mcp_mutation_response( array(
					'dry_run'       => true,
					'would_change'  => count( $changed ) > 0,
					'planned_count' => count( $changed ),
					'diff'          => $diff,
					'context'       => $context,
				) );
			}

			$snapshot = WeboMcpRankMath_SnapshotService::create( $action, self::option_names_for_patch( $patch ) );
			foreach ( $patch as $group => $keys ) {
				WeboMcpRankMath_OptionsRepository::patch_group( $group, $keys );
			}
			webo_rank_math_flush_sitemap_cache( $input );

			$context['backup_id']   = $snapshot['snapshot_id'];
			$context['snapshot_id'] = $snapshot['snapshot_id'];
			self::log_action( $action, false, $changed, array( 'backup_id' => $snapshot['snapshot_id'] ) );

			return webo_mcp_mutation_response( array(
				'dry_run'       => false,
				'changed'       => count( $changed ) > 0,
				'changed_count' => count( $changed ),
				'diff'          => $diff,
				'context'       => $context,
			) );
		}

		public static function optimize_settings( $input ) {
			$general = array(
				'404_monitor_mode'                    => 'simple',
				'404_monitor_ignore_query_parameters' => 'on',
				'redirections_header_code'            => '301',
			);
			$titles = array(
				'homepage_custom_robots' => 'off',
				'robots_global'          => array( 'index', 'follow' ),
				'twitter_card_type'      => 'summary_large_image',
				'nofollow_external_links' => array_key_exists( 'nofollow_external_links', $input ) ? self::bool_to_on_off( $input['nofollow_external_links'] ) : 'off',
				'nofollow_image_links'    => array_key_exists( 'nofollow_image_links', $input ) ? self::bool_to_on_off( $input['nofollow_image_links'] ) : 'off',
			);
			$sitemap = array(
				'attachment_sitemap'  => 'off',
				'tax_post_tag_sitemap' => 'off',
				'tax_category_sitemap' => 'on',
			);
			foreach ( self::public_post_types() as $post_type ) {
				if ( in_array( $post_type, array( 'post', 'page', 'docs' ), true ) ) {
					$sitemap[ 'pt_' . sanitize_key( $post_type ) . '_sitemap' ] = 'on';
				}
			}

			$response = self::option_response(
				'optimize-settings',
				$input,
				array( 'general' => $general, 'titles' => $titles, 'sitemap' => $sitemap ),
				array(),
				array( 'profile' => sanitize_key( (string) ( $input['profile'] ?? 'business' ) ) )
			);

			if ( ! is_wp_error( $response ) && empty( $response['dry_run'] ) ) {
				$modules = array_values( array_unique( array_merge( WeboMcpRankMath_OptionsRepository::get_modules(), array( 'sitemap', 'redirections', '404-monitor' ) ) ) );
				WeboMcpRankMath_OptionsRepository::set_modules( $modules );
				webo_rank_math_create_module_tables( $modules );
				$response['modules'] = $modules;
			}

			return $response;
		}

		public static function complete_brand_profile( $input ) {
			$normalized            = $input;
			$mode                  = self::mutation_mode( $input );
			$normalized['dry_run'] = ! empty( $mode['dry_run'] );
			if ( ! isset( $normalized['profile'] ) || in_array( $normalized['profile'], array( 'business', 'saas', 'local-business', 'organization-brand' ), true ) ) {
				$normalized['profile'] = 'organization';
			}
			$result = WeboMcpRankMath_BrandProfileService::complete( $normalized );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$result['ok']          = true;
			$result['action']      = 'complete-brand-profile';
			$result['nextActions'] = self::next_actions( 'complete-brand-profile' );
			if ( ! empty( $mode['blocked'] ) && ! empty( $mode['reason'] ) ) {
				$result['warnings'][] = $mode['reason'];
			}
			self::log_action( 'complete-brand-profile', ! empty( $result['dry_run'] ), $result['changed_fields'] ?? array(), array() );
			return $result;
		}

		public static function fix_common_issues( $input ) {
			$general = WeboMcpRankMath_OptionsRepository::get_group( 'general' );
			$titles  = WeboMcpRankMath_OptionsRepository::get_group( 'titles' );
			$sitemap = WeboMcpRankMath_OptionsRepository::get_group( 'sitemap' );
			$social  = WeboMcpRankMath_OptionsRepository::get_group( 'social' );
			$patch   = array();
			$warnings = array();

			if ( empty( $social['open_graph_image'] ) && ! empty( $input['open_graph_image'] ) ) {
				$patch['social']['open_graph_image'] = esc_url_raw( (string) $input['open_graph_image'] );
			} elseif ( empty( $social['open_graph_image'] ) ) {
				$warnings[] = 'OpenGraph image is empty and no fallback openGraphImage was provided.';
			}
			if ( 'on' === ( $titles['nofollow_external_links'] ?? '' ) ) {
				$patch['titles']['nofollow_external_links'] = 'off';
			}
			if ( 'on' === ( $titles['nofollow_image_links'] ?? '' ) ) {
				$patch['titles']['nofollow_image_links'] = 'off';
			}
			if ( 'on' === ( $sitemap['attachment_sitemap'] ?? '' ) ) {
				$patch['sitemap']['attachment_sitemap'] = 'off';
			}
			if ( 'on' === ( $sitemap['tax_post_tag_sitemap'] ?? '' ) ) {
				$patch['sitemap']['tax_post_tag_sitemap'] = 'off';
			}
			if ( empty( $general['404_monitor_mode'] ) ) {
				$patch['general']['404_monitor_mode'] = 'simple';
			}
			if ( empty( $titles['breadcrumbs_404_label'] ) ) {
				$patch['titles']['breadcrumbs_404_label'] = '404 Error: page not found';
			}
			if ( empty( $general['console_email_subject'] ) ) {
				$patch['general']['console_email_subject'] = 'SEO Report of Your Website';
			}

			if ( empty( $patch ) ) {
				return webo_mcp_mutation_response( array(
					'dry_run'       => true,
					'would_change'  => false,
					'planned_count' => 0,
					'diff'          => array(),
					'context'       => array( 'ok' => true, 'action' => 'fix-common-issues', 'warnings' => $warnings, 'nextActions' => self::next_actions( 'fix-common-issues' ) ),
				) );
			}

			return self::option_response( 'fix-common-issues', $input, $patch, $warnings );
		}

		public static function flush_cache( $input ) {
			$mode = self::mutation_mode( $input );
			if ( ! empty( $mode['dry_run'] ) ) {
				return webo_mcp_mutation_response( array(
					'dry_run'       => true,
					'would_change'  => true,
					'planned_count' => 3,
					'diff'          => array( 'rank_math_sitemap_cache', 'wp_rocket_cache_if_available', 'object_cache' ),
					'context'       => array( 'ok' => true, 'action' => 'flush-rankmath-cache', 'warnings' => array_filter( array( $mode['reason'] ?? '' ) ), 'nextActions' => self::next_actions( 'flush-rankmath-cache' ) ),
				) );
			}

			$rank_math = webo_rank_math_flush_sitemap_cache( $input );
			$rocket    = false;
			if ( function_exists( 'rocket_clean_domain' ) ) {
				rocket_clean_domain();
				$rocket = true;
			}
			$object_cache = function_exists( 'wp_cache_flush' ) ? wp_cache_flush() : false;
			self::log_action( 'flush-rankmath-cache', false, array( 'cache.flush' ), array() );

			return webo_mcp_mutation_response( array(
				'dry_run'       => false,
				'changed'       => true,
				'changed_count' => 3,
				'diff'          => array( 'rank_math' => $rank_math, 'wp_rocket' => $rocket, 'object_cache' => $object_cache ),
				'context'       => array( 'ok' => true, 'action' => 'flush-rankmath-cache', 'nextActions' => self::next_actions( 'flush-rankmath-cache' ) ),
			) );
		}

		public static function ai_optimize_low_ctr_posts( $input ) {
			unset( $input );
			if ( ! self::gsc_available() ) {
				return new WP_Error( 'webo_mcp_rank_math_gsc_unavailable', 'GSC addon is not available or not configured, so low CTR optimization can only return a setup error.' );
			}
			return array( 'ok' => true, 'action' => 'ai-optimize-low-ctr-posts', 'dry_run' => true, 'executed' => false, 'suggestions' => array(), 'warnings' => array( 'GSC integration detected, but automated query pull is delegated to the GSC addon.' ), 'nextActions' => array( 'Run sync-gsc before applying post SEO updates.' ) );
		}

		public static function generate_faq_schema( $input ) {
			$mode     = self::mutation_mode( $input );
			$dry_run  = ! empty( $mode['dry_run'] );
			$post_ids = array_values( array_filter( array_map( 'absint', (array) ( $input['post_ids'] ?? array() ) ) ) );
			$limit    = max( 1, min( 10, absint( $input['questions_per_post'] ?? 4 ) ) );
			$results  = array();
			$changed  = 0;

			foreach ( $post_ids as $post_id ) {
				if ( get_post_meta( $post_id, 'rank_math_schema_FAQPage', true ) ) {
					$results[] = array( 'post_id' => $post_id, 'skipped' => true, 'reason' => 'FAQ schema already exists.' );
					continue;
				}
				$post = get_post( $post_id );
				if ( ! $post ) {
					$results[] = array( 'post_id' => $post_id, 'error' => 'post_not_found' );
					continue;
				}
				$questions = self::extract_faq_pairs( $post, $limit );
				$schema    = array( '@type' => 'FAQPage', 'mainEntity' => $questions );
				if ( ! $dry_run ) {
					update_post_meta( $post_id, 'rank_math_schema_FAQPage', $schema );
				}
				$changed++;
				$results[] = array( 'post_id' => $post_id, 'schema' => $schema );
			}

			self::log_action( 'generate-faq-schema', $dry_run, wp_list_pluck( $results, 'post_id' ), array() );
			return webo_mcp_mutation_response( array(
				'dry_run'       => $dry_run,
				'would_change'  => $changed > 0,
				'planned_count' => $changed,
				'changed'       => ! $dry_run && $changed > 0,
				'changed_count' => $dry_run ? 0 : $changed,
				'diff'          => $results,
				'context'       => array( 'ok' => true, 'action' => 'generate-faq-schema', 'warnings' => array_filter( array( $mode['reason'] ?? '' ) ), 'nextActions' => self::next_actions( 'generate-faq-schema' ) ),
			) );
		}

		private static function extract_faq_pairs( $post, $limit ) {
			$title  = sanitize_text_field( get_the_title( $post ) );
			$text   = wp_strip_all_tags( (string) $post->post_content );
			$text   = trim( preg_replace( '/\s+/', ' ', $text ) );
			$answer = $text ? wp_trim_words( $text, 38, '...' ) : sprintf( 'See the page content for details about %s.', $title );
			$pairs  = array();
			for ( $i = 1; $i <= $limit; $i++ ) {
				$pairs[] = array( '@type' => 'Question', 'name' => 1 === $i ? sprintf( 'What should I know about %s?', $title ) : sprintf( 'What is detail %d for %s?', $i, $title ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $answer ) );
			}
			return $pairs;
		}

		public static function rebuild_internal_links( $input ) {
			$mode        = self::mutation_mode( $input );
			$dry_run     = ! empty( $mode['dry_run'] );
			$mode_arg    = sanitize_key( (string) ( $input['mode'] ?? 'suggest' ) );
			$limit       = max( 1, min( 100, absint( $input['limit'] ?? 20 ) ) );
			$post_ids    = array_values( array_filter( array_map( 'absint', (array) ( $input['post_ids'] ?? array() ) ) ) );
			$posts       = self::load_link_posts( $post_ids, $limit );
			$suggestions = self::build_link_suggestions( $posts );
			$inserted    = 0;

			if ( 'insert' === $mode_arg && ! $dry_run ) {
				foreach ( $suggestions as $item ) {
					$post = get_post( $item['source_post_id'] );
					if ( ! $post || false !== strpos( $post->post_content, $item['target_url'] ) ) {
						continue;
					}
					$anchor  = esc_html( $item['anchor'] );
					$link    = '<a href="' . esc_url( $item['target_url'] ) . '">' . $anchor . '</a>';
					$content = $post->post_content . "\n\n" . $link;
					wp_update_post( array( 'ID' => $post->ID, 'post_content' => $content ) );
					$inserted++;
				}
			}

			return webo_mcp_mutation_response( array(
				'dry_run'       => $dry_run || 'suggest' === $mode_arg,
				'would_change'  => count( $suggestions ) > 0,
				'planned_count' => count( $suggestions ),
				'changed'       => $inserted > 0,
				'changed_count' => $inserted,
				'diff'          => $suggestions,
				'context'       => array( 'ok' => true, 'action' => 'rebuild-internal-links', 'mode' => $mode_arg, 'warnings' => array_filter( array( $mode['reason'] ?? '' ) ), 'nextActions' => self::next_actions( 'rebuild-internal-links' ) ),
			) );
		}

		private static function load_link_posts( $post_ids, $limit ) {
			$args = array( 'post_type' => self::public_post_types(), 'post_status' => 'publish', 'posts_per_page' => $limit );
			if ( ! empty( $post_ids ) ) {
				$args['post__in'] = $post_ids;
			}
			return get_posts( $args );
		}

		private static function build_link_suggestions( $posts ) {
			$suggestions = array();
			foreach ( $posts as $source ) {
				foreach ( $posts as $target ) {
					if ( $source->ID === $target->ID ) {
						continue;
					}
					$title = sanitize_text_field( get_the_title( $target ) );
					if ( '' === $title || false === stripos( wp_strip_all_tags( $source->post_content ), $title ) ) {
						continue;
					}
					$suggestions[] = array( 'source_post_id' => (int) $source->ID, 'target_post_id' => (int) $target->ID, 'anchor' => $title, 'target_url' => get_permalink( $target ) );
				}
			}
			return $suggestions;
		}

		public static function sync_gsc( $input ) {
			if ( ! self::gsc_available() ) {
				return new WP_Error( 'webo_mcp_rank_math_gsc_unavailable', 'GSC addon is not available or not configured.' );
			}
			$mode    = self::mutation_mode( $input );
			$summary = array( 'synced_at' => current_time( 'c' ), 'source' => 'webo-mcp-gsc', 'top_pages' => array(), 'top_queries' => array(), 'low_ctr_pages' => array() );
			if ( empty( $mode['dry_run'] ) ) {
				set_transient( 'webo_rankmath_gsc_summary', $summary, HOUR_IN_SECONDS );
			}
			return array( 'ok' => true, 'action' => 'sync-gsc', 'dry_run' => ! empty( $mode['dry_run'] ), 'summary' => $summary, 'nextActions' => self::next_actions( 'sync-gsc' ) );
		}

		private static function gsc_available() {
			return function_exists( 'webo_mcp_gsc_query' ) || function_exists( 'webo_gsc_query' ) || class_exists( 'WeboMcpGsc' ) || class_exists( 'Webo_MCP_GSC' );
		}

		private static function log_action( $action, $dry_run, $changed, $extra ) {
			$entry = array_merge( array( 'action' => $action, 'dry_run' => (bool) $dry_run, 'changed_keys' => array_values( (array) $changed ), 'timestamp' => current_time( 'c' ), 'user_id' => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 ), $extra );
			$log   = get_option( 'webo_rankmath_action_log', array() );
			$log   = is_array( $log ) ? $log : array();
			$log[] = $entry;
			$log   = array_slice( $log, -50 );
			update_option( 'webo_rankmath_action_log', $log, false );
		}

		private static function next_actions( $action ) {
			return match ( $action ) {
				'optimize-settings'      => array( 'Run fix-common-issues as a dry-run.', 'Review changes, then execute with dryRun=false and force=true.' ),
				'complete-brand-profile' => array( 'Run flush-rankmath-cache after applying the profile.' ),
				'fix-common-issues'      => array( 'Review warnings for missing OpenGraph fallback image.' ),
				'sync-gsc'               => array( 'Run ai-optimize-low-ctr-posts after GSC data is available.' ),
				default                  => array(),
			};
		}
	}
}
