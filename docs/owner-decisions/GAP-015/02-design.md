---
work_id: GAP-015
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-29-gap-015-worktemplate-ui-owner-evidence.md
  plan: null
  branch: docs/GAP-015-worktemplate-ui-owner
  pr: https://github.com/kha997/zenamanagephp/pull/387
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
  created_at: "2026-10-29T18:20:19+07:00"
  updated_at: "2026-10-29T18:20:19+07:00"
generated_by: agent
---

# GAP-015 — WorkTemplate / WorkInstance screens: Gate 2 design (scope choice)

## Owner Summary

Đây là quyết định sản phẩm, chia nhiều lần phát hành. Đề xuất **Phương án A — làm trước màn hình THỰC HIỆN công việc theo
mẫu** (người ngoài hiện trường dùng hằng ngày), để màn hình SOẠN mẫu cho lần sau (ít người dùng, đang có nhập/xuất gói qua
API):

1. **Trong trang dự án**, thêm mục **"Công việc theo mẫu"**: danh sách các phiên làm việc (WorkInstance) đã áp dụng cho dự
   án, tiến độ (số bước xong / tổng).
2. **Trang chi tiết một phiên**: danh sách bước; mỗi bước xem / cập nhật trạng thái, người làm, hạn, các ô dữ liệu của
   bước; tải lên / xoá file đính kèm; **duyệt bước** (người có quyền duyệt); nút xuất báo cáo.
3. **Quyền mặc định**: PM có `work.view`, `work.update`, `work.approve`, `work.export`; Project Member có `work.view`,
   `work.update` (không duyệt). Admin giữ nguyên.
4. Màn hình chỉ gọi các API đã có (không đổi luật nghiệp vụ). Không đổi API soạn mẫu.

## So sánh phương án

| Phương án | Lần này làm gì | Kết luận |
|---|---|---|
| **A. Màn hình thực hiện công việc theo mẫu** | Danh sách + chi tiết phiên, cập nhật / duyệt bước, đính kèm, xuất; cấp quyền `work.*` cho PM / Member | **Đề xuất** — giá trị vận hành cao nhất, dùng API sẵn có |
| B. Màn hình soạn mẫu | Danh sách mẫu, tạo / sửa nháp (phase, bước, ô dữ liệu, checklist), phát hành, xoá; sửa quyền PM (`template.edit_draft`, `template.publish`) | Lớn hơn A (trình soạn nhiều cấp); ít người dùng |
| C. Cả A và B một lần | Như trên | Rất lớn, rủi ro cao — không nên gộp |
| D. Không xây | Ghi nhận "mẫu công việc chỉ dùng qua API" là phạm vi chủ đích, đóng GAP-015 | Không tốn công; người dùng web vẫn không làm được bước |

## Thiết kế sơ bộ: Phương án A (chi tiết file và test ở Gate 2 bổ sung nếu anh/chị chọn A)

- Route web mới dưới `/projects/{project}/work-instances` (danh sách, chi tiết) và các POST cho cập nhật bước, duyệt, đính
  kèm, xuất — mỗi route gắn `rbac:work.*` tương ứng, controller web gọi `WorkInstanceController` qua cách delegate đã dùng ở
  các trang khác (`DelegatesToApiControllers`).
- View Blade theo layout operator hiện có; trang dự án có link tới mục mới.
- Seeder: thêm cấp quyền `work.*` cho PM / Member (cùng cách GAP-030).
- Test: trang danh sách / chi tiết hiển thị đúng; cập nhật / duyệt / đính kèm qua web thành công và bị chặn đúng quyền;
  tenant khác 404; seeder cấp đúng quyền.

**Không đổi:** API, luật nghiệp vụ bước / duyệt, soạn mẫu (B), dữ liệu cũ.

## Decision Needed

Owner chọn: `APPROVE GAP-015 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Option D / Request changes / Decline.

Nếu chọn A hoặc B, tôi bổ sung danh sách file chính xác và test vào packet này trước khi code (anh/chị duyệt lại phần bổ
sung); nếu chọn D, tôi cập nhật register / roadmap và đóng GAP-015.

## What the owner is NOT being asked to decide

Chưa duyệt code, Gate 3, merge, phát hành hay seed môi trường thật.
