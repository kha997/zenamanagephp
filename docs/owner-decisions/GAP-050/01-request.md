---
work_id: GAP-050
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-09-05-gap-050-mysql-transaction-isolation-gate1-evidence.md
  plan: null
  branch: docs/GAP-050-gate1-mysql-transaction-isolation
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-05T16:20:00Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-05T16:20:00Z"
  updated_at: "2026-09-05T16:20:00Z"
generated_by: agent
---

## Owner Summary

`Zena RBAC/Tenant Invariants (MySQL parity)` fails deterministically (4/4,
100% this session) on canonical `main`
(`475e30eeb549042649b3871d175225fff80bdb11`) using the exact CI invocation,
on `ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource`
(expected `E404.NOT_FOUND`, actual `TENANT_INVALID`). This confirms GAP-049's
own diagnostic finding (a bare `ROLLBACK` wiping a test's own just-inserted
tenant row) and identifies the trigger: Laravel's own `RefreshDatabase`
self-healing check silently re-runs a full `migrate:fresh` mid-suite, on
the live shared connection, whenever `PDO::inTransaction()` unexpectedly
reads false at some earlier test's teardown — proven this session via
direct MySQL general-query-log evidence showing 3 unscheduled full-schema
wipe-and-rebuild cycles during a single 41-test run, beyond the CI script's
own intended one. The failure is confirmed volume/order-dependent (passes
100% in isolation, fails 100% as part of the full suite) and is **not**
caused by GAP-044's previously-fixed DDL helpers (confirmed clean this
run via GAP-044's own embedded probe). The exact PHP call site that first
flips `PDO::inTransaction()` to false was not pinned to certainty in the
time available for this Gate 1 (leading candidate: nested-`SAVEPOINT`
handling inside `firstOrCreate()`/`updateOrCreate()`, used densely by 8 of
the 15 affected test files) — flagged as Gate 2/implementation follow-up,
not required to establish that the mechanism found is real and sufficient.

Full evidence, methodology, blast-radius analysis, production-risk
assessment, and ranked remediation candidates:
`docs/audits/2026-09-05-gap-050-mysql-transaction-isolation-gate1-evidence.md`.

## Vấn đề vận hành

Check `Zena RBAC/Tenant Invariants (MySQL parity)` đỏ ổn định (không phải
flaky ngẫu nhiên — tái hiện 100% với đúng lệnh CI trên `main` hiện tại),
nhưng PASS 100% khi chạy riêng lẻ file test đó. Nguyên nhân gốc không nằm ở
logic của test hay code tenant/RBAC/document, mà ở chính cơ chế tự-bảo-vệ
của Laravel `RefreshDatabase`: khi một test bất kỳ trong cùng tiến trình
PHPUnit khiến `PDO::inTransaction()` bất ngờ trả về `false` lúc teardown,
Laravel tự động đặt lại cờ nội bộ khiến test `RefreshDatabase` TIẾP THEO
chạy lại TOÀN BỘ `migrate:fresh` — âm thầm, giữa chừng suite, ngay trên kết
nối DB mà mọi test khác đang dùng chung — không có dòng log nào báo hiệu
việc này xảy ra. Bằng chứng general query log của MySQL trong phiên này
cho thấy điều đó xảy ra 3 lần ngoài kế hoạch trong 1 lần chạy 41 test.

## Người dùng bị ảnh hưởng

- Chủ dự án / Owner: CI đỏ liên tục trên một check quan trọng
  (tenant-isolation contract), không thể phân biệt "regression thật" với
  "nợ hạ tầng test đã biết" chỉ bằng cách nhìn CI — đúng như GAP-049 đã
  gặp phải và phải xin exception riêng để merge.
- Đội phát triển tương lai: bất kỳ ai thêm test mới dùng
  `firstOrCreate()`/`updateOrCreate()` vào 1 trong 15 file `@group
  zena-invariants` có nguy cơ ngẫu nhiên trở thành "test kích hoạt" hoặc
  "test nạn nhân" của chu trình migrate:fresh ngoài kế hoạch này, khiến CI
  đỏ không đoán trước được.
