---
work_id: GAP-068
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-08-gap-068-reconciliation-mutation-response-evidence.md
  plan: docs/superpowers/plans/2026-10-08-gap-068-reconciliation-mutation-response.md
  branch: docs/GAP-068-reconciliation-mutation-response
  pr: https://github.com/kha997/zenamanagephp/pull/341
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T10:56:05+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-08, verbatim: 'Làm theo option B'. Reviewed design head: d3ba47bebb49ca58f3297f4a704203456f8cfee9. Approves Option B and its exact allowlist (mutation responses by id, paged history on API and web, no migration/route/permission change); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T10:52:00+07:00"
  updated_at: "2026-10-08T10:56:05+07:00"
generated_by: agent
---

# GAP-068 — Reconciliation API empty mutation response: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION B

Owner approved Option B in-session on 2026-10-08 ("Làm theo option B") against reviewed design head
`d3ba47bebb49ca58f3297f4a704203456f8cfee9`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Hai phương án, không phương án nào đổi cơ sở dữ liệu, quy tắc đối soát, khoá
hay quyền:

- **A — chỉ sửa API:** sau khi đối soát/gỡ, API lấy đúng lần đối soát đó theo
  id (không qua danh sách 100) nên luôn trả đủ dữ liệu. Trang web giữ nguyên
  (vẫn chỉ hiện 100 lần gần nhất của ví).
- **B — A + phân trang lịch sử (khuyến nghị):** thêm như A; trang đối soát hiện
  lịch sử theo trang (50 lần/trang, nút "Trang trước / Trang sau"), nên lần cũ
  hơn vẫn xem và gỡ được trên web. API danh sách lịch sử nhận thêm `page` /
  `per_page`; mặc định giữ như hiện tại (100 lần mới nhất), nên không phá ứng
  dụng đang gọi.

Đề xuất **Phương án B** vì ví kiểm quỹ hằng ngày sẽ vượt 100 lần sau khoảng 3
tháng.

## Options

| Option | Content | Verdict |
|---|---|---|
| A. API lookup by id | Mutation responses built from the affected reconciliation, uncapped | Fixes the reported defect only |
| **B. A + paged history** | A, plus `page`/`per_page` on the history read (API + web, 50 per page on the page) | **Recommended** — also fixes Finding 3 |
| C. Raise the cap | e.g. 1000 instead of 100 | Rejected: only moves the threshold |

## Design: Option B (exact allowlist)

1. `TreasuryReconciliationService::history(Project, ?TreasuryWallet, ?string $reconciliationId = null, int $page = 1, int $perPage = 100)`:
   when `$reconciliationId` is given, return only that reconciliation (same
   project/tenant scope, no cap); otherwise newest first with
   `offset(($page − 1) × $perPage)` / `limit($perPage)`. Same row shape as
   today. A small `historyItem(Project, string $id): ?array` helper wraps the
   by-id call.
2. `Api\Treasury\TreasuryReconciliationController`: `store`, `undo`,
   `undoEntry` respond with the service by-id helper (never `null` after a
   successful write). `index` accepts `page` (≥ 1) and `per_page` (1–100,
   default 100); invalid values → 422. Response stays a plain list.
3. `TreasuryPageController::reconcileWallet`: reads `?page=` (default 1), 50 per
   page; view shows "Trang trước / Trang sau" links (plain links, no
   JavaScript) when there is a previous page / when the page is full and a next
   one exists (fetch 51, show 50).
4. Tests (TDD, `tests/Feature/Treasury`): store and both undo endpoints return
   the affected reconciliation when more than 100 newer ones exist (red first,
   reproduces `data: null`); API `page`/`per_page` and validation; web page 2
   lists the older reconciliation and its "Gỡ" works; existing reconciliation
   tests unchanged and green.
5. Files: the service, the API controller, `TreasuryPageController`,
   `resources/views/treasury/reconcile.blade.php`, the three reconciliation
   test files (or one new test file), governed plan file, this packet and
   03-release. No route, migration, permission or workflow change.

## Verification required at Gate 3

Treasury tests green locally; PHPStan; SSOT/governance lints; exact-head CI
green; the red-first test shown failing on main before the fix.

## Out of scope

Reconciliation rules, locks, permissions, S4b–S6, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-068 Gate 2 Option B` (khuyến nghị) / Option A /
Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
