# AI_PLUGIN_GUIDE

Format-Version: 1.0
Plugin-Slug: webo-mcp-rank-math
Plugin-Type: addon
Parent-Plugin: webo-mcp

## 1) Purpose
- MCP addon for Rank Math SEO management.

## 2) Required Dependencies
- `webo-mcp` active.
- Rank Math plugin active.

## 3) MCP Access
- Use core endpoint from `webo-mcp`.
- Session flow: `initialize -> tools/list -> tools/call`.

## 4) Key Tools
- Use exact rank-math tool names from `tools/list`.

## 5) Safety Rules
- Validate required fields before updates.
- Confirm bulk SEO changes before execution.

## 6) Common Errors
- `unknown tool`: addon not active or session cache stale.
- `permission_denied`: missing capability.

## 7) Verification Checklist
- Addon + Rank Math active.
- Required tool visible in `tools/list`.
- Expected SEO fields are returned/updated.

## 8) Escalation
- Use `Gui_Dev` when request exceeds current tool surface.
