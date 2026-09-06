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
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-06T12:59:20Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-06T12:59:20Z"
  updated_at: "2026-09-06T12:59:20Z"
generated_by: agent
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
   — chưa được tái hiện độc lập trong Gate 2 này.

## Đề xuất Gate 3 (chưa được uỷ quyền triển khai)

Khuyến nghị: containment (tách nhỏ lệnh CI + phát hiện lỗi rõ ràng khi
self-healing kích hoạt) là bước Gate-3 phù hợp NGAY BÂY GIỜ vì lý do gốc
chưa xác định chắc chắn được — nhưng phải giữ hướng sửa-tận-gốc mở, không
đóng lại, và phải đáp ứng tiêu chí fallback tường minh (§K tài liệu spec)
trước khi coi containment là câu trả lời CUỐI CÙNG thay vì tạm thời. 6 tiêu
chí chấp nhận tường minh (§I) và 3 đặc tả regression test (§H) đã được định
nghĩa cho Gate 3 sử dụng.

## Quyết định Gate 2 cần Owner

`decision_requested: approve_or_correction_or_defer` — đề nghị Owner xác
nhận: (a) mức độ thu hẹp và bằng chứng phủ định giả thuyết
firstOrCreate/updateOrCreate đã đủ nghiêm ngặt để chuyển sang Gate 3
containment theo khuyến nghị B+C, hay cần đầu tư thêm một vòng chẩn đoán
sâu hơn (MySQL general_log/performance_schema mức dưới PHP) trước khi
quyết định; (b) có cần tái hiện độc lập 2 job MySQL còn lại (mysql-parity
5 file, Treasury 18 file) trước khi coi blast radius đã đủ rõ; (c) có chấp
nhận việc job Treasury hiện không gate build (vì lý do khác) có thể đang
âm thầm hấp thụ chính lỗi này mà không ai biết, và có cần một Work ID
riêng để xử lý việc đó.

Không có thay đổi code nào được thực hiện trong Gate 2. Không có thay đổi
hành vi tenant/RBAC/product nào. Không mở PR triển khai. Dừng tại Gate 2
chờ Owner xem xét.
