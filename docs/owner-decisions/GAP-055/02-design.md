---
work_id: GAP-055
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-29-gap-055-http-cache-flush-evidence.md
  plan: null
  branch: docs/GAP-055-admin-clear-cache-flush
  pr: https://github.com/kha997/zenamanagephp/pull/322
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-29T06:53:55+07:00"
  updated_at: "2026-09-29T06:53:55+07:00"
generated_by: agent
---

# GAP-055 — HTTP-triggered whole-cache flush: Gate 2 design

## OWNER GATE 2: AWAITING OWNER DECISION

## Owner Summary

Sửa để ba đường đã nêu ở Gate 1 **không bao giờ** xoá bộ nhớ đệm dùng chung
nữa, theo đúng cách GAP-054 đã làm cho việc chạy tự động: đường quản trị trả
lời "từ chối, không có gì an toàn để xoá" thay vì xoá; bước "chuẩn bị launch"
bỏ lệnh xoá bộ nhớ đệm; đường huy hiệu chỉ xoá đúng các mục huy hiệu của chính
người dùng đó. Có kiểm thử tự động chứng minh bộ đếm của khách hàng khác còn
nguyên. Đề xuất **Phương án 1**.

## Đính chính bằng chứng Gate 1 (phát hiện khi thiết kế)

Nút "Clear" trên trang bảo trì
(`resources/views/admin/maintenance-content.blade.php:162,515-523`) **không gọi
endpoint**: hàm `clearCache()` phía giao diện chỉ chạy `setTimeout` rồi ghi
"completed". Vì vậy hiện **bấm nút không xoá gì**; mặt A chỉ bị kích hoạt khi
có ai gọi thẳng `POST /admin/maintenance/clear-cache` (super-admin), như probe
Gate 1 đã làm. Kết luận kỹ thuật của Gate 1 (endpoint sống, xoá toàn cục, đã tái
hiện) không đổi; chỉ câu "bấm nút" trong phần tóm tắt là sai. Gate 1 đã có quyết
định nên không sửa tại chỗ; đính chính ghi ở đây.

## Sự thật dùng để thiết kế

- Không có "vùng bộ nhớ đệm riêng của bảo trì" nào để xoá an toàn: rate-limiter,
  OIDC state và mọi cache ứng dụng dùng chung một store (`config/cache.php`
  không có `limiter`; GAP-054 evidence §Defect 2).
- Khoá huy hiệu có dạng `badge_{itemId}_user_{userId}`; tập `itemId` hữu hạn
  và cố định trong `BadgeService::getBadgeEndpoint()` (15 mục).
- `BadgeService::clearItemBadgeCache()` không có caller nào.
- `LaunchChecklistService::executePreLaunchActions()` gọi `cache:clear`,
  `config:clear`, `route:clear`, rồi `optimize`, `config:cache`, `route:cache`,
  `migrate --force`. Chỉ `cache:clear` chạm store dùng chung; phần còn lại là
  file compiled cache/migration — ngoài phạm vi GAP-055.

## So sánh phương án

| | Mặt A (endpoint quản trị) | Mặt B (pre-launch) | Mặt C (huy hiệu) | Kết luận |
|---|---|---|---|---|
| **1. Fail-closed + xoá có mục tiêu** | Không mutation; trả 409 kèm giải thích (giống `maintenance:run --task=cache` sau GAP-054) | Bỏ `cache:clear`, giữ các bước khác | `clearUserBadgeCache` xoá đúng 15 khoá của user; xoá method chết `clearItemBadgeCache` | **Đề xuất** — nhất quán GAP-054, route/contract giữ nguyên |
| 2. Gỡ route | Xoá route + controller method | như 1 | như 1 | Phải sửa baseline route/middleware fixtures; lợi ích thêm không đáng |
| 3. Xoá theo danh sách "cache ứng dụng" | Đoán danh sách khoá/tag | như 1 | như 1 | Loại — không có namespace, đoán sai là lỗi mới (GAP-054 đã loại cùng lý do) |

## Thiết kế: Phương án 1

### Mặt A — `app/Http/Controllers/Admin/MaintenanceController.php::clearCache()`

- Không gọi `cache:clear`, `config:clear`, `route:clear`, `view:clear`,
  `Cache::flush()`.
- Trả HTTP **409** JSON `{"success": false, "message": "..."}`; thông điệp
  nói rõ store chứa bộ đếm rate-limit, OIDC state của mọi tenant nên không bị
  xoá từ web, compiled caches do bước deploy quản lý (GAP-054).
