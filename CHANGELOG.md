# Changelog

## 2.0.3

- Hardened `seo_bulk_update` for `post_id`, `slug`, or `url` targeting.
- Processed batch items independently without stopping on per-post errors.
- Flushed Rank Math cache and requested sitemap regeneration after real bulk writes.
- Returned success/failure counts, checkpoint ID, updated items, failed items, and before/after diffs.

## 2.0.2

- Added exact AI-client tools `seo_bulk_update`, `ai_optimize_low_ctr_posts`, and `rollback_checkpoint`.
- Added post-meta checkpoints before real `seo_quick_update` and `seo_bulk_update` writes.
- Added rollback test coverage for single and bulk Rank Math SEO meta updates.

## 2.0.1

- Fixed `seo_quick_update` and Rank Math post SEO mutations so `dry_run=false`, `false`, `0`, and `"0"` execute real meta updates.
- Aligned the bundled mutation contract with WEBO MCP Core `MutationGuard`.
- Kept `force=true` as confirmation for guarded operations only; it no longer switches preview mode to execution by itself.
- Added integration-style coverage for `seo_quick_update` execution against Rank Math title, description, and focus keyword meta.
