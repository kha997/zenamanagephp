---
work_id: GAP-058
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-29-gap-058-badge-api-evidence.md
  plan: null
  branch: docs/GAP-058-badge-api-500
  pr: https://github.com/kha997/zenamanagephp/pull/326
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-29T23:14:00+07:00"
  updated_at: "2026-09-29T23:14:00+07:00"
generated_by: agent
---

# GAP-058 — Retire the dead sidebar badge API: Gate 2 design

## OWNER GATE 2: AWAITING OWNER DECISION

## Owner Summary

Theo lựa chọn "không cần huy hiệu lúc này", gỡ hẳn phần huy hiệu không ai dùng:
8 đường API luôn lỗi, lớp xử lý trả số giả, và component thanh bên cũ không
trang nào hiển thị. Không người dùng nào thấy thay đổi. Phần còn lại của hệ
thanh bên cũ giữ nguyên. Đề xuất **Phương án 1**.

## So sánh phương án

| Phương án | Cách làm | Kết luận |
|---|---|---|
| **1. Gỡ bề mặt huy hiệu chết** | Xoá 8 route, `BadgeController`, `BadgeService`, component `Sidebar` + view `components/sidebar.blade.php` | **Đề xuất** — đúng lựa chọn Owner; không caller; tránh API luôn lỗi/số giả |
| 2. Chỉ sửa 2 import | API chạy nhưng luôn trả 0 | Loại: biến lỗi 500 thành số giả "0" — sai lệch dữ liệu, không ai dùng |
| 3. Gỡ toàn bộ hệ thanh bên cũ | Thêm sidebar builder, preset, tuỳ chọn `show_badges`, route legacy | Ngoài phạm vi Gate 1; cần điều tra riêng (có controller/route admin còn sống) |

## Thiết kế: Phương án 1

Xoá:

- `routes/api.php` — đúng khối `Route::prefix('badges')->middleware(['auth:sanctum'])->group(...)` (8 route `api.badges.*`).
- `app/Http/Controllers/Api/BadgeController.php`
- `app/Services/BadgeService.php`
- `app/View/Components/Sidebar.php` (caller duy nhất khác của `BadgeService`; không view nào dùng)
- `resources/views/components/sidebar.blade.php` (view duy nhất gọi `/api/badges`; chỉ component trên render nó)
- `tests/Unit/GAP055/BadgeUserCacheClearIsTargetedTest.php` — kiểm thử một service bị xoá; bất biến GAP-055 ("huy hiệu không xoá toàn bộ cache") trở nên hiển nhiên khi không còn bề mặt huy hiệu, và được thay bằng test dưới đây.

Giữ nguyên (ngoài phạm vi): `UserSidebarPreference::show_badges`,
`POST /api/user-preferences/toggle-badges`, `SidebarConfigRequest`
(`show_badge_from`), `PresetService`, `resources/views/components/dynamic-sidebar.blade.php`,
`resources/views/admin/sidebar-builder.blade.php`, route legacy
`routes/legacy/api_v1.php:76`.

### Kiểm thử (TDD — đỏ trước)

`tests/Feature/GAP058/BadgeApiRetiredTest.php`:

1. Bảng route runtime không còn URI nào bắt đầu bằng `api/badges` và không còn
   tên route `api.badges.*`.
2. `App\Http\Controllers\Api\BadgeController`, `App\Services\BadgeService`,
   `App\View\Components\Sidebar` không còn tồn tại; view
   `components.sidebar` không còn tồn tại.
3. Không file PHP/Blade nào trong `app`, `resources`, `routes` còn tham chiếu
   `BadgeService` hoặc `/api/badges`.

Đỏ ở base (route/class còn), xanh sau khi gỡ.

## File allowlist

5 file xoá + 1 test xoá ở trên, sửa `routes/api.php` (chỉ khối badges),
`tests/Feature/GAP058/BadgeApiRetiredTest.php` (mới),
`docs/superpowers/plans/2026-09-29-gap-058-badge-api-retirement-implementation.md`
(có governance frontmatter), Gate packet/evidence của GAP-058. Không sửa
`OPERATIONAL_GAP_REGISTER.md` (đối soát sau phát hành).

## Verification trước Gate 3

Test mới đỏ ở base, xanh ở subject; test sidebar/user-preference/route hiện có
(`SidebarConfigTest`, `SidebarServiceTest`, `UserPreferenceApiTest`,
route-middleware contracts, Routes Guardrails), `tests/Unit/OwnerGovernance`,
PHPStan (CI) xanh; toàn bộ required CI xanh exact head; digest canonical.

## Rollback

Revert squash commit (khôi phục nguyên trạng, gồm cả lỗi 500).

## Explicit Exclusions

Hệ thanh bên cũ ngoài 5 file trên; thiết kế menu Operator; tính năng huy hiệu
mới (Owner: không cần lúc này); deploy.

## Decision Needed

Owner chọn: Approve Phương án 1 / Approve Phương án 2 / Request changes /
Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge, release hay deployment.
