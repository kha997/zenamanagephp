---
work_id: GAP-063
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
  spec: docs/audits/2026-10-06-gap-063-treasury-runtime-readiness-audit.md
  plan: docs/superpowers/plans/2026-10-06-gap-063-treasury-s1-foundation-implementation.md
  branch: docs/GAP-063-treasury-runtime-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/336
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-06T23:10:38+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-06: 'APPROVE GAP-063 Gate 3'. Given after the packet (including the two disclosed files beyond the Gate-2 allowlist and the no-Finance-role-in-default-seeding note) was presented at PR head 71c1fc2baa47e9181ce179f669b75c61b28dbbe9 with 33/33 exact-head checks green; bound to implementation subject ba15d6837b3148c568b9e3b9510213a025433b30 and implementation-tree digest 52ba46313d71d5d200710eab794944eba9ae727611291d33d3cae0177801f507 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-06T22:23:55+07:00"
  updated_at: "2026-10-06T23:10:38+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Treasury S1 at subject ba15d683: 16 treasury.* codes + alias-based role defaults, TreasuryPolicy (tenant + code + all_projects-or-membership), parties/wallets API and operator pages; 44 new feature tests green; related suites green locally (276 + 515 tests); composer ssot:lint parts, governance lint and gate ordering pass; default DatabaseSeeder run gives System Admin 16, Project Manager 5, Project Member 1 treasury codes; pages verified in a local run; first CI run red on one PHPStan error (fixed), then 33/33 PR checks green; two disclosed files beyond the Gate-2 allowlist; canonical digest computed at subject."
technical_evidence:
  base_sha: "f652ceebfa5f641207138243a4a8e3eba7a2909c"
  subject_sha: "ba15d6837b3148c568b9e3b9510213a025433b30"
  implementation_tree_digest: "52ba46313d71d5d200710eab794944eba9ae727611291d33d3cae0177801f507"
  verified_pr_head_sha: "ba15d6837b3148c568b9e3b9510213a025433b30"
  verified_at: "2026-10-06T22:23:55+07:00"
owner_decision_binding:
  implementation_tree_digest: "52ba46313d71d5d200710eab794944eba9ae727611291d33d3cae0177801f507"
  decision_recorded_at: "2026-10-06T23:10:38+07:00"
---

# GAP-063 — Gate 3 release decision (Treasury S1)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-06, bound to implementation subject
`ba15d6837b3148c568b9e3b9510213a025433b30` and implementation-tree digest `52ba46313d71d5d200710eab794944eba9ae727611291d33d3cae0177801f507`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Treasury đã có thiết kế và bảng dữ liệu nhưng không ai dùng
được (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):**

- **Quyền:** 16 mã `treasury.*` (15 theo spec + `treasury.all_projects`).
  `TreasuryRolePermissionSeeder` cấp mặc định theo mọi tên vai trò tương
  đương: X (System Admin/Admin/super_admin/system_admin) đủ 16 kể cả tự duyệt;
  kế toán (Finance/accountant) 10; PM/kỹ sư 5; Project Member/Designer/QC/
  quality_inspector/Procurement chỉ xem; Client không có. Chỉ thêm, không gỡ
  quyền đã cấp tay.
- **Kiểm tra quyền** (`TreasuryPolicy`): cùng tenant + đúng mã + (`all_projects`
  hoặc thành viên dự án trong `project_user_roles`).
- **Đối tác** (cấp công ty) và **ví theo dự án**: loại theo spec, liên kết
  cùng tenant, không đổi dự án của ví, chỉ xoá khi chưa được tham chiếu ở bất kỳ
  cột nào của schema v17.
- **API** `/api/zena/treasury/parties`,
  `/api/zena/projects/{project}/treasury/wallets`; **web**: nhóm menu "Tài
  chính" (Ngân quỹ, Đối tác tài chính), trang ví của dự án, nút "Ngân quỹ" trên
  trang dự án.

**3. Khác biệt so với Gate 2 (công khai)**

- Thêm `app/Services/Treasury/TreasurySetupService.php` (ngoài danh sách
  file): gom quy tắc dùng chung cho API và web để hai nơi không lệch nhau.
- Sửa `resources/views/components/operator-nav-icon.blade.php` (ngoài danh
  sách): test sẵn có bắt buộc mỗi mục menu có biểu tượng.
- Thêm `HasFactory` vào 2 model Treasury (để dùng factory; không đổi schema).
- Lần CI đầu đỏ ở PHPStan (kiểu closure trong seeder) — đã sửa
  (`189eb729`); khi xem giao diện thấy nút form chưa đúng kiểu — đã sửa
  (`ba15d683`).

**4. Bằng chứng kỹ thuật**

- Base `f652ceeb`; subject `ba15d6837b3148c568b9e3b9510213a025433b30`; digest `52ba46313d71d5d200710eab794944eba9ae727611291d33d3cae0177801f507`; 35 file, chỉ thêm.
- Test mới: `tests/Feature/Treasury/` — seeder (19), policy (6), API (12),
  web (7): 44 test / 143 assertion xanh.
- Bộ liên quan xanh trên máy: menu + kiến trúc + schema Treasury (276); seeder,
  RBAC (gồm GAP-042), quản trị, Zena (515). `find_orphan_test_routes`,
  `lint_tests.sh` (có `rg`), domain-ownership, owner-governance-lint và
  gate ordering đạt.
- Chạy `DatabaseSeeder` mặc định trên CSDL mẫu: System Admin 16 mã, Project
  Manager 5, Project Member 1.
- Xem trực tiếp trên bản chạy local (admin mẫu): danh sách Ngân quỹ, trang ví
  dự án (2 ví, người giữ), trang đối tác, nhóm menu "Tài chính".
- CI exact head `ba15d683`: 33/33 pass (Automated Testing run `37484139344`).

**5. Lưu ý cho Owner**

- **Bộ seed mặc định không có vai trò "Finance"** (chỉ System Admin / Project
  Manager / Project Member). Tài khoản mẫu `finance@zena.local` có cột
  `role = finance` nhưng gắn vai trò Project Member nên hiện **chỉ xem**. Muốn
  kế toán đúng quyền: tạo/gán vai trò "Finance" trong màn hình phân quyền, hoặc
  mở việc riêng để thống nhất bộ vai trò.
- Tên có dấu ngoặc "( )" bị bộ lọc đầu vào sẵn có của hệ thống chặn (400) — hành
  vi cũ, không do việc này.
- Chưa có nhật ký thay đổi cho đối tác/ví (đến S2).

**6. Ngoài phạm vi** — Ghi sổ tiền (S2+), ví chung công ty, số dư, thống nhất
vai trò, deploy. Triển khai thật cần chạy seeder quyền.

**7. Rủi ro còn lại** — Thấp. Chỉ thêm mới; không có migration; dữ liệu thật
chưa có.

**8. Hoàn tác** — Revert squash commit.

**9. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, S2–S6, hay việc thống nhất bộ vai trò.
