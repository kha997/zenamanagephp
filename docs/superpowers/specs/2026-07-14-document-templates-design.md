# Document Templates (Goal #4 Slice 2) — Design Spec

Date: 2026-07-14
Status: approved by user (option A — in-app editor, 2026-07-14)
Depends on: Doc-gen slice 1 (merged, PR #165); existing DeliverableTemplate/Version engine.

## Purpose

"Thư viện biểu mẫu" đúng nghĩa: công ty tự soạn template HTML với placeholder trong app, cho 3 ngữ cảnh dữ liệu (hợp đồng / chứng chỉ nghiệm thu / dự án), preview tức thì, publish theo version, rồi xuất PDF từ đúng chỗ dữ liệu đang hiển thị. 2 biểu mẫu fixed-Blade của slice 1 GIỮ NGUYÊN (chuẩn cứng); template tự soạn là lớp bổ sung.

## Verified existing engine (reuse, không viết lại)

- Template = HTML chứa `{{key}}`; `DeliverableTemplateVersionService`: `inferPlaceholdersFromHtml()`, `normalizePlaceholdersSpec()` (6 kiểu: string/number/boolean/date/datetime/html), `renderHtml($templateHtml, array $context)` — thay thế bằng mảng phẳng key→value; version có `semver/storage_path/checksum/published_at`; file lưu `Storage::disk('local')`.
- PDF: `DeliverablePdfExportService::render($html, $options, $meta)`.

## User-approved decisions

1. **Option A**: soạn HTML trong app (textarea) + bảng placeholder theo ngữ cảnh (copy được) + nút "Xem thử" render với dữ liệu mẫu + nút "Chèn mẫu khởi điểm".
2. 3 context slice này: `contract`, `certificate`, `project` (context `work_instance` cũ giữ nguyên hoạt động hiện tại).
3. Quyền mới `document_template.view` / `document_template.manage` (seed như các quyền trước); luồng version draft→publish dùng cơ chế sẵn có.

## Component 1 — Context provider registry

`App\Services\DocumentContext\` gồm interface + registry + 3 provider:

```php
interface DocumentContextProvider
{
    public function slug(): string;                 // 'contract' | 'certificate' | 'project'
    public function label(): string;                // nhãn tiếng Việt
    /** @return list<array{key: string, type: string, label: string}> */
    public function keys(): array;                  // nguồn sinh bảng placeholder trên UI
    /** @return array<string, mixed> */
    public function build(\Illuminate\Database\Eloquent\Model $subject): array;
    /** @return array<string, mixed> */
    public function sample(): array;                // dữ liệu mẫu cho Xem thử (không chạm DB)
}
```

`DocumentContextRegistry` (bind singleton, đăng ký 3 provider) — `get(string $slug)`, `all()`.

Key tối thiểu mỗi provider (đặt tên `snake_case`, có prefix nhóm):
- **contract**: `contract_code, contract_title, contract_type_label, client_name, total_value, total_value_words, currency, signed_at, start_date, end_date, project_name, project_code, tenant_name, today` + `boq_table_html` (type html — bảng khối lượng render sẵn).
- **certificate**: toàn bộ key contract + `period_no, period_from, period_to, total_this_period, retention_amount, advance_deduction, net_payable, net_payable_words, approved_at` + `lines_table_html` (type html — bảng hạng mục kèm lũy kế, tái dùng `PaymentCertificateSummaryService`).
- **project**: `project_name, project_code, project_status, manager_name, client_display, tenant_name, today` + `design_items_table_html` (bảng hạng mục thiết kế + trạng thái + sửa lần N).

`*_words` dùng `VietnameseMoneyWords` (slice 1). Giá trị tiền format `number_format`. Key nào null → chuỗi rỗng (renderHtml sẵn xử lý — xác minh khi implement).

## Component 2 — Schema + quyền

- `deliverable_templates` + cột `context` string default `'work_instance'` (backfill tự nhiên qua default; template cũ không đổi hành vi).
- Seed 2 quyền mới vào `ZenaPermissionsSeeder` + `TestDatabaseSeeder`, grant cùng chỗ với `contract.*`.

## Component 3 — UI thư viện biểu mẫu (operator)

Routes nhóm operator, rbac tương ứng:
- `GET /document-templates` (view) — danh sách: tên, context label, version published mới nhất, status.
- `GET /document-templates/create`, `POST /document-templates` (manage) — form: tên, mô tả, context (select 3), HTML (textarea monospace), bên phải bảng placeholder từ `provider->keys()` (key + kiểu + nhãn, bấm copy), nút "Chèn mẫu khởi điểm" (JS chèn sample HTML per context — sample nhúng sẵn trong view).
- `GET /document-templates/{id}/edit`, `POST /document-templates/{id}` (manage) — sửa = tạo **version mới** qua `DeliverableTemplateVersionService` (lưu file storage, infer spec); KHÔNG sửa đè version cũ.
- `POST /document-templates/{id}/preview` (manage) — render HTML hiện tại của form với `provider->sample()` → trả HTML hiển thị trong khung (target _blank hoặc iframe-srcdoc; giữ đơn giản: mở tab mới trả text/html).
- `POST /document-templates/{id}/publish` (manage) — set `published_at` version mới nhất.

## Component 4 — Xuất tài liệu theo template

- Trang `contracts.show`: card/khối "Xuất biểu mẫu" — select các template `context=contract` đã publish + nút Xuất; tương tự trên `certificate-show` (`context=certificate`, chỉ khi certificate approved) và `projects.show` (`context=project`).
- Endpoint: `GET /contracts/{id}/documents/{template}` (+ tương tự certificate/project), rbac `contract.view`/`payment_certificate.view`/`project.view`: load subject scoped theo tenant → provider `build($subject)` → `renderHtml(template html, context)` → PDF qua `DeliverablePdfExportService` → download, filename slug từ tên template + mã thực thể. Template chưa publish hoặc sai context → 404.

## Error handling

Template thiếu placeholder so với context → key thừa của context bị bỏ qua, placeholder không có trong context render thành chuỗi rỗng (khoan chặn cứng — spec infer đã cảnh báo lúc soạn qua bảng placeholder). Preview với HTML quá 200KB → validation error. Mọi endpoint scoped tenant + 404 đồng nhất. PDF engine unavailable → back-error như slice 1.

## Testing

Provider unit: mỗi provider build() từ seed thật ra đúng key/giá trị (kể cả `*_words`, `*_table_html` chứa tên hạng mục); sample() đủ key so với keys(). CRUD template + version tăng khi sửa + publish; preview trả HTML chứa dữ liệu mẫu; export: đúng template đúng context → PDF, sai context → 404, chưa publish → 404, cross-tenant → 404, thiếu quyền (team_member) → chặn; template cũ context work_instance không hỏng (chạy suite WorkInstance export hiện có). Baseline: 0 path mới.

## Out of scope

Rich-text/WYSIWYG editor, DOCX, đa ngôn ngữ, chữ ký số, lưu bản xuất vào Document module, xóa template (chỉ status), share template giữa tenant.
