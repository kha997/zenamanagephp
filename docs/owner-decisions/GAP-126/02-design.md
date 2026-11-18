---
work_id: GAP-126
gate: 2
gate_status: approved
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
  recorded_at: "2026-11-18T15:58:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-18, verbatim: 'APPROVE A-1 B-1' (answer to the Gate 2 question naming A-1 and B-1 as described in this packet). Approves A-1 (login, login.post, password.reset, logout, logout.post registered in every environment; local.dev-login.operator stays local/testing only) and B-1 (web login refuses is_active = false with the same error as a wrong password), one PR / one Gate 3, with the exact allowlist in this packet; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-18T15:50:00+07:00"
  updated_at: "2026-11-18T15:58:00+07:00"
generated_by: agent
---

# GAP-126 — Web login in production: Gate 2 design

## OWNER GATE 2: APPROVED — A-1, B-1

Owner approved A-1 and B-1 in-session on 2026-11-18. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Anh/chị chọn **2 điểm**:

1. **Bật trang đăng nhập ở máy chủ thật** — đề xuất **A-1**: `/login`, đăng xuất và trang "quên mật khẩu" (hiện chỉ quay
   về `/login`) chạy ở mọi môi trường. Đường **đăng nhập không cần mật khẩu** của máy dev (`/local/dev-login/operator`)
   vẫn **chỉ** có ở máy dev.
2. **Tài khoản đã khoá** — đề xuất **B-1**: tài khoản `is_active = false` không đăng nhập web được, giống API. Báo lỗi
   chung "thông tin đăng nhập không đúng", không nói tài khoản bị khoá.

**Phát hành:** một PR, một Gate 3.

## Điểm 1 — Route đăng nhập

| Phương án | Nội dung |
|---|---|
| **A-1 (đề xuất)** | Đưa `login`, `login.post`, `password.reset`, `logout` (GET) và `logout.post` ra khỏi khối `local/testing`. Chỉ `local.dev-login.operator` ở lại trong khối. |
| A-2 | Như A-1 nhưng bỏ `logout` dạng GET ở production (chống bị ép đăng xuất qua link). Phải sửa link đăng xuất trong `layouts/simple.blade.php` thành form POST. |

## Điểm 2 — Tài khoản đã khoá

| Phương án | Nội dung |
|---|---|
| **B-1 (đề xuất)** | `Auth::attempt([... 'is_active' => true])`. Sai mật khẩu và tài khoản khoá trả cùng một lỗi, cùng tính vào giới hạn 5 lần / phút. |
| B-2 | Giữ nguyên (tài khoản khoá vẫn vào được web). |

## Thiết kế chi tiết (theo A-1, B-1)

- `routes/web.php`: tách khối `if (app()->environment(['local', 'testing']))` hiện tại thành:
  - các route đăng nhập / đăng xuất, ngoài mọi điều kiện môi trường, tên route giữ nguyên;
  - khối `local/testing` chỉ còn `local.dev-login.operator`.
- `AuthController::login()`: thêm `'is_active' => true` vào điều kiện `Auth::attempt`. Nhánh tài khoản demo vẫn chỉ chạy ở
  local/testing như hiện nay. Giới hạn 5 lần / phút giữ nguyên.
- Không đổi giao diện, không đổi middleware, không thêm route mới.

**Test** `tests/Feature/Auth/ProductionWebLoginTest.php`:
- chạy `php artisan route:list --json` với `APP_ENV=production` (cùng cách `RouteNameCollisionInvariantTest`): có đủ 5
  route trên, không có `local.dev-login.operator`;
- đăng nhập đúng mật khẩu → chuyển tới `/app/today`;
- tài khoản `is_active = false` với đúng mật khẩu → không đăng nhập được, cùng lỗi như sai mật khẩu;
- chưa đăng nhập mở `/app/today` → chuyển về `/login`.

Test route chạy dưới `production` phải đỏ trước khi sửa, xanh sau.

## Files (allowlist)

- `routes/web.php`
- `app/Http/Controllers/AuthController.php`
- `tests/Feature/Auth/ProductionWebLoginTest.php` (mới)
- `docs/owner-decisions/GAP-126/*`, `docs/audits/2026-11-18-gap-126-production-web-login-evidence.md`,
  `docs/superpowers/plans/2026-11-18-gap-126-production-web-login.md`

File khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung.

## Owner amendment 1 (approved 2026-11-18)

Owner decision in-session on 2026-11-18, verbatim: 'APPROVE GAP-126 amendment 1'.

The first real run of **Production Deployment** (`.github/workflows/production.yml`, run 44270113158, SHA `ca7a878b`)
failed in the build job at "Package immutable release artifact": `tar: .: file changed as we read it` — the archive was
written inside the directory being archived. The workflow had never run for real before.

Added to the allowlist, only to fix defects proven by a failed real run (each with its run log):

- `.github/workflows/production.yml`
- `scripts/deploy/*.sh`

Verification: dispatch the workflow from this branch with an exact SHA from `main` (the workflow only deploys SHAs
reachable from `main`) until the run reaches `health_verified`.

## Ngoài phạm vi

- Gửi email quên mật khẩu (cấp lại mật khẩu vẫn dùng `php artisan production:user --reset-password`).
- Giao diện trang đăng nhập.
- Cổng khách hàng.
- Deploy.

## Verification required at Gate 3

- Test mới đỏ trước, xanh sau.
- Toàn bộ Unit + Feature + Integration và các lint.
- `route:list` dưới `APP_ENV=production` có đủ 5 route, không có route dev-login.

## Rollback

Revert PR.

## Decision Needed

Owner chọn: `APPROVE GAP-126 Gate 2 A-1 B-1` (hoặc phương án khác cho từng điểm) / Request more information / Decline /
Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
