---
work_id: GAP-030
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
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
  recorded_at: "2026-10-29T16:17:08+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-29, verbatim: 'APPROVE GAP-030 Gate 3'. Given on the packet at PR head 2ec9994f7a9b925fed857280c0dad65e0a66af5c; bound to implementation subject c73fcf717c2b83e83d3b8abc6d865fe29e128bf8 and implementation-tree digest 8ab4ed924a03d7fbd5fdb2c22b2c0a403e3f2a3ae6b9d56a999db8cea3a20bf3 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment, environment seeding or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T15:09:19+07:00"
  updated_at: "2026-10-29T16:17:08+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-030 at subject c73fcf717c2b83e83d3b8abc6d865fe29e128bf8 (Gate 2 Option A). rfis.resolve-escalation uses rbac:rfi.resolve-escalation (escalate keeps rbac:rfi.escalate); RfiController unchanged. ZenaPermissionsSeeder adds rfi.resolve-escalation (System Admin via canonical list). ZenaProjectManagerRolePermissionSeeder matches project_manager or 'Project Manager', grants PM rfi.escalate/cancel/resolve-escalation and Project Member rfi.resolve-escalation. Red before 3 (target resolve 403, escalate-only test, seeded role-name test), green after; scratch DatabaseSeeder run: System Admin all RFI codes incl. resolve-escalation, Project Manager escalate/resolve-escalation/cancel, Project Member resolve-escalation. RouteRbacCodesSeededTest and route-security invariants green. Unit + Feature + Integration 2845 tests, 2 known root-only failures; tests/Feature/Api 824/824. PHPStan clean, php-cs-fixer 0, composer ssot:lint and lint_tests pass, route guard OK. Exact-head CI at c73fcf71: all green except test and browser-tests still running at packet time."
technical_evidence:
  base_sha: "23a909459b8161c613bdceabc6d52029b33d440c"
  subject_sha: "c73fcf717c2b83e83d3b8abc6d865fe29e128bf8"
  implementation_tree_digest: "8ab4ed924a03d7fbd5fdb2c22b2c0a403e3f2a3ae6b9d56a999db8cea3a20bf3"
  verified_pr_head_sha: "c73fcf717c2b83e83d3b8abc6d865fe29e128bf8"
  verified_at: "2026-10-29T15:09:19+07:00"
owner_decision_binding:
  implementation_tree_digest: "8ab4ed924a03d7fbd5fdb2c22b2c0a403e3f2a3ae6b9d56a999db8cea3a20bf3"
  decision_recorded_at: "2026-10-29T16:17:08+07:00"
---

# GAP-030 — Gate 3 release decision (RFI escalation resolve permission)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-29 ("APPROVE GAP-030 Gate 3"), bound to implementation subject
`c73fcf717c2b83e83d3b8abc6d865fe29e128bf8` and implementation-tree digest `8ab4ed924a03d7fbd5fdb2c22b2c0a403e3f2a3ae6b9d56a999db8cea3a20bf3`. No deployment, environment seeding or
release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Người nhận leo thang RFI không xử lý được vì API dùng nhầm quyền `rfi.escalate`; seed mặc định để PM
không có quyền RFI nào (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — subject `c73fcf71`:
- API xử lý leo thang dùng quyền mới `rfi.resolve-escalation`; luật "người nhận / PM / admin" giữ nguyên.
- Seed mặc định: System Admin có quyền mới; **Project Manager** có lại `rfi.escalate`, `rfi.cancel` và quyền mới;
  **Project Member** có quyền mới.

**3. Bằng chứng** — base `23a90945`; subject `c73fcf717c2b83e83d3b8abc6d865fe29e128bf8`;
digest `8ab4ed924a03d7fbd5fdb2c22b2c0a403e3f2a3ae6b9d56a999db8cea3a20bf3`.
- **Đỏ trước:** 3 test (người nhận chỉ có quyền mới bị 403; người chỉ có `rfi.escalate` vẫn xử lý được; seed role tên
  `Project Manager` không có quyền). **Xanh sau:** cả 3.
- Seed thử trên DB tạm: bảng quyền đúng như mục 2.
- Test bất biến bảo mật route và test "mọi mã quyền route đều được seed" (GAP-098) xanh.
- Toàn bộ Unit + Feature + Integration: 2.845 test, chỉ 2 test hỏng sẵn do chạy bằng root; `tests/Feature/Api` 824/824.
- PHPStan, php-cs-fixer, SSOT lint, route guard sạch. CI trên `c73fcf71`: xanh hết, còn `test` và browser-tests đang
  chạy lúc lập hồ sơ.

**4. Công khai**
- Người chỉ có `rfi.escalate` (không có quyền mới) **không còn** xử lý leo thang được — đúng thiết kế; với seed mặc định,
  mọi role có `rfi.escalate` cũng có quyền mới.
- Môi trường đã có dữ liệu cần chạy lại, theo thứ tự: `ZenaPermissionsSeeder`, **`ZenaAdminRolePermissionSeeder`** (cấp
  quyền mới cho role admin — thiếu bước này thì admin không phải super_admin sẽ mất quyền xử lý leo thang; Codex chỉ ra),
  rồi `ZenaProjectManagerRolePermissionSeeder` (đều chỉ thêm quyền); PR này không chạy.

**5. Ngoài phạm vi** — đổi luật xử lý; giao diện web; role khác; deploy / seed môi trường thật.

**6. Rủi ro còn lại** — Thấp: chỉ đổi quyền ở 1 route và thêm quyền mặc định.

**7. Hoàn tác** — Revert squash commit; quyền đã seed để lại vô hại hoặc xoá tay.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-030 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, chạy seeder trên môi trường thật hay release.
