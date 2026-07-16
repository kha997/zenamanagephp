# Document Generation Slice 1 Implementation Plan (Goal #4)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 2 biểu mẫu PDF sinh tự động: Biên bản nghiệm thu khối lượng (per chứng chỉ đã duyệt) và Phụ lục bảng khối lượng (per hợp đồng có BOQ), kèm helper số-thành-chữ tiếng Việt.

**Architecture:** Fixed-Blade + `DeliverablePdfExportService::render()` — nhân bản pattern đang chạy tại `Api\ContractController::pdf` (dòng 311-336) và `Web\ContractPageController::downloadPdf`. Không migration, không model mới ngoài 1 helper thuần. Spec: `docs/superpowers/specs/2026-07-14-document-generation-1-design.md`.

## Global Constraints

- Toàn bộ quy tắc đã thành nếp: `Model::query()`, CẤM helper `auth()` (dùng `Auth` facade), **file mới KHÔNG BAO GIỜ có entry trong phpstan-baseline.neon** (kiểm bằng `git diff <base>..HEAD -- phpstan-baseline.neon | grep "^+.*path:"` → phải trống), count baseline chỉ giảm. Nếu PHPStan kêu magic property trên model → thêm `@property` docblock vào model, không baseline.
- Checklist sau MỖI task: Architecture (29) / Feature (905) / phpstan exit 0. Sau push cuối: guardrails CI success.
- Test permission-denial dùng role `team_member` (admin bypass RBAC).
- Blade PDF: tái dùng đúng khung style của `resources/views/contracts/pdf.blade.php` (DejaVu Sans — bắt buộc cho tiếng Việt có dấu trong PDF).
- **Bước 0 bắt buộc trước khi viết test endpoint:** đọc test hiện có của endpoint PDF hợp đồng (grep `contracts.pdf\|downloadPdf\|DeliverablePdfExportUnavailable` trong tests/) để biết CI xử lý engine PDF thế nào (mock? skip? engine thật?) và MIRROR đúng cách đó.

---

### Task 1: `VietnameseMoneyWords` helper (thuần, TDD)

**Files:** Create `app/Support/VietnameseMoneyWords.php`; Test `tests/Unit/Support/VietnameseMoneyWordsTest.php`.

**Interface:** `public static function toWords(float $amount): string` — làm tròn về đồng, trả chuỗi thường kết thúc bằng " đồng" (viết hoa chữ đầu), ví dụ `toWords(225000000)` = `"Hai trăm hai mươi lăm triệu đồng"`.

- [ ] **Step 1 — failing test** với bảng case CHÍNH XÁC (viết trước, không sửa sau khi implement):

| input | expected |
|---|---|
| 0 | `Không đồng` |
| 1000 | `Một nghìn đồng` |
| 15000000 | `Mười lăm triệu đồng` |
| 225000000 | `Hai trăm hai mươi lăm triệu đồng` |
| 620000000 | `Sáu trăm hai mươi triệu đồng` |
| 1234567890 | `Một tỷ hai trăm ba mươi tư triệu năm trăm sáu mươi bảy nghìn tám trăm chín mươi đồng` |
| 101 | `Một trăm linh một đồng` |
| 21 | `Hai mươi mốt đồng` |
| 5000005 | `Năm triệu không trăm linh năm đồng` |

- [ ] **Step 2 — implement** (thuật toán nhóm 3 chữ số chuẩn VN: đơn vị nghìn/triệu/tỷ; quy tắc "mốt" cho 1 ở hàng đơn vị khi hàng chục ≥2, "lăm" cho 5 khi hàng chục ≥1, "linh" khi hàng chục =0 và hàng đơn vị >0, "không trăm" giữa các nhóm). Pure static, không phụ thuộc framework.
- [ ] **Step 3 — PASS toàn bảng → full checklist → commit** `feat(support): Vietnamese money-to-words helper`.

---

### Task 2: Biên bản nghiệm thu khối lượng (PDF A)

**Files:**
- Modify: `routes/web.php` — cạnh `contracts.certificates.show`:
  `Route::get('/contracts/{id}/certificates/{certificate}/pdf', [..., 'certificatePdf'])->middleware('rbac:payment_certificate.view')->name('contracts.certificates.pdf');`
- Modify: `app/Http/Controllers/Web/ContractPageController.php` — method `certificatePdf`
- Create: `resources/views/contracts/certificate-pdf.blade.php`
- Modify: `resources/views/contracts/certificate-show.blade.php` — nút xuất (chỉ khi approved)
- Test: `tests/Feature/Zena/CertificatePdfTest.php`

**Controller (mirror `downloadPdf` của chính file này — đọc nó trước):** scoped fetch contract + certificate (`where('contract_id', ...)` → 404 cross-contract); guard `status === approved` else back-error "Chỉ xuất biên bản cho chứng chỉ đã duyệt."; data = certificate + lines qua `PaymentCertificateSummaryService::lineSummaries($cert)` + contract + tenant name + `VietnameseMoneyWords::toWords($cert->net_payable)`; `view('contracts.certificate-pdf', ...)->render()` → `$pdfService->render($html, [], ['generated_at' => now()->toIso8601String()])` (inject `DeliverablePdfExportService` qua method param như downloadPdf); bắt `DeliverablePdfExportUnavailableException` → back-error; response headers y hệt downloadPdf, filename `bien-ban-nghiem-thu-ky-{$cert->period_no}-{$contract->code}.pdf`.

