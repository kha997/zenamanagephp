---
work_id: GAP-060
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
  spec: docs/audits/2026-09-30-gap-060-a11y-perf-workflow-evidence.md
  plan: docs/superpowers/plans/2026-09-30-gap-060-nightly-workflow-implementation.md
  branch: docs/GAP-060-a11y-perf-ci-red
  pr: https://github.com/kha997/zenamanagephp/pull/329
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-30T21:13:14+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-09-30: 'APPROVE GAP-060 Gate 3'. Given after the packet (including the v1→v2 design change, the 02-design.md plan pointer, and the untouched tests/bootstrap.php comment) was presented at PR head 57df8663412ed79075be2417ef8b8faf8bf3c565; bound to implementation subject 59d44a7ad6afd1ecf738dfb5397cbbafbb7324fd and implementation-tree digest ba873b4a8fe27e830f477e0a5275ae6cecae45f18f2d80ec82adca713af8cca8 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-30T20:54:37+07:00"
  updated_at: "2026-09-30T21:13:14+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Option-1v2 implementation at subject 59d44a7a: workflow-reference guard RED at base (1 missing script, 2 empty groups) then GREEN; the rewritten nightly workflow was dispatched on the branch (run 36722287884) and passed with the GAP-040 proof executing 2 tests / 15 assertions on real MySQL; 213 governance/architecture tests green locally; all 33 exact-head PR checks green; diff exactly the Gate-2 v2 allowlist; canonical digest computed at subject."
technical_evidence:
  base_sha: "8233b3ef7b56857e46c62185c8e005e377cd8372"
  subject_sha: "59d44a7ad6afd1ecf738dfb5397cbbafbb7324fd"
  implementation_tree_digest: "ba873b4a8fe27e830f477e0a5275ae6cecae45f18f2d80ec82adca713af8cca8"
  verified_pr_head_sha: "59d44a7ad6afd1ecf738dfb5397cbbafbb7324fd"
  verified_at: "2026-09-30T20:54:37+07:00"
owner_decision_binding:
  implementation_tree_digest: "ba873b4a8fe27e830f477e0a5275ae6cecae45f18f2d80ec82adca713af8cca8"
  decision_recorded_at: "2026-09-30T21:13:14+07:00"
---

# GAP-060 — Gate 3 release decision

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-09-30, bound to implementation subject
`59d44a7ad6afd1ecf738dfb5397cbbafbb7324fd` and implementation-tree digest `ba873b4a8fe27e830f477e0a5275ae6cecae45f18f2d80ec82adca713af8cca8`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Workflow chạy mỗi đêm "Accessibility & Performance
Testing" đỏ 200/200 lần gần nhất; một phần kể cả chạy được cũng không kiểm tra
gì (Gate 1).

**2. Sau thay đổi:**

- `.github/workflows/a11y-perf-testing.yml` → "Nightly MySQL Cold-Start Proof
  (GAP-040)": một job chạy `tests/E2E/TransactionIsolationColdStartTest.php`
  (`--fail-on-empty-test-suite`) trên MySQL thật — nơi duy nhất bằng chứng
  GAP-040 này chạy.
- Gỡ: accessibility-tests (trùng CI chính), performance-budget/performance-heavy
  (script không tồn tại, 0 test), lighthouse-ci (không cấu hình DB, đo trang
  đăng nhập), test-summary, bước chạy bộ `tests/E2E` (hỏng — GAP-061).
- Test kiến trúc mới `tests/Architecture/WorkflowReferencesExistTest.php`.

**3. Khác biệt so với Gate 2**

- Gate 2 v1 (giữ và sửa E2E) được thay bằng v2 sau khi triển khai phát hiện bộ
  E2E chưa từng chạy được (15 test) và bằng chứng GAP-040 chỉ chạy ở đây — Owner
  đã duyệt v2.
- File plan khai báo `owner_gate_2_record: …/02-design.md` (lint suy ra đường
  dẫn không có hậu tố phiên bản; v1 đã duyệt và trỏ `superseded_by` → v2) —
  cùng cách GAP-052.
- Ghi nhận, không sửa (ngoài allowlist): chú thích trong `tests/bootstrap.php`
  (khoảng dòng 55-58) vẫn nhắc các job đã gỡ.

**4. Bằng chứng kỹ thuật**

- Base `8233b3ef`; subject `59d44a7ad6afd1ecf738dfb5397cbbafbb7324fd`; digest
  `ba873b4a8fe27e830f477e0a5275ae6cecae45f18f2d80ec82adca713af8cca8`.
- Guard: base → `.github/scripts/ci_prepare_testing_env.sh` thiếu; nhóm
  `performance_budget`, `performance_heavy` rỗng. Subject → 0 vi phạm.
- **Chạy thật trên nhánh:** `gh workflow run a11y-perf-testing.yml --ref
  docs/GAP-060-a11y-perf-ci-red` → run `36722287884` **success**; job
  "GAP-040 Cold-Start Proof (MySQL)": `2 passed (15 assertions)`.
- Local: 213 test (Architecture + OwnerGovernance) xanh; YAML parse OK.
- CI exact head `59d44a7a`: 33/33 pass.

**5. Ngoài phạm vi** — Sửa/viết lại `tests/E2E` (GAP-061, ghi sổ ở đối soát
sau phát hành); Lighthouse/ngưỡng hiệu năng; CI chính; deploy.

**6. Rủi ro còn lại** — Thấp. Mất các job vốn không bao giờ chạy được; bằng
chứng đang có giá trị được giữ và nay có thể xanh.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay sửa bộ E2E (GAP-061).
