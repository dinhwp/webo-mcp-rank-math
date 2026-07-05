# Changelog

## 2.0.1

- Fixed `seo_quick_update` and Rank Math post SEO mutations so `dry_run=false`, `false`, `0`, and `"0"` execute real meta updates.
- Aligned the bundled mutation contract with WEBO MCP Core `MutationGuard`.
- Kept `force=true` as confirmation for guarded operations only; it no longer switches preview mode to execution by itself.
- Added integration-style coverage for `seo_quick_update` execution against Rank Math title, description, and focus keyword meta.

