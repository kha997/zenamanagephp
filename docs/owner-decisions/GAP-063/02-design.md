---
work_id: GAP-063
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-06-gap-063-treasury-runtime-readiness-audit.md
  plan: null
  branch: docs/GAP-063-treasury-runtime-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/336
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-06T19:51:30+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-06: 'APPROVE GAP-063 Gate 2 Option A'. Reviewed design head: cc82ec8c800cb37ec455ba1f7e8abfd368c15a80. Approves Option A and its exact allowlist, including the two flagged points (alias-based role defaults across the inconsistent role catalogue; Client gets no Treasury access) and the 16th code treasury.all_projects; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-06T18:37:44+07:00"
  updated_at: "2026-10-06T19:51:30+07:00"
generated_by: agent
---

# GAP-063 — Treasury S1 foundation: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-06 against reviewed design head
`cc82ec8c800cb37ec455ba1f7e8abfd368c15a80`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Thiết kế lát S1: (1) bộ quyền `treasury.*` và quyền mặc định theo vai trò như
Owner đã trả lời; (2) kiểm tra quyền theo tenant **và** thành viên dự án; (3)
quản lý **đối tác tài chính** (cấp công ty) và **ví theo dự án**; (4) màn hình
web "Ngân quỹ" + API. Chưa có ghi sổ tiền (S2).

**Hai điểm cần Owner xác nhận khi duyệt:**

- **Tên vai trò thực tế không thống nhất.** Bộ seed mặc định của hệ thống tạo
  vai trò "System Admin / Project Manager / Project Member"; bộ khác tạo "Admin /
  PM / SiteEngineer / Finance…"; tài khoản mẫu dùng "project_manager /
  site_engineer / finance…". Thiết kế gán quyền theo **tất cả các tên tương
  đương** (không phân biệt hoa thường) để câu trả lời của Owner đúng ở mọi nơi;
  quản trị viên vẫn chỉnh được trong màn hình phân quyền.
- **Khách hàng (Client) không được xem Ngân quỹ.** Câu trả lời trước là "các vai
  trò khác chỉ xem khi là thành viên dự án"; thiết kế loại riêng Client vì đây
  là tiền nội bộ (ví cá nhân, tạm ứng). Nếu Owner muốn Client xem, chọn
  "Request changes".

Đề xuất **Phương án A**.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Codes + alias-based role defaults + policy with membership + parties/wallets web & API** | As below | **Recommended** |
| B. Same, but only the canonical ZenaRbacSeeder role names | Simpler | Owner's answer would not apply to the roles the default seeder actually creates |
| C. API only | No web | Contradicts Owner answer 3 |

## Design: Option A (exact allowlist)

### 1. Permission codes (16)

Spec §9's 15 codes — `treasury.view`, `manage_parties`, `manage_wallets`,
`declare_funding`, `create_transfer`, `create_expense`, `submit_expense`,
`approve_expense`, `self_approve_expense`, `reconcile`, `reverse`,
`adjust`, `view_audit`, `export`, `manage_period_lock` — plus
**`treasury.all_projects`** (see §3). All registered now so later slices only
use them; S1 itself enforces `view`, `manage_parties`, `manage_wallets`,
`all_projects`.

Registered in `ZenaPermissionsSeeder::CANONICAL_PERMISSIONS` and in
`ZenaRbacSeeder`'s code list (same pattern as `design-item.*`).

### 2. Role defaults (Owner answers 1)

| Holder | Role names matched (case-insensitive) | Codes |
|---|---|---|
| X — owner/director | existing admin aliases (`System Admin`, `Admin`, `super_admin`, `system_admin`) via `ZenaAdminRolePermissionSeeder` (gets every canonical code) | all 16 |
| Accountant | `Finance`, `finance`, `accountant` | view, all_projects, manage_parties, manage_wallets, approve_expense, reconcile, reverse, adjust, view_audit, export |
| Z — PM / site engineer | `PM`, `Project Manager`, `project_manager`, `SiteEngineer`, `site_engineer` | view, declare_funding, create_transfer, create_expense, submit_expense |
| Viewers | `Project Member`, `project_member`, `Designer`, `designer`, `QC`, `qc`, `quality_inspector`, `Procurement`, `procurement` | view |
| Client | — | none |

