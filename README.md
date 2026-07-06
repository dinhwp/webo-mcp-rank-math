# WEBO MCP - Rank Math Addon

Rank Math SEO addon for WEBO MCP (v2.0.3). Exposes `webo-rank-math/*` abilities through the WordPress Abilities API bridge so AI clients (ChatGPT, Claude, Codex, Cursor, etc.) can manage Rank Math SEO settings without wp-admin or WP-CLI.

**v2.0.3** hardens `seo_bulk_update` for AI clients with `post_id`, `slug`, or `url` targeting, non-stopping batch errors, checkpoints, cache flush, sitemap regeneration, and explicit success/failure counts.

## v2 action-level API

Use `webo-rank-math/semantic-action` or `webo-rank-math/config-mutate` with high-level actions instead of sending raw Rank Math option payloads. Mutating v2 actions default to preview mode and write when `dryRun=false` (or `dry_run=false`) is supplied. Destructive/guarded actions still require `force=true` in addition to `dryRun=false`.

Supported v2 actions: `optimize-settings`, `complete-brand-profile`, `fix-common-issues`, `flush-rankmath-cache`, `ai-optimize-low-ctr-posts`, `generate-faq-schema`, `rebuild-internal-links`, and `sync-gsc`.

```json
{
  "action": "optimize-settings",
  "profile": "saas",
  "dryRun": false,
  "force": true
}
```
Every write returns a diff and `backup_id`/`snapshot_id`; dry-run responses never write options.

## Website

