# AI_PLUGIN_GUIDE

Format-Version: 1.0
Plugin-Slug: webo-mcp-rank-math
Plugin-Type: addon
Parent-Plugin: webo-mcp

## 1) Purpose
- MCP addon for Rank Math SEO (free): plugin status, options, modules, post/term/user SEO meta, bulk upsert, redirections (list/get/create/update/delete).

## 2) Required Dependencies
- `webo-mcp` active (Abilities API).
- Rank Math plugin (`seo-by-rank-math`) active when using Rank Math tools.

## 3) MCP Access
- Use core endpoint from `webo-mcp`.
- Session flow: `initialize -> tools/list -> tools/call`.

## 4) Key Tools
- Use exact ability names from `tools/list` (prefix `webo-rank-math/`). Redirection tools require Rank Math Redirections module enabled.

## 5) Safety Rules
- Validate required fields before updates.
- Confirm bulk SEO changes before execution.

## 6) Common Errors
- `unknown tool`: addon not active or session cache stale.
- `permission_denied`: missing capability (e.g. manage_options, edit_posts).

## 7) Verification Checklist
- Addon + Rank Math active.
- Tool visible in `tools/list`.
- Expected SEO fields returned/updated.

## 8) Escalation
- Use `Gui_Dev` when request exceeds current tool surface.