- Governance: nếu không xử lý, mô hình "xin exception mỗi lần Gate 3 gặp
  check này" (như GAP-049 đã làm) có nguy cơ trở thành tiền lệ ngầm định,
  trái với chính tuyên bố tường minh của GAP-049 rằng exception đó không
  phải waiver chung.

## Bằng chứng

Xem đầy đủ trích dẫn file/dòng, log MySQL general query log, và bằng chứng
tái hiện trực tiếp trong
`docs/audits/2026-09-05-gap-050-mysql-transaction-isolation-gate1-evidence.md`.
Tóm tắt cốt lõi:

1. Tái hiện 4/4 lần trên `main` canonical (`475e30ee`) bằng đúng lệnh CI —
   không dùng rerun-until-green làm bằng chứng, mỗi lần chạy 1 lần, báo cáo
   kết quả thật.
2. Chạy riêng file test lỗi: PASS 100% (4/4 test trong file) — chứng minh
   lỗi phụ thuộc số lượng/thứ tự test trong cùng tiến trình, không phải lỗi
   nội tại của file đó.
3. Log truy vấn MySQL trực tiếp cho thấy `create table tenants` (và toàn bộ
   `drop table` trước đó) chạy 4 lần trong 1 tiến trình PHPUnit — 1 lần dự
   kiến (script CI tự chạy trước khi launch PHPUnit) + 3 lần KHÔNG có trong
   kế hoạch, cả 4 lần cùng connection ID với connection chính mọi test dùng
   chung.
4. Đọc trực tiếp mã nguồn `vendor/laravel/framework/.../RefreshDatabase.php`
   (đúng bản đã khoá trong `composer.lock` của repo) xác nhận cơ chế tự
   healing này là hành vi framework có chủ đích, không phải bug tự viết —
   nhưng tương tác với cách CI gộp 41 test/1 tiến trình tạo ra hệ quả không
   mong muốn.
5. Đã loại trừ: helper DDL đã được GAP-044 vá (`ensureInteractionLogsTable`
   etc.) — probe riêng của GAP-044 xác nhận không lệch trạng thái lần này.

## Đề xuất Gate 2 (chưa được uỷ quyền triển khai)

4 hướng khắc phục được liệt kê với ưu/nhược điểm trong tài liệu bằng chứng
(§J), khuyến nghị kết hợp: (1) tách nhỏ lệnh `--group=zena-invariants`
thành nhiều lần chạy PHPUnit nhỏ hơn (giảm trực tiếp yếu tố khối
lượng/thứ tự đã chứng minh là nguyên nhân), cộng với (3) phát hiện và báo
lỗi tường minh ngay khi cơ chế self-healing của Laravel kích hoạt (biến
một lỗi hiện đang khó chẩn đoán thành một lỗi rõ ràng ngay lập tức).
Hướng (2) — truy tìm chính xác điểm gọi PHP gây lệch `PDO::inTransaction()`
— là hướng triệt để nhất nhưng cần thêm công cụ chẩn đoán chưa hoàn thành
trong Gate 1 này.

## Quyết định Gate 1 cần Owner

`decision_requested: approve_or_correction_or_defer` — đề nghị Owner xác
nhận: (a) chẩn đoán ở Gate 1 này đã đủ vững để mở Gate 2 thiết kế khắc phục
theo hướng khuyến nghị (1+3), hay cần điều chỉnh/đào sâu thêm điểm nào;
(b) có nên đầu tư thêm công cụ chẩn đoán để truy tìm chính xác điểm gọi
PHP (hướng 2) trước khi quyết định Gate 2, hay để đó cho một vòng sau;
(c) mức độ ưu tiên xử lý GAP-050 so với các Work ID khác đang mở.

Không có thay đổi code nào được thực hiện. Không có thay đổi hành vi
tenant/RBAC/product nào. Không mở PR triển khai. Dừng tại Gate 1 chờ Owner
xem xét.
