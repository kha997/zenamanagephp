---
work_id: GAP-030
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-29-gap-030-rfi-resolve-escalation-evidence.md
  plan: docs/superpowers/plans/2026-10-29-gap-030-rfi-resolve-escalation.md
  branch: docs/GAP-030-rfi-resolve-escalation
  pr: https://github.com/kha997/zenamanagephp/pull/386
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T14:57:07+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-29, verbatim: 'APPROVE GAP-030 Gate 2 Option A'. Reviewed design head: 87f7cf7ebde4449868ada82be481a1c36035cbc2. Approves Option A and its exact allowlist (rfis.resolve-escalation route uses rbac:rfi.resolve-escalation; new rfi.resolve-escalation code in ZenaPermissionsSeeder; ZenaProjectManagerRolePermissionSeeder matches project_manager or 'project manager', adds rfi.resolve-escalation for PM and grants it to Project Member; RfiApiTest and ZenaProjectManagerRolePermissionSeederTest updates; plan and packets); no RFI controller/service, rule, UI, other-role or notification change; not Gate 3, merge, release, deployment or environment seeding."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T14:48:04+07:00"
  updated_at: "2026-10-29T14:57:07+07:00"
generated_by: agent
---

# GAP-030 — RFI escalation resolve permission: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-29 against reviewed design head
`87f7cf7ebde4449868ada82be481a1c36035cbc2`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Đề xuất **Phương án A**:

1. **Quyền mới `rfi.resolve-escalation`** cho API xử lý leo thang (thay cho `rfi.escalate`). Luật bên trong giữ nguyên:
   chỉ người nhận leo thang, PM của dự án, hoặc admin mới xử lý được; người khác vẫn bị từ chối.
2. **Ai giữ quyền mới:** cả 3 role nội bộ mặc định — **System Admin, Project Manager, Project Member** — vì bất kỳ người
   dùng nào trong công ty cũng có thể được chọn làm người nhận leo thang; quyền này chỉ "mở cửa", luật bên trong mới quyết
   định ai thật sự được xử lý.
3. **Sửa lỗi tên role PM trong seeder:** seeder nhận cả `Project Manager` lẫn `project_manager` → PM mặc định có lại các
   quyền RFI đã định (leo thang, huỷ, …) và quyền mới.
4. Môi trường đã có dữ liệu: chạy lại seeder (chỉ thêm quyền, không gỡ) — PR này không chạy.

## So sánh phương án

| Phương án | Ai giữ `rfi.resolve-escalation` | Leo thang cho ai | Kết luận |
|---|---|---|---|
| **A** | Admin, PM, Project Member | Bất kỳ ai trong công ty (như hiện nay) | **Đề xuất** — người nhận nào cũng xử lý được; luật bên trong giữ an toàn |
| B | Chỉ Admin + PM | Chỉ người có quyền resolve (leo thang cho người khác → báo lỗi 422) | Chặt hơn; Project Member không còn được chọn làm người nhận |
| C | Như A | Như A | Không sửa lỗi tên role PM (PM mặc định vẫn không có quyền RFI) — không nên |

## Thiết kế: Phương án A

### 1. Quyền và route

- `ZenaPermissionsSeeder` thêm mã `rfi.resolve-escalation` (Admin tự nhận vì seeder admin cấp mọi mã trong danh sách này).
- `routes/api_zena.php`: `rfis.resolve-escalation` dùng `rbac:rfi.resolve-escalation`; `rfis.escalate` giữ `rbac:rfi.escalate`.
- `RfiController::resolveEscalation()` **không đổi**.

### 2. Cấp quyền mặc định

- `ZenaProjectManagerRolePermissionSeeder`: tìm role theo tên `project_manager` **hoặc** `project manager`; thêm
  `rfi.resolve-escalation` vào danh sách của PM.
- Role `Project Member` nhận `rfi.resolve-escalation` (chỉ mã này) — trong cùng seeder trên, đổi tên phạm vi thành "RFI
  role grants" (một seeder, không thêm seeder mới).

### 3. Test (đỏ trước / xanh sau)

- `tests/Feature/Api/RfiApiTest.php`: test người nhận tự xử lý leo thang cấp `rfi.resolve-escalation` thay vì
  `rfi.escalate` (đỏ trước: 403; xanh sau: 200). Test "người không liên quan bị 403" cấp quyền mới — vẫn 403 (luật bên
  trong). Thêm test: người chỉ có `rfi.escalate` không còn xử lý được (403).
- `tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php`: thêm ca role tên `Project Manager` nhận quyền; Project
  Member nhận `rfi.resolve-escalation`.
- Test bảo vệ GAP-098 (`RouteRbacCodesSeededTest`) phải xanh với mã mới.

### 4. Files (allowlist)

`routes/api_zena.php` (1 dòng); `database/seeders/ZenaPermissionsSeeder.php` (1 mã);
`database/seeders/ZenaProjectManagerRolePermissionSeeder.php`; `tests/Feature/Api/RfiApiTest.php`;
`tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php`; plan
`docs/superpowers/plans/2026-10-29-gap-030-rfi-resolve-escalation.md`; packet và evidence GAP-030.

**Không đổi:** controller / service RFI, luật người nhận / PM / admin, giao diện, các role khác, thông báo.

## Verification required at Gate 3

Đỏ trước / xanh sau như trên; RFI, seeder, route-security invariants, `RouteRbacCodesSeededTest`; toàn bộ Unit + Feature +
Integration và `tests/Feature/Api` không có lỗi mới; PHPStan, php-cs-fixer, SSOT lint, route guard; exact-head CI xanh.

## Rollback

Revert squash commit; quyền đã seed (nếu có) để lại vô hại hoặc xoá tay.

## Decision Needed

Owner chọn: `APPROVE GAP-030 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge, phát hành, chạy seeder trên môi trường thật.
