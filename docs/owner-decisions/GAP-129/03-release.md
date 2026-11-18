---
work_id: GAP-129
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
  spec: docs/audits/2026-11-18-gap-129-admin-ui-evidence.md
  plan: docs/superpowers/plans/2026-11-18-gap-129-admin-ui.md
  branch: docs/GAP-129-admin-ui
  pr: https://github.com/kha997/zenamanagephp/pull/420
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-18T23:45:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-18, verbatim: 'APPROVE GAP-129 Gate 3'. Chosen option text: 'Merge, deploy SHA mới qua workflow, chạy production:rbac, kiểm tra trên app.zena.com.vn'. Given on PR #420 after exact-head CI: 34 checks green; Security Vulnerability Scan red with the known picomatch GHSA-fc5q-39xc-vjpf advisory (also red on main, handled by GAP-127 / PR #419), Owner Governance Lint red only through its live check of that scan. Bound to implementation subject 51ac55ca92fe73bc8b8bfb23991ae6b9caef56ce and implementation-tree digest 774d1151a83fda6a1d8f58c348126df1f2db27bb12998cb47c28f1420240a6b2. Authorizes merge, deployment of the merged SHA via Production Deployment, and one production:rbac run."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-18T23:20:00+07:00"
  updated_at: "2026-11-18T23:45:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-129 at subject 51ac55ca92fe73bc8b8bfb23991ae6b9caef56ce (implementation commit 2c03bfb3; Gate 2 L-1, U-1, R-1, C-1, amendments 1-2). L-1: sidebar section 'Quản trị' (Tài khoản, Vai trò, Công ty) and routes /app/admin/* in the canonical /app group (auth, tenant.isolation), all gated rbac:admin.company.manage; /admin/users and /admin/tenants redirect to app.admin.users / app.admin.company; mock views admin/users.blade.php and admin/tenants.blade.php deleted. U-1: UserAdminController + StaffAccountService (create with a canonical role and a 20-character one-time password flashed once; change role; deactivate / reactivate; reset password; audit rows production.user.created, production.user.password_reset, admin.user.role_changed, admin.user.deactivated, admin.user.reactivated with source web and the actor; no self-deactivation or self-demotion; the company keeps one active super_admin; users of another company 404). EnsureUserIsActive appended to the web middleware group signs a deactivated user out on the next request. production:user delegates create / reset to the service with unchanged output and audit. R-1: RoleAdminController lists the 5 canonical roles with description, company members and permissions grouped by module, read-only. C-1: CompanyAdminController shows the own company and renames it (audit admin.company.renamed), slug unchanged. Amendment 1: new canonical permission admin.company.manage (ZenaPermissionsSeeder), received only by the admin roles via ZenaAdminRolePermissionSeeder; Project Manager holds user.read / user.manage_roles / settings.general.update, so those codes would have let a PM promote themselves. Amendment 2: OrphanAdminViewsRemovedTest asserts the two mock views are removed; the *-content partials stay because the unrouted admin-layout includes them. Tests: tests/Feature/Admin 13 tests red on main's routes and kernel (12 errors, 1 failure), green after; with ProductionUserCommandTest, Auth and Zena invariants 407 tests green. Local Unit + Feature + Integration 3078 tests: 14 failures, the known local-environment ones (EvidenceFreshnessLiveCheckTest 5, ZenaInvariantsDiscoveryTest 2, DashboardApiTest 7), identical on main. PHPStan and php-cs-fixer clean on the new files; button inventory has no orphans. Browser check on a local production-like SQLite demo (migrate, production:rbac, production:bootstrap): super_admin 222 permissions, other roles unchanged; created a Finance account and saw the one-time password; roles and company pages render; sidebar section visible; 375 px phone width shows cards, no horizontal overflow. Exact-head CI recorded in the PR."
technical_evidence:
  base_sha: "a3743ac6289e4ba4c21ee6d6352c4922a1628c81"
  subject_sha: "51ac55ca92fe73bc8b8bfb23991ae6b9caef56ce"
  implementation_tree_digest: "774d1151a83fda6a1d8f58c348126df1f2db27bb12998cb47c28f1420240a6b2"
  verified_pr_head_sha: "51ac55ca92fe73bc8b8bfb23991ae6b9caef56ce"
  verified_at: "2026-11-18T23:20:00+07:00"
owner_decision_binding:
  implementation_tree_digest: "774d1151a83fda6a1d8f58c348126df1f2db27bb12998cb47c28f1420240a6b2"
  decision_recorded_at: "2026-11-18T23:45:00+07:00"
