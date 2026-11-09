---
work_id: GAP-117
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-10-gap-117-project-milestones-evidence.md
  plan: null
  branch: docs/GAP-117-project-milestones
  pr: https://github.com/kha997/zenamanagephp/pull/407
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
  created_at: "2026-11-10T02:45:00+07:00"
  updated_at: "2026-11-10T02:45:00+07:00"
generated_by: agent
---

# GAP-117 — Project milestones (mốc tiến độ): Gate 2 design

## Owner Summary

Anh/chị cần chọn **6 điểm**:

1. **Mốc nằm ở đâu** — đề xuất **L-1**: trang riêng **"Mốc tiến độ"** của từng dự án. Mở từ trang dự án và từ ô "Mốc"
   trên OPPM.
2. **Ai được sửa** — đề xuất **E-1**: PM của dự án, hoặc người có quyền sửa dự án (giống OPPM). Ai xem được dự án thì xem
   được mốc.
3. **Thao tác** — đề xuất **A-1**: thêm, sửa, đánh dấu xong, mở lại, huỷ (có lý do), sắp thứ tự. **Không xoá hẳn**, để giữ
   lịch sử. Mọi thao tác ghi nhật ký.
4. **Tách theo công ty** — đề xuất **T-1**: thêm cột công ty cho mốc, điền sẵn cho dữ liệu cũ, tự lọc theo công ty.
5. **"Quá hạn"** — đề xuất **O-1**: không lưu cứng nữa, luôn tính theo ngày thực tế. Dời ngày mục tiêu thì tự hết quá hạn.
6. **Mã cũ hỏng** (sự kiện "hoàn thành mốc", hàm tính tiến độ theo mốc không ai gọi) — đề xuất **C-2**: để nguyên, làm
   riêng sau. Các phần này không chạy nên không ảnh hưởng.

**Phát hành:** một PR, một Gate 3, một migration chỉ thêm cột.

## Điểm 1 — Mốc nằm ở đâu

| Phương án | Nội dung |
|---|---|
| **L-1 (đề xuất)** | Trang riêng **"Mốc tiến độ"** (`/app/projects/{dự án}/milestones`), có danh sách và các ô nhập. Trang dự án có nút "Mốc tiến độ"; ô "Mốc" trên OPPM có link sang. |
| L-2 | Thẻ "Mốc tiến độ" ngay trên trang chi tiết dự án. Trang dự án vốn đã dài, thêm ô nhập sẽ rối hơn. |

## Điểm 2 — Ai được sửa

| Phương án | Nội dung |
|---|---|
| **E-1 (đề xuất)** | **Xem:** người có `project.view` trong cùng công ty. **Sửa:** PM của dự án (`pm_id`) hoặc người có `project.update`, giống OPPM (GAP-110). Không thêm quyền mới. |
| E-2 | Thêm quyền mới `milestone.manage` và cấp cho các vai trò. Phải sửa seeder quyền và lệnh `production:rbac`. |

## Điểm 3 — Thao tác

| Phương án | Nội dung |
|---|---|
| **A-1 (đề xuất)** | **Thêm / sửa:** tên (bắt buộc), mô tả, ngày mục tiêu (bắt buộc), thứ tự. **Xong:** ngày xong mặc định hôm nay, không được ở tương lai. **Mở lại:** mốc đã xong hoặc đã huỷ trở về "đang chờ". **Huỷ:** bắt buộc có lý do. Mốc đã huỷ không sửa được, phải mở lại trước. **Không xoá hẳn.** Mọi thao tác ghi nhật ký (giá trị trước / sau). |
| A-2 | Như A-1, thêm **xoá hẳn**. Mất lịch sử; OPPM đã chụp lại số cũ sẽ không đối chiếu được. |

## Điểm 4 — Tách theo công ty

