<?php
/**
 * WEBO MCP - Rank Math Redirections Abilities
 *
 * List, get, create, update, delete redirections via Rank Math Redirections module (free).
 * Uses RankMath\Redirections\DB when available.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if Rank Math Redirections DB class is available.
 *
 * @return bool
 */
function webo_rank_math_redirections_db_available() {
	return class_exists( 'RankMath\Redirections\DB' );
}

/**
 * Check if Rank Math Redirection model is available.
 *
 * @return bool
 */
function webo_rank_math_redirection_model_available() {
	return class_exists( 'RankMath\Redirections\Redirection' );
}

/**
 * Get the Rank Math redirections table name.
 *
 * @return string
 */
function webo_rank_math_redirections_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'rank_math_redirections';
}

/**
 * Ensure the Rank Math redirections tables exist for the current site.
 *
 * @return bool
 */
function webo_rank_math_redirections_ensure_tables() {
	global $wpdb;

	$table = webo_rank_math_redirections_table_name();
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $found === $table ) {
		return true;
	}

	if ( class_exists( 'RankMath\Installer' ) && method_exists( 'RankMath\Installer', 'create_tables' ) ) {
		\RankMath\Installer::create_tables( array( 'redirections' ) );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	return $found === $table;
}

/**
 * Read redirections directly from the Rank Math table.
 *
 * @param array $args Query arguments.
 * @return array
 */
function webo_rank_math_redirections_direct_list( $args ) {
	global $wpdb;

	if ( ! webo_rank_math_redirections_ensure_tables() ) {
		return array(
			'count'        => 0,
			'redirections' => array(),
		);
	}

	$table  = webo_rank_math_redirections_table_name();
	$limit  = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 50;
	$paged  = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
	$offset = ( $paged - 1 ) * $limit;
	$status = isset( $args['status'] ) ? (string) $args['status'] : 'any';
	$search = isset( $args['search'] ) ? (string) $args['search'] : '';
	$where  = array();
	$params = array();

	if ( in_array( $status, array( 'active', 'inactive', 'trashed' ), true ) ) {
		$where[]  = 'status = %s';
		$params[] = $status;
	} else {
		$where[] = "status != 'trashed'";
	}

	if ( '' !== $search ) {
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$where[]  = '(sources LIKE %s OR url_to LIKE %s)';
		$params[] = $like;
		$params[] = $like;
	}

	$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
	$count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
	$list_sql  = "SELECT * FROM {$table} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";

	$count = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );
	$rows_params = array_merge( $params, array( $limit, $offset ) );
	$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $rows_params ), ARRAY_A );

	foreach ( $rows as &$row ) {
		if ( isset( $row['sources'] ) && is_string( $row['sources'] ) ) {
			$row['sources'] = maybe_unserialize( $row['sources'] );
		}
	}
	unset( $row );

	return array(
		'count'        => $count,
		'redirections' => $rows,
	);
}

/**
 * Insert a Rank Math redirection directly when the Rank Math model/DB wrappers fail.
 *
 * @param array $args Redirection data.
 * @return int|false
 */
function webo_rank_math_redirections_direct_insert( $args ) {
	global $wpdb;

	if ( ! webo_rank_math_redirections_ensure_tables() ) {
		return false;
	}

	$table = webo_rank_math_redirections_table_name();
	$now   = current_time( 'mysql' );
	$data  = array(
		'sources'     => maybe_serialize( isset( $args['sources'] ) ? $args['sources'] : array() ),
		'url_to'      => isset( $args['url_to'] ) ? (string) $args['url_to'] : '',
		'header_code' => isset( $args['header_code'] ) ? (int) $args['header_code'] : 301,
		'hits'        => 0,
		'status'      => isset( $args['status'] ) ? (string) $args['status'] : 'active',
		'created'     => $now,
		'updated'     => $now,
	);

	$inserted = $wpdb->insert( $table, $data, array( '%s', '%s', '%d', '%d', '%s', '%s', '%s' ) );

	return $inserted ? (int) $wpdb->insert_id : false;
}

/**
 * Normalize source format for DB: array of { pattern, comparison [, ignore] }.
 *
 * @param string|array $source Source URL or array of sources.
 * @param string       $comparison Comparison: exact, contains, start, end, regex.
 * @param string       $ignore     Optional 'case' for case-insensitive.
 * @return array
 */
