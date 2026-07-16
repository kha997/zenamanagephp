# Document Generation Slice 1 — Design Spec (Goal #4)

Date: 2026-07-14
Status: approved by user (documents A + B, fixed-Blade engine, 2026-07-14)
Depends on: IPC + Retention slices (PaymentCertificate, deductions, contract BOQ) — merged.

## Purpose

Goal #4 hiện ~15% (một PDF hợp đồng duy nhất). Slice 1 thêm 2 biểu mẫu giá trị cao nhất, dữ liệu đã có sẵn 100%, treo trên trục hợp đồng:

- **A — Biên bản nghiệm thu khối lượng** (per `PaymentCertificate` ĐÃ DUYỆT) — giấy tờ khách cần để chuyển tiền.
- **B — Phụ lục hợp đồng: Bảng khối lượng** (per contract có BOQ) — đính kèm khi ký HĐ.

## User-approved decisions

1. Slice 1 = A + B; các biểu mẫu khác (bàn giao hồ sơ thiết kế, biên bản hiện trường) để sau.
2. **Engine: fixed-Blade** theo đúng pattern `contracts/pdf.blade.php` + `DeliverablePdfExportService::render($html, $options, $meta)` (đã xác minh tại `Api\ContractController::pdf`, dòng 311-336). Template tự soạn/placeholder (`DeliverableTemplateVersion`) là slice 2 — KHÔNG đụng trong slice này.

## Component 0 — Số thành chữ tiếng Việt

Biên bản VN bắt buộc dòng "Bằng chữ: ...". Helper thuần `App\Support\VietnameseMoneyWords::toWords(float $amount): string` (đơn vị "đồng", xử lý 0, hàng nghìn/triệu/tỷ, quy tắc "mốt/lăm/linh", làm tròn về số nguyên đồng). Pure function + unit test bảng case.

## Component A — Biên bản nghiệm thu khối lượng

- Route: `GET /contracts/{id}/certificates/{certificate}/pdf` (nhóm operator, rbac `payment_certificate.view`), name `contracts.certificates.pdf`.
- CHỈ cho chứng chỉ `approved` — trạng thái khác trả back-error "Chỉ xuất biên bản cho chứng chỉ đã duyệt."
- Nội dung blade `contracts/certificate-pdf.blade.php` (khung DejaVu Sans như pdf hợp đồng): quốc hiệu tiêu ngữ; tên biên bản + "Kỳ {period_no}" + giai đoạn from→to; thông tin dự án/hợp đồng/2 bên (bên A = `client_name` HĐ, bên B = tenant name); bảng hạng mục từ `PaymentCertificateSummaryService::lineSummaries()` (STT, hạng mục, ĐVT, KL HĐ, lũy kế trước, KL kỳ này, đơn giá, thành tiền); tổng giá trị kỳ; khấu trừ: Giữ lại retention / Thu hồi tạm ứng; **Đề nghị thanh toán: {net_payable}** + "Bằng chữ: ..."; ngày duyệt; khối ký ĐẠI DIỆN BÊN A / ĐẠI DIỆN BÊN B.
- Filename: `bien-ban-nghiem-thu-ky-{N}-{contract_code}.pdf`.
- Nút "Xuất biên bản (PDF)" trên trang certificate-show, chỉ hiện khi `approved`.

## Component B — Phụ lục bảng khối lượng

- Route: `GET /contracts/{id}/boq-pdf` (rbac `contract.view`), name `contracts.boq.pdf`. Yêu cầu HĐ có BOQ với ≥1 dòng — không thì back-error.
- Blade `contracts/boq-pdf.blade.php`: tiêu đề "PHỤ LỤC HỢP ĐỒNG — BẢNG KHỐI LƯỢNG" + số HĐ; bảng (STT, mã, hạng mục, ĐVT, khối lượng, đơn giá, thành tiền = qty×price); **Tổng giá trị** + "Bằng chữ: ..."; khối ký 2 bên.
- Filename: `phu-luc-khoi-luong-{contract_code}.pdf`. Nút trong card "Bảng khối lượng HĐ".

## Error handling

- Cross-tenant/không tồn tại: 404 (scoped findOrFail như các endpoint contract hiện có); certificate khác contract: 404.
- Engine PDF không khả dụng: bắt `DeliverablePdfExportUnavailableException` → back-error thân thiện (web context — khác API 501; xem cách `ContractPageController::downloadPdf` xử lý và làm cùng kiểu).
- Không tự động tạo Document record trong hệ quản lý tài liệu (YAGNI — chỉ tải file; lưu trữ vào Document là slice sau nếu cần).

## Testing

- Unit `VietnameseMoneyWords`: 0 / 1.000 / 15.000.000 / 225.000.000 / 1.234.567.890 / số có "mốt", "lăm", "linh" — bảng case đối chiếu chuỗi chính xác.
- View render (không cần engine PDF): render 2 blade với dữ liệu seed, assert chuỗi then chốt (tên biên bản, số kỳ, "Đề nghị thanh toán", số tiền format, "Bằng chữ", tên hạng mục).
- Endpoint: certificate approved → 200 `application/pdf` (theo cách test pdf hợp đồng hiện có xử lý engine trong CI — đọc test đó trước, mirror); certificate draft → back error; cross-tenant → 404; thiếu quyền → chặn (role `team_member`).
- Regression: Contract + Certificate suites, guardrails CI xanh sau push.

## Out of scope

Template tự soạn/placeholder (slice 2), biên bản bàn giao thiết kế, lưu PDF vào Document module, chữ ký số, email gửi khách, đa ngôn ngữ.
