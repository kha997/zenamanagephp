---
work_id: GAP-017
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-25-gap-017-invitation-dead-ends-evidence.md
  plan: null
  branch: docs/GAP-017-invitation-decline
  pr: https://github.com/kha997/zenamanagephp/pull/367
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
  created_at: "2026-10-25T13:22:55+07:00"
  updated_at: "2026-10-25T13:22:55+07:00"
generated_by: agent
---

# GAP-017 — Invitation decline link: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Khi thiết kế, mình kiểm tra thêm và thấy:

- **Cả controller `Web\InvitationController` chỉ có đúng một route trỏ vào, là route từ chối đang hỏng.** 9 hàm còn lại
  (danh sách, tạo, quản lý, gửi lại, huỷ, nhận lời mời cũ…) không còn route nào. Ba view `invitations/index`,
  `create`, `manage` chỉ được controller này dùng.
- Lời mời hiện được tạo, thu hồi và nhận qua `/api/teams/{team}/invitations` và trang `/invitations/accept/{token}`.
  Không có chức năng từ chối lời mời ở đâu cả.
- Bảng `invitations` chỉ cho 4 trạng thái: pending, accepted, expired, cancelled. Không có trạng thái "declined".

**Phương án A (khuyến nghị): xoá phần chết.**
- Xoá route `/invitations/decline/{token}` và link chuyển hướng cũ `/invite/decline/{token}`.
- Xoá `Web\InvitationController` (không còn route nào khác dùng) và 3 view chỉ nó dùng; xoá các dòng phpstan-baseline của
  file này. Cách làm giống GAP-077.
- Sau khi sửa, mở link từ chối sẽ nhận **404** thay vì **500**.
- Thêm test: hai link trên trả 404, và không còn route tên `invitations.decline`.

**Phương án B: làm chức năng từ chối thật.**
- Thêm trạng thái `declined` (cần migration đổi enum của bảng), hàm từ chối trong `TeamInvitationService`, trang xác nhận
  từ chối, và quyết định ai được từ chối (người được mời đã đăng nhập, hay bất kỳ ai có link).
- Phạm vi lớn hơn và có đổi schema; cần Owner trả lời thêm các câu hỏi nghiệp vụ.

**Phương án C:** chỉ xoá route và link chuyển hướng, giữ controller và view chết lại.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. `routes/web.php`: remove `Route::get('/decline/{token}', …)->name('decline')` from the `invitations.` group and the
   `Route::permanentRedirect('/invite/decline/{token}', '/invitations/decline/{token}')` line. The accept route and the
   `/invite/accept/{token}` redirect are unchanged.
2. `legacy-map.json`: remove the `/invite/decline/{token}` entry.
3. Delete `app/Http/Controllers/Web/InvitationController.php` and `resources/views/invitations/index.blade.php`,
   `create.blade.php`, `manage.blade.php`. `resources/views/invitations/accept.blade.php` stays (used by
   `AuthenticatedInvitationAcceptController`).
4. `phpstan-baseline.neon`: remove the entries whose path is `app/Http/Controllers/Web/InvitationController.php`.
5. New test `tests/Feature/Web/InvitationDeclineRouteRemovedTest.php`: `GET /invitations/decline/{token}` and
   `GET /invite/decline/{token}` return 404; `Route::has('invitations.decline')` is false; `invitations.accept` still exists.
6. Docs: plan, this packet, 03-release.

**Not changed:** the invitation API, the accept page, the `invitations` schema, and `Api\InvitationController`.

## Verification

- The new test fails on the base (500 and the named route exists) and passes after the change.
- `php artisan route:list`: the only difference is the two removed routes.
- Invitation tests (`--filter=Invitation`), PHPStan, php-cs-fixer, and the governance and docs lints pass.
- Exact-head CI green.

## Rollback

Revert the squash commit.

## Out of scope

- Whether an expired invitation should show its own page (product question; the accept page shows the expiry date and
  accepting an expired invitation already returns an error, not a 500).
- Historical root docs that list the decline route (`ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md`).
- Updating `OPERATIONAL_GAP_REGISTER.md`; that is the separate register reconciliation the Owner queued next.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-017 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