---

# GAP-129 — Gate 3 release decision (company admin pages)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-18 ("APPROVE GAP-129 Gate 3"), bound to implementation subject
`51ac55ca92fe73bc8b8bfb23991ae6b9caef56ce` and implementation-tree digest `774d1151a83fda6a1d8f58c348126df1f2db27bb12998cb47c28f1420240a6b2`. Merge with the pre-existing red
Security Vulnerability Scan (GAP-127), deployment via Production Deployment and one `production:rbac` run are authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?**
- Tạo tài khoản, cấp lại mật khẩu: phải SSH chạy lệnh.
- Đổi vai trò, khoá người nghỉ việc: phải sửa database.
- `/admin/users` và `/admin/tenants` hiện dữ liệu giả.

**2. Sau thay đổi (Gate 2 L-1, U-1, R-1, C-1, bổ sung 1–2)**

Mục **"Quản trị"** mới trong thanh bên, chỉ Owner thấy:
- **Tài khoản:**
  - danh sách; tạo tài khoản (mật khẩu ngẫu nhiên hiện **một lần**); đổi vai trò; khoá / mở khoá; cấp lại mật khẩu;
  - mỗi thao tác ghi audit;
  - không tự khoá hay tự hạ vai trò mình; công ty luôn còn ít nhất một Owner đang hoạt động;
  - người bị khoá bị **đăng xuất ngay** ở lần bấm tiếp theo.
- **Vai trò:** 5 vai trò chuẩn, số người, danh sách quyền theo nhóm. Chỉ xem.
- **Công ty:** đổi tên công ty. Mã `slug` giữ nguyên.
- `/admin/users`, `/admin/tenants` chuyển sang trang thật.
- Lệnh `production:user` dùng chung logic, hoạt động như cũ.

**Bổ sung 1:** quyền mới `admin.company.manage`, chỉ Owner có. Vai trò PM có sẵn `user.read`, `user.manage_roles` nên
không dùng các quyền đó được (PM sẽ tự nâng mình lên Owner).

**3. Đã kiểm thử** — base `a3743ac6`; subject `51ac55ca92fe73bc8b8bfb23991ae6b9caef56ce`; digest
`774d1151a83fda6a1d8f58c348126df1f2db27bb12998cb47c28f1420240a6b2`.

**Test**
- 13 test mới: đỏ trên code main, xanh sau khi làm. Có cả:
  - 4 vai trò không phải Owner đều bị chặn;
  - tài khoản công ty khác → 404;
  - người bị khoá bị đăng xuất.
- Cùng các test lệnh `production:user`, đăng nhập, bất biến Zena: 407 test xanh.
- Toàn bộ 3.078 test: chỉ 14 lỗi môi trường máy local đã biết (giống hệt trên main).
- PHPStan, php-cs-fixer sạch; không có nút mồ côi.

**Thử trên trình duyệt** (bản demo giống production)
- Tạo tài khoản kế toán, thấy mật khẩu một lần.
- Trang Vai trò, Công ty hiển thị đúng; mục "Quản trị" hiện trên thanh bên.
- Điện thoại 375px: dạng thẻ, không tràn ngang.

**4. Lưu ý vận hành**
- Sau khi deploy, chạy **một lần** để nạp quyền mới cho Owner:
  ```
  cd /var/www/zena/current && sudo -u deploy php artisan production:rbac
  ```
  Chưa chạy thì Owner vẫn vào được trang (Owner được bỏ qua kiểm tra quyền), nhưng mục "Quản trị" chưa hiện trên thanh
  bên.
- Mật khẩu một lần chỉ hiện trên màn hình. Không gửi email.

**5. Ngoài phạm vi**
- Sửa quyền của vai trò, vai trò tự tạo.
- Nhiều công ty.
- Email mời / quên mật khẩu.
- Các trang giả khác trong `/admin`.

**6. Rủi ro còn lại** — Thấp. Middleware đăng xuất người bị khoá áp dụng cho mọi trang web; toàn bộ test vẫn xanh.

**7. Hoàn tác** — Revert PR. Không có migration. Quyền `admin.company.manage` đã nạp thì để lại vô hại.

**8. Đề xuất** — Phát hành (merge), deploy, rồi chạy `production:rbac`.

**9. CI trên đúng head** — ghi nhận trong PR trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-129 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Sửa quyền vai trò, nhiều công ty (ngoài phạm vi).
