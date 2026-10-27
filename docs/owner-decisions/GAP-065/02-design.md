---
work_id: GAP-065
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-07-gap-065-architecture-test-db-leak-evidence.md
  plan: docs/superpowers/plans/2026-10-27-gap-065-architecture-test-db-leak.md
  branch: docs/GAP-065-architecture-test-db-leak
  pr: https://github.com/kha997/zenamanagephp/pull/338
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-27T23:22:54+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-27, verbatim: 'APPROVE GAP-065 Gate 2 Option A'. Reviewed design head: 692b33d22f9bb2dd8d80a4918d64a7c21c045f8b. Approves Option A and its exact allowlist (add RefreshDatabase to tests/Architecture/DebugRouteBoundaryInvariantTest.php only, plan, packets); no application code, no other test file, no CI change; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-27T23:21:37+07:00"
  updated_at: "2026-10-27T23:22:54+07:00"
generated_by: agent
---

# GAP-065 — Architecture test DB leak: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-27 against reviewed design head
`692b33d22f9bb2dd8d80a4918d64a7c21c045f8b`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Sửa để test `DebugRouteBoundaryInvariantTest` không còn để lại tenant / user trong DB test. **Chỉ sửa file test**,
không đổi mã ứng dụng, không deploy.

**Điểm Owner cần quyết: sửa theo cách nào.** Đề xuất **Phương án A**: thêm `RefreshDatabase` vào lớp test đó (một
dòng `use` và một dòng trong lớp). Mỗi test của lớp chạy trong giao dịch và được hoàn tác, nên dữ liệu không rò sang
test sau. Thử nghiệm ngày 07/10 đã cho thấy cách này làm lệnh tái hiện xanh 35/35.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Isolate the class** | Add `RefreshDatabase` to `DebugRouteBoundaryInvariantTest` | **Recommended** — smallest change, already proven by the evidence's Finding 4; the class's subprocess `route:list` / `route:cache` tests do not use the test DB |
| B. Split the class | Move the three HTTP regression tests (quick-login ×2, dashboard-data) to a new `tests/Feature` class with `RefreshDatabase`; `tests/Architecture` stays DB-free | Cleaner layering (no other Architecture test touches the DB), but moves existing tests to a new file and class |
| C. Defer | Keep relying on suite order | The leak stays; selective or reordered runs keep failing |

## Design: Option A (exact allowlist)

### 1. Change

`tests/Architecture/DebugRouteBoundaryInvariantTest.php`: import `Illuminate\Foundation\Testing\RefreshDatabase` and
`use RefreshDatabase;` in the class. No test body, assertion or name changes.

### 2. Files (allowlist)

`tests/Architecture/DebugRouteBoundaryInvariantTest.php` (the two lines above); governed plan; this packet and
03-release.

**Not changed:** application code, `ProductionBootstrapCommand`, every other test file, the ten Finding 5 candidates
(separate Work ID), any suite-wide guard (separate design), CI configuration.

### 3. Verification (TDD-style)

- Red before: the evidence's reproduction command (`VendorApiTest` + `tests/Architecture` +
  `ProductionBootstrapCommandTest`) fails 3 tests on current `main`.
- Green after: the same command passes, and the `DebugRouteBoundaryInvariantTest` class passes alone and inside
  `tests/Architecture`.
- Full local suites (Architecture, Feature/Api, Web, Zena, Deployment, Unit), PHPStan, php-cs-fixer and the SSOT test
  lint are clean; exact-head CI is green.

## Verification required at Gate 3

Red-before / green-after of the reproduction command recorded; suites and CI green.

## Decision Needed

Owner chọn: `APPROVE GAP-065 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge hay phát hành; không sửa 10 file ứng viên khác; không thêm cơ chế chặn rò dữ liệu cho cả
bộ test.
