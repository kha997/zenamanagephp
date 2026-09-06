---
work_id: GAP-050
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/superpowers/specs/2026-09-06-gap-050-gate2-mysql-transaction-isolation-design.md
  plan: null
  branch: docs/GAP-050-gate2-mysql-transaction-isolation-design
  pr: "https://github.com/kha997/zenamanagephp/pull/304"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-06T12:59:20Z"
  owner_response_reference: "GAP-050 Gate 2 Owner Decision Round 1 (relayed via coordinator session): 'GAP-050 Gate 2 — CHANGES REQUESTED, bounded correction only. The existing Gate-2 investigation is accepted as strong, but one high-leverage order-dependent diagnostic is still missing before Owner approval. Do NOT restart broad framework/PDO investigation and do NOT implement remediation.' Owner directed: (1) fix references.pr in the Gate-2 packet to point to PR #304; (2) perform deterministic order-aware delta minimization on canonical main / real MySQL 8.0 — capture the actual execution order of the 41 zena-invariants tests, preserve that order, systematically reduce the tests preceding the known victim using prefix bisection/ddmin, find the smallest practical ordered reproducing sequence; (3) for the minimized reproducer, determine which test is the immediate precursor to the first observed transaction loss, PDO/Laravel transaction state at precursor teardown and victim setup/request boundaries, and whether the failure requires one specific precursor, a small interaction set, or only larger suite volume; (4) if a small reproducer is found, instrument only that reproducer to locate the earliest state transition, without descending into driver-level tooling unless proven necessary; (5) if systematic minimization cannot reduce below a materially large sequence, record that negative result precisely as evidence supporting process isolation as the justified final containment; (6) do not independently reproduce Treasury or the other MySQL job in this correction — static blast-radius evidence is sufficient for GAP-050, track Treasury separately later; (7) keep the Sanctum Bearer-token fidelity finding separate from GAP-050 implementation scope, record a follow-up Work ID recommendation only. Update the Gate-2 spec/packet with the minimization evidence and revise the final recommendation if warranted. No production/test/CI implementation changes. Keep gate_status: awaiting_owner. Push docs only, verify exact-head governance checks once, then stop for Owner review.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-06T12:59:20Z"
  updated_at: "2026-09-06T14:30:00Z"
generated_by: agent
---

## Owner Decision History — Round 1 — CHANGES REQUESTED, bounded correction (permanent record, never erased)

**Owner Gate 2 Round 1 decision: CHANGES REQUESTED** (not a rejection —
the existing investigation was accepted as strong; one specific,
high-leverage diagnostic was directed as a bounded correction, with an
explicit instruction not to restart the broad framework/PDO investigation
and not to implement remediation). Full verbatim directive preserved in
this file's frontmatter `decision_provenance.owner_response_reference`
above. The correction — deterministic order-preserving delta minimization
of the real 41-test sequence, four reduced-subset trials (7, 39, 10, 36
tests), all failing to reproduce — is recorded in §L of the spec document
and summarized in the Round-2 Owner Summary below. This Round 1 record is
preserved permanently and must not be removed by any future revision.

---

## Owner Summary

Per Owner Gate-1 approval (`01-request.md`, head
`c3d45b62ac7126a718f0093f7f9bebd5714b0742`, PR #303, merged as
`c5516c582d40e5c94b24c4de98121f64d539dfd1`), this Gate 2 performed a
design/research-only investigation attempting to pin the exact PHP-level
trigger for the `PDO::inTransaction()` desync Gate 1 identified. **No
remediation code was written; no tenant/RBAC/product behavior changed.**

**Result: the leading Gate-1 hypothesis (`firstOrCreate()`/
`updateOrCreate()`) is disproven as the direct trigger** — exhaustive
instrumentation of every Eloquent-level nested-transaction call site
(`Illuminate\Database\Concerns\ManagesTransactions`), a complete SQL query
capture (`DB::listen()`), and Sanctum's entire token-guard resolution path
all show **zero activity** in the exact window where the transaction dies
(between the end of the failing test's own setup code and the first line
of the next HTTP request's first middleware). The transition is now
bracketed to that precise window — narrower than any application code the
team maintains — but the exact statement or driver-level event responsible
is **not** pinned to certainty; this is reported honestly as unresolved at
the exact-line level, per the Owner's proven/strongly-supported/unresolved
framework. 11 of 11 full-suite reproductions against real MySQL 8.0 on the
exact CI invocation failed identically, confirming this is not a rare
fluke.

Full evidence, the disproven hypothesis, the three ranked remaining
candidates, blast-radius quantification (including a second, currently
non-gating CI job — Treasury's 18-file real-MySQL step — that shares the
same structural risk and was not independently reproduced), a
root-cause-vs-containment comparison, regression-test specifications,
explicit acceptance criteria, rejected approaches, and fallback criteria
for suite-splitting: `docs/superpowers/specs/2026-09-06-gap-050-gate2-mysql-transaction-isolation-design.md`.

**Round 1 correction (§L of the spec):** per the Owner's directive above,
deterministic order-preserving delta minimization was then performed on
the real 41-test sequence. Four materially different reduced subsets (7,
39, 10, and 36 of the 41 tests — the theoretical precursor set alone,
every fast file with the two slow files removed, the two slow files
alone, and a near-complete 36-test set missing only 6 small unrelated
files) **all failed to reproduce**; only the full 41-test set does. This
is the negative result §K anticipated: it shows the failure depends on
volume/composition close to the *entire* suite's, not a small precursor
or interaction set, and **revises the recommendation from §G's "B+C
correct immediate candidate, A kept equally open" to "B+C as the primary
Gate-3 recommendation"** (§L.5) — while still not closing off root-cause
investigation, since §K's own condition 3 (validated safe N, not merely
"smaller N") remains the right bar before calling containment sufficient.

