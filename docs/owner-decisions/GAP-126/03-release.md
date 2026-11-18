---
work_id: GAP-126
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
  spec: docs/audits/2026-11-18-gap-126-production-web-login-evidence.md
  plan: null
  branch: docs/GAP-126-production-web-login
  pr: https://github.com/kha997/zenamanagephp/pull/417
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-18T16:45:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-18, verbatim: 'APPROVE GAP-126 Gate 3'. Chosen option text: 'Phát hành: merge dù Security Scan đỏ sẵn (không phải check bắt buộc), rồi deploy SHA mới qua workflow. Lỗi dependency ghi thành GAP riêng.' Given on PR #417 head d59afcb5 after exact-head CI: every test, MySQL and route check green (Build Dockerfile green on rerun after a pecl download failure); Security Vulnerability Scan red, identical failure already on main (nightly 2026-11-17, composer audit advisories; plus npm GHSA-fc5q-39xc-vjpf picomatch), PR touches no dependency file; Owner Governance Lint red only through its live check of that scan. Bound to implementation subject 467a5addd4f85d85b821424183eed18962225224 and implementation-tree digest e46ecd9a17512573be70125adb18f052740fb5f7b19e609b14e0cb3fbba98c6b. Authorizes merge and deployment of the merged SHA via Production Deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-18T16:20:00+07:00"
  updated_at: "2026-11-18T16:45:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-126 at subject 467a5addd4f85d85b821424183eed18962225224 (implementation commit 1a58ac84; Gate 2 A-1, B-1, amendment 1). A-1: routes/web.php registers login, login.post, password.reset, logout and logout.post in every environment with unchanged names; only local.dev-login.operator stays inside the local/testing block. B-1: AuthController::login() calls Auth::attempt with is_active = true, so a deactivated account gets the same error as a wrong password and counts toward the existing 5/min limit; the local/testing demo fallback is unchanged. Amendment 1: production.yml writes the release tarball to RUNNER_TEMP (first real run 44270113158 failed with 'tar: .: file changed as we read it'). New ProductionWebLoginTest (5): route:list under APP_ENV=production has the five routes and no dev login; dev login still exists under local; active login redirects to /app/today; deactivated login refused with the same error as a wrong password; guest /app/today redirects to /login. Red before (2 failures: missing production route, deactivated user signed in), green after. Related suites green: Feature/Auth, AuthControllerDemoLoginProductionGuardTest, CsrfProtectionTest, RouteNameCollisionInvariantTest, DebugRouteBoundaryInvariantTest (40 tests). Local Unit + Feature + Integration 3065 tests: 14 failures, all in EvidenceFreshnessLiveCheckTest (5), ZenaInvariantsDiscoveryTest (2) and DashboardApiTest (7), identical with main's routes/web.php and AuthController (local environment, not GAP-126). Production Deployment dispatched from this branch with main SHA ca7a878b: run 44271685624 health_verified (readiness 200, queue canary completed by a real worker). Exact-head CI recorded in the PR."
technical_evidence:
  base_sha: "ca7a878b31cf3ef80fa56523b5f7a78a40a01d43"
  subject_sha: "467a5addd4f85d85b821424183eed18962225224"
  implementation_tree_digest: "e46ecd9a17512573be70125adb18f052740fb5f7b19e609b14e0cb3fbba98c6b"
  verified_pr_head_sha: "467a5addd4f85d85b821424183eed18962225224"
  verified_at: "2026-11-18T16:20:00+07:00"
owner_decision_binding:
  implementation_tree_digest: "e46ecd9a17512573be70125adb18f052740fb5f7b19e609b14e0cb3fbba98c6b"
  decision_recorded_at: "2026-11-18T16:45:00+07:00"
---

# GAP-126 — Gate 3 release decision (web login in production)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-18 ("APPROVE GAP-126 Gate 3"), bound to implementation subject
`467a5addd4f85d85b821424183eed18962225224` and implementation-tree digest `e46ecd9a17512573be70125adb18f052740fb5f7b19e609b14e0cb3fbba98c6b`.
Merge with the pre-existing red Security Vulnerability Scan (same failure on main since 2026-11-17, no dependency file in
this PR) and deployment of the merged SHA are authorized. The dependency advisories go to a separate GAP.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Trên máy chủ thật không có trang đăng nhập web:
- `/login` trả 404;
- `/app/today` báo lỗi 500 ("Route [login] not defined").

Không ai đăng nhập được.

**2. Sau thay đổi (Gate 2 A-1, B-1, bổ sung 1)**
- **A-1:** `/login`, đăng xuất và "quên mật khẩu" chạy ở mọi môi trường, tên route giữ nguyên. Đường đăng nhập không cần
  mật khẩu (`/local/dev-login/operator`) vẫn chỉ có ở máy dev.
- **B-1:** tài khoản đã khoá (`is_active = false`) không đăng nhập web được. Báo cùng lỗi như sai mật khẩu và vẫn tính vào
  giới hạn 5 lần / phút.
- **Bổ sung 1:** sửa bước đóng gói của workflow **Production Deployment**. Lần chạy thật đầu tiên bị lỗi
  `file changed as we read it`; giờ file nén được ghi ra thư mục tạm rồi mới chuyển về.

**3. Đã kiểm thử** — base `ca7a878b`; subject `467a5addd4f85d85b821424183eed18962225224`; digest
`e46ecd9a17512573be70125adb18f052740fb5f7b19e609b14e0cb3fbba98c6b`.

**Test**
- Test mới `ProductionWebLoginTest` (5 test), đỏ trước khi sửa, xanh sau:
  - `route:list` chạy với `APP_ENV=production` có đủ 5 route, không có route dev-login;
  - route dev-login vẫn có ở `local`;
  - đăng nhập đúng → `/app/today`;
  - tài khoản khoá → bị từ chối, cùng lỗi như sai mật khẩu;
  - chưa đăng nhập mở `/app/today` → chuyển về `/login`.
- 40 test liên quan (đăng nhập, CSRF, chặn tài khoản demo ở production, bất biến route) đều xanh.
- Toàn bộ 3.065 test: 14 lỗi, nằm ở 3 file (`EvidenceFreshnessLiveCheckTest`, `ZenaInvariantsDiscoveryTest`,
  `DashboardApiTest`). Chạy lại 3 file đó với code của main cho ra đúng 14 lỗi đó, nên chúng do môi trường máy local,
  không do GAP-126.

**Deploy thử**
- Workflow chạy từ nhánh này với SHA của main (`ca7a878b`), run 44271685624.
- Kết quả `health_verified`: readiness 200, queue canary được worker thật xử lý.

**4. Lưu ý vận hành**
- Sau khi merge, deploy SHA mới qua **Production Deployment**, rồi làm runbook go-live mục 5–7.
- "Quên mật khẩu" chưa gửi email: cấp lại mật khẩu bằng `php artisan production:user --email=<email> --reset-password`.

**5. Ngoài phạm vi**
- Gửi email quên mật khẩu.
- Giao diện trang đăng nhập.
- Websocket: `websocket:serve` không chạy được vì thiếu thư viện Ratchet. Sẽ ghi thành GAP riêng.

**6. Rủi ro còn lại** — Thấp. Chỉ route, một điều kiện đăng nhập và một bước đóng gói.

**7. Hoàn tác** — Revert PR.

**8. Đề xuất** — Phát hành (merge), rồi deploy.

**9. CI trên đúng head** — ghi nhận trong PR trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-126 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Nội dung GAP websocket.
