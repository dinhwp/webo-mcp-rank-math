<?php
/**
 * Plugin Name: WEBO MCP - Rank Math Addon
 * Description: Rank Math SEO management abilities addon for WEBO MCP.
 * Version: 1.0.0
 * Author: WEBO
 * Text Domain: webo-mcp-rank-math
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: webo-mcp, seo-by-rank-math
 * Network: true
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WEBO_MCP_RANK_MATH_PATH', plugin_dir_path(__FILE__));

function webo_mcp_rank_math_bootstrap() {
    static $initialized = false;

    if ($initialized) {
        return true;
    }

    if (!function_exists('wp_register_ability')) {
        return false;
    }

    add_filter('wp_register_ability_args', function($args, $name) {
        if (0 !== strpos((string) $name, 'webo-rank-math/')) {
            return $args;
        }

        if (!isset($args['meta']) || !is_array($args['meta'])) {
            $args['meta'] = [];
        }

        if (!isset($args['meta']['mcp']) || !is_array($args['meta']['mcp'])) {
            $args['meta']['mcp'] = [];
        }

        $args['meta']['show_in_rest'] = true;
        if (!array_key_exists('public', $args['meta']['mcp'])) {
            $args['meta']['mcp']['public'] = true;
        }
        if (!isset($args['meta']['mcp']['type'])) {
            $args['meta']['mcp']['type'] = 'tool';
        }

        return $args;
    }, 10, 2);

    require_once WEBO_MCP_RANK_MATH_PATH . 'abilities/rank-math-management.php';

    $initialized = true;
    return true;
}

add_action('plugins_loaded', function() {
    webo_mcp_rank_math_bootstrap();
}, 5);

add_action('webo_mcp_loaded', 'webo_mcp_rank_math_bootstrap', 20);
add_action('init', 'webo_mcp_rank_math_bootstrap', 1);

add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!function_exists('wp_register_ability')) {
        echo '<div class="notice notice-warning"><p><strong>WEBO MCP Rank Math:</strong> Missing dependency <code>webo-mcp</code> (Abilities API not available).</p></div>';
    }

    if (!defined('RANK_MATH_VERSION') && !class_exists('RankMath\\Helper')) {
        echo '<div class="notice notice-warning"><p><strong>WEBO MCP Rank Math:</strong> Missing dependency <code>seo-by-rank-math</code>. Please install and activate Rank Math SEO.</p></div>';
    }
});
