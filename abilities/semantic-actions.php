<?php
/**
 * Semantic action abilities — AI-first, high-level actions that map intent to Rank Math options.
 *
 * These actions are additive: they sit on top of all existing tools without removing them.
 * Each action follows the strict mutate workflow:
 *  validate → read → snapshot → build patch → [dry_run check] → write → flush → verify → return.
 *
 * Actions registered here:
 *  - apply-brand-profile     Apply a complete brand identity to Knowledge Graph, homepage, social, schema.
 *  - complete-brand-profile  Apply a profile and clean leftover old brand/entity values in one call.
 *  - entity-cleanup          Alias of complete-brand-profile.
 *  - migrate-brand           Replace old brand name occurrences across all Rank Math option groups.
 *  - brand-cleanup           Replace old brand URLs, logos, social profiles, and related identity values.
 *  - configure-homepage      Set homepage title and description.
 *  - configure-social        Set social profile URLs in the Social option group.
 *  - configure-schema-defaults  Set default schema type for post types.
 *  - configure-sitemap-profile  Configure sitemap post type and taxonomy settings.
 *  - audit-brand-seo         Read and report current brand-related Rank Math settings.
 *  - fix-brand-seo           Auto-fix missing or empty brand fields.
 *  - optimize-settings / fix-common-issues / flush-rankmath-cache / ai-optimize-low-ctr-posts / generate-faq-schema / rebuild-internal-links / sync-gsc (v2 action-level API).
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================================
// Handler functions
// ============================================================================

/**
 * Handler: apply-brand-profile
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_apply_brand_profile( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		return WeboMcpRankMath_BrandProfileService::apply( $input );
	} );
}

/**
 * Handler: complete-brand-profile / entity-cleanup
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_complete_brand_profile( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		return WeboMcpRankMath_BrandProfileService::complete( $input );
	} );
}

/**
 * Handler: migrate-brand
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_migrate_brand( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		return WeboMcpRankMath_MigrationService::migrate( $input );
	} );
}

/**
 * Handler: brand-cleanup
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_brand_cleanup( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		return WeboMcpRankMath_MigrationService::cleanup( $input );
	} );
}

/**
 * Handler: configure-homepage
 * Sets homepage_title and homepage_description in rank-math-options-titles.
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_configure_homepage( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$dry_run = isset( $input['dry_run'] ) ? webo_mcp_is_truthy( $input['dry_run'] ) : true;
		$patch   = array();

		$title = isset( $input['title'] ) ? sanitize_text_field( trim( (string) $input['title'] ) ) : null;
		$desc  = isset( $input['description'] ) ? sanitize_textarea_field( trim( (string) $input['description'] ) ) : null;

		if ( null !== $title ) {
			$patch['homepage_title'] = $title;
		}
		if ( null !== $desc ) {
			$patch['homepage_description'] = $desc;
		}

		if ( empty( $patch ) ) {
			return new WP_Error( 'webo_mcp_configure_homepage_empty', 'Provide at least title or description.' );
		}

		$current = WeboMcpRankMath_OptionsRepository::get_group( 'titles' );
		$diff    = WeboMcpRankMath_BrandProfileMapper::build_diff( array( 'titles' => $patch ), array( 'titles' => $current ) );
		$changed = WeboMcpRankMath_BrandProfileMapper::changed_fields( $diff );

		if ( $dry_run ) {
			return webo_mcp_mutation_response( array(
				'dry_run'       => true,
				'would_change'  => count( $changed ) > 0,
				'planned_count' => count( $changed ),
				'diff'          => $diff,
				'context'       => array( 'action' => 'configure-homepage', 'changed_fields' => $changed ),
			) );
		}

		$snapshot = WeboMcpRankMath_SnapshotService::create( 'configure-homepage', array( 'rank-math-options-titles' ) );
		WeboMcpRankMath_OptionsRepository::patch_group( 'titles', $patch );

		return webo_mcp_mutation_response( array(
			'dry_run'       => false,
			'changed'       => count( $changed ) > 0,
			'changed_count' => count( $changed ),
			'diff'          => $diff,
			'context'       => array(
				'action'         => 'configure-homepage',
				'changed_fields' => $changed,
				'snapshot_id'    => $snapshot['snapshot_id'],
			),
		) );
	} );
}

/**
 * Handler: configure-social
 * Stores social profile URLs in rank-math-options-general (social_url_*) and rank-math-options-social.
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_configure_social( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$dry_run       = isset( $input['dry_run'] ) ? webo_mcp_is_truthy( $input['dry_run'] ) : true;
		$social_fields = array( 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'github', 'pinterest' );
		$patch_general = array();
		$patch_social  = array();

		foreach ( $social_fields as $field ) {
			if ( isset( $input[ $field ] ) && '' !== trim( (string) $input[ $field ] ) ) {
				$url = esc_url_raw( trim( (string) $input[ $field ] ) );
				$patch_general[ "social_url_{$field}" ] = $url;
				$patch_social[ "{$field}_link" ]        = $url;
			}
		}

		if ( empty( $patch_general ) ) {
			return new WP_Error( 'webo_mcp_configure_social_empty', 'Provide at least one social profile URL.' );
		}

		$current_general = WeboMcpRankMath_OptionsRepository::get_group( 'general' );
		$current_social  = WeboMcpRankMath_OptionsRepository::get_group( 'social' );
		$diff            = array_merge(
			WeboMcpRankMath_BrandProfileMapper::build_diff( array( 'general' => $patch_general ), array( 'general' => $current_general ) ),
			WeboMcpRankMath_BrandProfileMapper::build_diff( array( 'social' => $patch_social ), array( 'social' => $current_social ) )
		);
		$changed = WeboMcpRankMath_BrandProfileMapper::changed_fields( $diff );

		if ( $dry_run ) {
			return webo_mcp_mutation_response( array(
				'dry_run'       => true,
				'would_change'  => count( $changed ) > 0,
				'planned_count' => count( $changed ),
				'diff'          => $diff,
				'context'       => array( 'action' => 'configure-social', 'changed_fields' => $changed ),
			) );
		}

		$snapshot = WeboMcpRankMath_SnapshotService::create( 'configure-social', array(
			'rank-math-options-general',
			'rank-math-options-social',
		) );
		WeboMcpRankMath_OptionsRepository::patch_group( 'general', $patch_general );
		WeboMcpRankMath_OptionsRepository::patch_group( 'social', $patch_social );

		return webo_mcp_mutation_response( array(
			'dry_run'       => false,
			'changed'       => count( $changed ) > 0,
			'changed_count' => count( $changed ),
			'diff'          => $diff,
			'context'       => array(
				'action'         => 'configure-social',
				'changed_fields' => $changed,
				'snapshot_id'    => $snapshot['snapshot_id'],
			),
		) );
	} );
}

/**
 * Handler: configure-schema-defaults
 * Sets default_rich_snippet (schema type) for given post types in rank-math-options-titles.
 *
 * @param array<string,mixed> $input  { post_types: {post_type: schema_type}, dry_run }
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_configure_schema_defaults( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$dry_run    = isset( $input['dry_run'] ) ? webo_mcp_is_truthy( $input['dry_run'] ) : true;
		$post_types = is_array( $input['post_types'] ?? null ) ? $input['post_types'] : array();

		if ( empty( $post_types ) ) {
			return new WP_Error( 'webo_mcp_configure_schema_defaults_empty', 'Provide post_types as an object mapping post_type => schema_type.' );
		}

		$patch   = array();
		foreach ( $post_types as $pt => $schema_type ) {
			$pt_key         = sanitize_key( (string) $pt );
			$schema_key     = sanitize_key( (string) $schema_type );
			$patch[ "pt_{$pt_key}_default_rich_snippet" ] = $schema_key;
		}

		$current = WeboMcpRankMath_OptionsRepository::get_group( 'titles' );
		$diff    = WeboMcpRankMath_BrandProfileMapper::build_diff( array( 'titles' => $patch ), array( 'titles' => $current ) );
		$changed = WeboMcpRankMath_BrandProfileMapper::changed_fields( $diff );

		if ( $dry_run ) {
			return webo_mcp_mutation_response( array(
				'dry_run'       => true,
				'would_change'  => count( $changed ) > 0,
				'planned_count' => count( $changed ),
				'diff'          => $diff,
				'context'       => array( 'action' => 'configure-schema-defaults', 'changed_fields' => $changed ),
			) );
		}

		$snapshot = WeboMcpRankMath_SnapshotService::create( 'configure-schema-defaults', array( 'rank-math-options-titles' ) );
		WeboMcpRankMath_OptionsRepository::patch_group( 'titles', $patch );

		return webo_mcp_mutation_response( array(
			'dry_run'       => false,
			'changed'       => count( $changed ) > 0,
			'changed_count' => count( $changed ),
			'diff'          => $diff,
			'context'       => array(
				'action'         => 'configure-schema-defaults',
				'changed_fields' => $changed,
				'snapshot_id'    => $snapshot['snapshot_id'],
			),
		) );
	} );
}

/**
 * Handler: configure-sitemap-profile
 * Toggles post types and taxonomies in the sitemap option group.
 *
 * @param array<string,mixed> $input  { include_post_types, exclude_post_types, include_taxonomies, exclude_taxonomies, dry_run }
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_configure_sitemap_profile( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$dry_run = isset( $input['dry_run'] ) ? webo_mcp_is_truthy( $input['dry_run'] ) : true;
		$patch   = array();

		foreach ( (array) ( $input['include_post_types'] ?? array() ) as $pt ) {
			$patch[ 'pt_' . sanitize_key( $pt ) . '_sitemap' ] = 'on';
		}
		foreach ( (array) ( $input['exclude_post_types'] ?? array() ) as $pt ) {
			$patch[ 'pt_' . sanitize_key( $pt ) . '_sitemap' ] = 'off';
		}
		foreach ( (array) ( $input['include_taxonomies'] ?? array() ) as $tax ) {
			$patch[ 'tax_' . sanitize_key( $tax ) . '_sitemap' ] = 'on';
		}
		foreach ( (array) ( $input['exclude_taxonomies'] ?? array() ) as $tax ) {
			$patch[ 'tax_' . sanitize_key( $tax ) . '_sitemap' ] = 'off';
		}

		if ( empty( $patch ) ) {
			return new WP_Error( 'webo_mcp_configure_sitemap_empty', 'Provide at least one include/exclude post_type or taxonomy.' );
		}

		$current = WeboMcpRankMath_OptionsRepository::get_group( 'sitemap' );
		$diff    = WeboMcpRankMath_BrandProfileMapper::build_diff( array( 'sitemap' => $patch ), array( 'sitemap' => $current ) );
		$changed = WeboMcpRankMath_BrandProfileMapper::changed_fields( $diff );

		if ( $dry_run ) {
			return webo_mcp_mutation_response( array(
				'dry_run'       => true,
				'would_change'  => count( $changed ) > 0,
				'planned_count' => count( $changed ),
				'diff'          => $diff,
				'context'       => array( 'action' => 'configure-sitemap-profile', 'changed_fields' => $changed ),
			) );
		}

		$snapshot = WeboMcpRankMath_SnapshotService::create( 'configure-sitemap-profile', array( 'rank-math-options-sitemap' ) );
		WeboMcpRankMath_OptionsRepository::patch_group( 'sitemap', $patch );
		webo_rank_math_flush_sitemap_cache();

		return webo_mcp_mutation_response( array(
			'dry_run'       => false,
			'changed'       => count( $changed ) > 0,
			'changed_count' => count( $changed ),
			'diff'          => $diff,
			'context'       => array(
				'action'         => 'configure-sitemap-profile',
				'changed_fields' => $changed,
				'snapshot_id'    => $snapshot['snapshot_id'],
			),
		) );
	} );
}

/**
 * Handler: audit-brand-seo
 * Returns the current state of all brand-related Rank Math settings without mutating anything.
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_audit_brand_seo( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () {
		$general = WeboMcpRankMath_OptionsRepository::get_group( 'general' );
		$titles  = WeboMcpRankMath_OptionsRepository::get_group( 'titles' );
		$social  = WeboMcpRankMath_OptionsRepository::get_group( 'social' );

		$brand_keys_general = array(
			'knowledgegraph_type', 'knowledgegraph_name', 'knowledgegraph_url',
			'knowledgegraph_description', 'knowledgegraph_logo',
			'social_url_facebook', 'social_url_twitter', 'social_url_instagram',
			'social_url_linkedin', 'social_url_youtube', 'social_url_pinterest',
			'social_networks',
		);
		$brand_keys_titles = array(
			'website_name', 'website_alternate_name',
			'homepage_title', 'homepage_description',
			'breadcrumbs_home_label', 'breadcrumbs_home_link',
			'twitter_card_type',
		);
		$brand_keys_social = array(
			'facebook_link', 'twitter_link',
			'facebook_author_urls', 'twitter_author_names',
			'social_urls',
		);

		$report_general = array_filter( array_intersect_key( $general, array_flip( $brand_keys_general ) ) );
		$report_titles  = array_filter( array_intersect_key( $titles, array_flip( $brand_keys_titles ) ) );
		$report_social  = array_filter( array_intersect_key( $social, array_flip( $brand_keys_social ) ) );

		$issues = array();
		if ( empty( $general['knowledgegraph_name'] ) ) {
			$issues[] = 'Knowledge Graph name is not set.';
		}
		if ( empty( $general['knowledgegraph_type'] ) ) {
			$issues[] = 'Knowledge Graph type is not set.';
		}
		if ( empty( $titles['website_name'] ) ) {
			$issues[] = 'Website name is not set.';
		}
		if ( empty( $titles['homepage_title'] ) ) {
			$issues[] = 'Homepage title is not set.';
		}
		if ( empty( $titles['homepage_description'] ) ) {
			$issues[] = 'Homepage description is not set.';
		}
		if ( empty( $general['social_url_facebook'] ) && empty( $social['facebook_link'] ) ) {
			$issues[] = 'Facebook social profile is not configured.';
		}

		return array(
			'action'             => 'audit-brand-seo',
			'general_brand_keys' => $report_general,
			'titles_brand_keys'  => $report_titles,
			'social_brand_keys'  => $report_social,
			'issues'             => $issues,
			'issue_count'        => count( $issues ),
			'health'             => count( $issues ) === 0 ? 'ok' : ( count( $issues ) <= 2 ? 'warning' : 'error' ),
		);
	} );
}

/**
 * Handler: fix-brand-seo
 * Auto-fills empty brand fields from provided input, leaving existing values untouched.
 *
 * @param array<string,mixed> $input  Same shape as apply-brand-profile but only fills missing keys.
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_action_fix_brand_seo( $input ) {
	return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
		$dry_run = isset( $input['dry_run'] ) ? webo_mcp_is_truthy( $input['dry_run'] ) : true;

		// Get the full brand patch.
		$full_patch = WeboMcpRankMath_BrandProfileMapper::map( $input );

		// Read current state.
		$groups_needed = array_keys( $full_patch );
		$current       = array();
		foreach ( $groups_needed as $group ) {
			$current[ $group ] = WeboMcpRankMath_OptionsRepository::get_group( $group );
		}

		// Filter patch: only include keys that are currently empty/null.
		$fix_patch = array();
		foreach ( $full_patch as $group => $keys ) {
			foreach ( $keys as $key => $val ) {
				$existing = $current[ $group ][ $key ] ?? '';
				if ( '' === $existing || null === $existing ) {
					$fix_patch[ $group ][ $key ] = $val;
				}
			}
		}

		if ( empty( $fix_patch ) ) {
			return webo_mcp_mutation_response( array(
				'dry_run'       => $dry_run,
				'would_change'  => false,
				'planned_count' => 0,
				'diff'          => array(),
				'context'       => array(
					'action'   => 'fix-brand-seo',
					'warnings' => array( 'All brand fields are already populated. Nothing to fix.' ),
				),
			) );
		}

		$diff    = WeboMcpRankMath_BrandProfileMapper::build_diff( $fix_patch, $current );
		$changed = WeboMcpRankMath_BrandProfileMapper::changed_fields( $diff );

		if ( $dry_run ) {
			return webo_mcp_mutation_response( array(
				'dry_run'       => true,
				'would_change'  => count( $changed ) > 0,
				'planned_count' => count( $changed ),
				'diff'          => $diff,
				'context'       => array( 'action' => 'fix-brand-seo', 'changed_fields' => $changed ),
			) );
		}

		$option_names = array();
		foreach ( array_keys( $fix_patch ) as $group ) {
			$map = WeboMcpRankMath_OptionsRepository::option_group_map();
			if ( isset( $map[ $group ] ) ) {
				$option_names[] = $map[ $group ];
			}
		}
		$snapshot = WeboMcpRankMath_SnapshotService::create( 'fix-brand-seo', $option_names );

		foreach ( $fix_patch as $group => $keys ) {
			WeboMcpRankMath_OptionsRepository::patch_group( $group, $keys );
		}
		webo_rank_math_flush_sitemap_cache();

		return webo_mcp_mutation_response( array(
			'dry_run'       => false,
			'changed'       => count( $changed ) > 0,
			'changed_count' => count( $changed ),
			'diff'          => $diff,
			'context'       => array(
				'action'         => 'fix-brand-seo',
				'changed_fields' => $changed,
				'snapshot_id'    => $snapshot['snapshot_id'],
			),
		) );
	} );
}

// ============================================================================
// Unified dispatcher function  (webo_rank_math_semantic_action)
// ============================================================================

/**
 * Dispatch semantic actions via the 'webo-rank-math/semantic-action' MCP tool.
 *
 * @param array<string,mixed> $input  Tool input; 'action' key selects the handler.
 * @return array<string,mixed>|\WP_Error
 */
