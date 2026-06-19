<?php
/**
 * WEBO MCP - Rank Math post SEO meta abilities.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$schema_site_id = array( 'site_id' => array( 'type' => 'integer', 'minimum' => 1 ) );
$schema_post_id_slug = array(
	'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
	'slug'      => array( 'type' => 'string' ),
	'post_type' => array( 'type' => 'string', 'default' => 'post' ),
);

add_action( 'wp_abilities_api_init', function () use ( $schema_site_id, $schema_post_id_slug ) {
	wp_register_ability( 'webo-rank-math/get-post-seo-meta', array(
		'label'       => 'Get Post SEO Meta (Rank Math)',
		'description' => 'Get Rank Math SEO metadata for a post by ID or slug.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( $schema_post_id_slug, array(
				'keys' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Optional meta keys.' ),
			), $schema_site_id ),
			'oneOf' => array(
				array( 'required' => array( 'post_id' ) ),
				array( 'required' => array( 'slug' ) ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$post_id = webo_rank_math_resolve_post_id( $input );
				if ( ! $post_id ) {
					return new WP_Error( 'post_not_found', 'Post not found by post_id/slug.', array( 'status' => 404 ) );
				}
				$post = get_post( $post_id );
				if ( ! $post ) {
					return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
				}
				return array(
					'post_id'   => (int) $post->ID,
					'post_type' => $post->post_type,
					'slug'      => $post->post_name,
					'seo_meta'  => webo_rank_math_collect_post_meta( $post_id, $input['keys'] ?? null ),
				);
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/update-post-seo-meta', array(
		'label'       => 'Update Post SEO Meta (Rank Math)',
		'description' => 'Update Rank Math SEO metadata for one post. Set value to null to delete key.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'seo_meta' ),
			'properties' => array_merge( $schema_post_id_slug, array(
				'seo_meta' => array( 'type' => 'object', 'additionalProperties' => true ),
				'dry_run'  => array( 'type' => 'boolean', 'default' => true ),
			), $schema_site_id ),
			'oneOf' => array(
				array( 'required' => array( 'post_id' ) ),
				array( 'required' => array( 'slug' ) ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$post_id = webo_rank_math_resolve_post_id( $input );
				if ( ! $post_id ) {
					return new WP_Error( 'post_not_found', 'Post not found by post_id/slug.', array( 'status' => 404 ) );
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return new WP_Error( 'forbidden', 'Permission denied for this post.', array( 'status' => 403 ) );
				}
				$dry_run = webo_rank_math_resolve_dry_run( $input, false );
				$result  = webo_rank_math_update_post_meta_map( $post_id, $input['seo_meta'], $dry_run );
				$post = get_post( $post_id );
				return array_merge( $result, array(
					'updated'  => ! $dry_run && ! empty( $result['changed'] ),
					'post_id'  => $post_id,
					'slug'     => $post ? $post->post_name : null,
					'seo_meta' => $dry_run ? null : webo_rank_math_collect_post_meta( $post_id ),
				) );
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/bulk-upsert-post-seo-meta', array(
		'label'       => 'Bulk Upsert Post SEO Meta (Rank Math)',
		'description' => 'Bulk update post SEO metadata by post_id or slug per item.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'items' ),
			'properties' => array_merge( $schema_site_id, array(
				'items' => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => 200,
					'items'    => array(
						'type'       => 'object',
						'required'   => array( 'seo_meta' ),
						'properties' => array_merge( $schema_post_id_slug, array(
							'seo_meta' => array( 'type' => 'object', 'additionalProperties' => true ),
						) ),
						'oneOf' => array(
							array( 'required' => array( 'post_id' ) ),
							array( 'required' => array( 'slug' ) ),
						),
						'additionalProperties' => false,
					),
				),
				'stop_on_error' => array( 'type' => 'boolean', 'default' => false ),
				'dry_run'       => array( 'type' => 'boolean', 'default' => true ),
			) ),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$stop_on_error = ! empty( $input['stop_on_error'] );
				$results       = array();
				$success_count = 0;
				$failure_count = 0;
				$stopped_early = false;

				foreach ( (array) $input['items'] as $index => $item ) {
					$post_id = webo_rank_math_resolve_post_id( $item );
					if ( ! $post_id ) {
						$failure_count++;
						$results[] = array( 'index' => $index, 'success' => false, 'error_code' => 'post_not_found', 'error_message' => 'Post not found by post_id/slug.' );
						if ( $stop_on_error ) {
							$stopped_early = true;
							break;
						}
						continue;
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						$failure_count++;
						$results[] = array( 'index' => $index, 'post_id' => $post_id, 'success' => false, 'error_code' => 'forbidden', 'error_message' => 'Permission denied.' );
						if ( $stop_on_error ) {
							$stopped_early = true;
							break;
						}
						continue;
					}
					$dry_run = webo_rank_math_resolve_dry_run( $input, false );
					$result = webo_rank_math_update_post_meta_map( $post_id, $item['seo_meta'], $dry_run );
					$post = get_post( $post_id );
					$success_count++;
					$results[] = array_merge( array( 'index' => $index, 'success' => true, 'post_id' => $post_id, 'slug' => $post ? $post->post_name : null ), $result );
				}

				return array(
					'success_count'  => $success_count,
					'failure_count'  => $failure_count,
					'stopped_early'  => $stopped_early,
					'results'        => $results,
				);
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/cleanup-post-schema-meta', array(
		'label'       => 'Cleanup Post Schema Meta (Rank Math)',
		'description' => 'Delete malformed dynamic Rank Math schema postmeta keys matching rank_math_schema_*. Use delete_all to reset all custom schema for a post.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( $schema_post_id_slug, array(
				'delete_all' => array( 'type' => 'boolean', 'default' => false ),
				'dry_run'    => array( 'type' => 'boolean', 'default' => true ),
			), $schema_site_id ),
			'oneOf' => array(
				array( 'required' => array( 'post_id' ) ),
				array( 'required' => array( 'slug' ) ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				$post_id = webo_rank_math_resolve_post_id( $input );
				if ( ! $post_id ) {
					return new WP_Error( 'post_not_found', 'Post not found by post_id/slug.', array( 'status' => 404 ) );
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return new WP_Error( 'forbidden', 'Permission denied for this post.', array( 'status' => 403 ) );
				}

				$post    = get_post( $post_id );
				$dry_run = webo_rank_math_resolve_dry_run( $input, ! empty( $input['delete_all'] ) );
				$cleanup = webo_rank_math_cleanup_post_schema_meta( $post_id, ! empty( $input['delete_all'] ), $dry_run );

				return array_merge(
					array(
						'updated'   => ! $dry_run && ! empty( $cleanup['deleted_count'] ),
						'post_id'   => $post_id,
						'post_type' => $post ? $post->post_type : null,
						'slug'      => $post ? $post->post_name : null,
					),
					$cleanup
				);
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/audit-schema-meta', array(
		'label'       => 'Audit Schema Meta (Rank Math)',
		'description' => 'Audit dynamic Rank Math schema postmeta keys matching rank_math_schema_* across posts, pages, and CPTs. Reports malformed string or array-without-@type values without deleting them.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'post_types' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Optional post types. Defaults to public post types except attachment.',
				),
				'statuses'   => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Optional post statuses. Defaults to publish.',
				),
				'limit'      => array( 'type' => 'integer', 'default' => 500, 'minimum' => 1, 'maximum' => 2000 ),
				'offset'     => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0 ),
			), $schema_site_id ),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) {
			return webo_rank_math_with_site( $input['site_id'] ?? 0, function () use ( $input ) {
				return webo_rank_math_audit_schema_meta( $input );
			} );
		},
		'permission_callback' => function ( $input ) {
			return function_exists( 'webo_mcp_multisite_current_user_can_for_site' )
				? webo_mcp_multisite_current_user_can_for_site( 'edit_posts', $input['site_id'] ?? 0 )
				: current_user_can( 'edit_posts' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );
} );
