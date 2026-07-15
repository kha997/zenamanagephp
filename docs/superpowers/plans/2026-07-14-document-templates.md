# Document Templates Implementation Plan (Goal #4 Slice 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thư viện biểu mẫu tự soạn: editor HTML in-app + bảng placeholder theo 3 context (contract/certificate/project), preview với dữ liệu mẫu, publish theo version, xuất PDF từ trang hợp đồng/chứng chỉ/dự án.

**Architecture:** Tổng quát hóa engine sẵn có — KHÔNG viết lại substitution: mọi render đi qua `DeliverableTemplateVersionService::renderHtml()`; cái mới là registry 3 `DocumentContextProvider` + cột `context` + UI thư viện + endpoint xuất. Spec: `docs/superpowers/specs/2026-07-14-document-templates-design.md`.

## Global Constraints

- Mọi quy tắc đã thành nếp: `Model::query()`, CẤM helper `auth()` (Auth facade), **file mới không có entry baseline** (`git diff <base>..HEAD -- phpstan-baseline.neon | grep "^+.*path:"` → trống; lỗi magic property → `@property` docblock trên model), count baseline chỉ giảm.
- Checklist sau MỖI task: Architecture 29 / Feature ≥922 / phpstan exit 0. Push cuối: guardrails CI success.
- Test permission-denial dùng role `team_member`.
- **BƯỚC 0 của mọi task đụng DeliverableTemplate/Version:** đọc `DeliverableTemplateVersionService` + `Api\DeliverableTemplateController` + test `DeliverableTemplateMvpApiTest` TRƯỚC — tái dùng method sẵn có (tạo version, lưu storage, infer spec, publish), không tự chế luồng song song. Nếu service thiếu method cần thiết (vd tạo version từ HTML string), thêm method mới vào service đó thay vì viết service mới.
- Template cũ context `work_instance` phải hoạt động y nguyên: suite `WorkInstanceDeliverableExportApiTest` + `DeliverableTemplateMvpApiTest` là guard — chạy sau mỗi task đụng schema/service.

---

### Task 1: Context provider registry + 3 providers