New `database/seeders/TreasuryRolePermissionSeeder.php` (pattern of
`ZenaProjectManagerRolePermissionSeeder`: `syncWithoutDetaching`, idempotent,
never removes existing grants), called from `DatabaseSeeder` after
`ZenaAdminRolePermissionSeeder` and from `ZenaRbacSeeder`. Self-approval
(`self_approve_expense`) is held by X only.

### 3. Access rule — `App\Policies\TreasuryPolicy` (+ wallet/party abilities)

A user may act on a project's Treasury only if **all** hold:
1. same tenant as the project/record;
2. holds the action's `treasury.*` code;
3. holds `treasury.all_projects` **or** is an active member of the project
   (row in `project_user_roles` with `deleted_at` NULL — the canonical
   membership table).

Parties are tenant-scoped (no project): rules 1–2 only.

### 4. Parties (tenant level)

- Fields per approved schema: `party_type`, `name`, optional
  `linked_user_id` (user of the same tenant), optional `linked_account_id`
  (CRM account of the same tenant).
- `party_type` ∈ investor, intermediary, owner, employee, labour, supplier,
  subcontractor, authority, other (PR #245 §7.1, validated in the app).
- Create / edit / list; delete only when no wallet (or later document)
  references it — otherwise a clear error.

### 5. Wallets (project level only — Owner answer 2)

- `project_id` required in S1 (company wallets later); `wallet_type` ∈
  company_bank, company_cash, owner_personal, employee_cash, employee_bank,
  intermediary_control, other (PR #245 §7.2); `name`; optional custodian party
  (same tenant).
- `project_id` immutable after create; delete only when unreferenced.
- No balance column (balances are derived in S2).

### 6. Web UI (Owner answer 3) — operator layout

- Nav group **"Tài chính"**: "Ngân quỹ" → `/operator/treasury` (projects the
  user may access, each linking to its page) and "Đối tác tài chính" →
  `/operator/treasury/parties` (only with `manage_parties`).
- `/operator/projects/{project}/treasury`: wallet list + create/edit/delete
  forms; placeholder "Số dư và giao dịch sẽ có ở bước tiếp theo".
- A "Ngân quỹ" link on the project page header when the user may view.
- Routes guarded by `auth`, `tenant.isolation`, `rbac:treasury.view` and the
  policy.

### 7. API (`routes/api_zena.php`, existing auth/tenant/envelope group)

`GET|POST /treasury/parties`, `GET|PUT|DELETE /treasury/parties/{party}`,
`GET|POST /projects/{project}/treasury/wallets`,
`GET|PUT|DELETE /projects/{project}/treasury/wallets/{wallet}`.
Cross-tenant ids → 404; no permission/membership → 403.

### 8. Files (allowlist)

Seeders (`ZenaPermissionsSeeder`, `ZenaRbacSeeder`, `DatabaseSeeder`, new
`TreasuryRolePermissionSeeder`); `app/Policies/TreasuryPolicy.php` + its
registration; request/controller classes under `App\Http\Controllers\Api\Treasury`
and `App\Http\Controllers\Web\Treasury`; Blade views under
`resources/views/treasury/`; `OperatorNavigationDefinition` (2 items); project
page header link; route files; model factories for the two models (SSOT lint
requires factories); tests; `docs/superpowers/plans/…gap-063…` (governed
plan). **No migration, no change to Treasury models' schema, no ledger code.**

### 9. Tests (TDD)

- Seeder: codes exist; each role alias gets exactly its codes; Client none;
  rerun idempotent and never removes other grants.
- Policy matrix: role × member/non-member × same/other tenant for view and
  manage abilities.
- API + web feature tests: CRUD happy paths, validation (types, same-tenant
  links/custodian), 404 cross-tenant, 403 non-member, delete-when-referenced
  blocked, `project_id` immutability.
- Nav: items visible only with the right codes.

## Verification required at Gate 3

All new tests green locally and in CI (incl. MySQL jobs and SSOT lint); full
exact-head CI green; screenshots of the three pages.

## Risks

Role-name aliasing is a workaround for the inconsistent role catalogue (not
fixed here; worth its own Work ID). No audit trail for party/wallet edits in
S1 (Treasury audit arrives with posting in S2).

## Out of scope

Ledger/posting (S2+), company wallets, balances, audit trail, role catalogue
clean-up, deployment.
