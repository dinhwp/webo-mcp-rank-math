<?php
/**
 * WEBO MCP - Rank Math abilities loader.
 *
 * Loads helpers, category, all ability modules, and the new AI-first semantic action layer.
 * Keep this file as the single entry point for the abilities sub-system.
 *
 * Architecture layers loaded here (in dependency order):
 *  1. Infrastructure — OptionsRepository (single source of truth for Rank Math options R/W)
 *  2. Infrastructure — SnapshotService   (point-in-time option snapshots + rollback)
 *  3. Domain         — BrandProfileValidator, BrandProfileMapper
 *  4. Service        — BrandProfileService, MigrationService
 *  5. Presentation   — ability category, semantic-actions dispatcher
 *
 * Individual ability files are kept registered for REST compatibility but MCP
 * traffic is routed exclusively through the unified dispatcher tools.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$abilities_path = dirname( __FILE__ ) . '/';
$includes_path  = dirname( $abilities_path ) . '/includes/';

// -------------------------------------------------------------------
// 1 & 2. Infrastructure — Repository and Snapshot (load once, guard).
// -------------------------------------------------------------------
if ( ! class_exists( 'WeboMcpRankMath_OptionsRepository' ) ) {
	require_once $includes_path . 'class-rank-math-options-repository.php';
}
if ( ! class_exists( 'WeboMcpRankMath_SnapshotService' ) ) {
	require_once $includes_path . 'class-snapshot-service.php';
}

// -------------------------------------------------------------------
// 3. Domain — Validator and Mapper.
// -------------------------------------------------------------------
if ( ! class_exists( 'WeboMcpRankMath_BrandProfileValidator' ) ) {
	require_once $includes_path . 'class-brand-profile-validator.php';
}
if ( ! class_exists( 'WeboMcpRankMath_BrandProfileMapper' ) ) {
	require_once $includes_path . 'class-brand-profile-mapper.php';
}

// -------------------------------------------------------------------
// 4. Services — Brand profile and Migration.
// -------------------------------------------------------------------
if ( ! class_exists( 'WeboMcpRankMath_BrandProfileService' ) ) {
	require_once $includes_path . 'class-brand-profile-service.php';
}
if ( ! class_exists( 'WeboMcpRankMath_MigrationService' ) ) {
	require_once $includes_path . 'class-migration-service.php';
}

// -------------------------------------------------------------------
// 5. Presentation — Category + Semantic actions dispatcher.
// -------------------------------------------------------------------
require_once $abilities_path . 'category.php';
require_once $abilities_path . 'semantic-actions.php';

// Individual granular ability files below are kept as REST-compatible
// fallbacks and internal-use abilities; they are NOT the primary MCP
// dispatch path (that goes through unified-dispatchers.php).
//
// require_once $abilities_path . 'ability-plugin-status.php';
// require_once $abilities_path . 'ability-post-meta.php';
// require_once $abilities_path . 'ability-term-meta.php';
// require_once $abilities_path . 'ability-user-meta.php';
// require_once $abilities_path . 'ability-options.php';
// require_once $abilities_path . 'redirections.php';  // Unified in dispatchers