[webomcp.com](https://webomcp.com) — product overview, docs, and ecosystem updates.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- [WEBO MCP](https://webomcp.com) 2.0.29+
- [Rank Math SEO](https://rankmath.com/) 1.0.268+ (`seo-by-rank-math`)

## Installation

1. Install and activate Rank Math SEO.
2. Install and activate WEBO MCP.
3. Install and activate this addon.
4. Use the WEBO MCP router endpoint: `POST /wp-json/mcp/v1/router`.

---

## Tools reference

### Tools kept from v1.0 (all still work, unchanged)

| Tool | Actions / Notes |
|------|----------------|
| `webo-rank-math/config-query` | `plugin-status`, `get-options`, `get-modules` |
| `webo-rank-math/config-mutate` | `update-options`, `update-modules`, `flush-sitemap-cache`, `apply-basic-seo` |
| `webo-rank-math/post-seo-query` | `get`, `audit` |
| `webo-rank-math/post-seo-mutate` | `update`, `bulk-upsert`, `cleanup` |
| `webo-rank-math/term-seo-query` | `get` |
| `webo-rank-math/term-seo-mutate` | `update` |
| `webo-rank-math/user-seo-query` | `get` |
| `webo-rank-math/user-seo-mutate` | `update` |
| `webo-rank-math/redirect-query` | `list`, `get` |
| `webo-rank-math/redirect-mutate` | `create`, `update`, `delete` |
| `webo-rank-math/schema-mutate` | `upsert`, `delete`, `cleanup` — defaults `dry_run:true` |
| `seo_quick_update` | Title / description / focus_keyword only (safe AI write) |
| `seo_bulk_update` | Bulk title / description / focus_keyword updates with checkpoints |
| `ai_optimize_low_ctr_posts` | Low-CTR optimization alias for GSC-backed Rank Math action |
| `rollback_checkpoint` | Roll back post-meta checkpoints or option snapshots |

### Semantic tools added in v1.1.0

| Tool | Actions |
|------|---------|
| `webo-rank-math/semantic-action` | `apply-brand-profile`, `migrate-brand`, `configure-homepage`, `configure-social`, `configure-schema-defaults`, `configure-sitemap-profile`, `audit-brand-seo`, `fix-brand-seo` |

All semantic actions default to `dry_run: true`. Pass `dry_run: false` to write.

---

## Semantic actions (AI-first usage)

### `apply-brand-profile`

Maps a brand identity to Knowledge Graph, homepage, social profiles, breadcrumbs, OpenGraph, Twitter card, and schema entity — all at once.

**Input:**

```json
{
  "action": "apply-brand-profile",
  "profile": "personal",
  "brand_name": "DinhWP",
  "alternate_name": "Đinh WP",
  "url": "https://dinhwp.com",
  "description": "DinhWP chia sẻ WordPress, AI Automation, MCP, Plugin Development, SEO và kinh nghiệm xây dựng SaaS thực tế.",
  "facebook": "https://facebook.com/dinhwp",
  "github": "https://github.com/dinhwp",
  "dry_run": true
}
```

**What it maps to (Rank Math options):**

| Input field | Rank Math option key |
|-------------|----------------------|
| `brand_name` | `general.knowledgegraph_name`, `titles.website_name`, `titles.breadcrumbs_home_label` |
| `profile` | `general.knowledgegraph_type` (`person` / `organization`) |
| `url` | `general.knowledgegraph_url`, `titles.breadcrumbs_home_link` |
| `description` | `general.knowledgegraph_description`, `titles.homepage_description` |
| `alternate_name` | `titles.website_alternate_name` |
| `facebook` | `general.social_url_facebook`, `social.facebook_link`, `social.facebook_author_urls` |
| `twitter` | `general.social_url_twitter`, `social.twitter_link`, `social.twitter_author_names` |
| `github`, `instagram`, `linkedin`, `youtube`, `pinterest` | `general.social_url_*`, `general.social_networks` (sameAs) |
| *(all social)* | `titles.twitter_card_type` = `summary_large_image` |

**Output (dry_run=true):**

```json
{
  "dry_run": true,
  "executed": false,
  "would_change": true,
  "planned_count": 14,
  "diff": [ ... ],
  "action": "apply-brand-profile",
  "changed_fields": ["general.knowledgegraph_name", "..."],
  "warnings": []
}
```

**Output (dry_run=false):** same shape with `dry_run: false`, `executed: true`, `changed: true`, `snapshot_id`.

---

### `migrate-brand`

Scans all Rank Math option groups for the old brand string and replaces every occurrence safely.

```json
{
  "action": "migrate-brand",
  "from": "Webo",
  "to": "DinhWP",
  "dry_run": true
}
```

- Recurses into nested arrays inside option groups.
- Creates a snapshot before writing (rollback on error).
- Returns a full diff of every changed key.

---

### `configure-homepage`

```json
{
  "action": "configure-homepage",
  "title": "DinhWP – WordPress & AI Automation",
  "description": "Chia sẻ WordPress thực tế, AI Automation, MCP, Plugin Development.",
  "dry_run": false
}
```

---

### `configure-social`

```json
{
  "action": "configure-social",
  "facebook": "https://facebook.com/dinhwp",
  "github": "https://github.com/dinhwp",
  "dry_run": false
}
```

---

### `configure-schema-defaults`

Set default schema type per post type in `rank-math-options-titles`.

```json
{
  "action": "configure-schema-defaults",
  "post_types": {
    "post": "article",
    "page": "webpage",
    "product": "product"
  },
  "dry_run": true
}
```

---

### `configure-sitemap-profile`

```json
{
  "action": "configure-sitemap-profile",
  "include_post_types": ["post", "page", "portfolio"],
  "exclude_post_types": ["attachment"],
  "exclude_taxonomies": ["post_tag"],
  "dry_run": false
}
```

---

### `audit-brand-seo`

Read-only. Returns current brand fields and a list of issues.

```json
{
  "action": "audit-brand-seo"
}
```

Response includes `health: "ok" | "warning" | "error"` and `issues[]`.

---

### `fix-brand-seo`

Same input as `apply-brand-profile` but **only fills empty fields** — never overwrites existing values.

```json
{
  "action": "fix-brand-seo",
  "profile": "personal",
  "brand_name": "DinhWP",
  "url": "https://dinhwp.com",
  "dry_run": true
}
```

---

## Mutation safety contract

Every mutate action (old and new) follows this contract:

| State | `dry_run` | `executed` | Keys present |
|-------|-----------|------------|-------------|
| Preview | `true` | `false` | `would_change`, `planned_count`, `diff` |
| Executed | `false` | `true` | `changed`, `changed_count`, `diff` |

Misleading keys (`success`, `updated`, `updated_count`, `deleted`, `deleted_count`) are **stripped from preview responses** so AI clients cannot misread a dry run as a completed write.

Dangerous mutations (`delete`, `cleanup`, `migrate-brand` on large option sets) also require `force: true` or a `checkpoint_id` to execute.

---

## Architecture (v1.1.0)

```
includes/
  mutation-contract.php                 Safety contract helpers (unchanged)
  license-client.php                    EDD license client (unchanged)
  class-rank-math-options-repository.php  ← NEW: single R/W source for RM options
  class-snapshot-service.php             ← NEW: point-in-time snapshots + rollback
  class-brand-profile-validator.php      ← NEW: input validation layer
  class-brand-profile-mapper.php         ← NEW: input → Rank Math option key mapping
  class-brand-profile-service.php        ← NEW: apply-brand-profile orchestration
  class-migration-service.php            ← NEW: migrate-brand orchestration

abilities/
  helpers.php                           Post/term/user meta helpers (unchanged)
  category.php                          Ability category registration (unchanged)
  unified-dispatchers.php               11 unified MCP tools (unchanged API)
  rank-math-management.php              Loader — now loads all new classes
  semantic-actions.php                  ← NEW: AI-first semantic action dispatcher
  redirections.php                      Redirection CRUD abilities (unchanged)
  ability-*.php                         Granular abilities (REST compat, unchanged)

webo-mcp-rank-math.php                  Bootstrap + ToolRegistry (semantic-action added)
```

**Key principles:**
- `OptionsRepository` is the **only** place that calls `get_option`/`update_option` for Rank Math options in the new layer.
- Controllers/Services never write options directly.
- Every large mutation creates a **Snapshot** first; on write error the snapshot rolls back automatically.
- All mutations support `dry_run` (default: `true`).

---

## For AI clients (ChatGPT / Claude / Codex / Cursor)

### Recommended workflow for a new site brand setup

```
1. audit-brand-seo          → see what's missing
2. apply-brand-profile      → dry_run=true first, review diff
3. apply-brand-profile      → dry_run=false to apply
4. audit-brand-seo          → verify health=ok
```

### Rename a brand across the whole site

```
1. migrate-brand  from="OldName"  to="NewName"  dry_run=true   → review diff
2. migrate-brand  from="OldName"  to="NewName"  dry_run=false  → apply
```

### Quick post SEO update (safe, no schema/robots changes)

```json
{
  "name": "seo_quick_update",
  "arguments": {
    "slug": "my-post-slug",
    "title": "New SEO Title",
    "description": "New meta description.",
    "dry_run": false
  }
}
```

### Schema upsert (dry run first)

```json
{
  "name": "webo-rank-math/schema-mutate",
  "arguments": {
    "post_id": 8961,
    "action": "upsert",
    "schema": { "@type": "Article", "headline": "My Article" },
    "dry_run": true
  }
}
```

---

## Abilities (granular, REST-compatible)

All abilities are registered under category `webo-rank-math` and available at `GET /wp-json/wp/v2/abilities`.

| Ability | Description |
|---------|-------------|
| `webo-rank-math/config-query` | Plugin status, options, modules |
| `webo-rank-math/config-mutate` | Update options, modules, flush cache, apply baseline |
| `webo-rank-math/post-seo-query` | Read post SEO meta + audit |
| `webo-rank-math/post-seo-mutate` | Update, bulk-upsert, cleanup post SEO meta |
| `webo-rank-math/term-seo-query` | Read term SEO meta |
| `webo-rank-math/term-seo-mutate` | Update term SEO meta |
| `webo-rank-math/user-seo-query` | Read user/author SEO meta |
| `webo-rank-math/user-seo-mutate` | Update user SEO meta |
| `webo-rank-math/redirect-query` | List / get redirections |
| `webo-rank-math/redirect-mutate` | Create / update / delete redirections |
| `webo-rank-math/schema-mutate` | Upsert / delete / cleanup schema post meta |
| `webo-rank-math/semantic-action` | AI-first brand, homepage, social, sitemap actions |

---

## Permissions

| Area | Capability |
|------|-----------|
| Plugin status, options, modules, redirections, semantic actions | `manage_options` |
| Post SEO meta | `edit_posts` + `edit_post` per post |
| Term SEO meta | `manage_categories` |
| User SEO meta | `edit_users` |

---

## Running tests

```bash
# No WordPress needed (pure PHP unit tests):
php tests/options-repository-cases.php
php tests/snapshot-service-cases.php
php tests/brand-profile-service-cases.php
php tests/migration-service-cases.php
php tests/post-id-resolution-cases.php

# Needs WordPress (wp-cli):
wp eval-file tests/rank-math-dry-run-cases.php --allow-root
wp eval-file tests/seo-quick-update-cases.php --allow-root
```

---

## Development layout

```
abilities/
  helpers.php                  Meta key lists, collect/update helpers, site switcher
  category.php                 Ability category registration
  unified-dispatchers.php      10 unified dispatcher abilities + handler functions
  rank-math-management.php     Loader (loads classes then semantic-actions.php)
  semantic-actions.php         NEW: 8 AI-first semantic action handlers
  redirections.php             Redirection CRUD (uses RankMath\Redirections\DB)
  ability-*.php                Granular per-feature abilities (REST compat)

includes/
  mutation-contract.php                    Dry-run safety contract
  license-client.php                       EDD license client
  class-rank-math-options-repository.php   Repository layer
  class-snapshot-service.php              Snapshot + rollback
  class-brand-profile-validator.php       Input validation
  class-brand-profile-mapper.php          Input → Rank Math key mapping
  class-brand-profile-service.php         apply-brand-profile service
  class-migration-service.php             migrate-brand service

webo-mcp-rank-math.php   Plugin bootstrap, ToolRegistry, ability meta filters
frontend-fallback.php    Lightweight fallback for title/description/robots/redirects
```

## Scope vs Rank Math free

| Area | Status |
|------|--------|
| Post SEO meta (title, description, focus keyword, canonical, robots, OG, Twitter, schema) | Covered |
| Term SEO meta | Covered |
| User/author SEO meta | Covered |
| Global options (general, titles, sitemap, social, instant-indexing) | Covered |
| Modules | Covered |
| Plugin status | Covered |
| Redirections | Covered (requires Redirections module) |
| Brand profile / Knowledge Graph | Covered (v1.1.0 semantic actions) |
| Brand migration (rename across all options) | Covered (v1.1.0 migrate-brand) |
| Sitemap profile | Covered (v1.1.0 configure-sitemap-profile) |

## Support

Contact the WEBO team via [webomcp.com](https://webomcp.com) for product support or bug reports.
