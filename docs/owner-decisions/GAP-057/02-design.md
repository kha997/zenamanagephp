---
work_id: GAP-057
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-29-gap-057-http-launch-actions-evidence.md
  plan: null
  branch: docs/GAP-057-http-launch-actions
  pr: https://github.com/kha997/zenamanagephp/pull/324
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
  created_at: "2026-09-29T18:04:46+07:00"
  updated_at: "2026-09-29T18:04:46+07:00"
generated_by: agent
---

# GAP-057 — Web requests run production migrations: Gate 2 design

## OWNER GATE 2: AWAITING OWNER DECISION

## Owner Summary

Không yêu cầu web nào được chạy migrate hay dựng lại cấu hình nữa — việc đó chỉ
thuộc bước triển khai chính thức (GAP-049). "Các bước chuẩn bị launch" đổi
thành **báo cáo chỉ-đọc**: còn bao nhiêu migration chưa chạy, cấu hình/route đã
được dựng sẵn chưa. Nút gọi "thực hiện" trả lời "từ chối — việc này do triển
khai đảm nhận". Có kiểm thử chứng minh không lệnh nào được chạy. Đề xuất
**Phương án 1**.

## So sánh phương án

| Phương án | Cách làm | Kết luận |
|---|---|---|
| **1. Chỉ-đọc + từ chối** | `executePreLaunchActions()` thay bằng `getPreLaunchReadiness()` chỉ đọc; `POST pre-launch-actions` trả 409 kèm readiness; `GET launch-report` nhúng readiness thay vì thực thi | **Đề xuất** — route/contract gần như giữ nguyên, báo cáo vẫn hữu ích và trung thực |
| 2. Gỡ route `pre-launch-actions` | Xoá route + method | Phải sửa baseline fixture route/middleware; mất luôn thông tin readiness |
| 3. Giữ nhưng gọi `deploy:migrate` | Chạy lệnh an toàn hơn từ web | Loại: vẫn chạy migration trong request web, vẫn ghi đè compiled cache của bản đang phục vụ |

## Thiết kế: Phương án 1

### `app/Services/LaunchChecklistService.php`

- Xoá `executePreLaunchActions()`; thêm `getPreLaunchReadiness(): array`
  **không gọi Artisan, không ghi file/DB**:
  - `pending_migrations`: số migration trong `migrator->paths()` +
    `database/migrations` chưa có trong bảng migrations (đọc repository của
    migrator; lỗi đọc → `null` kèm `error`).
  - `config_cached`: `app()->configurationIsCached()`.
  - `routes_cached`: `app()->routesAreCached()`.
  - `performed_by`: `"deployment (.github/workflows/production.yml → php artisan deploy:migrate)"`.
- `executeLaunchActions()` (mô phỏng) không đổi — ngoài phạm vi.

### `app/Http/Controllers/FinalIntegrationController.php`

- `executePreLaunchActions()` → HTTP **409**
  `{"success": false, "message": "...deployment only (GAP-049)...", "readiness": {...}}`.
- `generateLaunchReport()`: khoá `pre_launch_actions` thay bằng
  `pre_launch_readiness` (dữ liệu chỉ-đọc); không còn gọi hàm thực thi nào.
  (Không có UI hay caller nào đọc khoá cũ — `git grep` evidence.)

### Kiểm thử (TDD — đỏ trước)

1. `tests/Feature/GAP057/LaunchEndpointsRunNoArtisanTest.php` — super-admin,
   `Artisan` giả ghi lại mọi lệnh:
   - `GET /api/v1/final-integration/launch-report` → 200, **không** lệnh
     Artisan nào, có `data.pre_launch_readiness`.
   - `POST /api/v1/final-integration/pre-launch-actions` → 409, không lệnh
     Artisan nào.
2. `tests/Unit/GAP057/PreLaunchReadinessIsReadOnlyTest.php` —
   `getPreLaunchReadiness()` trả đủ khoá, không gọi Artisan; `pending_migrations`
   = 0 trên DB test đã migrate; `executePreLaunchActions` không còn tồn tại.
3. Sửa `tests/Unit/GAP055/PreLaunchActionsNoCacheFlushTest.php` (đang gọi
   `executePreLaunchActions()` và khẳng định đúng 6 lệnh): chuyển sang khẳng
   định `getPreLaunchReadiness()` không gọi lệnh nào (bảo toàn bất biến GAP-055
   "không `cache:clear`", chặt hơn).

## File allowlist

`app/Services/LaunchChecklistService.php`,
`app/Http/Controllers/FinalIntegrationController.php`, 2 test mới ở trên,
`tests/Unit/GAP055/PreLaunchActionsNoCacheFlushTest.php`,
`docs/superpowers/plans/2026-09-29-gap-057-http-launch-actions-implementation.md`
(có governance frontmatter), Gate packet/evidence của GAP-057. Không sửa
`OPERATIONAL_GAP_REGISTER.md` (đối soát sau phát hành).

## Verification trước Gate 3

Test mới đỏ ở base, xanh ở subject; test route-middleware hiện có
(`V1LegacyRouteHardeningContractTest`, `TenantIsolationV1ContractTest`),
`LaunchChecklistServiceBackupTest`, `BackupStorageLocationTest`,
`tests/Unit/OwnerGovernance` xanh; `git grep` không còn `Artisan::call` trong
`LaunchChecklistService`; toàn bộ required CI xanh ở exact head; digest
canonical.

## Rollback

Revert squash commit.

## Explicit Exclusions

- Các endpoint checklist mô phỏng luôn "success".
- Xoá tính năng final-integration.
- `Admin\MaintenanceController::optimize()` (không có route).
- Deploy.

## Decision Needed

Owner chọn: Approve Phương án 1 / Approve Phương án 2 / Request changes /
Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge, release hay deployment.
