---
work_id: GAP-123
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-11-gap-123-work-template-authoring-evidence.md
  plan: null
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
  created_at: "2026-11-11T01:45:01+07:00"
  updated_at: "2026-11-11T01:45:01+07:00"
generated_by: agent
---

# GAP-123 — Authoring work templates in the web app: Gate 2 design

## Owner Summary

Anh/chị cần chọn **3 điểm**.

1. **Soạn được những gì** — đề xuất **E-1**. Mỗi bước gồm:
   - tên, loại (việc / duyệt / bàn giao);
   - làm sau bước nào, số ngày;
   - mô tả, mức ưu tiên;
   - giai đoạn;
   - checklist và tài liệu cần nộp (mỗi dòng một mục).

   Đây đúng là những gì sinh ra việc khi áp dụng mẫu. Các "trường nhập liệu riêng" của mẫu cũ được **giữ nguyên**, không sửa
   trên trang này.
2. **Ai được soạn** — đề xuất **P-1**: chỉ **Owner / quản trị**. Vai trò này đã có sẵn quyền soạn và phát hành, nên không
   đổi phân quyền. PM vẫn chỉ xem và áp dụng mẫu.
3. **Bắt đầu từ đâu** — đề xuất **S-1**:
   - tạo mẫu trống;
   - nút **"Nhân bản"** trên mọi mẫu;
   - thêm **3 mẫu chuẩn tiếng Việt** (Thiết kế, Thi công, Nghiệm thu), dịch từ 3 mẫu tiếng Anh. 3 mẫu tiếng Anh vẫn giữ.

**Luồng làm việc:** danh sách mẫu → soạn (lưu nháp) → xem trước danh sách việc sẽ tạo → **Phát hành**. Chỉ bản đã phát hành
mới hiện ở trang dự án để áp dụng. Sửa một mẫu đã phát hành thì tạo bản nháp mới; dự án đã áp dụng bản cũ không bị ảnh
hưởng.

**Phát hành:** một PR, một Gate 3.

## Điểm 1 — Soạn được những gì

| Phương án | Nội dung |
|---|---|
| **E-1 (đề xuất)** | Mỗi bước soạn được:<br>• tên, loại (việc / duyệt / bàn giao);<br>• "làm sau bước" (chọn các bước phía trên), số ngày;<br>• mô tả, mức ưu tiên;<br>• tên giai đoạn;<br>• checklist (mỗi dòng một mục, đánh dấu bắt buộc bằng `*` đầu dòng);<br>• tài liệu cần nộp (cùng cách).<br>Người làm: luôn là PM của dự án, như các mẫu hiện nay.<br>Trường nhập liệu riêng của bước có sẵn: giữ nguyên, trang chỉ ghi "có N trường nhập liệu riêng". |
| E-2 | Như E-1, thêm soạn trường nhập liệu riêng (tên, kiểu chữ / số / ngày / danh sách chọn, bắt buộc). Trang phức tạp hơn nhiều. |

## Điểm 2 — Ai được soạn và phát hành

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Chỉ Owner / quản trị (`super_admin`): đã có `template.edit_draft` và `template.publish`. Không đổi phân quyền. |
| P-2 | Thêm quyền soạn nháp (`template.edit_draft`) cho PM; phát hành vẫn chỉ Owner. Cần đổi seeder phân quyền. |

## Điểm 3 — Bắt đầu từ đâu

| Phương án | Nội dung |
|---|---|
| **S-1 (đề xuất)** | Mẫu trống, nút "Nhân bản" (chép bản mới nhất sang mẫu mới ở dạng nháp), và **3 mẫu chuẩn tiếng Việt** mới (`WT-VN-THIETKE`, `WT-VN-THICONG`, `WT-VN-NGHIEMTHU`), cùng các bước như 3 mẫu tiếng Anh, đã phát hành. Nạp bằng `production:rbac` như 3 mẫu cũ, cho mọi công ty. |
| S-2 | Chỉ mẫu trống và "Nhân bản". Muốn bản tiếng Việt thì tự nhân bản mẫu tiếng Anh rồi sửa. |

## Thiết kế chi tiết (theo E-1, P-1, S-1)

### Trang

| Route | Quyền | Nội dung |
|---|---|---|
| `app.work-templates.index` | `template.view` | Danh sách mẫu: tên, mã, trạng thái (nháp / đã phát hành), bản phát hành mới nhất, có bản nháp chưa. |
| `app.work-templates.create` / `store` | `template.edit_draft` | Tạo mẫu: mã, tên, mô tả. |
| `app.work-templates.edit` / `update` | `template.edit_draft` | Soạn bản nháp: tên, mô tả và bảng các bước. Có thêm 3 dòng trống để thêm bước; dòng trống được bỏ qua. Không cần JavaScript. Có phần **xem trước**: các việc sẽ tạo, thứ tự, ngày bắt đầu / kết thúc tính từ ngày áp dụng. |
| `app.work-templates.publish` | `template.publish` | Phát hành bản nháp. |
| `app.work-templates.duplicate` | `template.edit_draft` | Nhân bản thành mẫu mới (mã mới), dạng nháp. |

