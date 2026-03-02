<?php
/**
 * WEBO MCP - Rank Math Management Abilities
 */

if (!defined('ABSPATH')) {
    exit;
}

function webo_rank_math_meta_keys() {
    return [
        'rank_math_title',
        'rank_math_description',
        'rank_math_focus_keyword',
        'rank_math_canonical_url',
        'rank_math_robots',
        'rank_math_facebook_title',
        'rank_math_facebook_description',
        'rank_math_facebook_image',
        'rank_math_twitter_title',
        'rank_math_twitter_description',
        'rank_math_twitter_image',
        'rank_math_twitter_card_type',
        'rank_math_schema_type',
        'rank_math_pillar_content',
        'rank_math_seo_score',
    ];
}

function webo_rank_math_default_option_names() {
    return [
        'rank_math_modules',
        'rank_math_options_general',
        'rank_math_options_titles',
        'rank_math_options_sitemap',
        'rank_math_options_instant_indexing',
        'rank_math_google_analytic_options',
        'rank_math_google_analytic_profile',
    ];
}

function webo_rank_math_resolve_post_id($input) {
    if (!empty($input['post_id'])) {
        $post_id = absint($input['post_id']);
        return $post_id > 0 ? $post_id : null;
    }

    if (!empty($input['slug'])) {
        $post_type = !empty($input['post_type']) ? $input['post_type'] : 'post';
        $query = new WP_Query([
            'name' => sanitize_title($input['slug']),
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        if (!empty($query->posts)) {
            return (int) $query->posts[0];
        }
    }

    return null;
}

function webo_rank_math_collect_post_meta($post_id, $keys = null) {
    $keys = is_array($keys) && !empty($keys) ? $keys : webo_rank_math_meta_keys();
    $result = [];

    foreach ($keys as $key) {
        $value = get_post_meta($post_id, $key, true);
        if ($value !== '' && $value !== null) {
            $result[$key] = maybe_unserialize($value);
        }
    }

    return $result;
}

function webo_rank_math_collect_term_meta($term_id, $keys = null) {
    $keys = is_array($keys) && !empty($keys) ? $keys : webo_rank_math_meta_keys();
    $result = [];

    foreach ($keys as $key) {
        $value = get_term_meta($term_id, $key, true);
        if ($value !== '' && $value !== null) {
            $result[$key] = maybe_unserialize($value);
        }
    }

    return $result;
}

function webo_rank_math_update_post_meta_map($post_id, $meta) {
    foreach ((array) $meta as $key => $value) {
        $meta_key = sanitize_key($key);
        if ($meta_key === '') {
            continue;
        }

        if ($value === null) {
            delete_post_meta($post_id, $meta_key);
        } else {
            update_post_meta($post_id, $meta_key, $value);
        }
    }
}

function webo_rank_math_update_term_meta_map($term_id, $meta) {
    foreach ((array) $meta as $key => $value) {
        $meta_key = sanitize_key($key);
        if ($meta_key === '') {
            continue;
        }

        if ($value === null) {
            delete_term_meta($term_id, $meta_key);
        } else {
            update_term_meta($term_id, $meta_key, $value);
        }
    }
}

add_action('wp_abilities_api_categories_init', function() {
    if (!wp_has_ability_category('webo-rank-math')) {
        wp_register_ability_category('webo-rank-math', [
            'label' => 'WEBO Rank Math',
            'description' => 'Full Rank Math MCP management: plugin status, modules, options, post SEO meta, term SEO meta, and bulk updates by ID/slug.'
        ]);
    }
});

add_action('wp_abilities_api_init', function() {
    wp_register_ability('webo-rank-math/get-plugin-status', [
        'label' => 'Get Rank Math Plugin Status',
        'description' => 'Get current status of Rank Math plugin and key options/modules.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Target site ID in multisite (optional).',
                ],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $active_plugins = (array) get_option('active_plugins', []);

                return [
                    'rank_math_active' => in_array('seo-by-rank-math/rank-math.php', $active_plugins, true) || defined('RANK_MATH_VERSION'),
                    'rank_math_version' => defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : null,
                    'rank_math_modules' => (array) get_option('rank_math_modules', []),
                    'options_available' => array_values(array_filter(webo_rank_math_default_option_names(), function($name) {
                        return get_option($name, null) !== null;
                    })),
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('manage_options', $input['site_id'] ?? 0);
            }
            return current_user_can('manage_options');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);

    wp_register_ability('webo-rank-math/get-post-seo-meta', [
        'label' => 'Get Post SEO Meta (Rank Math)',
        'description' => 'Get Rank Math SEO metadata for a post by ID or slug.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'slug' => [
                    'type' => 'string',
                ],
                'post_type' => [
                    'type' => 'string',
                    'default' => 'post',
                ],
                'keys' => [
                    'type' => 'array',
                    'items' => [ 'type' => 'string' ],
                    'description' => 'Optional list of meta keys to read. Default is core Rank Math keys.',
                ],
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'oneOf' => [
                ['required' => ['post_id']],
                ['required' => ['slug']],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $post_id = webo_rank_math_resolve_post_id($input);
                if (!$post_id) {
                    return new WP_Error('post_not_found', 'Post not found by post_id/slug.', ['status' => 404]);
                }

                $post = get_post($post_id);
                if (!$post) {
                    return new WP_Error('post_not_found', 'Post not found.', ['status' => 404]);
                }

                return [
                    'post_id' => (int) $post->ID,
                    'post_type' => $post->post_type,
                    'slug' => $post->post_name,
                    'seo_meta' => webo_rank_math_collect_post_meta($post_id, $input['keys'] ?? null),
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('edit_posts', $input['site_id'] ?? 0);
            }
            return current_user_can('edit_posts');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);

    wp_register_ability('webo-rank-math/update-post-seo-meta', [
        'label' => 'Update Post SEO Meta (Rank Math)',
        'description' => 'Update Rank Math SEO metadata for one post by ID or slug. Set value to null to delete specific key.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'required' => ['seo_meta'],
            'properties' => [
                'post_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'slug' => [
                    'type' => 'string',
                ],
                'post_type' => [
                    'type' => 'string',
                    'default' => 'post',
                ],
                'seo_meta' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'description' => 'Map of Rank Math meta key => value. Use null to remove key.',
                ],
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'oneOf' => [
                ['required' => ['post_id']],
                ['required' => ['slug']],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $post_id = webo_rank_math_resolve_post_id($input);
                if (!$post_id) {
                    return new WP_Error('post_not_found', 'Post not found by post_id/slug.', ['status' => 404]);
                }

                if (!current_user_can('edit_post', $post_id)) {
                    return new WP_Error('forbidden', 'Permission denied for this post.', ['status' => 403]);
                }

                webo_rank_math_update_post_meta_map($post_id, $input['seo_meta']);

                $post = get_post($post_id);
                return [
                    'updated' => true,
                    'post_id' => $post_id,
                    'slug' => $post ? $post->post_name : null,
                    'seo_meta' => webo_rank_math_collect_post_meta($post_id),
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('edit_posts', $input['site_id'] ?? 0);
            }
            return current_user_can('edit_posts');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);

    wp_register_ability('webo-rank-math/bulk-upsert-post-seo-meta', [
        'label' => 'Bulk Upsert Post SEO Meta (Rank Math)',
        'description' => 'Bulk update post SEO metadata by post_id or slug for each item.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'required' => ['items'],
            'properties' => [
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 200,
                    'items' => [
                        'type' => 'object',
                        'required' => ['seo_meta'],
                        'properties' => [
                            'post_id' => [
                                'type' => 'integer',
                                'minimum' => 1,
                            ],
                            'slug' => [
                                'type' => 'string',
                            ],
                            'post_type' => [
                                'type' => 'string',
                                'default' => 'post',
                            ],
                            'seo_meta' => [
                                'type' => 'object',
                                'additionalProperties' => true,
                            ],
                        ],
                        'oneOf' => [
                            ['required' => ['post_id']],
                            ['required' => ['slug']],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'stop_on_error' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $stop_on_error = !empty($input['stop_on_error']);
                $results = [];
                $success_count = 0;
                $failure_count = 0;
                $stopped_early = false;

                foreach ((array) $input['items'] as $index => $item) {
                    $post_id = webo_rank_math_resolve_post_id($item);
                    if (!$post_id) {
                        $failure_count++;
                        $results[] = [
                            'index' => $index,
                            'success' => false,
                            'error_code' => 'post_not_found',
                            'error_message' => 'Post not found by post_id/slug.',
                        ];
                        if ($stop_on_error) {
                            $stopped_early = true;
                            break;
                        }
                        continue;
                    }

                    if (!current_user_can('edit_post', $post_id)) {
                        $failure_count++;
                        $results[] = [
                            'index' => $index,
                            'post_id' => $post_id,
                            'success' => false,
                            'error_code' => 'forbidden',
                            'error_message' => 'Permission denied for this post.',
                        ];
                        if ($stop_on_error) {
                            $stopped_early = true;
                            break;
                        }
                        continue;
                    }

                    webo_rank_math_update_post_meta_map($post_id, $item['seo_meta']);
                    $post = get_post($post_id);

                    $success_count++;
                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'post_id' => $post_id,
                        'slug' => $post ? $post->post_name : null,
                    ];
                }

                return [
                    'success_count' => $success_count,
                    'failure_count' => $failure_count,
                    'stopped_early' => $stopped_early,
                    'results' => $results,
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('edit_posts', $input['site_id'] ?? 0);
            }
            return current_user_can('edit_posts');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);

    wp_register_ability('webo-rank-math/get-term-seo-meta', [
        'label' => 'Get Term SEO Meta (Rank Math)',
        'description' => 'Get Rank Math SEO metadata for taxonomy term.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'required' => ['term_id'],
            'properties' => [
                'term_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'keys' => [
                    'type' => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $term = get_term($input['term_id']);
                if (!$term || is_wp_error($term)) {
                    return new WP_Error('term_not_found', 'Term not found.', ['status' => 404]);
                }

                return [
                    'term_id' => (int) $term->term_id,
                    'taxonomy' => $term->taxonomy,
                    'slug' => $term->slug,
                    'seo_meta' => webo_rank_math_collect_term_meta((int) $term->term_id, $input['keys'] ?? null),
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('manage_categories', $input['site_id'] ?? 0);
            }
            return current_user_can('manage_categories');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);

    wp_register_ability('webo-rank-math/update-term-seo-meta', [
        'label' => 'Update Term SEO Meta (Rank Math)',
        'description' => 'Update Rank Math SEO metadata for one taxonomy term. Set value to null to delete key.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'required' => ['term_id', 'seo_meta'],
            'properties' => [
                'term_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'seo_meta' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $term = get_term($input['term_id']);
                if (!$term || is_wp_error($term)) {
                    return new WP_Error('term_not_found', 'Term not found.', ['status' => 404]);
                }

                webo_rank_math_update_term_meta_map((int) $term->term_id, $input['seo_meta']);

                return [
                    'updated' => true,
                    'term_id' => (int) $term->term_id,
                    'taxonomy' => $term->taxonomy,
                    'seo_meta' => webo_rank_math_collect_term_meta((int) $term->term_id),
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('manage_categories', $input['site_id'] ?? 0);
            }
            return current_user_can('manage_categories');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);

    wp_register_ability('webo-rank-math/get-options', [
        'label' => 'Get Rank Math Options',
        'description' => 'Read Rank Math option groups, including modules and option sets.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'option_names' => [
                    'type' => 'array',
                    'items' => [ 'type' => 'string' ],
                    'description' => 'Specific option names to read. Defaults to common Rank Math option groups.',
                ],
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $names = !empty($input['option_names']) ? array_values(array_filter(array_map('sanitize_key', (array) $input['option_names']))) : webo_rank_math_default_option_names();
                $options = [];

                foreach ($names as $name) {
                    $value = get_option($name, null);
                    if ($value !== null) {
                        $options[$name] = $value;
                    }
                }

                return [
                    'count' => count($options),
                    'options' => $options,
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('manage_options', $input['site_id'] ?? 0);
            }
            return current_user_can('manage_options');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);

    wp_register_ability('webo-rank-math/update-options', [
        'label' => 'Update Rank Math Options',
        'description' => 'Update Rank Math options by option name. Option value must be full value for each option key.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'required' => ['options'],
            'properties' => [
                'options' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'description' => 'Map: option_name => option_value.',
                ],
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $updated = [];
                foreach ((array) $input['options'] as $name => $value) {
                    $option_name = sanitize_key($name);
                    if ($option_name === '' || strpos($option_name, 'rank_math') !== 0) {
                        continue;
                    }

                    update_option($option_name, $value);
                    $updated[$option_name] = get_option($option_name);
                }

                return [
                    'updated_count' => count($updated),
                    'options' => $updated,
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('manage_options', $input['site_id'] ?? 0);
            }
            return current_user_can('manage_options');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);

    wp_register_ability('webo-rank-math/get-modules', [
        'label' => 'Get Rank Math Modules',
        'description' => 'Get current active Rank Math modules list from option rank_math_modules.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $modules = array_values(array_filter((array) get_option('rank_math_modules', [])));
                sort($modules);

                return [
                    'count' => count($modules),
                    'modules' => $modules,
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('manage_options', $input['site_id'] ?? 0);
            }
            return current_user_can('manage_options');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);

    wp_register_ability('webo-rank-math/update-modules', [
        'label' => 'Update Rank Math Modules',
        'description' => 'Enable/disable Rank Math modules in bulk via rank_math_modules option.',
        'category' => 'webo-rank-math',
        'input_schema' => [
            'type' => 'object',
            'required' => ['modules'],
            'properties' => [
                'modules' => [
                    'type' => 'array',
                    'items' => [ 'type' => 'string' ],
                    'minItems' => 0,
                    'description' => 'Final active module list to store in rank_math_modules option.',
                ],
                'site_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback' => function($input) {
            $context = function_exists('webo_mcp_multisite_switch_to_site')
                ? webo_mcp_multisite_switch_to_site($input['site_id'] ?? 0)
                : [ 'switched' => false ];

            if (is_wp_error($context)) {
                return $context;
            }

            try {
                $modules = array_values(array_unique(array_filter(array_map(function($value) {
                    return sanitize_key((string) $value);
                }, (array) $input['modules']))));

                sort($modules);
                update_option('rank_math_modules', $modules);

                return [
                    'updated' => true,
                    'count' => count($modules),
                    'modules' => $modules,
                ];
            } finally {
                if (!empty($context['switched'])) {
                    restore_current_blog();
                }
            }
        },
        'permission_callback' => function($input) {
            if (function_exists('webo_mcp_multisite_current_user_can_for_site')) {
                return webo_mcp_multisite_current_user_can_for_site('manage_options', $input['site_id'] ?? 0);
            }
            return current_user_can('manage_options');
        },
        'meta' => [
            'show_in_rest' => true,
        ],
    ]);
});
