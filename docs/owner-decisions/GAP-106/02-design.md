---
work_id: GAP-106
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-11-04-gap-106-treasury-evidence-attachments-evidence.md
  plan: null
  branch: docs/GAP-106-treasury-evidence-attachments
  pr: https://github.com/kha997/zenamanagephp/pull/396
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
  created_at: "2026-11-04T09:45:00+07:00"
  updated_at: "2026-11-04T09:45:00+07:00"
generated_by: agent
---

# GAP-106 — Treasury evidence attachments: Gate 2 design

## Owner Summary

Đính file chứng từ vào các khoản tiền Ngân quỹ. Anh/chị cần chọn **4 điểm**:

1. **Nơi lưu và cách giữ kín.** Đề xuất **S-2**: bảng đính kèm riêng của Ngân quỹ, chỉ xem / tải qua quyền Ngân quỹ.
2. **Khoản nào được đính.** Đề xuất **E-1**: mọi chứng từ tiền của Ngân quỹ.
3. **Bắt buộc chứng từ.** Đề xuất **M-1**: công tắc theo công ty, mặc định tắt. Khi bật, khoản chi phải có ít nhất 1 chứng
   từ mới gửi duyệt được.
4. **Gỡ chứng từ.** Đề xuất **D-1**: chỉ gỡ được khi khoản chưa ghi sổ; đã ghi sổ thì giữ nguyên làm vết.

## Điểm 1 — Nơi lưu và giữ kín

| Phương án | Nội dung | Ưu / nhược |
|---|---|---|
| S-1 | Dùng hệ tài liệu chung (`Document`), gắn loại `treasury_financial_document`, rồi lọc bỏ khỏi trang "Tài liệu" chung và các API tài liệu. | Đúng khuyến nghị "dùng lại" của thiết kế gốc. Nhưng hệ tài liệu có nhiều lối đọc (trang web, nhiều API, thống kê, phiên bản…). Bỏ sót một lối là lộ sao kê / phiếu chi, và phải sửa nhiều chỗ ngoài Ngân quỹ. |
| **S-2 (đề xuất)** | Bảng mới `treasury_document_attachments` (giống đính kèm của quy trình mẫu). File nằm ở ổ lưu trữ riêng tư hiện có. Chỉ xem / tải qua route Ngân quỹ, kiểm tra quyền Ngân quỹ của dự án. | Kín ngay từ đầu, không đụng module tài liệu chung. Khác khuyến nghị "dùng lại" của bản gốc, nhưng chỉ thêm một bảng, không thêm hệ lưu trữ mới: cùng ổ lưu trữ, cùng cách kiểm tra file như phần quy trình mẫu. |

## Điểm 2 — Khoản nào được đính

| Phương án | Nội dung |
|---|---|
| **E-1 (đề xuất)** | Mọi chứng từ tiền của Ngân quỹ: tiền nhận, góp vốn, chuyển ví, khoản chi, tạm ứng, nộp lại tạm ứng, điều chỉnh. "Thiếu chứng từ" chỉ tính cho **khoản chi đã ghi sổ** (nơi bản gốc đòi chứng từ). |
| E-2 | Chỉ khoản chi. |

## Điểm 3 — Bắt buộc chứng từ (§12.7)

| Phương án | Nội dung |
|---|---|
| **M-1 (đề xuất)** | Công tắc theo công ty "Khoản chi phải có chứng từ", lưu trong cài đặt công ty (`tenants.settings`), **mặc định tắt**. Khi bật, khoản chi chưa có chứng từ thì không gửi duyệt được. Chỉ người có quyền chủ (`treasury.self_approve_expense` — chỉ vai trò Owner / Admin có) mới đổi công tắc; việc đổi có ghi nhật ký. |
| M-2 | Luôn bắt buộc với khoản chi. |
| M-3 | Không bắt buộc; chỉ cảnh báo "thiếu chứng từ". |

## Điểm 4 — Gỡ chứng từ

| Phương án | Nội dung |
|---|---|
| **D-1 (đề xuất)** | Người tải lên gỡ được khi khoản còn nháp / chờ duyệt / bị từ chối. Đã ghi sổ thì không gỡ được; đính nhầm thì đính thêm bản đúng (cả hai đều còn, có ghi chú). |
| D-2 | Cho gỡ cả sau ghi sổ, có ghi nhật ký. |

## Thiết kế chi tiết (theo S-2, E-1, M-1, D-1)

### Dữ liệu
Một migration tạo bảng `treasury_document_attachments`:
- Cột:
  - `id` (ulid), `tenant_id`, `project_id`;
  - `financial_document_id`, khoá ngoại ghép (`tenant_id`, `id`) tới `treasury_financial_documents`;
  - `evidence_type`, `original_name`, `file_path`, `mime_type`, `file_size`, `file_hash` (sha256);
  - `note` (cho phép trống), `uploaded_by`, timestamps.
- Ràng buộc unique (`financial_document_id`, `file_hash`): cùng một file không đính hai lần vào cùng khoản.
- Không đổi bảng cũ. Bảng mới, nên không đụng các CHECK của GAP-038.

Loại chứng từ (theo bản gốc §7.12):
- ảnh chuyển khoản, sao kê ngân hàng, hoá đơn, phiếu thu / biên nhận, phiếu chi;
- xác nhận nhận tiền mặt, xác nhận tổ đội;
- hợp đồng / đơn hàng, khác.