function webo_rank_math_semantic_action( $input ) {
	$action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';

	if ( '' === $action ) {
		return new WP_Error( 'webo_mcp_missing_action', 'action is required for semantic-action.' );
	}

	return match ( $action ) {
		'apply-brand-profile'        => webo_rank_math_action_apply_brand_profile( $input ),
		'complete-brand-profile',
		'entity-cleanup'             => webo_rank_math_action_complete_brand_profile( $input ),
		'migrate-brand'              => webo_rank_math_action_migrate_brand( $input ),
		'brand-cleanup'             => webo_rank_math_action_brand_cleanup( $input ),
		'configure-homepage'         => webo_rank_math_action_configure_homepage( $input ),
		'configure-social'           => webo_rank_math_action_configure_social( $input ),
		'configure-schema-defaults'  => webo_rank_math_action_configure_schema_defaults( $input ),
		'configure-sitemap-profile'  => webo_rank_math_action_configure_sitemap_profile( $input ),
		'audit-brand-seo'            => webo_rank_math_action_audit_brand_seo( $input ),
		'fix-brand-seo'              => webo_rank_math_action_fix_brand_seo( $input ),
		'optimize-settings',
		'fix-common-issues',
		'flush-rankmath-cache',
		'ai-optimize-low-ctr-posts',
		'generate-faq-schema',
		'rebuild-internal-links',
		'sync-gsc'                  => WeboMcpRankMath_ActionService::dispatch( $input ),
		default                      => new WP_Error(
			'webo_mcp_invalid_action',
			sprintf(
				'Unknown semantic action: %s. Available: apply-brand-profile, complete-brand-profile, entity-cleanup, migrate-brand, brand-cleanup, configure-homepage, configure-social, configure-schema-defaults, configure-sitemap-profile, audit-brand-seo, fix-brand-seo.',
				$action
			)
		),
	};
}

