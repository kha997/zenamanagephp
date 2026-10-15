---
work_id: GAP-079
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
  spec: docs/audits/2026-10-14-gap-079-roleseeder-permission-name-evidence.md
  plan: docs/superpowers/plans/2026-10-14-gap-079-roleseeder-permission-name.md
  branch: docs/GAP-079-roleseeder-permission-name
  pr: https://github.com/kha997/zenamanagephp/pull/352
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T07:02:33+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-15, verbatim: 'APPROVE GAP-079 Gate 3'. Given after the packet was presented at PR head a5b612053238edd20d87bbfa2b9f82eef7e4e9a9 (34/34 exact-head checks green after the Owner Governance Lint wait-timeout re-run; implementation CI 34/34 green at 1a8e2e07); bound to implementation subject 1a8e2e0740e5f93f30bca260bdd2e81720e2b440 and implementation-tree digest 25b7cabbd6b6b5c25230ec5c25e5391f1796f503e294d0e4162f43f427b5c678 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T23:15:23+07:00"
  updated_at: "2026-10-15T07:02:33+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-079 at subject 1a8e2e07: RoleSeeder now sets name = code when creating its permissions; new data-only migration 2026_10_14_000000_backfill_null_permission_names (classified expand) sets name = code for every permissions row whose name is NULL, down() is a no-op; AudChangeRequestPermissionSeedingTest no longer allowlists project.read and user.manage; new PermissionNameBackfillMigrationTest; AUD-28 marked fixed in the audit index. Red first: the audit test without the allowlist failed on the unchanged seeder with exactly project.read and user.manage. PHPStan clean; Unit 943 and Feature 1767 run locally with one root-only failure each (same on main), Integration 31 passed; governance, SSOT and docs lints pass; exact-head CI 34/34 green at 1a8e2e07 after one re-run of PerformanceMonitoringTest (a 404 probe took 104 ms against a 100 ms budget, outside this change; disclosed on the PR). The code-quality bot flags pre-existing style issues in RoleSeeder.php and the audit test (also on main), left unfixed as outside the allowlist; the new migration was formatted with the project cs-fixer config."
technical_evidence:
  base_sha: "c7775a13ebabd22674815cc7fa4c9450fa020847"
  subject_sha: "1a8e2e0740e5f93f30bca260bdd2e81720e2b440"
  implementation_tree_digest: "25b7cabbd6b6b5c25230ec5c25e5391f1796f503e294d0e4162f43f427b5c678"
  verified_pr_head_sha: "1a8e2e0740e5f93f30bca260bdd2e81720e2b440"
  verified_at: "2026-10-14T23:15:23+07:00"
owner_decision_binding:
  implementation_tree_digest: "25b7cabbd6b6b5c25230ec5c25e5391f1796f503e294d0e4162f43f427b5c678"
  decision_recorded_at: "2026-10-15T07:02:33+07:00"
---

# GAP-079 — Gate 3 release decision (tên quyền NULL do RoleSeeder)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-15 ("APPROVE GAP-079 Gate 3"), bound to implementation subject
`1a8e2e0740e5f93f30bca260bdd2e81720e2b440` and implementation-tree digest `25b7cabbd6b6b5c25230ec5c25e5391f1796f503e294d0e4162f43f427b5c678`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** `RoleSeeder` tạo `project.read` và `user.manage` không
có `name`; sau khi seed, 2 quyền này có `name = NULL`, nên
`User::hasPermission('project.read')` trả về sai (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):**

- `RoleSeeder` đặt `name = code`.
- Migration mới chỉ sửa dữ liệu: điền `name = code` cho mọi quyền đang NULL
  (database đã seed trước đây cũng được sửa). `down()` không làm gì.
- Test audit bỏ ngoại lệ; thêm test cho migration. AUD-28 được đánh dấu đã sửa.

**3. Bằng chứng**

- Base `c7775a13`; subject `1a8e2e0740e5f93f30bca260bdd2e81720e2b440`; digest `25b7cabbd6b6b5c25230ec5c25e5391f1796f503e294d0e4162f43f427b5c678`.
- **Đỏ trước:** test audit (bỏ ngoại lệ) lỗi đúng `project.read`,
  `user.manage` trên seeder cũ; xanh sau khi sửa.
- PHPStan sạch. Test trên máy: Unit 943, Feature 1767 (mỗi bộ 1 lỗi chỉ do
  chạy bằng root, cũng lỗi trên main), Integration 31 đạt.
- Lint governance, SSOT, docs đạt; CI exact head `1a8e2e0` 34/34 xanh.

**4. Công khai**

- Test hiệu năng `PerformanceMonitoringTest` lỗi một lần (404 mất 104 ms,
  ngưỡng 100 ms) — không liên quan thay đổi; đã ghi trên PR, chạy lại 1 lần
  và đạt.
- Bot code style báo `RoleSeeder.php` và test audit có lỗi định dạng có sẵn
  (main cũng vậy); không sửa vì ngoài allowlist. Migration mới đã định dạng
  đúng.

**5. Ảnh hưởng** — Khi deploy, migration sẽ điền tên cho các quyền đang NULL
trên database thật. Không đổi vai trò hay gán quyền. Không deploy trong gate
này.

**6. Rủi ro còn lại** — Thấp: chỉ điền giá trị vào ô đang trống, bằng đúng
giá trị `PermissionSeeder` vẫn ghi; cột `name` không có unique.

**7. Hoàn tác** — Revert squash commit (tên đã điền vẫn giữ, vô hại).

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-079 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy.
