---
work_id: GAP-119
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-10-gap-119-project-analytics-cleanup-evidence.md
  plan: null
  branch: docs/GAP-119-project-analytics-cleanup
  pr: https://github.com/kha997/zenamanagephp/pull/409
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-10T12:51:29+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-10, verbatim: 'APPROVE GAP-119 Gate 1'. Bound to reviewed Draft PR #409 head 2629336664bd55ffb76eec4aee4cc652c417e39a, canonical base 7352fc46f3dd3f7c55484767d399f3c60b7fc8ab. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-10T12:43:40+07:00"
  updated_at: "2026-11-10T12:51:29+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-119 Gate 1 in-session on 2026-11-10 against Draft PR #409 head
`2629336664bd55ffb76eec4aee4cc652c417e39a`. This authorizes preparation of Gate 2 only.

## Owner Summary

Xử lý phần **thống kê dự án** cũ (mục tồn "ProjectAnalyticsService X-1") và **mã mốc cũ đang hỏng** (để lại từ GAP-117).

**Phần thống kê dự án hỏng hoàn toàn:**
- **4 địa chỉ API** thống kê dự án đều báo **lỗi 500**: xem thống kê một dự án, gợi ý tìm kiếm, tìm kiếm nâng cao, danh sách bộ
  lọc. Nguyên nhân là chúng trỏ tới các hàm không tồn tại. Tôi đã gọi thử cả 4 để xác nhận.
- **Dịch vụ thống kê** (khoảng 700 dòng) không nơi nào gọi tới. Gọi thử thì lỗi ngay vì dùng các hàm dự án không có.
- **Không màn hình nào dùng** các API này. Chức năng tương ứng đã có ở "sức khoẻ dự án" (GAP-103) trên trang dự án và OPPM.

**Mã mốc cũ** (không chạy, không ai gọi):
- sự kiện "hoàn thành mốc" và hai sự kiện dự án khác viết sai, không bao giờ được phát ra;
- bộ ghi nhật ký cho các sự kiện đó chưa từng được đăng ký;
- một dịch vụ thông báo thời gian thực khoảng 400 dòng không ai gọi;
- hàm tính tiến độ theo số mốc không ai dùng, chỉ còn một test.

**Gate 2 sẽ đưa phương án:**
- gỡ hẳn API và dịch vụ thống kê hỏng (kèm test chặn chúng quay lại), hay làm lại API thống kê dựa trên "sức khoẻ dự án";
- gỡ phần mã mốc cũ ở mức nào: chỉ phần mốc, hay cả nhóm sự kiện và dịch vụ không dùng, hay để nguyên.

## Vấn đề vận hành

- Các API hỏng trả lỗi 500 nếu có ai gọi tới.
- Mã chết làm người đọc hiểu nhầm là hệ thống có thống kê và thông báo thời gian thực, trong khi thực tế không có.
- Mã chết cũng tốn công bảo trì: GAP-117 đã phải sửa theo phần thống kê này.

## Người dùng bị ảnh hưởng

Không ai dùng các phần này trên giao diện. Chủ yếu ảnh hưởng tới người phát triển và độ tin cậy của API.

## Bằng chứng

`docs/audits/2026-11-10-gap-119-project-analytics-cleanup-evidence.md`.

## Phạm vi đề xuất

Gate 2: các route `api/analytics/*` của `ProjectAnalyticsController`, dịch vụ thống kê, và nhóm sự kiện / bộ ghi / dịch vụ
thông báo không dùng. Có test chặn. Không đổi màn hình nào đang dùng.

## Loại trừ rõ ràng

- `api/analytics/dashboard` và các API thống kê khác (`AnalyticsController`) đang hoạt động.
- "Sức khoẻ dự án", OPPM.
- Bảng nhật ký dự án và các nơi đang dùng nó.
- Deploy.

## Khả năng hoàn tác

Revert PR. Không migration, không đổi dữ liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-119 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