function webo_rank_math_redirection_sources( $source, $comparison = 'exact', $ignore = '' ) {
	if ( is_array( $source ) && isset( $source[0]['pattern'] ) ) {
		return $source;
	}
	$pattern = is_string( $source ) ? $source : ( isset( $source['pattern'] ) ? $source['pattern'] : '' );
	if ( '' === $pattern ) {
		return array();
	}
	$one = array(
		'pattern'     => $pattern,
		'comparison'  => in_array( $comparison, array( 'exact', 'contains', 'start', 'end', 'regex' ), true ) ? $comparison : 'exact',
	);
	if ( 'case' === $ignore ) {
		$one['ignore'] = 'case';
	}
	return array( $one );
}

add_action( 'wp_abilities_api_init', function () {
	if ( ! webo_rank_math_redirections_db_available() ) {
		return;
	}

	$db = 'RankMath\Redirections\DB';

	wp_register_ability( 'webo-rank-math/list-redirections', array(
		'label'       => 'List Rank Math Redirections',
		'description' => 'List redirections with optional paging and status filter. Requires Redirections module.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'limit'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 500,
					'default'     => 50,
					'description' => 'Items per page.',
				),
				'paged'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'default'     => 1,
					'description' => 'Page number.',
				),
				'status' => array(
					'type'        => 'string',
					'enum'        => array( 'any', 'active', 'inactive', 'trashed' ),
					'default'     => 'any',
					'description' => 'Filter by status.',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search in sources or destination.',
				),
				'site_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) use ( $db ) {
			$context = function_exists( 'webo_mcp_multisite_switch_to_site' )
				? webo_mcp_multisite_switch_to_site( $input['site_id'] ?? 0 )
				: array( 'switched' => false );

			if ( is_wp_error( $context ) ) {
				return $context;
			}

			try {
				webo_rank_math_redirections_ensure_tables();

				$args = array(
					'limit'  => isset( $input['limit'] ) ? (int) $input['limit'] : 50,
					'paged'  => isset( $input['paged'] ) ? (int) $input['paged'] : 1,
					'status' => isset( $input['status'] ) ? $input['status'] : 'any',
					'search' => isset( $input['search'] ) ? $input['search'] : '',
				);
				$out = $db::get_redirections( $args );
				$redirections = isset( $out['redirections'] ) ? $out['redirections'] : array();
				foreach ( $redirections as &$r ) {
					if ( isset( $r['sources'] ) && is_string( $r['sources'] ) ) {
						$r['sources'] = maybe_unserialize( $r['sources'] );
					}
				}
				unset( $r );

				if ( empty( $redirections ) && ! empty( $out['count'] ) ) {
					return webo_rank_math_redirections_direct_list( $args );
				}

				return array(
					'count'         => isset( $out['count'] ) ? (int) $out['count'] : count( $redirections ),
					'redirections'  => $redirections,
				);
			} finally {
				if ( ! empty( $context['switched'] ) ) {
					restore_current_blog();
				}
			}
		},
		'permission_callback' => function ( $input ) {
			if ( function_exists( 'webo_mcp_multisite_current_user_can_for_site' ) ) {
				return webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 );
			}
			return current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/get-redirection', array(
		'label'       => 'Get Rank Math Redirection by ID',
		'description' => 'Get a single redirection by ID.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'      => array( 'type' => 'integer', 'minimum' => 1 ),
				'site_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) use ( $db ) {
			$context = function_exists( 'webo_mcp_multisite_switch_to_site' )
				? webo_mcp_multisite_switch_to_site( $input['site_id'] ?? 0 )
				: array( 'switched' => false );

			if ( is_wp_error( $context ) ) {
				return $context;
			}

			try {
				$id = absint( $input['id'] );
				$r  = $db::get_redirection_by_id( $id, 'all' );
				if ( ! $r || empty( $r['id'] ) ) {
					return new WP_Error( 'redirection_not_found', 'Redirection not found.', array( 'status' => 404 ) );
				}
				if ( isset( $r['sources'] ) && is_string( $r['sources'] ) ) {
					$r['sources'] = maybe_unserialize( $r['sources'] );
				}
				return $r;
			} finally {
				if ( ! empty( $context['switched'] ) ) {
					restore_current_blog();
				}
			}
		},
		'permission_callback' => function ( $input ) {
			if ( function_exists( 'webo_mcp_multisite_current_user_can_for_site' ) ) {
				return webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 );
			}
			return current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/create-redirection', array(
		'label'       => 'Create Rank Math Redirection',
		'description' => 'Add a new redirection. Source and destination required; type 301/302/307/410/451.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'source', 'destination' ),
			'properties' => array(
				'source'      => array(
					'type'        => 'string',
					'description' => 'Source URL or pattern to redirect from.',
				),
				'destination' => array(
					'type'        => 'string',
					'description' => 'Destination URL (empty for 410/451).',
				),
				'type'        => array(
					'type'        => 'string',
					'enum'        => array( '301', '302', '307', '410', '451' ),
					'default'     => '301',
					'description' => 'HTTP redirect type.',
				),
				'status'      => array(
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive' ),
					'default'     => 'active',
				),
				'comparison'  => array(
					'type'        => 'string',
					'enum'        => array( 'exact', 'contains', 'start', 'end', 'regex' ),
					'default'     => 'exact',
				),
				'ignore_case' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Case-insensitive matching for exact.',
				),
				'site_id'     => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) use ( $db ) {
			$context = function_exists( 'webo_mcp_multisite_switch_to_site' )
				? webo_mcp_multisite_switch_to_site( $input['site_id'] ?? 0 )
				: array( 'switched' => false );

			if ( is_wp_error( $context ) ) {
				return $context;
			}

			try {
				webo_rank_math_redirections_ensure_tables();

				$header_code = isset( $input['type'] ) ? (string) $input['type'] : '301';
				if ( in_array( $header_code, array( '410', '451' ), true ) ) {
					$destination = '';
				} else {
					$destination = isset( $input['destination'] ) ? $input['destination'] : '';
				}
				$sources = webo_rank_math_redirection_sources(
					$input['source'],
					isset( $input['comparison'] ) ? $input['comparison'] : 'exact',
					! empty( $input['ignore_case'] ) ? 'case' : ''
				);
				if ( empty( $sources ) ) {
					return new WP_Error( 'invalid_source', 'Source is required.', array( 'status' => 400 ) );
				}

				$args = array(
					'sources'     => $sources,
					'url_to'      => $destination,
					'header_code' => $header_code,
					'status'     => isset( $input['status'] ) ? $input['status'] : 'active',
				);

				if ( webo_rank_math_redirection_model_available() ) {
					$model = \RankMath\Redirections\Redirection::from( $args );
					$id    = $model->save();
				} else {
					$id = $db::add( $args );
				}

				if ( ! $id ) {
					$id = webo_rank_math_redirections_direct_insert( $args );
				}

				if ( ! $id ) {
					global $wpdb;
					return new WP_Error(
						'create_failed',
						'Failed to create redirection.',
						array(
							'status'     => 500,
							'last_error' => isset( $wpdb->last_error ) ? $wpdb->last_error : '',
						)
					);
				}

				$r = $db::get_redirection_by_id( $id, 'all' );
				if ( $r && isset( $r['sources'] ) && is_string( $r['sources'] ) ) {
					$r['sources'] = maybe_unserialize( $r['sources'] );
				}
				return array(
					'created'      => true,
					'id'           => (int) $id,
					'redirection'  => $r ?: array( 'id' => $id ),
				);
			} finally {
				if ( ! empty( $context['switched'] ) ) {
					restore_current_blog();
				}
			}
		},
		'permission_callback' => function ( $input ) {
			if ( function_exists( 'webo_mcp_multisite_current_user_can_for_site' ) ) {
				return webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 );
			}
			return current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/update-redirection', array(
		'label'       => 'Update Rank Math Redirection',
		'description' => 'Update an existing redirection by ID.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'minimum' => 1 ),
				'source'      => array( 'type' => 'string' ),
				'destination' => array( 'type' => 'string' ),
				'type'        => array(
					'type'    => 'string',
					'enum'    => array( '301', '302', '307', '410', '451' ),
				),
				'status'      => array(
					'type' => 'string',
					'enum' => array( 'active', 'inactive' ),
				),
				'comparison'  => array(
					'type'    => 'string',
					'enum'    => array( 'exact', 'contains', 'start', 'end', 'regex' ),
				),
				'ignore_case' => array( 'type' => 'boolean' ),
				'site_id'     => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) use ( $db ) {
			$context = function_exists( 'webo_mcp_multisite_switch_to_site' )
				? webo_mcp_multisite_switch_to_site( $input['site_id'] ?? 0 )
				: array( 'switched' => false );

			if ( is_wp_error( $context ) ) {
				return $context;
			}

			try {
				$id = absint( $input['id'] );
				$existing = $db::get_redirection_by_id( $id, 'all' );
				if ( ! $existing || empty( $existing['id'] ) ) {
					return new WP_Error( 'redirection_not_found', 'Redirection not found.', array( 'status' => 404 ) );
				}

				$args = array( 'id' => $id );
				if ( array_key_exists( 'source', $input ) ) {
					$args['sources'] = webo_rank_math_redirection_sources(
						$input['source'],
						isset( $input['comparison'] ) ? $input['comparison'] : 'exact',
						! empty( $input['ignore_case'] ) ? 'case' : ''
					);
				} else {
					$args['sources'] = isset( $existing['sources'] ) ? ( is_string( $existing['sources'] ) ? maybe_unserialize( $existing['sources'] ) : $existing['sources'] ) : array();
				}
				if ( array_key_exists( 'destination', $input ) ) {
					$args['url_to'] = $input['destination'];
				} else {
					$args['url_to'] = isset( $existing['url_to'] ) ? $existing['url_to'] : '';
				}
				if ( array_key_exists( 'type', $input ) ) {
					$args['header_code'] = $input['type'];
					if ( in_array( $args['header_code'], array( '410', '451' ), true ) ) {
						$args['url_to'] = '';
					}
				} else {
					$args['header_code'] = isset( $existing['header_code'] ) ? $existing['header_code'] : '301';
				}
				if ( array_key_exists( 'status', $input ) ) {
					$args['status'] = $input['status'];
				} else {
					$args['status'] = isset( $existing['status'] ) ? $existing['status'] : 'active';
				}

				$updated = $db::update( $args );
				if ( false === $updated ) {
					return new WP_Error( 'update_failed', 'Failed to update redirection.', array( 'status' => 500 ) );
				}

				$r = $db::get_redirection_by_id( $id, 'all' );
				if ( $r && isset( $r['sources'] ) && is_string( $r['sources'] ) ) {
					$r['sources'] = maybe_unserialize( $r['sources'] );
				}
				return array(
					'updated'      => true,
					'id'           => $id,
					'redirection'  => $r,
				);
			} finally {
				if ( ! empty( $context['switched'] ) ) {
					restore_current_blog();
				}
			}
		},
		'permission_callback' => function ( $input ) {
			if ( function_exists( 'webo_mcp_multisite_current_user_can_for_site' ) ) {
				return webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 );
			}
			return current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );

	wp_register_ability( 'webo-rank-math/delete-redirection', array(
		'label'       => 'Delete Rank Math Redirection',
		'description' => 'Delete one or more redirections by ID.',
		'category'    => 'webo-rank-math',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'      => array(
					'description' => 'Redirection ID (integer) or array of IDs to delete.',
					'oneOf'       => array(
						array( 'type' => 'integer', 'minimum' => 1 ),
						array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'minItems' => 1 ),
					),
				),
				'site_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback' => function ( $input ) use ( $db ) {
			$context = function_exists( 'webo_mcp_multisite_switch_to_site' )
				? webo_mcp_multisite_switch_to_site( $input['site_id'] ?? 0 )
				: array( 'switched' => false );

			if ( is_wp_error( $context ) ) {
				return $context;
			}

			try {
				$id = $input['id'];
				$ids = is_array( $id ) ? array_map( 'absint', $id ) : array( absint( $id ) );
				$ids = array_filter( $ids );
				if ( empty( $ids ) ) {
					return new WP_Error( 'invalid_id', 'At least one ID is required.', array( 'status' => 400 ) );
				}

				$deleted = $db::delete( $ids );
				return array(
					'deleted' => (int) $deleted,
					'ids'     => $ids,
				);
			} finally {
				if ( ! empty( $context['switched'] ) ) {
					restore_current_blog();
				}
			}
		},
		'permission_callback' => function ( $input ) {
			if ( function_exists( 'webo_mcp_multisite_current_user_can_for_site' ) ) {
				return webo_mcp_multisite_current_user_can_for_site( 'manage_options', $input['site_id'] ?? 0 );
			}
			return current_user_can( 'manage_options' );
		},
		'meta' => array( 'show_in_rest' => true ),
	) );
} );
