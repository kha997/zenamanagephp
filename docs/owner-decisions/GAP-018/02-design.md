---
work_id: GAP-018
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-28-gap-018-smart-tools-evidence.md
  plan: null
  branch: docs/GAP-018-smart-tools-api-prefix
  pr: https://github.com/kha997/zenamanagephp/pull/381
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T00:07:50+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-28, verbatim: 'APPROVE GAP-018 Gate 2 Option A'. Reviewed design head: bf3a2340809739ff79517781eb8661a9aa58c000. Approves Option A and its exact allowlist (delete the four smart-tools components and test-smart-tools.blade.php; new DeadSmartToolsComponentsRemovedTest; plan and packets); LaunchChecklistService, universal-frame layout and routes unchanged; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T00:05:44+07:00"
  updated_at: "2026-10-29T00:07:50+07:00"
generated_by: agent
---

# GAP-018 — Dead smart-tools components: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-28 against reviewed design head
`bf3a2340809739ff79517781eb8661a9aa58c000`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Đề xuất **Phương án A**: **xoá 5 file** (4 component + trang demo) và thêm một test chặn chúng quay lại. Không sửa code khác.

Mục "smart tools" trong danh sách kiểm tra "sẵn sàng ra mắt" cũ sẽ tự chuyển sang **chưa đạt** (vì nó kiểm tra file
`smart-search` có tồn tại không) — đúng với thực tế là tính năng này không có.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Xoá 5 file + test chặn** | Danh sách kiểm tra cũ để nguyên, mục "smart tools" thành chưa đạt | **Đề xuất** — nhỏ nhất, kết quả kiểm tra phản ánh đúng thực tế |
| B. Như A + bỏ mục "smart tools" khỏi `LaunchChecklistService` | Sửa thêm service (bỏ 1 mục và 1 hàm) | Gọn hơn, nhưng đổi dữ liệu trả về của API final-integration cũ (thuộc nhóm GAP-019) |
| C. Giữ nguyên (đã có chú thích "lưu trữ") | Không đổi gì; đóng GAP như chấp nhận | Code chết 1.374 dòng vẫn còn |

## Thiết kế: Phương án A

1. Xoá: `resources/views/components/smart-search.blade.php`, `smart-filters.blade.php`, `analysis-drawer.blade.php`,
   `export-component.blade.php`, `resources/views/test-smart-tools.blade.php`.
2. Test mới `tests/Feature/Architecture/DeadSmartToolsComponentsRemovedTest.php` (theo mẫu `DeadTemplatesPageRemovedTest`):
   5 view `components.smart-search`, `components.smart-filters`, `components.analysis-drawer`,
   `components.export-component`, `test-smart-tools` không còn tồn tại. Đỏ trước (còn tồn tại) / xanh sau.

**Files (allowlist):** 5 file xoá; test mới; plan `docs/superpowers/plans/2026-10-28-gap-018-dead-smart-tools.md`; packet và
evidence GAP-018.

**Không đổi:** `LaunchChecklistService`, `layouts/universal-frame.blade.php`, `/api/v1/universal-frame/*`, mọi route.

## Verification required at Gate 3

Đỏ trước / xanh sau; Architecture, Feature (final-integration nếu có test), Web suites; `php artisan view:cache` thành
công; SSOT lint, PHPStan, route guard; exact-head CI xanh.

## Rollback

Revert squash commit.

## Decision Needed

Owner chọn: `APPROVE GAP-018 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge hay phát hành; không đụng GAP-019.