**Files:**
- Create: `app/Services/DocumentContext/DocumentContextProvider.php` (interface — đúng chữ ký trong spec Component 1), `DocumentContextRegistry.php`, `ContractContextProvider.php`, `CertificateContextProvider.php`, `ProjectContextProvider.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind registry singleton — xem cách các singleton khác được bind trong file này trước)
- Test: `tests/Unit/Services/DocumentContextProvidersTest.php` (Feature-style RefreshDatabase nếu cần seed)

**Nội dung provider theo đúng bảng key trong spec.** Điểm cần chú ý:
- `certificate` provider: gọi `ContractContextProvider::build()` cho phần contract keys (composition, không copy-paste); `lines_table_html` render bảng `<table>` inline-style (PDF không có CSS ngoài) từ `PaymentCertificateSummaryService::lineSummaries()` — cột như blade certificate-pdf slice 1.
- `*_words` dùng `App\Support\VietnameseMoneyWords::toWords()`.
- `sample()`: mảng literal thuần (không DB), đủ MỌI key của `keys()` — test tự đối chiếu `array_keys(sample()) ⊇ keys()`.
- Kiểu trả về build(): mọi giá trị string hóa sẵn (`number_format` cho tiền, `d/m/Y` cho ngày) — renderHtml chỉ thay chuỗi.

- [ ] Steps: failing test (mỗi provider: build() từ seed thật assert ≥5 key đại diện gồm 1 `*_words` + 1 `*_table_html` chứa tên hạng mục; sample-coverage test; registry get/all) → implement → PASS → checklist → commit `feat(documents): document context providers for contract, certificate, project`.

---

### Task 2: Cột `context` + quyền + CRUD/editor/preview/publish UI

**Files:**
- Create: migration `add_context_to_deliverable_templates_table` (string default `'work_instance'`, real down())
- Modify: `app/Models/DeliverableTemplate.php` (fillable + `@property`), seeders 2 quyền `document_template.view/manage` (mirror cách seed `contract.expense.*`)
- Create: `app/Http/Controllers/Web/DocumentTemplatePageController.php`, views `resources/views/document-templates/{index,form}.blade.php`
- Modify: `routes/web.php` (nhóm operator, 6 route theo spec Component 3)
- Test: `tests/Feature/Zena/DocumentTemplateLibraryTest.php`

**Điểm thực thi:**
- `store`/`update`: validate name/context (`Rule::in(['contract','certificate','project'])` — KHÔNG cho tạo mới context work_instance qua UI này) + `html` required max 200000; lưu qua `DeliverableTemplateVersionService` (Bước 0 xác định method — tạo template + version, storage, infer spec). Update = version mới.
- `preview`: validate html như trên + context → `renderHtml($html, $registry->get($context)->sample())` → `response($html, 200)->header('Content-Type', 'text/html; charset=utf-8')`.
- Form view: textarea monospace tên `html`; cột phải bảng placeholder (loop `provider->keys()`: `{{key}}` + nhãn + kiểu, nút copy = JS `navigator.clipboard`); nút "Chèn mẫu khởi điểm" (JS gán textarea = sample HTML nhúng trong `@verbatim` block per context — 3 mẫu ngắn ~30 dòng: khung quốc hiệu + tiêu đề + vài placeholder + khối ký, phong cách blade PDF slice 1); nút Xem thử submit form preview `formtarget="_blank"`.
- `publish`: set `published_at = now()` cho version mới nhất của template (qua service nếu có method, không thì update trực tiếp version model).

- [ ] Steps: Bước 0 → failing tests (CRUD list/create/edit tạo version mới/publish; preview trả html chứa giá trị sample; validation context lạ; permission denial team_member; **suite WorkInstance export + DeliverableTemplateMvpApiTest vẫn xanh**) → implement → PASS → checklist → commit `feat(documents): template library UI with editor, placeholders panel, preview and publish`.

---

### Task 3: Endpoint xuất theo template + dropdown trên 3 trang

**Files:**
- Modify: `routes/web.php` — 3 route:
  - `GET /contracts/{id}/documents/{template}` (rbac `contract.view`, name `contracts.documents.render`)
  - `GET /contracts/{id}/certificates/{certificate}/documents/{template}` (rbac `payment_certificate.view`, name `contracts.certificates.documents.render`)
  - `GET /projects/{project}/documents/{template}` (nhóm app, rbac `project.view`, name `projects.documents.render`)
- Modify: `ContractPageController` (2 method), `Web\ProjectController` (1 method) — pattern chung: subject scoped tenant findOrFail → template scoped tenant + `where('context', ...)` + có version published (`whereNotNull('published_at')` mới nhất) → thiếu điều kiện nào cũng `findOrFail`/404 → `renderHtml(file HTML của version, provider->build($subject))` → PDF qua `DeliverablePdfExportService` (bắt Unavailable → back-error) → download `Str::slug(template name)-{code}.pdf`. Certificate route: thêm guard approved như certificate-pdf slice 1.
- Modify views: `contracts/show.blade.php`, `contracts/certificate-show.blade.php` (chỉ khi approved), `projects/show.blade.php` — khối "Xuất biểu mẫu": nếu có template published đúng context → select + nút GET; controller show() truyền danh sách (id, name).
- Test: `tests/Feature/Zena/DocumentTemplateRenderTest.php` — happy path 3 context (mirror cách test PDF slice 1 xử lý engine); template sai context → 404; chưa publish → 404; cross-tenant → 404; certificate draft → back-error; dropdown render đúng danh sách.

- [ ] Steps: failing tests → routes → methods → views → PASS → checklist → commit `feat(documents): render published templates to PDF from contract, certificate and project pages`.

---

### Task 4: Final verification + PR

- [ ] 3 con số + baseline-diff trống path mới + guardrails CI success + suite WorkInstance/DeliverableTemplate cũ xanh.
- [ ] `gh pr create` — base = nhánh đích các PR trước, title `feat(documents): self-service document template library (goal #4 slice 2)`. KHÔNG merge.

## Self-review notes

- Spec coverage: C1→T1, C2+C3→T2, C4→T3; error-handling (missing-key → rỗng, 404 đồng nhất, engine unavailable, preview size cap) đều có test chỉ định.
- Interface provider khai báo một lần (spec C1), T1 implement, T2 (bảng placeholder, sample) và T3 (build) chỉ tiêu thụ.
- Backward-compat: default `'work_instance'` + 2 suite guard cũ chạy ở T2/T4.
