---
work_id: GAP-123
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/audits/2026-11-11-gap-123-work-template-authoring-evidence.md
  plan: docs/superpowers/plans/2026-11-11-gap-123-work-template-authoring.md
  branch: docs/GAP-123-work-template-authoring
  pr: https://github.com/kha997/zenamanagephp/pull/413
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
  created_at: "2026-11-11T06:32:35+07:00"
  updated_at: "2026-11-11T06:32:35+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-123 at subject 186e5e2914fb946f7335a7940fb0f7748004b704 (Gate 2 E-1, P-1, S-1; one PR, implementation commit 186e5e29; no migration, no change to how templates are applied). New Web\\WorkTemplatePageController with routes app.work-templates.index (template.view), create / store / edit / update / duplicate (template.edit_draft) and publish (template.publish), scoped to the user's company (404 for another company's template). New WorkTemplates\\WorkTemplateEditorService turns editor rows (name, type task / approval / deliverable / inspection, the steps it follows — only steps above it, so no cycles — days 0-365 stored as sla_hours = days x 24, description, priority, phase, checklist and required documents one per line with '*' for required) into the API steps[] payload and back; custom fields, assignee rule, assignment rules, required document types, other config keys and unchanged checklist / document keys of existing steps are carried over; new steps go to the project manager. Writes go through the existing WorkTemplateCrudService (draft, draft-from-published, publish, versioning) and are audited with the API action names. The edit page previews the tasks apply would create with day offsets matching the apply timeline. Schema v2 templates are shown as API-only. Menu item 'Mẫu công việc'. New WorkTemplateVietnameseBaselineSeeder: WT-VN-THIETKE, WT-VN-THICONG, WT-VN-NGHIEMTHU, published, same step keys / order / dependencies / durations / types / field keys as the English baselines, Vietnamese text, run by production:rbac (when a company exists) and DatabaseSeeder, idempotent. Tests: WorkTemplateAuthoringPageTest (8) red before, green after — end to end create, save, preview, publish, appear in the project apply list, apply creates the tasks with names, dates, dependencies and PM assignee; editing a published template opens a new draft and an applied project keeps its version; custom fields kept and duplicate copies them; validation; PM sees the list but every write is refused and nothing changes; company isolation; menu; Vietnamese seeder. ProjectWorkTemplateApplyTest green. Demo app: created, saved, previewed ('Ngày 3 – 7'), published and applied a 3-step template from the screen; the project received the 3 tasks with the right order, dependencies and dates. PHPStan (no errors, no baseline entries), php-cs-fixer, ssot:lint, deptrac, route guard, docs-lint, governance lint pass. Local Unit + Feature + Integration 3043 tests: only the 2 known root-only failures; Feature/Api 825 green. Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "9f98b2082e2f229c7d7f82761ae6b4538369e9b1"
  subject_sha: "186e5e2914fb946f7335a7940fb0f7748004b704"
  implementation_tree_digest: "8d57e3f4d94bae7fa2f1b1f902f6b740a8aa78d4479e6ccf05b3ca5cef7df9d6"
  verified_pr_head_sha: "186e5e2914fb946f7335a7940fb0f7748004b704"
  verified_at: "2026-11-11T06:32:35+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-123 — Gate 3 release decision (authoring work templates)

## Gói quyết định phát hành

**1. Vấn đề là gì?**
- Không có trang nào để tạo hay sửa mẫu công việc; chỉ tạo được qua API.
- Mỗi công ty chỉ có 3 mẫu tiếng Anh.

**2. Sau thay đổi (Gate 2 E-1, P-1, S-1)** — subject `186e5e29`.

**Menu "Mẫu công việc"**

| Trang | Ai dùng | Làm gì |
|---|---|---|
| Danh sách | Ai có quyền xem mẫu (Owner, PM) | Tên, mã, trạng thái (đã phát hành / có bản nháp), phiên bản phát hành |
| Tạo mẫu | Owner | Mã, tên, mô tả → sang trang soạn |
| Soạn mẫu | Owner | Sửa các bước, lưu nháp, xem trước, nhân bản, phát hành |

**Mỗi bước soạn được:**
- tên, loại (Việc / Duyệt / Bàn giao / Nghiệm thu);
- số ngày, mức ưu tiên, giai đoạn, mô tả;
- checklist và tài liệu cần nộp (mỗi dòng một mục, `*` đầu dòng là bắt buộc);
- **"làm sau bước"**, chỉ chọn được các bước phía trên, nên không thể tạo vòng lặp.

**Lưu ý khi soạn:**
- Có sẵn 3 dòng trống để thêm bước mới.
- Trường nhập liệu riêng của các bước có sẵn được giữ nguyên.
- Phần **xem trước** cho biết việc nào làm ngày nào, tính từ ngày bắt đầu dự án. Ví dụ: "Thiết kế sơ bộ: Ngày 3 – 7".

