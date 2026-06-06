# WEBO MCP - Rank Math Addon

Rank Math SEO addon for WEBO MCP. It exposes `webo-rank-math/*` abilities through the WordPress Abilities API bridge so MCP clients can manage Rank Math SEO metadata, options, modules, and redirections without wp-admin or WP-CLI.

## Website

[webomcp.com](https://webomcp.com) - product overview, docs, and ecosystem updates.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- [WEBO MCP](https://webomcp.com) 2.0.29+
- [Rank Math SEO](https://rankmath.com/) 1.0.268+ (`seo-by-rank-math`)

## Installation

1. Install and activate Rank Math SEO.
2. Install and activate WEBO MCP.
3. Install and activate this addon.
4. Use the WEBO MCP router endpoint from the main plugin: `POST /wp-json/mcp/v1/router`.

## Public MCP tools (`tools/list`)

MCP discovery lists the dispatcher abilities: `webo-rank-math/config-query`, `config-mutate`, `post-seo-query`, `post-seo-mutate`, `schema-mutate`, `term-seo-query`, `term-seo-mutate`, `user-seo-query`, `user-seo-mutate`, `redirect-query`, and `redirect-mutate`. Pass an `action` argument per ability schema (for example redirect: `redirect-query` with `action` `list` or `get`; `redirect-mutate` with `create`, `update`, or `delete`). Granular ability names below remain registered for REST and internal `wp_run_ability` calls but are not advertised as MCP tools.

## Abilities

All abilities are registered under category `webo-rank-math`.

| Ability | Description |
|--------|-------------|
| `webo-rank-math/get-plugin-status` | Read Rank Math plugin state, version, active modules, and available option groups |
| `webo-rank-math/get-post-seo-meta` | Read Rank Math SEO metadata for one post by `post_id` or `slug` + `post_type` |
| `webo-rank-math/update-post-seo-meta` | Update Rank Math SEO metadata for one post; `null` deletes a key |
| `webo-rank-math/bulk-upsert-post-seo-meta` | Bulk update post SEO metadata by `post_id` or `slug` |
| `webo-rank-math/get-term-seo-meta` | Read Rank Math SEO metadata for one term |
| `webo-rank-math/update-term-seo-meta` | Update Rank Math SEO metadata for one term |
| `webo-rank-math/get-user-seo-meta` | Read Rank Math author/archive SEO metadata |
| `webo-rank-math/update-user-seo-meta` | Update Rank Math author/archive SEO metadata |
| `webo-rank-math/get-options` | Read Rank Math option groups |
| `webo-rank-math/update-options` | Update Rank Math options; only `rank_math*` and `rank-math-options-*` keys are accepted |
| `webo-rank-math/get-modules` | Read active Rank Math modules |
| `webo-rank-math/update-modules` | Replace the active module list stored in `rank_math_modules` |
| `webo-rank-math/list-redirections` | List Rank Math redirections with pagination and filters |
| `webo-rank-math/get-redirection` | Read one Rank Math redirection by ID |
| `webo-rank-math/create-redirection` | Create a redirection with source, destination, status, and type |
| `webo-rank-math/update-redirection` | Update a redirection by ID |
| `webo-rank-math/delete-redirection` | Delete one or more redirections by ID |

### Common input rules

- `site_id` is supported on multisite-aware abilities.
- Post abilities accept either `post_id` or `slug` plus optional `post_type`.
- `get-post-seo-meta`, `get-term-seo-meta`, and `get-user-seo-meta` accept optional `keys` arrays to limit which `seo_meta` fields are returned.
- `bulk-upsert-post-seo-meta` accepts `items[]` with `seo_meta` plus either `post_id` or `slug`, and returns `success_count`, `failure_count`, `stopped_early`, and per-item `results`.
- `seo_meta` can contain any `rank_math_*` key; helper defaults cover common title, description, robots, social, schema, pillar content, and primary category fields.
- `schema-mutate` writes only `rank_math_schema_*` post meta. Actions: `upsert`, `delete`, `cleanup`. It defaults to `dry_run: true`; set `dry_run: false` or `force: true` to write.
- `get-options` accepts optional `option_names`; if omitted, the addon reads its default Rank Math option groups, including current `rank-math-options-*` groups.
- `update-modules` accepts a `modules` array and can clear the stored module list by sending an empty array.
- Redirections require the Rank Math Redirections module. The addon prefers
  Rank Math's model/DB wrappers and falls back to the Rank Math redirections
  table when those wrappers return inconsistent results.
- The addon includes a small public fallback that renders saved Rank Math title
  and meta description when a site stores Rank Math meta but the Rank Math
  frontend head hook is not firing. The same fallback layer can run active
  exact-match Rank Math redirections when the Rank Math frontend redirect hook
  is missing.

### Redirection schema notes

- `list-redirections` supports `limit` up to 500, plus `paged`, `status`, and `search`.
- `create-redirection` and `update-redirection` accept `comparison` values `exact`, `contains`, `start`, `end`, or `regex`.
- `ignore_case` maps to Rank Math source matching with `ignore = case`.
- Redirect `type` accepts `301`, `302`, `307`, `410`, or `451`; for `410` and `451`, destination is cleared automatically.
- `delete-redirection` uses the input key `id`, which may be a single integer or an array of integers.

### Schema mutate examples

Dry run an Article schema update:

```json
{
  "name": "webo-rank-math/schema-mutate",
  "arguments": {
    "post_id": 8961,
    "action": "upsert",
    "schema_key": "rank_math_schema_Article",
    "schema": {
      "@type": "Article",
      "headline": "Example headline",
      "description": "Example description"
    },
    "dry_run": true
  }
}
```

Write the update:

```json
{
  "name": "webo-rank-math/schema-mutate",
  "arguments": {
    "post_id": 8961,
    "action": "upsert",
    "schema_key": "rank_math_schema_Article",
    "schema": {
      "@type": "Article",
      "headline": "Example headline",
      "description": "Example description"
    },
    "dry_run": false
  }
}
```

Delete one schema meta key:

```json
{
  "name": "webo-rank-math/schema-mutate",
  "arguments": {
    "post_id": 8961,
    "action": "delete",
    "schema_key": "rank_math_schema_Article",
    "dry_run": false
  }
}
```

## Permissions

- Plugin status, options, modules, redirections: `manage_options`
- Post SEO meta: `edit_posts`, plus `edit_post` per updated post
- Term SEO meta: `manage_categories`
- User SEO meta: `edit_users`

## Skills for MCP clients

The maintained public agent skills are published with the main [WEBO MCP](https://webomcp.com) documentation.

- [WEBO MCP skills documentation](https://webomcp.com)
- `webo-mcp-ability-rank-math`
- `webo-mcp-rank-math-redirections`

Install examples via the `skills` CLI:

```bash
npx skills add https://webomcp.com --skill webo-mcp-ability-rank-math -a cursor -g -y
npx skills add https://webomcp.com --skill webo-mcp-rank-math-redirections -a cursor -g -y
```

## Development layout

- `webo-mcp-rank-math.php` - bootstrap, dependency notices, textdomain loading
- `abilities/helpers.php` - key lists, site switching, collect/update helpers
- `abilities/category.php` - `webo-rank-math` category registration
- `abilities/ability-plugin-status.php` - plugin state ability
- `abilities/ability-post-meta.php` - post SEO meta abilities
- `abilities/ability-term-meta.php` - term SEO meta abilities
- `abilities/ability-user-meta.php` - user SEO meta abilities
- `abilities/ability-options.php` - options and modules abilities
- `abilities/redirections.php` - redirection CRUD abilities

## Scope against Rank Math free

| Area | Status | Notes |
|------|--------|-------|
| Post and term SEO fields | Covered | Default helpers include title, description, focus keyword, canonical, robots, social, schema, pillar, primary category |
| User or author SEO fields | Covered | Author archive title, description, robots, canonical |
| Global options | Covered | `rank_math*` and `rank-math-options-*` options, default groups for general, titles, sitemap, social, analytics |
| Modules | Covered | Read and replace active module list |
| Plugin status | Covered | Version, active state, modules, option groups |
| Redirections | Covered | Requires Rank Math Redirections module |

## Support

Contact the WEBO team for product support or bug reports.
