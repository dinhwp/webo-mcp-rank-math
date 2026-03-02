# WEBO MCP - Rank Math Addon

Addon tích hợp Rank Math SEO cho hệ WEBO MCP (`webo-mcp`).

## Dependencies

- `webo-mcp`
- `seo-by-rank-math`

## Abilities

- `webo-rank-math/get-plugin-status`
- `webo-rank-math/get-post-seo-meta`
- `webo-rank-math/update-post-seo-meta`
- `webo-rank-math/bulk-upsert-post-seo-meta`
- `webo-rank-math/get-term-seo-meta`
- `webo-rank-math/update-term-seo-meta`
- `webo-rank-math/get-options`
- `webo-rank-math/update-options`
- `webo-rank-math/get-modules`
- `webo-rank-math/update-modules`

## Notes

- Hỗ trợ thao tác theo `post_id` hoặc `slug` + `post_type`.
- Hỗ trợ multisite qua `site_id`.
- Với update meta: truyền `null` để xóa key.
- `update-options` chỉ cho cập nhật option name bắt đầu bằng `rank_math`.