**Phiên bản:**
- Sửa một mẫu đã phát hành sẽ tạo **bản nháp mới**.
- Dự án đã áp dụng bản cũ không bị ảnh hưởng.
- Chỉ bản đã phát hành mới hiện ở trang dự án để áp dụng.

**3 mẫu tiếng Việt mới, đã phát hành sẵn:**

| Mẫu | Các bước |
|---|---|
| Thiết kế | Thu thập đầu vào thiết kế → Triển khai và phối hợp hồ sơ thiết kế → Duyệt thiết kế nội bộ → Phát hành hồ sơ thiết kế |
| Thi công | Huy động đội thi công → Đặt hàng vật tư và trình mẫu → Thi công hiện trường → Xác nhận sẵn sàng bàn giao |
| Nghiệm thu | Lập kế hoạch nghiệm thu → Kiểm tra tại hiện trường → Khắc phục và đóng nghiệm thu |

- Các bước, thứ tự và số ngày giống hệt bản tiếng Anh.
- Nạp bằng `production:rbac` như 3 mẫu cũ. Chạy lại nhiều lần cũng không tạo trùng.

**Không đổi:**
- cách áp dụng mẫu vào dự án;
- trang áp dụng;
- phân quyền (Owner đã có sẵn quyền soạn và phát hành).

**Hai điểm làm rõ so với thiết kế:**
- **Thêm loại "Nghiệm thu".** Các mẫu chuẩn có dùng loại bước này. Nếu thiếu, mở mẫu chuẩn ra sửa sẽ bị báo lỗi.
- **Khi PM bấm vào trang soạn,** trang đưa về chỗ cũ kèm thông báo "không có quyền", không phải trang lỗi 403. Đây là cách toàn
  bộ trang web hiện xử lý thiếu quyền. Kết quả vẫn như thiết kế: PM không sửa, tạo, phát hành hay nhân bản được gì.

**3. Đã kiểm thử** — base `9f98b208`; subject `186e5e2914fb946f7335a7940fb0f7748004b704`; digest
`8d57e3f4d94bae7fa2f1b1f902f6b740a8aa78d4479e6ccf05b3ca5cef7df9d6`.

**Thử thật trên app demo, làm hoàn toàn trên màn hình:**
1. Tạo mẫu "Nhà phố 3 tầng" gồm 3 bước: Khảo sát hiện trạng → Thiết kế sơ bộ → Chủ nhà duyệt phương án.
2. Lưu nháp, xem trước, phát hành.
3. Áp dụng vào một dự án → dự án có đúng 3 việc tiếng Việt, đúng thứ tự, phụ thuộc và ngày.

**Test tự động mới (8), đỏ trước, xanh sau:**
- cả vòng tạo → lưu → phát hành → áp dụng;
- sửa mẫu đã phát hành tạo bản nháp mới, dự án cũ giữ bản cũ;
- giữ trường nhập liệu riêng; nhân bản chép đủ;
- báo lỗi khi thiếu tên, trùng tên, "làm sau" bước phía dưới, số ngày sai, loại sai;
- PM chỉ xem, không thay đổi được gì;
- không thấy mẫu của công ty khác;
- menu hiện đúng;
- 3 mẫu tiếng Việt đầy đủ và không tạo trùng.

**Kiểm tra khác:**
- Test áp dụng mẫu hiện có vẫn xanh.
- PHPStan sạch, không thêm ngoại lệ nào. php-cs-fixer, SSOT lint, deptrac, kiểm tra route, docs-lint, governance lint đều
  sạch.
- Toàn bộ 3.043 test: chỉ còn 2 lỗi có sẵn do chạy bằng root. API 825 test xanh.

**4. Lưu ý vận hành**
- Sau khi deploy, chạy `php artisan production:rbac` một lần để nạp 3 mẫu tiếng Việt cho các công ty đang có.
- Việc này nằm trong bước deploy của anh/chị; tôi không chạy trên server.

**5. Ngoài phạm vi**
- Xoá / lưu trữ mẫu (vẫn qua API).
- Soạn trường nhập liệu riêng.
- Mẫu tài liệu.
- Deploy.

**6. Rủi ro còn lại** — Thấp.
- Dùng đúng phần lưu và phiên bản sẵn có.
- Dự án đã áp dụng không bị ảnh hưởng.

**7. Hoàn tác** — Revert PR.
- Mẫu đã tạo và 3 mẫu tiếng Việt vẫn nằm trong cơ sở dữ liệu, không ảnh hưởng gì.

**8. Đề xuất** — Phát hành (merge).

**9. CI trên đúng head** — ghi nhận trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-123 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, chạy `production:rbac` trên server.