| Phương án | Nội dung |
|---|---|
| **T-1 (đề xuất)** | Migration thêm cột `tenant_id` (được để trống) và chỉ mục, rồi **điền cho mọi mốc cũ** theo dự án. Mốc mới tự lấy công ty của dự án. Model dùng bộ lọc công ty chuẩn (`TenantScope`). Xử lý luôn phần mốc trong rủi ro AUD-05. |
| T-2 | Giữ như hiện nay: chỉ lọc qua dự án, thêm test cách ly. Rẻ hơn, nhưng sau này chỉ cần một truy vấn quên lọc là lộ dữ liệu. |

## Điểm 5 — "Quá hạn"

| Phương án | Nội dung |
|---|---|
| **O-1 (đề xuất)** | Bỏ đoạn tự lưu "overdue". "Quá hạn" = đang chờ và ngày mục tiêu đã qua, tính lúc hiển thị. Các trang OPPM / Hôm nay vốn đã tính như vậy. Cùng migration trên, mốc cũ đang lưu "overdue" đổi về "pending" (thông tin quá hạn vẫn còn vì ngày mục tiêu không đổi). |
| O-2 | Giữ nguyên. Mốc dời ngày vẫn bị đếm là quá hạn trên OPPM. |

## Điểm 6 — Mã cũ hỏng

| Phương án | Nội dung |
|---|---|
| **C-2 (đề xuất)** | Không đụng `ProjectMilestoneCompleted`, `LogProjectActivity`, `Project::calculateProgress()`. Không có gì gọi chúng, và bộ ghi nhật ký kia cũng thiếu import cho cả ba sự kiện. Nên dọn trong một Work ID riêng. |
| C-1 | Xoá sự kiện hỏng và nhánh tương ứng trong listener, cùng PR này. Mở rộng phạm vi sang phần nhật ký dự án cũ. |

## Thiết kế chi tiết (theo L-1, E-1, A-1, T-1, O-1, C-2)

### Dữ liệu (một migration, loại `expand`)
- Thêm `project_milestones.tenant_id` (ULID, được để trống) và chỉ mục `(tenant_id, project_id)`.
- Điền `tenant_id` từ `projects.tenant_id` cho mọi dòng.
- Đổi `status = overdue` → `pending`.
- `down()` bỏ cột và chỉ mục. Trạng thái đã đổi không đổi ngược: thông tin quá hạn vẫn tính lại được từ ngày.
- Thêm một dòng `expand` vào `database/migrations/classifications.json`.

### Model `ProjectMilestone`
- Dùng `TenantScope`.
- Khi tạo: điền `tenant_id` từ dự án nếu còn trống.
- Bỏ đoạn tự lưu "overdue".
- `isOverdue()` tính theo ngày (đang chờ và mục tiêu trước hôm nay).
- `markCompleted()` lưu ngày, không lưu giờ.

### Dịch vụ `ProjectMilestoneService`
- Các hàm: `create`, `update`, `complete`, `reopen`, `cancel`, `listForProject`.
- Mọi thao tác ghi kiểm tra E-1 (403 nếu không đủ quyền) và khoá dòng mốc.
- Kiểm tra mốc thuộc đúng dự án và công ty (404 nếu không).
- Ghi `AuditLog` (`milestone.created` / `updated` / `completed` / `reopened` / `cancelled`), có giá trị trước / sau.
- Lý do huỷ lưu trong `metadata`.

### Giao diện (web)
- Các route trong nhóm `app.` (mẫu POST giống OPPM), đều có `rbac:project.view` và tìm dự án theo công ty:
  - GET `/projects/{project}/milestones` → `projects.milestones.index`;
  - POST `/projects/{project}/milestones` → `.store`;
  - POST `/projects/{project}/milestones/{milestone}` → `.update`;
  - POST `/projects/{project}/milestones/{milestone}/complete`, `/reopen`, `/cancel`.