// ============================================================================
// Ability registration
// ============================================================================

add_action( 'wp_abilities_api_init', function () {
	wp_register_ability( 'webo-rank-math/semantic-action', array(
		'label'       => 'Rank Math Semantic Action',
		'description' => 'AI-first semantic and v2 action-level Rank Math actions. action: apply-brand-profile, complete-brand-profile, entity-cleanup, migrate-brand, brand-cleanup, configure-homepage, configure-social, configure-schema-defaults, configure-sitemap-profile, audit-brand-seo, fix-brand-seo, optimize-settings, fix-common-issues, flush-rankmath-cache, ai-optimize-low-ctr-posts, generate-faq-schema, rebuild-internal-links, sync-gsc.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'                 => 'object',
			'required'             => array( 'action' ),
			'additionalProperties' => true,
			'properties'           => array(
				'action' => array(
					'type'        => 'string',
					'description' => 'Semantic action name.',
					'enum'        => array(
						'apply-brand-profile',
						'complete-brand-profile',
						'entity-cleanup',
						'migrate-brand',
						'brand-cleanup',
						'configure-homepage',
						'configure-social',
						'configure-schema-defaults',
						'configure-sitemap-profile',
						'audit-brand-seo',
						'fix-brand-seo',
						'optimize-settings',
						'fix-common-issues',
						'flush-rankmath-cache',
						'ai-optimize-low-ctr-posts',
						'generate-faq-schema',
						'rebuild-internal-links',
						'sync-gsc',
					),
				),
				// apply-brand-profile inputs
				'profile'              => array( 'type' => 'string', 'enum' => array( 'personal', 'organization', 'company' ) ),
				'brand_name'           => array( 'type' => 'string' ),
				'person_name'          => array( 'type' => 'string' ),
				'alternate_name'       => array( 'type' => 'string' ),
				'url'                  => array( 'type' => 'string', 'format' => 'uri' ),
				'description'          => array( 'type' => 'string' ),
				'logo'                 => array( 'type' => 'string', 'format' => 'uri' ),
				'homepage_title'       => array( 'type' => 'string' ),
				'homepage_description' => array( 'type' => 'string' ),
				// social links
				'facebook'             => array( 'type' => 'string', 'format' => 'uri' ),
				'twitter'              => array( 'type' => 'string', 'format' => 'uri' ),
				'instagram'            => array( 'type' => 'string', 'format' => 'uri' ),
				'linkedin'             => array( 'type' => 'string', 'format' => 'uri' ),
				'youtube'              => array( 'type' => 'string', 'format' => 'uri' ),
				'github'               => array( 'type' => 'string', 'format' => 'uri' ),
				'pinterest'            => array( 'type' => 'string', 'format' => 'uri' ),
				// migrate-brand / brand-cleanup inputs
				'from'                 => array( 'type' => 'string' ),
				'to'                   => array( 'type' => 'string' ),
				'old_brand'            => array( 'type' => 'string' ),
				'old_url'              => array( 'type' => 'string', 'format' => 'uri' ),
				'old_logo'             => array( 'type' => 'string', 'format' => 'uri' ),
				'old_open_graph_image' => array( 'type' => 'string', 'format' => 'uri' ),
				'old_publisher_name'   => array( 'type' => 'string' ),
				'old_publisher_logo'   => array( 'type' => 'string', 'format' => 'uri' ),
				'old_email_report_logo' => array( 'type' => 'string', 'format' => 'uri' ),
				'old_email'            => array( 'type' => 'string' ),
				'old_contact_email'    => array( 'type' => 'string' ),
				'old_person_name'      => array( 'type' => 'string' ),
				'old_organization_name' => array( 'type' => 'string' ),
				'old_organization'     => array( 'type' => 'object', 'additionalProperties' => true ),
				'old_person'           => array( 'type' => 'object', 'additionalProperties' => true ),
				'old_publisher'        => array( 'type' => 'object', 'additionalProperties' => true ),
				'old_contact'          => array( 'type' => 'object', 'additionalProperties' => true ),
				'old_social_profiles'  => array( 'type' => 'object', 'additionalProperties' => true ),
				'old_same_as'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'replacements'         => array( 'type' => 'object', 'additionalProperties' => true ),
				'old_values'           => array( 'type' => 'object', 'additionalProperties' => true ),
				'brand_cleanup'        => array( 'type' => 'object', 'additionalProperties' => true ),
				// configure-homepage
				'title'                => array( 'type' => 'string' ),
				// configure-schema-defaults
				'post_types'           => array( 'type' => 'object', 'additionalProperties' => true ),
				// configure-sitemap-profile
				'include_post_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'exclude_post_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'include_taxonomies'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'exclude_taxonomies'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				// shared
				'dry_run'              => array( 'type' => 'boolean', 'default' => true ),
				'dryRun'               => array( 'type' => 'boolean', 'default' => true ),
				'force'                => array( 'type' => 'boolean', 'default' => false ),
				'brandName'            => array( 'type' => 'string' ),
				'alternateName'        => array( 'type' => 'string' ),
				'openGraphImage'       => array( 'type' => 'string', 'format' => 'uri' ),
				'sameAs'               => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'format' => 'uri' ) ),
				'nofollowExternalLinks'=> array( 'type' => 'boolean' ),
				'nofollowImageLinks'   => array( 'type' => 'boolean' ),
				'limit'                => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
				'postIds'              => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'questionsPerPost'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10 ),
				'mode'                 => array( 'type' => 'string', 'enum' => array( 'suggest', 'insert' ) ),
				'site_id'              => array( 'type' => 'integer', 'minimum' => 1 ),
			),
		),
		'execute_callback' => 'webo_rank_math_semantic_action',
		'permission_callback' => static function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 )
				: current_user_can( 'manage_options' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			'webo_mcp'     => array( 'scope' => 'admin', 'risk' => 'high' ),
		),
	) );
}, 20 );