- Ghi `logMaintenanceTask(..., 'warning')` như các nhánh khác.
- Route, middleware, UI giữ nguyên (nút giả trên UI không gọi endpoint — ngoài
  phạm vi).

### Mặt B — `app/Services/LaunchChecklistService.php::executePreLaunchActions()`

- Bỏ đúng một dòng `Artisan::call('cache:clear')`. `config:clear`,
  `route:clear`, `optimize`, `config:cache`, `route:cache`, `migrate --force`
  **không đổi** (thuộc gap riêng đề xuất).
- Khoá kết quả `clear_caches` giữ nguyên tên để không đổi contract JSON.

### Mặt C — `app/Services/BadgeService.php`

- `clearUserBadgeCache()`: thay `Cache::flush()` bằng `Cache::forget()` cho
  từng `badge_{itemId}_user_{userId}` với `itemId` thuộc tập cố định; tập được
  tách thành một hằng số dùng chung với `getBadgeEndpoint()` để không lệch.
- Xoá `clearItemBadgeCache()` (không caller; hành vi duy nhất là lỗi).
- **Không** sửa import `Request` của `BadgeController` (lỗi chức năng riêng);
  sau thay đổi này, sửa import sẽ không còn mở lỗ hổng.

### Kiểm thử (TDD — viết đỏ trước)

1. `tests/Feature/GAP055/AdminClearCacheFailsClosedTest.php` — super-admin
   `POST /admin/maintenance/clear-cache` → 409, `success=false`; bộ đếm
   `RateLimiter` của tenant khác vẫn 5; khoá cache của tenant khác còn nguyên.
2. `tests/Unit/GAP055/PreLaunchActionsNoCacheFlushTest.php` — spy `Artisan`
   (không chạy thật migrate/optimize): `executePreLaunchActions()` không bao giờ
   gọi `cache:clear`; vẫn gọi các lệnh còn lại đúng thứ tự.
3. `tests/Unit/GAP055/BadgeUserCacheClearIsTargetedTest.php` — sau
   `clearUserBadgeCache($u1)`: khoá huy hiệu của u1 mất; khoá huy hiệu u2, bộ
   đếm `RateLimiter`, khoá tenant khác còn nguyên; method
   `clearItemBadgeCache` không còn tồn tại.
4. Sửa kỳ vọng cũ: `tests/Feature/FinalSystemTest.php:302` và
   `tests/Feature/PerformanceTest.php:357` (đang kỳ vọng 200/`success=true`
   cho clear-cache) → kỳ vọng 409/`success=false`.

## File allowlist

`app/Http/Controllers/Admin/MaintenanceController.php`,
`app/Services/LaunchChecklistService.php`, `app/Services/BadgeService.php`,
3 file test mới ở trên, `tests/Feature/FinalSystemTest.php`,
`tests/Feature/PerformanceTest.php`, implementation plan
`docs/superpowers/plans/2026-09-29-gap-055-http-cache-flush-implementation.md`,
và các Gate packet/evidence của GAP-055. Không file nào khác.
`OPERATIONAL_GAP_REGISTER.md` **không** đổi trong GAP-055 (sửa sổ sẽ làm lệch
digest của chính GAP-055); trạng thái sẽ được đối soát sau phát hành như
OWN-2026-011/013.

## Verification trước Gate 3

- 3 test mới đỏ ở base, xanh sau sửa; 2 test cũ sửa kỳ vọng chạy xanh.
- `GAP052DashboardWidgetContractTest`, test RBAC/tenant hiện có, Routes
  Guardrails, PHPStan (CI), Owner Governance Lint: xanh.
- `git grep` chứng minh không còn `Cache::flush()`/`cache:clear` trên 3 mặt.
- Toàn bộ required CI trên exact head xanh; digest tính bằng hàm canonical.

## Rollback

Revert squash commit. Không có migration, dữ liệu hay cấu hình cần hoàn tác.

## Explicit Exclusions

- `migrate --force`/`optimize`/`config:cache` từ web (gap riêng).
- Sửa import `BadgeController`, khôi phục huy hiệu thanh bên.
- Nút giả và các nút mô phỏng khác trên trang bảo trì.
- 14 chỗ flush không reachable (evidence §Not HTTP-reachable).
- Deploy, cấu hình Redis.

## Decision Needed

Owner chọn: Approve Phương án 1 / Approve Phương án 2 / Request changes /
Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge, release hay deployment.
