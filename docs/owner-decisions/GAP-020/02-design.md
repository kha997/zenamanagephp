---
work_id: GAP-020
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-29-gap-020-orphan-admin-views-evidence.md
  plan: null
  branch: docs/GAP-020-orphan-admin-views
  pr: https://github.com/kha997/zenamanagephp/pull/382
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T00:48:40+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-29, verbatim: 'APPROVE GAP-020 Gate 2 Option A'. Reviewed design head: cccbdf89b50e833a85c4ffddab7534efa7511194. Approves Option A and its exact allowlist (delete resources/views/admin/users/index.blade.php and admin/tenants/index.blade.php; new OrphanAdminViewsRemovedTest; plan and packets); live admin views, routes and universal-frame layout unchanged; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T00:47:18+07:00"
  updated_at: "2026-10-29T00:48:40+07:00"
generated_by: agent
---

# GAP-020 — Orphan admin views: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-29 against reviewed design head
`cccbdf89b50e833a85c4ffddab7534efa7511194`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Đề xuất **Phương án A**: **xoá 2 file** `admin/users/index` và `admin/tenants/index` và thêm một test chặn chúng quay lại.
Trang thật `/admin/users`, `/admin/tenants` không đổi.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Xoá 2 file + test chặn** | Như GAP-018 | **Đề xuất** — gọn, hết nhầm lẫn |
| B. Giữ file, đổi tên thành `*.orphan.blade.php` | Vẫn còn 1.042 dòng chết | Giảm nhầm nhưng không bỏ được code chết |
| C. Giữ nguyên (đã có chú thích) | Đóng GAP như chấp nhận | Rủi ro sửa nhầm vẫn còn |

## Thiết kế: Phương án A

1. Xoá `resources/views/admin/users/index.blade.php` và `resources/views/admin/tenants/index.blade.php` (2 thư mục trống
   cũng mất theo).
2. Test mới `tests/Feature/Architecture/OrphanAdminViewsRemovedTest.php`: view `admin.users.index`,
   `admin.tenants.index` không tồn tại, còn `admin.users`, `admin.tenants` (trang thật) vẫn tồn tại. Đỏ trước / xanh sau.

**Files (allowlist):** 2 file xoá; test mới; plan `docs/superpowers/plans/2026-10-29-gap-020-orphan-admin-views.md`;
packet và evidence GAP-020.

**Không đổi:** `admin/users.blade.php`, `admin/tenants.blade.php`, route, `layouts/universal-frame.blade.php`.

## Verification required at Gate 3

Đỏ trước / xanh sau; `php artisan view:cache`; Architecture, Web, Feature suites; SSOT lint, route guard; exact-head CI
xanh.

## Rollback

Revert squash commit.

## Decision Needed

Owner chọn: `APPROVE GAP-020 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge hay phát hành; không đụng GAP-019.
