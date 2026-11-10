---
work_id: GAP-121
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
  spec: docs/audits/2026-11-10-gap-121-month-close-mysql-concurrency-evidence.md
  plan: docs/superpowers/plans/2026-11-10-gap-121-month-close-mysql-concurrency.md
  branch: docs/GAP-121-month-close-mysql-concurrency
  pr: https://github.com/kha997/zenamanagephp/pull/411
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-10T22:54:13+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-10, verbatim: 'APPROVE GAP-121 Gate 3'. Given on the packet at PR head 599508e0f6931c79b149d967cb51d505f023402a; bound to implementation subject cb4a7836cc76df17da15f913da61bd4ce5c5a50d and implementation-tree digest 4ecabc8097b89eaa8fe2a17ecf0d97fa760ed0f63d77faf3a4163b41ff59776d (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28, after exact-head CI including the new Month Close Concurrency job is green; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-10T22:52:43+07:00"
  updated_at: "2026-11-10T22:54:13+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-121 at subject cb4a7836cc76df17da15f913da61bd4ce5c5a50d (Gate 2 S-1, J-1 plus Owner amendment 1; one PR, implementation commit 910db3f2; tests, test-support commands and CI only, no change to close rules or locks, no migration). tests/Feature/Concurrency/MonthCloseConcurrencyTest (group stress, skips without MySQL) races two OS processes / MySQL connections on one month with process A holding its transaction 3 s: 1a payroll close then manual time entry (entry refused 'đã chốt', labour allocated from the 600 approved minutes), 1b entry then close (close refused 'chờ duyệt', month open), 2 two payroll closes (one succeeds, labour allocated once, one audit), 3a overhead close then payroll reopen (reopen refused, both closed), 3b reopen then overhead close (overhead refused, both draft, no allocations), 4 two overhead closes (one succeeds, one allocation set). Hidden commands payroll:concurrency-test-close / -reopen, overhead:concurrency-test-close, time:concurrency-test-add; only the holding process opens an outer transaction (an outer transaction in the racing process took its snapshot before the lock wait and produced a false pass for 1b — harness issue, not app code; noted: the close relies on not running inside an outer transaction). New CI job 'Month Close Concurrency (real MySQL)' and scripts/ci/month-close-concurrency-mysql (fails on a skip while MySQL is reachable). Amendment 1: one line in scripts/ssot/baselines/skipped_tests_baseline.txt. Evidence: 6/6 green on local MySQL 8, also through the CI script; mutation runs (local, not committed) — no shared lock in TimeEntryService::isLocked fails 1a and 1b, no FOR UPDATE in PayrollService::lockDraft fails 1b and 2, no payroll row lock in OverheadService::close fails 3b; code restored. PHPStan, php-cs-fixer, ssot:lint, docs-lint, governance lint pass. Local Unit + Feature + Integration 3030 tests: only the 2 known root-only failures (the new test skips on sqlite). Exact-head CI, including the new job, recorded in the body."
technical_evidence:
  base_sha: "503f92edbdc0e8da334ffc48ed22b17727d78947"
  subject_sha: "cb4a7836cc76df17da15f913da61bd4ce5c5a50d"
  implementation_tree_digest: "4ecabc8097b89eaa8fe2a17ecf0d97fa760ed0f63d77faf3a4163b41ff59776d"
  verified_pr_head_sha: "cb4a7836cc76df17da15f913da61bd4ce5c5a50d"
  verified_at: "2026-11-10T22:52:43+07:00"
owner_decision_binding:
  implementation_tree_digest: "4ecabc8097b89eaa8fe2a17ecf0d97fa760ed0f63d77faf3a4163b41ff59776d"
  decision_recorded_at: "2026-11-10T22:54:13+07:00"
---

# GAP-121 — Gate 3 release decision (real-MySQL month-close concurrency tests)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-10 ("APPROVE GAP-121 Gate 3"), bound to implementation subject
`cb4a7836cc76df17da15f913da61bd4ce5c5a50d` and implementation-tree digest `4ecabc8097b89eaa8fe2a17ecf0d97fa760ed0f63d77faf3a4163b41ff59776d`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Phần chốt tháng có khoá dữ liệu chống thao tác đồng thời, nhưng mới được kiểm bằng tay. Chưa có test tự
động nào chạy hai thao tác cùng lúc trên MySQL thật.

**2. Sau thay đổi (Gate 2 S-1, J-1 + bổ sung 1)** — subject `cb4a7836`.

**6 test** chạy **hai tiến trình song song trên MySQL thật**, cho cùng một tháng:

| # | Tình huống | Kết quả mong đợi (đều đạt) |
|---|---|---|
| 1a | Đang chốt lương → có người thêm giờ | Thêm giờ bị từ chối "đã chốt"; chi phí nhân công khớp đúng giờ đã duyệt |
| 1b | Đang thêm giờ → chốt lương | Chốt bị từ chối "còn giờ chờ duyệt"; tháng vẫn mở |
| 2 | Hai người cùng chốt lương | Một lần thành công; chia chi phí đúng một lần |
| 3a | Đang chốt chi phí chung → mở lại lương | Mở lại bị từ chối; cả hai vẫn chốt |
| 3b | Đang mở lại lương → chốt chi phí chung | Chốt chi phí chung bị từ chối; không phân bổ gì |
| 4 | Hai người cùng chốt chi phí chung | Một lần thành công; một bộ phân bổ |

**Job CI mới** "Month Close Concurrency (real MySQL)" chạy 6 test này mỗi PR. Job báo đỏ nếu test bị bỏ qua dù MySQL đang
chạy.

**Không đổi** quy tắc hay khoá của phần chốt tháng.

**Bổ sung 1:** thêm một dòng vào danh sách test được phép bỏ qua của SSOT lint, giống 4 test đồng thời sẵn có.

**3. Đã kiểm thử** — base `503f92ed`; subject `cb4a7836cc76df17da15f913da61bd4ce5c5a50d`; digest
`4ecabc8097b89eaa8fe2a17ecf0d97fa760ed0f63d77faf3a4163b41ff59776d`.
- **6/6 xanh trên MySQL 8**, chạy cả qua đúng script CI.
- **Chứng minh test bắt được lỗi thật.** Tạm bỏ từng khoá (chỉ trên máy, không commit), test tương ứng đều **đỏ**:

  | Khoá tạm bỏ | Test đỏ |
  |---|---|
  | Khoá khi thêm / duyệt giờ | 1a và 1b |
  | Khoá khi chốt lương | 1b và 2 |
  | Khoá lương khi chốt chi phí chung | 3b |

  Mã đã trả lại nguyên trạng.
- PHPStan, php-cs-fixer, SSOT lint, docs-lint, governance lint sạch.

**Phát hiện thêm:** khoá của phần chốt lương chỉ an toàn khi lệnh chốt **không** chạy bên trong một giao dịch khác. Trang web
hiện nay đúng như vậy. Sau này nếu có tác vụ nền gọi chốt lương thì cần lưu ý. Chú thích trong các lệnh hỗ trợ test đã ghi rõ điều này.

**4. Lưu ý vận hành** — Không có. Chỉ thêm test và CI, không đổi phần mềm chạy thật.

**5. Ngoài phạm vi**
- Đổi quy tắc chốt.
- Ngân quỹ.
- Deploy.

**6. Rủi ro còn lại** — Rất thấp. CI dài thêm một job khoảng vài phút.

**7. Hoàn tác** — Revert PR.

**8. Đề xuất** — Phát hành (merge).

**9. Kết quả chạy toàn bộ**
- **Unit + Feature + Integration:** 3.030 test, chỉ còn 2 lỗi có sẵn do chạy bằng root (`GrandfatherLoaderTest`,
  `BackupStorageLocationTest`).
- Test mới tự bỏ qua khi chạy trên SQLite, đúng thiết kế.
- **CI trên đúng head,** gồm job MySQL mới: ghi nhận trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-121 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
