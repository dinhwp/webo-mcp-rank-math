=== WEBO MCP - Rank Math Addon ===
Contributors: phuongwebo
Author URI: https://webomcp.com
Tags: mcp, seo, rank-math, ai, automation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.3
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rank Math SEO addon for WEBO MCP. Exposes webo-rank-math tools for SEO meta, options, modules, and redirections through the WordPress Abilities API bridge.

== Description ==

This release adds a v2 action-level API for Rank Math MCP: optimize-settings, complete-brand-profile, fix-common-issues, flush-rankmath-cache, ai-optimize-low-ctr-posts, generate-faq-schema, rebuild-internal-links, and sync-gsc. Mutations default to dry-run and write when dryRun=false or dry_run=false is supplied; guarded actions also require force=true.

WEBO MCP - Rank Math Addon extends WEBO MCP with Rank Math SEO management abilities.

= Official site: https://webomcp.com =

Use this addon when MCP clients need to read or update Rank Math data without going through wp-admin or WP-CLI.

This addon registers the following ability groups:

- Plugin status: version, active modules, option groups
- Post SEO meta: get, update, bulk upsert
- Term SEO meta: get, update
- User SEO meta: get, update
- Global options: get, update
- Modules: get, update
- Redirections: list, get, create, update, delete

Abilities are registered under the `webo-rank-math` category and exposed through the WEBO MCP router endpoint:

- POST /wp-json/mcp/v1/router

Supported dependencies:

- WEBO MCP 2.0.29+
- Rank Math SEO 1.0.268+ (seo-by-rank-math)

Common rules:

- Multisite-aware abilities accept `site_id`.
- Post abilities accept either `post_id` or `slug` plus optional `post_type`.
- SEO payloads accept Rank Math keys beginning with `rank_math_`.
- Redirection abilities require the Rank Math Redirections module.

Permissions by area:

- Plugin status, options, modules, redirections: `manage_options`
- Post SEO meta: `edit_posts`, plus `edit_post` for each updated post
- Term SEO meta: `manage_categories`
- User SEO meta: `edit_users`

Public MCP client skills are maintained in the main WEBO MCP repository:

- skills/webo-mcp-ability-rank-math/SKILL.md
- skills/webo-mcp-rank-math-redirections/SKILL.md

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/webo-mcp-rank-math
2. Install and activate Rank Math SEO
3. Install and activate WEBO MCP
4. Activate this addon in WordPress Admin
5. Send MCP JSON-RPC requests to POST /wp-json/mcp/v1/router

== Frequently Asked Questions ==

= Which endpoint should MCP clients use? =
POST /wp-json/mcp/v1/router from the main WEBO MCP plugin.

= Does this plugin work without WEBO MCP? =
No. This addon depends on WEBO MCP and the WordPress Abilities API bridge provided by that plugin.

= Does this plugin work without Rank Math SEO? =
No. Rank Math SEO must be installed and active.

= Can MCP clients manage Rank Math redirections? =
Yes, if the Rank Math Redirections module is active and RankMath\Redirections\DB is available.

= Are multisite requests supported? =
Yes. Multisite-aware abilities accept `site_id` so callers can target a specific site.

= Where are the MCP client skills documented? =
The maintained skill files live in the main WEBO MCP repository, not in this addon package.

== Changelog ==

= 2.0.3 =
* Harden `seo_bulk_update` for `post_id`, `slug`, or `url` targeting.
* Process batch items independently without stopping on per-post errors.
* Flush Rank Math cache and regenerate sitemap after real bulk writes.
* Return success/failure counts, checkpoint ID, updated items, failed items, and before/after diffs.

= 2.0.2 =
* Add exact AI-client tools `seo_bulk_update`, `ai_optimize_low_ctr_posts`, and `rollback_checkpoint`.
* Add post-meta checkpoints for `seo_quick_update` and `seo_bulk_update` before real writes.
* Add rollback coverage for single and bulk Rank Math SEO meta updates.