## Vấn đề vận hành

Gate 1 đã tìm ra CƠ CHẾ (self-healing của RefreshDatabase âm thầm chạy lại
migrate:fresh giữa suite) nhưng chưa chứng minh được ĐIỂM GỌI PHP chính xác
nào khiến `PDO::inTransaction()` lệch trạng thái lần đầu. Owner yêu cầu Gate
2 KHÔNG được mặc định `firstOrCreate()`/`updateOrCreate()` là nguyên nhân —
Gate 2 này đã trực tiếp bác bỏ giả thuyết đó bằng instrumentation toàn diện,
thu hẹp cửa sổ nghi vấn xuống mức chỉ còn framework/package nội bộ (Laravel
core + Sanctum), nhưng KHÔNG tìm ra được câu lệnh/API cụ thể gây ra —
báo cáo trung thực là "chưa giải quyết ở mức chính xác từng dòng, nhưng
được củng cố mạnh ở mức cửa sổ hẹp".

## Bằng chứng

Xem đầy đủ trong tài liệu spec đã dẫn. Tóm tắt cốt lõi:

1. 11/11 lần tái hiện thất bại giống hệt nhau trên `main` canonical, dùng
   đúng lệnh CI, không dùng rerun-until-green làm bằng chứng.
2. Instrumentation trực tiếp vào bản sao dùng-một-lần của
   `ManagesTransactions.php` (mọi entry/exit transaction+savepoint),
   `RefreshDatabase.php` (self-healing check), `Sanctum\Guard.php` (toàn
   bộ nhánh resolve token), cộng với `DB::listen()` bắt TOÀN BỘ câu SQL —
   không có gì trong 4 nguồn instrumentation này xuất hiện trong đúng cửa
   sổ giao dịch chết.
3. Phát hiện phụ (§F tài liệu spec): các request Bearer-token của bộ test
   này thực ra KHÔNG xác thực qua Sanctum guard — chúng xác thực qua
   session guard 'web' còn sót lại từ lần login trước đó trong cùng test,
   do `Authenticate::authenticate()` của Laravel tái xác nhận guard đã
   thoả mãn thay vì luôn thử theo thứ tự route khai báo — đây là một lỗ
   hổng fidelity test THẬT nhưng độc lập với lỗi giao dịch GAP-050, được
   ghi nhận cho một Work ID riêng trong tương lai, không xử lý ở đây.
4. Định lượng blast radius bằng grep tĩnh: `routes-guardrails.yml`'s
   `--group=mysql-parity` (5 file) và đặc biệt `treasury-check-constraints-mysql`
   step 3 (18 file, HIỆN TẠI không gate build vì lý do khác) có cùng mẫu
   cấu trúc rủi ro (nhiều test RefreshDatabase chung 1 tiến trình PHPUnit)
   — theo chỉ đạo Owner, KHÔNG tái hiện độc lập trong đợt sửa này (tracked
   riêng cho Treasury sau).
5. **Delta minimization (§L, đợt sửa 2026-09-06):** giữ nguyên đúng thứ
   tự thực thi thật của 41 test, thử 4 tập con nhỏ hơn khác nhau (7, 39,
   10, 36 test trong tổng 41) — TẤT CẢ đều PASS, không tái hiện được lỗi.
   Chỉ tập đầy đủ 41 test mới tái hiện. Đây là bằng chứng phủ định mạnh:
   lỗi phụ thuộc khối lượng/thành phần gần với TOÀN BỘ suite, không phải
   một tập nhỏ test tiền đề hay một cặp test "nặng" cụ thể nào.

## Đề xuất Gate 3 (chưa được uỷ quyền triển khai) — ĐÃ CẬP NHẬT sau đợt sửa

Khuyến nghị ban đầu (trước đợt sửa): containment (B+C) là ứng viên đúng
NGAY BÂY GIỜ nhưng giữ hướng sửa-tận-gốc (A) mở ngang hàng. **Sau bằng
chứng minimization (§L.5): nâng B+C thành khuyến nghị CHÍNH cho Gate 3**
— việc không thể thu nhỏ dưới ~88% kích thước suite củng cố containment,
nhưng vẫn KHÔNG đóng hẳn hướng A, vì §K điều kiện 3 (N an toàn đã được
XÁC THỰC, không chỉ "N nhỏ hơn") vẫn là ngưỡng đúng trước khi coi
containment là câu trả lời cuối cùng. 6 tiêu chí chấp nhận tường minh (§I)
và 3 đặc tả regression test (§H) vẫn áp dụng cho Gate 3.

## Quyết định Gate 2 cần Owner (Round 2 — sau đợt sửa)

`decision_requested: approve_or_correction_or_defer` — đề nghị Owner xác
nhận: (a) bằng chứng minimization (§L) đã đủ để coi correction này hoàn
tất và chuyển sang Gate 3 theo khuyến nghị B+C đã cập nhật; (b) có đồng ý
việc KHÔNG tái hiện Treasury/mysql-parity trong đợt sửa này (theo đúng chỉ
đạo) và sẽ theo dõi Treasury như một Work ID/track riêng sau; (c) có đồng
ý khuyến nghị mở một Work ID riêng (ngoài GAP-050) cho phát hiện phụ về
Sanctum Bearer-token fidelity (§F), không gộp vào phạm vi triển khai của
GAP-050.

Không có thay đổi code nào được thực hiện trong Gate 2 hay đợt sửa này.
Không có thay đổi hành vi tenant/RBAC/product nào. Không mở PR triển khai.
Dừng tại Gate 2 chờ Owner xem xét.