- Tên route khác với các route `milestones.*` cũ đã gỡ, nên guard `DeadMilestoneTemplateApiRemovedTest` vẫn giữ nguyên.
- Trang hiện danh sách theo thứ tự rồi theo ngày. Mỗi mốc có nhãn: đang chờ / quá hạn (đỏ) / xong / đã huỷ.
- Người chỉ có quyền xem thấy danh sách, không thấy ô nhập.
- Trang dự án có nút "Mốc tiến độ"; ô "Mốc" trên OPPM có link "Quản lý mốc".

### Test (đỏ trước / xanh sau)
**`ProjectMilestoneServiceTest`**
- Thêm / sửa / xong / mở lại / huỷ.
- Kiểm tra dữ liệu: tên, ngày bắt buộc; ngày xong không ở tương lai; huỷ cần lý do; mốc đã huỷ không sửa được.
- Quyền: PM, `project.update`, người chỉ xem bị từ chối, công ty khác bị từ chối.
- Nhật ký trước / sau.
- `tenant_id` tự điền.
- "Quá hạn" tự hết khi dời ngày.

**`ProjectMilestonePageTest`**
- Trang hiện mốc; người chỉ xem không thấy ô nhập.
- POST qua route; công ty khác nhận 404.
- Mốc vừa tạo hiện trên OPPM và trang "Hôm nay".

**Migration (trong `ProjectMilestoneServiceTest`)**
- Điền `tenant_id` và đổi overdue → pending cho dữ liệu cũ.

**`AudTenantScopeCoverageTest`**
- Thêm `ProjectMilestone` vào danh sách model phải có bộ lọc công ty.

Các test sẵn có về mốc (OPPM, Hôm nay, thống kê, PM dashboard) phải vẫn xanh.

## Files (allowlist)

- `database/migrations/2026_11_10_100000_add_tenant_id_to_project_milestones.php` (mới)
- `database/migrations/classifications.json`
- `app/Models/ProjectMilestone.php`
- `app/Services/ProjectMilestoneService.php` (mới)
- `app/Http/Controllers/Web/ProjectMilestonePageController.php` (mới)
- `routes/web.php`
- `resources/views/projects/milestones.blade.php` (mới)
- `resources/views/projects/show.blade.php`
- `resources/views/projects/_oppm-body.blade.php`
- `tests/Feature/ProjectMilestones/ProjectMilestoneServiceTest.php` (mới)
- `tests/Feature/ProjectMilestones/ProjectMilestonePageTest.php` (mới)
- `tests/Feature/Audit/AudTenantScopeCoverageTest.php`
- `docs/owner-decisions/GAP-117/*`, `docs/audits/2026-11-10-gap-117-project-milestones-evidence.md`,
  `docs/superpowers/plans/2026-11-10-gap-117-project-milestones.md`

File nào khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung (amendment) trong hồ sơ này.

## Ngoài phạm vi

- Gắn mốc với công việc / mẫu công việc.
- Gantt.
- Nhắc việc, thông báo.
- Cổng khách hàng.
- Giao diện điện thoại.
- Đổi cách tính tiến độ dự án.
- API JSON cho mốc.
- Dọn mã cũ hỏng (C-2).
- Deploy.

## Verification required at Gate 3

- Test mới: đỏ trước khi làm, xanh sau.
- Toàn bộ Unit + Feature + Integration và Feature/Api.
- Migration chạy được cả trên MySQL: lên, xuống, lên lại.
- PHPStan, php-cs-fixer, `ssot:lint`, deptrac, governance lint.
- CI trên đúng head.

## Rollback

Revert PR. Migration `down()` bỏ cột `tenant_id`. Mốc đã tạo vẫn còn; trạng thái quá hạn vẫn tính lại được từ ngày.

## Decision Needed

Owner chọn: `APPROVE GAP-117 Gate 2 L-1 E-1 A-1 T-1 O-1 C-2` (hoặc phương án khác cho từng điểm) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