Menu: mục **"Mẫu công việc"** trong nhóm hiện có, hiện theo quyền `template.view`.

### Lưu

- Trang dùng **đúng** `WorkTemplateCrudService` sẵn có: `createTemplate`, `updateTemplate` và `publishTemplate`.
- Nhân bản: tạo template mới với `steps` chép từ bản mới nhất.
- Form được chuyển thành mảng `steps` theo đúng định dạng API. Việc này do service mới `WorkTemplateEditorService` làm:
  - **key** lấy từ tên (giữ key cũ khi sửa);
  - **order** theo số thứ tự;
  - **depends_on:** chỉ được chọn các bước phía trên;
  - **sla_hours** = số ngày × 24;
  - **config:** description, priority, phase_key / phase_name / phase_order, checklist_items, required_docs;
  - **trường nhập liệu riêng** của bước cũ: chép lại nguyên vẹn.
- Kiểm tra trước khi lưu:
  - mỗi bước phải có tên;
  - số ngày từ 0 đến 365;
  - không có bước trùng tên;
  - không có vòng lặp phụ thuộc.
- Mẫu có cấu trúc kiểu mới (`schema_version` 2, có giai đoạn lồng nhau) **không mở để sửa trên trang**. Trang ghi "Mẫu này
  chỉ sửa được qua API". Hiện không mẫu nào thuộc loại này.
- Mọi thao tác ghi nhật ký kiểm toán như API (`zena.work-template.create`, `.update`, `.publish`).

### Mẫu tiếng Việt (S-1)

- Seeder mới `WorkTemplateVietnameseBaselineSeeder` tạo 3 mẫu, cùng cấu trúc và thứ tự bước với 3 mẫu tiếng Anh, tên và nội
  dung bằng tiếng Việt, đã phát hành.
- Chạy từ `production:rbac` và `DatabaseSeeder`, ngay sau seeder tiếng Anh.
- Không ghi đè nếu công ty đã có mẫu cùng mã.

### Test

Test mới `tests/Feature/WorkTemplates/WorkTemplateAuthoringPageTest.php`:
- Owner tạo mẫu 3 bước → lưu nháp → phát hành → mẫu hiện ở danh sách áp dụng của dự án → áp dụng tạo đúng 3 việc:
  đúng tên, thứ tự, phụ thuộc, hạn, checklist và tài liệu.
- Sửa mẫu đã phát hành tạo bản nháp mới. Dự án đã áp dụng giữ bản cũ.
- Nhân bản giữ các bước và trường nhập liệu riêng.
- Kiểm tra lỗi: thiếu tên, trùng tên, vòng lặp, số ngày sai.
- PM không vào được trang soạn (403) nhưng vẫn thấy danh sách.
- Mỗi công ty chỉ thấy mẫu của mình.
- Seeder tiếng Việt chạy lại nhiều lần không tạo trùng; mỗi mẫu có đủ bước như bản tiếng Anh.

## Files (allowlist)

- `app/Http/Controllers/Web/WorkTemplatePageController.php` (mới)
- `app/Services/WorkTemplates/WorkTemplateEditorService.php` (mới)
- `resources/views/work-templates/index.blade.php`, `resources/views/work-templates/edit.blade.php`,
  `resources/views/work-templates/create.blade.php` (mới)
- `routes/web.php`
- `app/Support/Navigation/OperatorNavigationDefinition.php`
- `database/seeders/WorkTemplateVietnameseBaselineSeeder.php` (mới), `database/seeders/DatabaseSeeder.php`,
  `app/Console/Commands/ProductionRbacCommand.php`
- `tests/Feature/WorkTemplates/WorkTemplateAuthoringPageTest.php` (mới)
- `docs/owner-decisions/GAP-123/*`, `docs/audits/2026-11-11-gap-123-work-template-authoring-evidence.md`,
  `docs/superpowers/plans/2026-11-11-gap-123-work-template-authoring.md`

Các test kiểm tra route và menu hiện có (ví dụ danh sách route, kiểm kê nút, test `production:rbac`) có thể cần thêm dòng.
File nào khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung (amendment) trong hồ sơ này.

## Ngoài phạm vi

- Đổi cách áp dụng mẫu vào dự án.
- Trang áp dụng.
- Xoá / lưu trữ mẫu (vẫn qua API).
- Trường nhập liệu riêng (E-2).
- Mẫu tài liệu.
- Deploy.

## Verification required at Gate 3

- Test mới đỏ trước, xanh sau. Các test mẫu công việc hiện có vẫn xanh.
- Thử thật trên app demo: tạo mẫu → phát hành → áp dụng vào dự án → xem các việc tạo ra.
- Toàn bộ Unit + Feature + Integration, PHPStan, php-cs-fixer, ssot:lint, deptrac, governance lint.

## Rollback

Revert PR. Mẫu đã tạo vẫn trong cơ sở dữ liệu; dự án đã áp dụng không bị ảnh hưởng.

## Decision Needed

Owner chọn: `APPROVE GAP-123 Gate 2 E-1 P-1 S-1` (hoặc phương án khác cho từng điểm) / Request more information / Decline /
Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
