---
work_id: GAP-130
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-20-gap-130-login-419-evidence.md
  plan: null
  branch: docs/GAP-130-login-419
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-20T02:00:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-20, verbatim: 'S-1 (đề xuất)' (answer to the GAP-130 Gate 2 question). Approves S-1 as described in this packet, one PR / one Gate 3, with the exact allowlist in this packet; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-20T01:55:00+07:00"
  updated_at: "2026-11-20T02:00:00+07:00"
generated_by: agent
---

# GAP-130 — Login shows 419 after a successful sign-in: Gate 2 design

## OWNER GATE 2: APPROVED — S-1

Owner approved S-1 in-session on 2026-11-20. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Đề xuất **S-1**, làm cả 3 việc trong một PR:

1. **Chống gửi hai lần:** bấm "Đăng nhập" xong thì nút bị khoá và đổi thành "Đang đăng nhập…".
2. **419 ở trang đăng nhập:**
   - đã đăng nhập rồi → vào thẳng app;
   - chưa đăng nhập (trang để quá lâu) → quay lại `/login`, giữ email đã nhập, báo "Phiên đã hết hạn, vui lòng đăng
     nhập lại".
   Không còn trang "419 Page Expired" ở đây. **Vẫn giữ nguyên bảo vệ CSRF**: lần gửi sai mã không bao giờ đăng nhập được.
3. **Đã đăng nhập mà mở `/login`** → vào thẳng app.

## Phương án

| Phương án | Nội dung |
|---|---|
| **S-1 (đề xuất)** | Cả 3 việc trên. |
| S-2 | Chỉ việc 1 (khoá nút). Vẫn còn trang 419 khi để trang đăng nhập quá lâu, hoặc khi trình quản lý mật khẩu tự gửi. |
| S-3 | Bỏ kiểm tra CSRF cho `/login`. **Không đề xuất:** mở đường cho kẻ xấu ép người dùng đăng nhập vào tài khoản của chúng (login CSRF). |

## Thiết kế chi tiết (theo S-1)

- **`resources/views/auth/login.blade.php`:** một đoạn script nhỏ trong trang. Khi form được gửi, nút submit bị
  `disabled` và đổi nhãn. Lần gửi thứ hai bị chặn. Không có JavaScript thì form vẫn chạy như cũ.
- **`app/Http/Middleware/VerifyCsrfToken.php`:** chỉ với `POST /login` (route `login.post`), request không phải JSON, khi
  mã CSRF không khớp:
  - đã đăng nhập → `redirect()->intended('/app/today')`;
  - chưa đăng nhập → `redirect('/login')` kèm lỗi ở ô email và giữ lại email (không giữ mật khẩu);
  - **không bao giờ** gọi `AuthController::login()`, nên không đăng nhập được bằng request sai mã.
  - Mọi route khác và request JSON giữ nguyên hành vi 419.
- **`routes/web.php`:** `GET /login` khi đã đăng nhập → chuyển hướng `/app/today`.

**Test** `tests/Feature/Auth/LoginCsrfRecoveryTest.php`, đỏ trước, xanh sau. Test bật lại kiểm tra CSRF thật (không dùng
handler riêng của môi trường testing):
- đã đăng nhập, gửi `/login` với mã cũ → chuyển vào `/app/today`, vẫn đăng nhập;
- chưa đăng nhập, mã sai, **đúng mật khẩu** → về `/login`, có lỗi, giữ email, **chưa đăng nhập**;
- route khác với mã sai → vẫn 419;
- request JSON tới `/login` với mã sai → vẫn 419;
- đã đăng nhập mở `/login` → `/app/today`;
- trang đăng nhập có đoạn chống gửi hai lần.

## Files (allowlist)

- `resources/views/auth/login.blade.php`
- `app/Http/Middleware/VerifyCsrfToken.php`
- `routes/web.php`
- `tests/Feature/Auth/LoginCsrfRecoveryTest.php` (mới)
- `docs/owner-decisions/GAP-130/*`, `docs/audits/2026-11-20-gap-130-login-419-evidence.md`

File khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung.

## Owner amendment 1 (approved 2026-11-20)

Owner decision in-session on 2026-11-20, verbatim: 'APPROVE GAP-130 amendment 1'.

The full suite showed two existing tests that encode the behaviour S-1 changes:
- `tests/Feature/CsrfProtectionTest.php::test_login_form_requires_csrf_token` expected HTTP 419. It now asserts the
  S-1 outcome: redirect to `/login`, an error, and still a guest (CSRF protection unchanged).
- `tests/Feature/Accessibility/AccessibilityTest.php::test_login_page_accessibility` opened `/login` while signed in
  (its `setUp` signs a user in). It now signs out first and checks the page as a guest.

Both files are added to the allowlist; only these two tests change.

## Ngoài phạm vi

- 419 ở các form khác.
- Thời gian phiên đăng nhập.
- Giao diện trang đăng nhập (ngoài nhãn nút khi đang gửi).
- Deploy.

## Verification required at Gate 3

- Test mới đỏ trước, xanh sau; toàn bộ Unit + Feature + Integration và các lint.
- Trên trình duyệt: bấm "Đăng nhập" hai lần liền → vào app, không có trang 419.

## Rollback

Revert PR.

## Decision Needed

Owner chọn: `APPROVE GAP-130 Gate 2 S-1` (hoặc S-2 / S-3) / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
