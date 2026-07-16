# Native Quotes (Goal #2 Slice 1) — Design Spec

Date: 2026-07-15
Status: approved by user (option A — native song song external, 2026-07-15)
Depends on: CRM (Opportunity), contract-finance spine (Contract → contract BOQ → certificates), doc-gen engine.

## Purpose

Báo giá có số tiền thật làm NGAY TRONG ZENA: dòng báo giá + đơn giá, chào nhiều lần (revision), gửi khách, chốt → **tự sinh Hợp đồng + Bảng khối lượng HĐ từ đúng các dòng đã chốt** — khép kín vòng báo giá → hợp đồng → nghiệm thu → tiền. External zena-boq-core GIỮ NGUYÊN song song, không đụng flow hiện có.

## User-approved decisions

1. **Option A**: native là nguồn chính thức mới; external không đụng.
2. Slice 1: chứng cứ đơn giá chỉ là `price_note` per dòng; thư viện đơn giá chuẩn/so sánh lịch sử = slice sau.
3. Copy nhanh trong slice 1: tạo báo giá mới từ bản cũ (revision) — thao tác nhiều nhất của sale.

## Verified integration facts

- `Contract` đã có sẵn `source_opportunity_id`, `source_quote_id`, `source_quote_revision` — native quote TÁI DÙNG các cột này (không migration trên contracts).
- `Api\OpportunityController::createContract` hiện guard "external quote must be ACCEPTED" (quanh dòng 470) và tự tạo Project nếu opportunity chưa convert — flow này được MỞ RỘNG, không viết flow song song.

## Data

`quotes`: ULID, `tenant_id` (+TenantScope), `opportunity_id` (indexed), `quote_number` (unique per tenant, tự sinh `BG-{YYYY}-{seq 4 số theo tenant+năm}`), `revision_no` unsignedInteger (unique cùng `opportunity_id`), `status` (draft/sent/accepted/rejected/superseded — TRANSITIONS map kiểu DesignItem), `subtotal` decimal(15,2) denormalized, `valid_until` date nullable, `notes` text nullable, `sent_at`/`decided_at` timestamps nullable, `created_by`, timestamps.

`quote_line_items`: ULID, `tenant_id`, `quote_id` (indexed), `sort_order` unsignedInteger, `code` nullable, `name`, `unit`, `quantity` decimal(14,3), `unit_price` decimal(15,2), `amount` decimal(15,2) (= qty×price, tính server-side), `price_note` string(500) nullable, timestamps. **Chú ý tên index unique/FK ≤ 64 ký tự (bài học MySQL) — đặt tên tường minh nếu tên tự sinh dài.**

## Lifecycle

```
draft → sent → accepted | rejected
draft → superseded (khi bị thay bởi revision mới đã gửi)
sent/rejected → superseded
accepted → (không đi đâu — chốt)
```

- Chỉ `draft` được sửa dòng (thêm/sửa/xóa, giữ nguyên pattern lock của certificate).
- **Gửi** (`sent`): yêu cầu ≥1 dòng, set `sent_at`, tính lại `subtotal`.
- **Khách chấp nhận** (`accepted`): set `decided_at`; đồng thời mọi quote khác của opportunity đang ở draft/sent → `superseded` (một opportunity chỉ một bản chốt). **Từ chối** (`rejected`): set `decided_at`.
- **Tạo bản chào mới** từ quote bất kỳ (kể cả rejected): copy toàn bộ dòng + notes sang quote draft mới, `revision_no` = max+1 của opportunity; bản gốc giữ nguyên status (lịch sử đàm phán).
- EventRecord cho sent/accepted/rejected (aggregate `quote`).

## Accept → Contract + BOQ (điểm ăn tiền của slice)

Mở rộng `Api\OpportunityController::createContract`:
- Guard hiện tại đổi thành: đạt khi **(external quote accepted) HOẶC (tồn tại native Quote `accepted` của opportunity)**. Message lỗi cập nhật tương ứng.
- Khi nguồn là native quote: `source_quote_id` = quote ULID, `source_quote_revision` = revision_no, `total_value` = subtotal của quote; sau khi tạo Contract, trong CÙNG transaction: tạo `Boq` (contract_id) + copy từng `QuoteLineItem` → `BoqLineItem` (code/name/unit/quantity/unit_price) — bảng khối lượng HĐ ra đời từ đúng dòng đã chốt, sẵn sàng cho chuỗi nghiệm thu.
- Nếu cả hai nguồn cùng accepted: ưu tiên native (ghi rõ trong response message). Idempotent như hiện tại (đã có contract → trả contract cũ).

## UI (operator, quyền dùng `crm.view`/`crm.manage` sẵn có — không quyền mới)

- Trang opportunity show: card "Báo giá (native)" — bảng quotes (số, rev, tổng, status badge, ngày), nút "Tạo báo giá" / per-quote "Tạo bản chào mới".
- Trang quote detail (`operator.crm.quotes.show`): thông tin + bảng dòng; khi draft: form thêm dòng (name/unit/qty/price/price_note) + sửa/xóa dòng + nút Gửi khách; khi sent: nút "Khách chấp nhận" (confirm 2 lần) / "Khách từ chối" + "Tạo bản chào mới"; hiện subtotal + "Bằng chữ".
- **PDF báo giá**: `GET quotes/{id}/pdf` — fixed blade `crm/quote-pdf.blade.php` (khung DejaVu Sans; tiêu đề BẢNG BÁO GIÁ, số + revision, thông tin account/opportunity, bảng dòng, tổng + bằng chữ qua `VietnameseMoneyWords`, hiệu lực đến `valid_until`, khối ký). Template-context `quote` cho thư viện biểu mẫu = slice sau.

## Error handling

Transition sai → back-error qua TRANSITIONS map; sửa dòng khi không draft → back-error; accept khi opportunity đã có contract từ nguồn khác → vẫn cho accept quote nhưng createContract idempotent trả contract cũ kèm message; cross-tenant/khác opportunity → 404 đồng nhất; PDF engine unavailable → back-error như các slice trước.

## Testing

Lifecycle đầy đủ (kể cả superseded hàng loạt khi accept); revision copy giữ nguyên dòng + price_note; subtotal đúng từng đồng; quote_number tuần tự theo tenant+năm, không đụng tenant khác; accept→createContract: contract đúng total/source_quote_id/revision + Boq + lines khớp từng dòng, idempotent; guard external-only cũ vẫn hoạt động (regression CrmApiTest + external quote suites); PDF view-render có dấu tiếng Việt + bằng chữ; RBAC (team_member); TenantScope guard 2 model mới; baseline 0 path mới.

## Out of scope

Thư viện đơn giá chuẩn & so sánh lịch sử (slice 2 của goal #2), discount/VAT/điều khoản thanh toán trên báo giá, gửi email cho khách, hiển thị quote trên client portal, template-context `quote`, đồng bộ 2 chiều với external.
