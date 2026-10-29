---
work_id: GAP-100
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-29-gap-100-test-db-leak-evidence.md
  plan: docs/superpowers/plans/2026-10-29-gap-100-test-db-leak.md
  branch: docs/GAP-100-test-db-leak
  pr: https://github.com/kha997/zenamanagephp/pull/383
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
  created_at: "2026-10-29T07:50:45+07:00"
  updated_at: "2026-10-29T07:50:45+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-100 at subject 984d9abdd327de002e946e69eb799e1dd3316e44 (Gate 2 Option A; implementation commit 30da9e66 plus a merge of main bringing the GAP-101 symfony/mime fix). RefreshDatabase on ProjectManagementTest and DeployMigrateCommandTest; new Tests\\Support\\DatabaseLeakGuard hooked into Tests\\TestCase (SQLite only, classes without database isolation): fails the test with table and count changes. DatabaseLeakGuardTest red before (class missing), green after 6/6. With the guard and before the 2 fixes, exactly the 3 known leaking tests failed with the GAP-100 message; after, green. Gate-1 reproduction (VendorApiTest + ProjectManagementTest + ProductionBootstrapCommandTest) red 3 before, OK 10/10 after. Unit + Feature + Integration 2836 tests, 2 failures (both root-only, known), 348 s vs 366 s measured without the guard; tests/Feature/Api 823/823. php-cs-fixer 0, lint_tests and composer ssot:lint pass, governance and docs lints pass. Exact-head CI at 984d9abd: all green including Security Vulnerability Scan, except browser-tests, Feature Tests and test still running at packet time."
technical_evidence:
  base_sha: "1b0e4f4d85d0db3f2150448968fdabf28334ad05"
  subject_sha: "984d9abdd327de002e946e69eb799e1dd3316e44"
  implementation_tree_digest: "a8a2eb3bdb9aa771c63d0dc92efd27b19bd37b6dd3a2dea4bac163bb367e169f"
  verified_pr_head_sha: "984d9abdd327de002e946e69eb799e1dd3316e44"
  verified_at: "2026-10-29T07:50:45+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-100 — Gate 3 release decision (test classes that leak DB rows)

## Gói quyết định phát hành

**1. Vấn đề là gì?** 2 lớp test để lại dữ liệu trong DB test, làm test khác đỏ giả khi đổi thứ tự chạy; chưa có cơ chế phát
hiện (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — subject `984d9abd`:
- `ProjectManagementTest`, `DeployMigrateCommandTest` dùng `RefreshDatabase` (mỗi file 3 dòng, không đổi assertion).
- Cơ chế phát hiện mới chạy trong mọi lần test (SQLite): lớp test không tự dọn mà để lại bản ghi thì chính test đó đỏ, kèm
  tên bảng và số bản ghi, ví dụ `GAP-100: ...ProjectManagementTest::... left rows in the shared test database (use
  RefreshDatabase or clean up): tenants: 0 -> 1, ...`.

**3. Bằng chứng** — base `1b0e4f4d`; subject `984d9abdd327de002e946e69eb799e1dd3316e44`;
digest `a8a2eb3bdb9aa771c63d0dc92efd27b19bd37b6dd3a2dea4bac163bb367e169f`.
- **Đỏ trước:** test của cơ chế phát hiện hỏng (chưa có lớp); bật cơ chế khi chưa sửa 2 file → đúng 3 test rò đỏ; lệnh tái
  hiện Gate 1 đỏ 3. **Xanh sau:** 6/6; 3 test kia xanh; lệnh tái hiện 10/10.
- Toàn bộ Unit + Feature + Integration: 2.836 test, chỉ 2 test hỏng sẵn do chạy bằng root (3 test rò dữ liệu theo thứ tự
  từng gặp trước đây không xuất hiện); `tests/Feature/Api` 823/823.
- Thời gian: 348 giây (đo 366 giây khi chưa có cơ chế) — không chậm đi đáng kể.
- php-cs-fixer, SSOT lint, governance/docs lint sạch. CI trên `984d9abd`: xanh hết, **gồm kiểm tra bảo mật**; còn
  browser-tests, Feature Tests và `test` đang chạy lúc lập hồ sơ.

**4. Công khai**
- PR đã nhận `main` (merge commit) để có bản vá `symfony/mime` của GAP-101; subject vì vậy là commit merge `984d9abd`.
  Phần thay đổi của GAP-100 không đổi so với `30da9e66`.
- Không có cơ chế bỏ qua: test mới nào để lại dữ liệu sẽ đỏ ngay, cần thêm `RefreshDatabase` hoặc tự dọn.

**5. Ngoài phạm vi** — test Dusk `SubmittalResubmitDirtyStateTest`, test chỉ chạy MySQL, mã ứng dụng, CI; deploy.

**6. Rủi ro còn lại** — Thấp: chỉ đổi file test.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-100 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không đổi CI hay mã ứng dụng.