**Blade** (khung style copy `contracts/pdf.blade.php`): quốc hiệu 2 dòng căn giữa; `<h1>BIÊN BẢN NGHIỆM THU KHỐI LƯỢNG HOÀN THÀNH</h1>` + `Kỳ {{ $certificate->period_no }} ({{ from → to d/m/Y }})`; đoạn thông tin: dự án, số HĐ, Bên A `{{ $contract->client_name ?? '—' }}`, Bên B `{{ $tenantName }}`; bảng hạng mục từ `$lineSummaries` (STT, tên — lấy qua boqLineItem, ĐVT, KL HĐ, lũy kế trước, **KL kỳ này**, đơn giá, thành tiền `number_format`); hàng tổng `{{ number_format($certificate->total_this_period) }}`; khối khấu trừ: `Giữ lại (retention): −{{ ... }}` / `Thu hồi tạm ứng: −{{ ... }}`; dòng đậm `ĐỀ NGHỊ THANH TOÁN: {{ number_format($certificate->net_payable) }} {{ $contract->currency }}` + `Bằng chữ: {{ $amountInWords }}`; bảng 2 cột ký `ĐẠI DIỆN BÊN A` / `ĐẠI DIỆN BÊN B` với `(Ký, ghi rõ họ tên)`.

**Test** (setup seed như `CertificateDeductionsTest` — HĐ retention 5%/advance 200tr/recovery 20%, kỳ 1 300tr đã approve → net 225tr):
1. View-render test (không cần engine): `view('contracts.certificate-pdf', [...])->render()` chứa "BIÊN BẢN NGHIỆM THU", "Kỳ 1", "225,000,000", "Hai trăm hai mươi lăm triệu đồng", tên hạng mục, "Giữ lại".
2. Endpoint approved → theo cách test PDF hiện có của repo (Bước 0): 200 + `application/pdf` hoặc cách mock/skip tương đương.
3. Certificate draft → redirect + error; certificate của contract khác → 404; tenant khác → 404; role `team_member` không có `payment_certificate.view` → chặn.
4. Nút render: certificate-show hiện "Xuất biên bản" khi approved, KHÔNG hiện khi draft.

- [ ] Steps: Bước 0 đọc test PDF cũ → failing tests → route+controller+blade+nút → PASS → full checklist → commit `feat(documents): acceptance-minutes PDF per approved payment certificate`.

---

### Task 3: Phụ lục bảng khối lượng (PDF B)

**Files:** route `GET /contracts/{id}/boq-pdf` (rbac `contract.view`, name `contracts.boq.pdf`); method `boqPdf` trong `ContractPageController`; `resources/views/contracts/boq-pdf.blade.php`; nút trong card "Bảng khối lượng HĐ" trên `contracts/show.blade.php`; test `tests/Feature/Zena/ContractBoqPdfTest.php`.

- Controller: scoped contract; `$contract->boq` với lineItems — nếu null/0 dòng → back-error "Hợp đồng chưa có bảng khối lượng."; tổng = Σ qty×unit_price (dòng thiếu giá tính 0, hiển thị "—" ở cột đơn giá); render + pdf như Task 2; filename `phu-luc-khoi-luong-{$contract->code}.pdf`.
- Blade: `PHỤ LỤC HỢP ĐỒNG — BẢNG KHỐI LƯỢNG` + `Số HĐ: {{ $contract->code }}`; bảng (STT, Mã, Hạng mục, ĐVT, Khối lượng, Đơn giá, Thành tiền); hàng `TỔNG GIÁ TRỊ: {{ number_format($total) }} {{ $contract->currency }}` + `Bằng chữ: ...`; khối ký 2 bên như Task 2.
- Test: view-render chứa tiêu đề + tên dòng + tổng + bằng chữ; HĐ không BOQ → back error; cross-tenant 404; endpoint 200 pdf (theo pattern Bước 0); nút hiện trong card BOQ.

- [ ] Steps: failing tests → implement → PASS → full checklist → commit `feat(documents): contract BOQ appendix PDF`.

---

### Task 4: Final verification + PR

- [ ] 3 con số chuẩn (Feature dự kiến 905 + số test mới, ghi lại) + baseline-diff trống path mới + guardrails CI success sau push.
- [ ] `gh pr create` — base = nhánh mà PR #164 vừa merge vào, title `feat(documents): acceptance minutes + BOQ appendix PDFs (goal #4 slice 1)`, body tóm tắt + 3 con số. KHÔNG merge.

## Self-review notes

- Spec coverage: C0→T1 (bảng case khớp spec), A→T2, B→T3; error-handling từng dòng của spec có test tương ứng (draft-guard, 404 đồng nhất, engine-unavailable theo pattern repo).
- Tên route/filename/nhãn tiếng Việt khai báo một lần mỗi nơi, nhất quán controller↔blade↔test.
- Không migration, không đổi hành vi module nào — additive thuần.
