---
work_id: GAP-125
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-11-gap-125-mobile-field-pages-evidence.md
  plan: null
  branch: docs/GAP-125-mobile-field-pages
  pr: https://github.com/kha997/zenamanagephp/pull/416
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
  created_at: "2026-11-11T13:28:22+07:00"
  updated_at: "2026-11-11T13:28:22+07:00"
generated_by: agent
---

# GAP-125 — Phone layout for the field pages: Gate 2 design

## Owner Summary

Anh/chị chọn **2 điểm**. Chỉ áp dụng dưới 768px (điện thoại); máy tính giữ nguyên.

1. **Thẻ cho 3 bảng bị cắt** — đề xuất **C-1**. Mỗi dòng thành một thẻ, giống "Việc của tôi" và "Duyệt giờ":
   - **danh sách nhật ký:** ngày, dự án, tóm tắt công việc, nhân lực, trạng thái, người lập, nút "Gửi duyệt" / "Phê duyệt";
   - **danh sách kiểm định:** tiêu đề, kế hoạch QC, người kiểm định, ngày, trạng thái;
   - **bảng NCR** trong trang chi tiết kiểm định: số NCR, tiêu đề, mức độ, trạng thái, người được giao.
2. **Trang chi tiết kiểm định** — đề xuất **D-1**:
   - **Thông tin** (trạng thái, người kiểm định, ngày) lên **đầu trang**;
   - **Mô tả**, **Kết quả**, form **Hoàn tất kiểm định** và form **Ghi nhận NCR mới** thành các mục **gập lại**, bấm để mở.

   Trang ngắn từ gần 5 màn hình xuống khoảng 2.

**Không đụng:** 4 trang không bị tràn (chi tiết NCR, xem / tạo nhật ký, chi tiết công việc).

**Phát hành:** một PR, một Gate 3.

## Điểm 1 — Thẻ cho 3 bảng

| Phương án | Nội dung |
|---|---|
| **C-1 (đề xuất)** | Thẻ dưới 768px cho cả 3 bảng (dùng lại lớp `operator-card-list` / `operator-item-card` của GAP-118). Nút trên thẻ cao ≥ 44px. Bảng máy tính giữ nguyên. |
| C-2 | Chỉ 2 danh sách (nhật ký, kiểm định); bảng NCR trong trang chi tiết giữ nguyên (cuộn ngang). |

## Điểm 2 — Trang chi tiết kiểm định

| Phương án | Nội dung |
|---|---|
| **D-1 (đề xuất)** | Dưới 768px: khối "Thông tin" lên đầu (CSS `order`); "Mô tả", "Kết quả kiểm định", "Hoàn tất kiểm định", "Ghi nhận NCR mới" dùng `<details>` gập lại, bấm tiêu đề để mở. Trên máy tính các khối luôn mở như hiện nay (tiêu đề bấm được bị ẩn). Không có JavaScript thì các khối vẫn mở như cũ. |
| D-2 | Chỉ đưa "Thông tin" lên đầu, không gập khối nào. |

## Thiết kế chi tiết (theo C-1, D-1)

- **Thẻ:**
  - partial mới cho mỗi bảng, đặt trong `operator-phone-only`;
  - bảng hiện có bọc trong `operator-wide-only` (cùng cách GAP-122).
- **Nút trên thẻ nhật ký:** form POST "Gửi duyệt" / "Phê duyệt" giống hệt bảng hiện có (cùng route, cùng điều kiện trạng
  thái).
- **Gập khối:**
  - `<details class="operator-phone-collapse" open data-phone-collapse>`;
  - một đoạn script nhỏ trong trang đóng các khối này khi màn hình < 768px;
  - CSS ẩn tiêu đề bấm được trên máy tính.
- **Thứ tự:** lớp `operator-phone-first` (CSS `order: -1` dưới 768px) cho khối "Thông tin".

**Test** `tests/Feature/Mobile/FieldPagesMobileLayoutTest.php`:
- thẻ có đủ thông tin và đúng nút theo trạng thái;
- bảng máy tính vẫn còn;
- các khối gập có tiêu đề, nội dung và form vẫn đầy đủ.

**Gate 3:** Playwright đo lại trên iPhone 13 (không còn bảng tràn, chiều cao trang chi tiết) và so ảnh máy tính trước / sau.

## Files (allowlist)

- `resources/views/site-diaries/index.blade.php`, `resources/views/site-diaries/_cards.blade.php` (mới)
- `resources/views/inspections/index.blade.php`, `resources/views/inspections/_cards.blade.php` (mới)
- `resources/views/inspections/show.blade.php`, `resources/views/inspections/_ncr-cards.blade.php` (mới)
- `resources/css/operator.css`
- `tests/Feature/Mobile/FieldPagesMobileLayoutTest.php` (mới)
- `docs/owner-decisions/GAP-125/*`, `docs/audits/2026-11-11-gap-125-mobile-field-pages-evidence.md`,
  `docs/superpowers/plans/2026-11-11-gap-125-mobile-field-pages.md`

File khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung.

## Ngoài phạm vi

- Quy tắc, phân quyền, dữ liệu.
- Giao diện máy tính.
- 4 trang không bị tràn.
- RFI, hồ sơ trình duyệt, mua sắm.
- Deploy.

## Verification required at Gate 3

- Test mới đỏ trước, xanh sau.
- Playwright trên điện thoại và máy tính (máy tính giống hệt trước).
- Toàn bộ Unit + Feature + Integration và các lint.

## Rollback

Revert PR. Chỉ giao diện.

## Decision Needed

Owner chọn: `APPROVE GAP-125 Gate 2 C-1 D-1` (hoặc phương án khác cho từng điểm) / Request more information / Decline /
Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
