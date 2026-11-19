---
work_id: GAP-130
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
  spec: docs/audits/2026-11-20-gap-130-login-419-evidence.md
  plan: null
  branch: docs/GAP-130-login-419
  pr: https://github.com/kha997/zenamanagephp/pull/421
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-20T03:10:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-20, verbatim: 'APPROVE GAP-130 Gate 3'. Chosen option text: 'Merge và deploy qua workflow, kiểm tra trên app.zena.com.vn'. Given on PR #421 after exact-head CI: 34 checks green; Security Vulnerability Scan red on composer audit (pre-existing on main, handled by GAP-127 / PR #419), Owner Governance Lint red only through its live check of that scan. Bound to implementation subject 2b232587bd9ee79098dab1da000b0f0e907cc9f5 and implementation-tree digest d7f504fec9478b02dcb539edb96e0d62703bb2f67a37fdb8e754c6875f9f87c9. Authorizes merge and deployment of the merged SHA via Production Deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-20T02:40:00+07:00"
  updated_at: "2026-11-20T03:10:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-130 at subject 2b232587bd9ee79098dab1da000b0f0e907cc9f5 (implementation commit 401876b1; Gate 2 S-1, amendment 1). login.blade.php: form marked data-single-submit, an inline script disables the submit button on the first submit and prevents a second one (no-JS behaviour unchanged); the email field keeps old('email'). VerifyCsrfToken::handle catches TokenMismatchException only for route login.post on non-JSON requests: an authenticated web user is redirected to intended /app/today; a guest is redirected to /login with an email error ('Phiên đăng nhập đã hết hạn…') and only the email as old input; AuthController::login never runs, so a bad token cannot sign anyone in; every other route and JSON requests rethrow (419). routes/web.php: GET /login redirects an authenticated web user to /app/today. Tests: LoginCsrfRecoveryTest 5 (double submit lands in /app/today and stays signed in; guest with an expired token and the correct password goes back to /login, has an error, keeps the email but not the password, and is still a guest; /logout and JSON /login with a bad token keep 419; signed-in GET /login redirects; the login page carries data-single-submit): 4 red before, green after. Amendment 1: CsrfProtectionTest::test_login_form_requires_csrf_token now asserts redirect to /login, an error and still a guest; AccessibilityTest::test_login_page_accessibility signs out first. Auth + CSRF + accessibility tests 35 green. Local Unit + Feature + Integration 3083 tests: only the 14 known local-environment failures (EvidenceFreshnessLiveCheckTest 5, ZenaInvariantsDiscoveryTest 2, DashboardApiTest 7), identical on main. PHPStan and php-cs-fixer clean. Browser check on a local production-like demo: double-clicking Login sent one POST /login and landed on /app/today; GET /login while signed in went to /app/today. Exact-head CI recorded in the PR."
technical_evidence:
  base_sha: "13a72bf9f0172520abe6b44019ce3a0fe929a125"
  subject_sha: "2b232587bd9ee79098dab1da000b0f0e907cc9f5"
  implementation_tree_digest: "d7f504fec9478b02dcb539edb96e0d62703bb2f67a37fdb8e754c6875f9f87c9"
  verified_pr_head_sha: "2b232587bd9ee79098dab1da000b0f0e907cc9f5"
  verified_at: "2026-11-20T02:40:00+07:00"
owner_decision_binding:
  implementation_tree_digest: "d7f504fec9478b02dcb539edb96e0d62703bb2f67a37fdb8e754c6875f9f87c9"
  decision_recorded_at: "2026-11-20T03:10:00+07:00"
---

# GAP-130 — Gate 3 release decision (no 419 page after a double-submitted login)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-20 ("APPROVE GAP-130 Gate 3"), bound to implementation subject
`2b232587bd9ee79098dab1da000b0f0e907cc9f5` and implementation-tree digest `d7f504fec9478b02dcb539edb96e0d62703bb2f67a37fdb8e754c6875f9f87c9`. Merge with the pre-existing red
Security Vulnerability Scan (GAP-127) and deployment via Production Deployment are authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Đăng nhập thành công nhưng màn hình hiện "419 Page Expired", vì form đăng nhập bị gửi hai lần liền
nhau.

**2. Sau thay đổi (Gate 2 S-1, bổ sung 1)**
- Bấm "Login" xong, nút bị khoá và hiện "Đang đăng nhập…". Không gửi được lần hai.
- Nếu vẫn có lần gửi với mã cũ:
  - đã đăng nhập → vào thẳng app;
  - chưa đăng nhập (trang để quá lâu) → về trang đăng nhập, giữ email, báo "Phiên đăng nhập đã hết hạn…".
- Đã đăng nhập mà mở `/login` → vào thẳng app.
- **Bảo vệ CSRF giữ nguyên:** gửi với mã sai không bao giờ đăng nhập được. Các form khác và API vẫn trả 419 như cũ.

**3. Đã kiểm thử** — base `13a72bf9`; subject `2b232587bd9ee79098dab1da000b0f0e907cc9f5`; digest
`d7f504fec9478b02dcb539edb96e0d62703bb2f67a37fdb8e754c6875f9f87c9`.
- 5 test mới (4 đỏ trước, xanh sau), gồm: gửi mã sai kèm **đúng mật khẩu** vẫn **không** đăng nhập được.
- Bổ sung 1: sửa 2 test cũ theo hành vi mới. Test đăng nhập, CSRF, khả năng tiếp cận: 35 xanh.
- Toàn bộ 3.083 test: chỉ 14 lỗi môi trường máy local đã biết (giống trên main).
- PHPStan, php-cs-fixer sạch.
- Trên trình duyệt (bản demo): bấm đúp "Login" → chỉ một lần gửi, vào "Hôm nay".

**4. Lưu ý vận hành** — Không có. Deploy qua workflow như thường lệ.

**5. Ngoài phạm vi** — 419 ở các form khác; thời gian phiên.

**6. Rủi ro còn lại** — Thấp.

**7. Hoàn tác** — Revert PR.

**8. Đề xuất** — Phát hành (merge) và deploy.

**9. CI trên đúng head** — ghi nhận trong PR trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-130 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

419 ở các form khác.
