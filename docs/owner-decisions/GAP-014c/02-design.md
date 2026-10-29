---
work_id: GAP-014c
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-29-gap-014c-ncr-capa-link-evidence.md
  plan: null
  branch: docs/GAP-014c-ncr-capa-link
  pr: https://github.com/kha997/zenamanagephp/pull/385
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T09:51:02+07:00"
  updated_at: "2026-10-29T09:51:02+07:00"
generated_by: agent
---

# GAP-014c — Durable NCR ↔ CAPA task link: Gate 2 design

## Owner Summary

Đề xuất **Phương án B**:

1. **Lưu liên kết:** mỗi task có thêm ô "thuộc NCR nào" (`ncr_id`, có thể trống). Một NCR có thể có nhiều task CAPA.
2. **Tạo task CAPA từ NCR:**
   - **Trên web:** trang NCR có nút **"Tạo task CAPA"**. Hệ thống tự tạo task (tên, mô tả, mức ưu tiên lấy từ NCR như gợi ý
     hiện nay) và gắn vào NCR.
   - **Qua API:** có lệnh tương ứng; luồng cũ (ứng dụng tự gửi nội dung gợi ý) vẫn chạy và được gắn NCR.
3. **Xem được hai chiều:**
   - Trang NCR và API NCR liệt kê task CAPA kèm trạng thái.
   - API task trả về NCR của nó.
4. **Không chặn đóng NCR** khi task CAPA còn dở; chỉ hiển thị. Muốn chặn thì chọn Phương án C.
5. **Dữ liệu cũ:** task CAPA cũ (chỉ có nhãn) **không tự gắn**, vì chỉ có thể đoán qua số NCR trong tên task. Task mới từ nay
   đều được gắn.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| A. Chỉ lưu liên kết qua API | `ncr_id` trên task; nội dung gợi ý gồm `ncr_id`; API task nhận và kiểm tra; NCR liệt kê task | Nhỏ nhất, nhưng người dùng web vẫn không tạo được task CAPA |
| **B. A + nút "Tạo task CAPA" (web và API)** | Như trên | **Đề xuất** — đóng đủ vòng NCR → khắc phục → theo dõi cho người dùng web |
| C. B + chặn đóng NCR khi còn task CAPA chưa xong | Chuyển NCR sang "đã đóng" bị từ chối nếu có task CAPA chưa hoàn thành (task đã huỷ không tính) | Chặt hơn; đổi quy trình đang dùng — Owner quyết |

## Thiết kế: Phương án B

### 1. Dữ liệu

- Migration mới thêm vào `tasks` cột `ncr_id`: ULID, cho phép trống, có index, khoá ngoại tới `ncrs.id`. Khi NCR bị xoá hẳn
  thì cột về trống (`set null`).
- Quan hệ: `Task::ncr()` và `Ncr::capaTasks()`.
- **Hoàn tác migration:** `down()` gỡ khoá ngoại, index và cột. Chỉ mất liên kết; task và NCR giữ nguyên.

### 2. Tạo liên kết

- **Tạo task CAPA từ NCR:**
  - API `POST /api/zena/inspections/{inspection}/ncrs/{ncr}/capa-tasks`, cần quyền `inspection.edit` và `task.create`.
  - Task được tạo từ đúng nội dung `task_handoff` hiện có, thêm `ncr_id`. Cùng tenant và cùng dự án với NCR.
  - Không tạo được khi NCR đã đóng.
- **Web:** trang NCR có nút "Tạo task CAPA", gọi lệnh trên qua controller web như các thao tác NCR khác.
- **Luồng cũ qua `POST /api/zena/tasks`:**
  - Nội dung `task_handoff.payload` có thêm `ncr_id`.
  - API task nhận `ncr_id` nếu NCR tồn tại, cùng tenant và cùng dự án với task; sai thì trả 422.
- **Không đổi NCR qua update:** sửa task không đổi được `ncr_id`.

### 3. Hiển thị

- API NCR (`show` / `list` / `transformNcr`) thêm `capa_tasks`: id, tên, trạng thái, hạn, người được giao.
- Trang web NCR có mục "Task CAPA" với danh sách và trạng thái.
- API task trả `ncr_id`.

### 4. Không đổi

Cách nhận diện task CAPA cho leo thang task quá hạn và dashboard vẫn dùng nhãn `inspection-ncr-capa`. Không đổi chuyển
trạng thái NCR, thông báo NCR (GAP-014b) hay dashboard (GAP-014a).

### 5. Test (đỏ trước / xanh sau)

`tests/Feature/NcrCapaTaskLinkTest.php` mới:
- Tạo task CAPA từ NCR qua API: task có `ncr_id`, nhãn CAPA, cùng dự án, và NCR liệt kê task.
- `POST /api/zena/tasks` với `ncr_id`: liên kết được lưu. NCR của tenant khác hoặc dự án khác: 422.
- NCR đã đóng: không tạo được task CAPA. Thiếu quyền: 403.
- Trang web NCR có nút tạo task; bấm nút thì task được tạo và hiển thị.
- Migration `down()` chạy được.

Các test hiện có (`InspectionNcrWorkflowTest`, escalation, dashboard) phải giữ xanh.

### 6. Files (allowlist)

- **Migration:** 1 file migration mới.
- **Model:** `app/Models/Task.php` (fillable, quan hệ); `app/Models/Ncr.php` (quan hệ).
- **API:** `app/Http/Controllers/Api/InspectionController.php` (lệnh tạo task CAPA, `capa_tasks`, `ncr_id` trong nội dung
  gợi ý); `app/Http/Controllers/Api/TaskController.php` (nhận và kiểm tra `ncr_id` khi tạo, trả `ncr_id`).
- **Web:** `app/Http/Controllers/Web/InspectionPageController.php` (nút tạo task, nạp task); `resources/views/inspections/ncr-show.blade.php`.
- **Route:** `routes/api_zena.php` và `routes/web.php` (mỗi file 1 route).
- **Test, plan và hồ sơ:** test mới; plan `docs/superpowers/plans/2026-10-29-gap-014c-ncr-capa-link.md`; packet và
  evidence GAP-014c.

Nếu route mới cần mã quyền chưa được seed thì dùng mã đã có. Test bảo vệ của GAP-098 sẽ kiểm tra điều này.

## Verification required at Gate 3

- Đỏ trước / xanh sau cho test mới.
- Các suite NCR, inspection, task, escalation và dashboard xanh.
- Toàn bộ Unit + Feature + Integration và `tests/Feature/Api` không có lỗi mới.
- Migration chạy lên rồi xuống được.
- PHPStan, php-cs-fixer, SSOT lint, route guard và deptrac sạch.
- CI trên đúng head xanh, gồm các job MySQL.

## Rollback

Revert squash commit. Trên môi trường đã chạy migration thì chạy `migrate:rollback` cho đúng migration này: chỉ mất
liên kết, task và NCR còn nguyên.

## Decision Needed

Owner chọn: `APPROVE GAP-014c Gate 2 Option B` (khuyến nghị) / Option A / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge, phát hành, chạy migration trên môi trường thật; không gắn task CAPA cũ.
