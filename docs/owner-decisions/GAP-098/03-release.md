---
work_id: GAP-098
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-28-gap-098-unseeded-v1-permissions-evidence.md
  plan: docs/superpowers/plans/2026-10-28-gap-098-v1-permission-gap.md
  branch: docs/GAP-098-unseeded-v1-permissions
  pr: https://github.com/kha997/zenamanagephp/pull/379
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-28T21:10:20+07:00"
  updated_at: "2026-10-28T21:10:20+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-098 at subject 2d478c15e5ce83f9f636aabe58715bdfee8453c0 (Gate 2 Option A + Owner amendment 1). Routes: project.edit->project.update (2), component.edit->component.update (1), component.view->component.read (3, amendment 1), change_request.view|stats->change_request.read (4), change_request.edit->change_request.update (1). New V1PermissionGapSeeder creates 27 codes with additive default grants (Admin all; PM 11; accountant 2; viewer 2) and is registered in DatabaseSeeder. New RouteRbacCodesSeededTest (seeds DatabaseSeeder's RBAC block, every dotted rbac code on a route must exist): red before listing 33 codes, green after. New V1PermissionGapTest (seeded non-super-admin roles): red before 2 of 3 (403, 403), green after 3/3. Full Feature + Unit 3590 tests, 5 failures identical to the GAP-021 baseline (2 root-only, 3 suite-order leaks); PHPStan clean, php-cs-fixer 0, composer ssot:lint passes, route guard OK, deptrac 0. Exact-head CI at 2d478c15: all checks green except browser-tests still running at packet time."
technical_evidence:
  base_sha: "c1420a3e9b41c94c55fd57465db3ede105dfb660"
  subject_sha: "2d478c15e5ce83f9f636aabe58715bdfee8453c0"
  implementation_tree_digest: "ceff94aace46fbc3cfcd0d6070a83852b2e6f00b7022133284204af3354b0e72"
  verified_pr_head_sha: "2d478c15e5ce83f9f636aabe58715bdfee8453c0"
  verified_at: "2026-10-28T21:10:20+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-098 — Gate 3 release decision (unseeded `/api/v1` permission codes)

## OWNER GATE 3: AWAITING OWNER

## Gói quyết định phát hành

**1. Vấn đề là gì?** Các route `/api/v1` đòi mã quyền không ai tạo, nên chỉ super admin dùng được (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A + Owner amendment 1)** — subject `2d478c15`:
- **Đổi tên 6 mã trên 11 route** sang mã đã có: `project.edit`→`project.update`, `component.edit`→`component.update`,
  `component.view`→`component.read` (amendment 1), `change_request.view` / `.stats`→`change_request.read`,
  `change_request.edit`→`change_request.update`.
- **Seeder mới tạo 27 mã** và gán mặc định: Admin tất cả; PM 11 mã (baseline, nộp CR, xem/ước tính/thống kê compensation,
  tạo/sửa mẫu, xem người dùng); kế toán 2; thành viên dự án 2. Quản trị vai trò/quyền chỉ Admin.
- **Test bảo vệ:** mọi mã quyền route đòi phải được seeder tạo — lỗi kiểu GAP-021 / 098 sẽ làm CI đỏ.

**3. Bằng chứng** — base `c1420a3e`; subject `2d478c15e5ce83f9f636aabe58715bdfee8453c0`;
digest `ceff94aace46fbc3cfcd0d6070a83852b2e6f00b7022133284204af3354b0e72`.
- **Đỏ trước:** test bảo vệ liệt kê 33 mã thiếu; test hành vi hỏng 2/3 (403 khi PM xem baseline, 403 khi Admin xem danh
  sách vai trò).
- **Xanh sau:** test bảo vệ xanh; test hành vi 3/3.
- Toàn bộ Feature + Unit: 3590 test, 5 hỏng — **đúng 5 test đã hỏng sẵn** như lúc GAP-021 (2 do chạy bằng root, 3 do rò
  dữ liệu theo thứ tự test).
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch. CI trên `2d478c15`: xanh hết, chỉ còn browser-tests đang
  chạy lúc lập hồ sơ.

**4. Công khai**
- Owner amendment 1: thêm `component.view`→`component.read` (3 route) — khảo sát đếm nhầm vì mã này chỉ có trong seeder
  dành cho test. Tổng thành 33 mã / 72 route.
- **Phát hiện thêm, không sửa (ngoài phạm vi):** vài route v1 vẫn hỏng dù đã qua phân quyền —
  `PATCH /api/v1/projects/{id}` trả 500 (gọi `Project::STATUSES` không tồn tại); nhóm change request v1 hỏng (route chỉ
  nhận id số trong khi dữ liệu dùng ULID, thiếu method `getStatistics`, chữ ký `show` lệch). Ngoài ra nhóm change request
  v1 còn kiểm tra vai trò theo tên dạng `project_manager`, không nhận tên đã seed `Project Manager`. Đề xuất ghi thành GAP
  riêng.
- Vì vậy test hành vi không phủ change request v1; việc đổi tên mã cho nhóm đó được test bảo vệ kiểm tra.
- Môi trường đã có dữ liệu cần chạy `php artisan db:seed --class=V1PermissionGapSeeder` để có 27 mã; PR này không chạy.

**5. Ngoài phạm vi** — sửa các route v1 hỏng ở trên; nhóm `rbac:admin`; deploy / chạy seeder trên môi trường thật.

**6. Rủi ro còn lại** — Thấp. Vai trò được thêm quyền mới đúng như bảng Gate 2 (chỉ cộng, không gỡ).

**7. Hoàn tác** — Revert squash commit; mã đã seed (nếu có) để lại vô hại hoặc xoá tay.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-098 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, chạy seeder trên môi trường thật hay sửa các route v1 hỏng.