### Quy tắc file
- Tối đa 10 MB mỗi file.
- Chỉ nhận PDF, JPG, JPEG, PNG, WEBP, XLSX, XLS, DOCX, DOC; kiểm tra cả đuôi và loại file thật.
- Tên lưu là mã ngẫu nhiên, đặt trong `treasury/{tenant}/{project}/{chứng từ}/`, trên ổ mặc định (riêng tư, không public).
- Tải về kiểu "attachment" (không mở trực tiếp trong trình duyệt), kèm `X-Content-Type-Options: nosniff`.

### Quyền
- **Xem / tải:** chính sách `treasury.view-project` (quyền `treasury.view` + thành viên dự án hoặc `treasury.all_projects`).
- **Đính kèm:** người lập chứng từ đó, hoặc người có `treasury.approve_expense` hay `treasury.reconcile` trên dự án.
- **Gỡ:** theo D-1, chỉ người tải lên, chỉ khi khoản chưa ghi sổ.
- Mọi lần đính / gỡ ghi audit (`entity_type` `treasury_document_attachment`).

### Thiếu chứng từ
- "Thiếu chứng từ" = khoản chi đã ghi sổ (chưa đảo) không có file nào.
- Trang Ngân quỹ: thẻ "Khoản chi thiếu chứng từ" và cảnh báo `missing_evidence` (số khoản, tổng tiền).
- Báo cáo "Thiếu chứng từ" (CSV qua đường S6).
- Sổ giao dịch có bộ lọc "thiếu chứng từ", và mỗi dòng hiện số file đính.

### Giao diện
Trên trang Ngân quỹ:
- Mỗi dòng chứng từ (sổ giao dịch, khoản chi chờ duyệt, nháp) có ô "Chứng từ (n)", mở ra danh sách file kèm loại, người
  tải, ngày, nút Tải về / Gỡ (nếu được), và form đính thêm (chọn loại, file, ghi chú).
- Công tắc "Khoản chi phải có chứng từ" ở trang Ngân quỹ tổng (chỉ người có quyền chủ thấy).

### Dịch vụ / route
- `TreasuryEvidenceService` (mới): đính, gỡ, liệt kê, đếm, tìm khoản thiếu chứng từ, đọc / ghi công tắc.
- `TreasuryExpenseService::submit`: khi công tắc bật mà khoản chi chưa có chứng từ thì báo lỗi "Khoản chi cần ít nhất một
  chứng từ".
- Route web mới (tất cả có `rbac` + chính sách Ngân quỹ):
  - đính: `POST .../treasury/documents/{doc}/attachments`;
  - tải: `GET .../attachments/{att}`;
  - gỡ: `POST .../attachments/{att}/delete`;
  - công tắc: `POST /app/treasury/settings/evidence`.

### Test (đỏ trước / xanh sau)
- **Đính và tải:**
  - đính / tải đúng quyền; người không có quyền Ngân quỹ dự án → bị chặn;
  - file thuộc dự án / tenant khác → 404;
  - file sai loại / quá 10 MB → lỗi;
  - trùng file → lỗi.
- **Gỡ:** được khi nháp; không được sau ghi sổ.
- **Bắt buộc chứng từ:** công tắc tắt → gửi duyệt được; bật → không có chứng từ thì không gửi được, có thì được; chỉ quyền
  chủ đổi được công tắc.
- **Thiếu chứng từ:** thẻ, cảnh báo, báo cáo, bộ lọc đếm đúng (bỏ khoản đã đảo).
- **Không lộ:** file Ngân quỹ **không** xuất hiện ở trang "Tài liệu" chung.
- **Migration:** chạy trên MySQL thật (lên / gỡ / lên).
- **Số truy vấn:** sổ giao dịch không tăng theo số file.

### Files (allowlist)
- Mới:
  - `database/migrations/2026_11_04_100000_create_treasury_document_attachments_table.php`;
  - `app/Models/Treasury/TreasuryDocumentAttachment.php`;
  - `app/Services/Treasury/TreasuryEvidenceService.php`;
  - `app/Http/Controllers/Web/Treasury/TreasuryEvidencePageController.php`.
- Sửa:
  - `app/Services/Treasury/TreasuryExpenseService.php` (kiểm tra khi gửi duyệt);
  - `TreasuryDashboardService.php`, `TreasuryReportService.php`;
  - `app/Http/Controllers/Web/Treasury/TreasuryPageController.php`;
  - `routes/web.php`;
  - views `resources/views/treasury/project.blade.php`, `_dashboard.blade.php`, `index.blade.php`; mới
    `_evidence.blade.php`.
- Test mới `tests/Feature/Treasury/TreasuryEvidenceTest.php`.
- Plan `docs/superpowers/plans/2026-11-04-gap-106-treasury-evidence-attachments.md`; packet và evidence GAP-106.

Không quyền mới, không đổi cách ghi sổ, không sửa module tài liệu chung.

## Ngoài phạm vi

OCR; import sao kê; khoá kỳ; ngưỡng / hạn tạm ứng; xem trước ảnh trong trang; đính kèm qua API; deploy.

## Verification required at Gate 3

Đỏ trước / xanh sau; Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới; migration chạy trên MySQL thật;
PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch; CI trên đúng head xanh.

## Rollback

Revert squash commit; migration `down()` xoá bảng (file đã lưu vẫn nằm trong ổ, xoá tay nếu cần).

## Decision Needed

Owner chọn: `APPROVE GAP-106 Gate 2 S-2 E-1 M-1 D-1` (hoặc phương án khác cho từng điểm) / Changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
