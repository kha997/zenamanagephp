---
work_id: GAP-069
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-08-gap-069-reconciliation-page-overflow-evidence.md
  plan: null
  branch: docs/GAP-069-reconciliation-page-overflow
  pr: https://github.com/kha997/zenamanagephp/pull/342
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
  created_at: "2026-10-08T13:31:00+07:00"
  updated_at: "2026-10-08T13:31:00+07:00"
generated_by: agent
---

# GAP-069 — Reconciliation history page overflow: Gate 2 design

## Owner Summary

Đặt **giới hạn số trang tối đa 10.000** (đủ cho 500.000 lần đối soát mỗi ví
trên web, 1.000.000 lần trên API). Hai phương án:

- **A — giới hạn ở cả ba chỗ (khuyến nghị):**
  - Dịch vụ tự kẹp số trang vào khoảng 1–10.000 trước khi tính vị trí, nên
    không nơi nào gọi vào có thể gây tràn số.
  - API trả lỗi 422 nếu `page` lớn hơn 10.000.
  - Trang web tự đưa số trang về trong khoảng 1–10.000; trang quá xa chỉ hiện
    "Chưa có lần đối soát nào".
- **B — chỉ sửa trang web:** kẹp số trang ở trang web. API vẫn nhận số trang
  lớn tuỳ ý.

Đề xuất **Phương án A** vì nó chặn tận gốc trong dịch vụ.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Bound everywhere** | Service clamps `page` to 1–10 000 and `per_page` to 1–100 before computing the offset; API `page` max 10 000 (422 above); web clamps `?page=` to 1–10 000 | **Recommended** |
| B. Web only | Clamp `?page=` in `TreasuryPageController` | API offset still unbounded |

## Design: Option A (exact allowlist)

1. `TreasuryReconciliationService`: constant `MAX_HISTORY_PAGE = 10000`;
   `history()` clamps `$page` to `[1, MAX_HISTORY_PAGE]` and `$perPage` to
   `[1, 100]` before computing `offset`.
2. `Api\Treasury\TreasuryReconciliationController::index`: `page` rule becomes
   `integer|min:1|max:10000`.
3. `TreasuryPageController::reconcileWallet`: clamps `?page=` to
   `[1, MAX_HISTORY_PAGE]`; the next-page probe is only run when
   `$page < MAX_HISTORY_PAGE` (no multiplication beyond the bound).
4. Tests (TDD, `tests/Feature/Treasury/TreasuryReconciliationHistoryPagingTest.php`):
   web `?page=9223372036854775807` and `?page=99999999999999999999` → 200
   (red first: 500 on main); API `page=10001` → 422, `page=10000` → 200 with an
   empty list; existing paging tests unchanged.
5. Files: the service, the API controller, `TreasuryPageController`, the test
   file, governed plan file, this packet and 03-release. No view, route,
   migration, permission or workflow change.

## Verification required at Gate 3

Treasury tests green locally; red-first test shown failing before the fix;
PHPStan; SSOT / governance / docs lints; exact-head CI green.

## Out of scope

Reconciliation rules, locks, permissions, other paged lists, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-069 Gate 2 Option A` (khuyến nghị) / Option B /
Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