= 2.0.1 =
* Fix: `seo_quick_update` and post SEO mutations now execute real updates for `dry_run=false`, `false`, `0`, and `"0"`.
* Fix: align bundled mutation contract with WEBO MCP Core write-mode handling.
* Test: add execution coverage for Rank Math title, description, and focus keyword updates.

= 2.0.0 =
* Add v2 action-level Rank Math MCP API for optimize-settings, complete-brand-profile, fix-common-issues, flush-rankmath-cache, ai-optimize-low-ctr-posts, generate-faq-schema, rebuild-internal-links, and sync-gsc.
* Keep v2 writes safe by default with dry-run responses, option group allowlists, and automatic backups before forced mutations.
* Detect namespaced WEBO MCP GSC addon classes for GSC-backed actions while preserving legacy fallbacks.

= 1.0.17 =
* Keep public Rank Math dispatcher tools visible in MCP `tools/list`, including global config query/mutate, while still hiding granular/internal aliases.

= 1.0.14 =
* Add robots meta fallback for stored Rank Math robots values when the active frontend stack renders an empty robots tag.

= 1.0.11 =
* MCP `tools/list` lists only unified `*-query`/`*-mutate` dispatcher abilities; granular redirection and legacy abilities remain for REST/internal use (`mcp.public` false).

= 1.0.10 =
* Add public redirect fallback for active Rank Math redirections when Rank Math frontend hooks are not firing.

= 1.0.9 =
* Add public frontend fallback for saved Rank Math title and meta description when Rank Math head output is missing.
* Create and list redirections through resilient Rank Math model and direct DB fallbacks, with DB errors exposed on failure.

= 1.0.8 =
* Allow MCP option updates for Rank Math option groups stored as `rank-math-options-*`.
* Include current Rank Math option group names in the default options readback.

= 1.0.5 =
* Ensure `webo-rank-math/*` abilities are bridged into WEBO MCP `tools/list`.
* Fix Rank Math SEO meta tools being registered internally but hidden from MCP clients.

= 1.0.4 =
* Standardize plugin metadata with current WEBO MCP addon conventions.
* Add translations bootstrap and canonical plugin URL constant.
* Refresh README documentation and add WordPress.org style readme.txt.
* Document the public Rank Math skills maintained in the main WEBO MCP repository.

= 1.0.0 =
* Initial public addon release for Rank Math SEO abilities.

== Upgrade Notice ==

= 2.0.3 =
Improves `seo_bulk_update` for direct ChatGPT batch SEO writes with safer per-item errors and clearer result counts.

= 2.0.2 =
Adds exact-name AI tools for bulk SEO updates, low-CTR optimization, and checkpoint rollback.

= 2.0.0 =
Adds the safe v2 Rank Math action API for AI clients. Mutations default to preview and execute with dryRun=false; guarded actions also require force=true.

= 1.0.17 =
MCP clients can now discover Rank Math global config query/mutate tools for Titles, Sitemap, Social, modules and related option groups.

= 1.0.14 =
Stored noindex/follow robots values can now render even when the frontend stack outputs an empty robots tag.

= 1.0.11 =
Switch MCP callers from granular `webo-rank-math/list-redirections` (etc.) to `redirect-query` / `redirect-mutate` with the appropriate `action`.

= 1.0.10 =
Active Rank Math redirections can now run on public pages even when the Rank Math frontend redirect hook is missing.

= 1.0.9 =
Saved Rank Math title and description can now render on public pages even when the Rank Math frontend head hook is missing.

= 1.0.8 =
MCP clients can now update Rank Math option groups such as `rank-math-options-sitemap`.

= 1.0.5 =
Rank Math SEO abilities now appear in MCP tools/list so clients can update SEO meta through the router.

= 1.0.4 =
Metadata and documentation alignment release. No new runtime dependency beyond current WEBO MCP and Rank Math requirements.
