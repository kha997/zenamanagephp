---
work_id: GAP-019
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-29-gap-019-universal-frame-evidence.md
  plan: docs/superpowers/plans/2026-10-29-gap-019-universal-frame-removal.md
  branch: docs/GAP-019-universal-frame-shell
  pr: https://github.com/kha997/zenamanagephp/pull/389
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T23:04:11+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T23:04:11+07:00"
  updated_at: "2026-10-29T23:04:11+07:00"
generated_by: agent
---

# GAP-019 — Universal-frame shell with no route owner: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Đề xuất **Phương án A**: xoá giao diện demo `universal-frame` cùng các component chỉ nó dùng (14 file, ~2.000 dòng), và bỏ
3 mục "kiểm tra" giả trong báo cáo sẵn sàng ra mắt (vốn chỉ xem file demo có tồn tại không). Giao diện đang chạy
(`layouts.operator`) và API `/api/v1/universal-frame/*` **không đổi**.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Xoá shell + component + bỏ 3 mục kiểm tra giả** | Như thiết kế dưới | **Đề xuất** — hết code chết, báo cáo không còn tín hiệu giả |
| B. Xoá shell + component, giữ 3 mục kiểm tra | Ít sửa service hơn | 3 mục luôn báo `false`, báo cáo sai theo hướng ngược lại |
| C. Giữ nguyên làm demo, đóng GAP với lý do | Không sửa code | Code chết và CDN không ghim phiên bản vẫn còn; `smart_tools` vẫn báo `false` |

## Thiết kế: Phương án A

### 1. Xoá view (14 file)

- `resources/views/layouts/universal-frame.blade.php`
- `resources/views/test-universal-frame.blade.php`
- `resources/views/components/{activity-panel,alert-bar,kpi-strip,mobile-drawer,mobile-fab,mobile-navigation,universal-header,universal-navigation}.blade.php`
- `resources/views/components/navigation/{admin-nav,mobile-admin-nav,mobile-tenant-nav,tenant-nav}.blade.php` (chỉ
  `universal-navigation` dùng)

Đã kiểm tra: không route, controller, view hay test nào khác tham chiếu các file này.

### 2. `app/Services/LaunchChecklistService.php`

`runSystemIntegrationChecks()` bỏ 3 mục `universal_page_frame`, `smart_tools`, `mobile_optimization` và 3 hàm riêng
`checkUniversalPageFrame()`, `checkSmartTools()`, `checkMobileOptimization()`. Giữ `accessibility`,
`performance_optimization`, `api_integration`. `getReadinessScore()` không dùng 3 mục này nên không đổi. Response của
`/api/v1/final-integration/system-integration-checks` (và các khoá `integration_status` / `integration_checks`) mất 3 khoá
đó; không view hay test nào đọc chúng.

### 3. Test (đỏ trước / xanh sau)

`tests/Feature/Architecture/UniversalFrameShellRemovedTest.php` mới:
- 14 view trên không còn tồn tại (`view()->exists()` = false).
- `LaunchChecklistService::runSystemIntegrationChecks()` không còn 3 khoá trên và vẫn trả 3 khoá còn lại.

Các test hiện có cho `/api/v1/universal-frame/*` và final-integration (`V1LegacyRouteHardeningContractTest`,
`TenantIsolationV1ContractTest`, `LaunchEndpointsRunNoArtisanTest`, `PreLaunchReadinessIsReadOnlyTest`) phải giữ xanh;
`php artisan view:cache` chạy được.

### 4. Files (allowlist)

14 view xoá; `app/Services/LaunchChecklistService.php`; test mới; plan
`docs/superpowers/plans/2026-10-29-gap-019-universal-frame-removal.md`; packet và evidence GAP-019.

## Verification required at Gate 3

Đỏ trước / xanh sau; Unit + Feature + Integration và `tests/Feature/Api` không có lỗi mới; `view:cache`; PHPStan,
php-cs-fixer, SSOT lint, route guard, deptrac sạch; CI trên đúng head xanh.

## Rollback

Revert squash commit (chỉ view và một service; không migration, không dữ liệu).

## Explicit Exclusions

`/api/v1/universal-frame/*` và controller của nó; `layouts.operator` và giao diện đang chạy; các mục kiểm tra khác của
`LaunchChecklistService` (kể cả `accessibility` vốn cũng dựa trên file); deploy.

## Decision Needed

Owner chọn: `APPROVE GAP-019 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge, phát hành hay deploy.
