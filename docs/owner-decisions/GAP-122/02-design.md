---
work_id: GAP-122
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-11-gap-122-mobile-time-review-evidence.md
  plan: docs/superpowers/plans/2026-11-11-gap-122-mobile-time-review.md
  branch: docs/GAP-122-mobile-time-review
  pr: https://github.com/kha997/zenamanagephp/pull/412
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-11T00:11:10+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-11, verbatim: 'APPROVE GAP-122 Gate 2 L-1 A-1 N-1'. Reviewed design head: 6f10de5fefaf73f371362495448b0b55892f7235. Approves L-1 (phone cards grouped by person with a total per person), A-1 (per-card approve and reject-with-reason forms plus an approve-all-for-this-person form, posting to the existing review action; no pre-ticked boxes on the phone) and N-1 (one-line week navigation on the phone), one PR / one Gate 3, with the exact allowlist in this packet; no change to review rules, permissions, the month lock or the desktop table; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-11T00:09:40+07:00"
  updated_at: "2026-11-11T00:11:10+07:00"
generated_by: agent
---

# GAP-122 — Phone layout for "Duyệt giờ": Gate 2 design

## OWNER GATE 2: APPROVED — L-1, A-1, N-1

Owner approved L-1, A-1 and N-1 in-session on 2026-11-11 against reviewed design head
`6f10de5fefaf73f371362495448b0b55892f7235`. This authorizes only the bounded implementation defined by this packet; it does not authorize Gate 3,
merge, release, or deployment.

## Owner Summary

Anh/chị cần chọn **3 điểm**. Mọi thay đổi chỉ áp dụng cho màn hình **dưới 768px** (điện thoại); máy tính giữ nguyên.

1. **Cách hiển thị** — đề xuất **L-1**:
   - mỗi dòng giờ là một thẻ;
   - các thẻ **gom theo người**, đầu nhóm ghi tên và **tổng giờ** của người đó.
2. **Cách duyệt** — đề xuất **A-1**:
   - mỗi thẻ có nút **"Duyệt"** và **"Từ chối"** (bấm "Từ chối" thì mở ô nhập lý do);
   - mỗi nhóm có nút **"Duyệt cả N dòng của <tên>"**;
   - **không còn ô chọn sẵn** trên điện thoại, nên không còn duyệt hoặc từ chối nhầm hàng loạt.
3. **Thanh chọn tuần** — đề xuất **N-1**: trên điện thoại rút gọn thành một hàng "‹ Tuần trước · Tuần sau ›", không bị
   xuống dòng.

**Nguyên tắc:**
- Không đổi quy tắc duyệt, phân quyền hay khoá tháng.
- Các nút trên thẻ gửi vào **đúng chức năng duyệt hiện có**, nên mọi kiểm tra vẫn áp dụng như cũ.

**Phát hành:** một PR, một Gate 3.

## Điểm 1 — Cách hiển thị (dưới 768px)

| Phương án | Nội dung |
|---|---|
| **L-1 (đề xuất)** | Thẻ cho từng dòng giờ, **gom theo người**. Đầu nhóm: tên + tổng giờ chờ duyệt. Thẻ: ngày, việc, công trình, **thời lượng (chữ lớn)**, ghi chú, nhãn "Tự dừng" / "Nhập tay". |
| L-2 | Thẻ theo thứ tự hiện có (ngày, người), không gom. Đơn giản hơn nhưng khó soát tuần của từng người. |

## Điểm 2 — Cách duyệt (dưới 768px)

| Phương án | Nội dung |
|---|---|
| **A-1 (đề xuất)** | Mỗi thẻ: nút "Duyệt" và "Từ chối". "Từ chối" mở ô lý do (bắt buộc) và nút xác nhận. Mỗi nhóm: nút "Duyệt cả N dòng của <tên>". Không có ô chọn trên điện thoại. |
| A-2 | Giữ cách chọn nhiều dòng như máy tính, nhưng ô chọn to hơn, **mặc định không chọn**, và thanh nút duyệt / từ chối dính ở đáy màn hình. |

## Điểm 3 — Thanh chọn tuần

| Phương án | Nội dung |
|---|---|
| **N-1 (đề xuất)** | Trên điện thoại: một hàng gọn "‹ Tuần trước", "Tuần sau ›", "Giờ của tôi", không xuống dòng. Máy tính giữ như cũ. |
| N-2 | Giữ nguyên (nút vẫn bấm được, chỉ xuống dòng). |

## Thiết kế chi tiết (theo L-1, A-1, N-1)

### Cấu trúc trang

- **Bảng máy tính** và form chọn nhiều dòng được bọc trong `operator-wide-only`: ẩn dưới 768px, không đổi gì khác.
- **Thẻ** (partial mới `time/_review-cards.blade.php`) bọc trong `operator-phone-only`: chỉ hiện dưới 768px.
- Thẻ nằm **ngoài** form của bảng, mỗi nút có form riêng. Ô chọn bị ẩn của bảng không bao giờ bị gửi kèm.

### Mỗi nút trên thẻ là một form POST tới `app.time.review.act`, đúng tham số đang dùng

- **"Duyệt":** `ids[]` = dòng đó, `action=approve`, `week`.
- **"Từ chối":** nằm trong `<details>`. Gồm ô `reason` (required, tối đa 500 ký tự) và nút xác nhận; gửi `ids[]` = dòng
  đó, `action=reject`, `week`.
- **"Duyệt cả N dòng của <tên>":** gửi `ids[]` của mọi dòng của người đó trong danh sách, `action=approve`, `week`.

### Kiểu dáng

- Dùng các lớp sẵn có của GAP-118: `operator-card-list`, `operator-item-card`, `operator-button-touch` (cao 44px).
- Thêm vài lớp nhỏ trong `operator.css` cho đầu nhóm và thanh chọn tuần.

### Không đổi

- Controller, service, route.
- Thông báo thành công / lỗi.
- Link từ nhắc việc cuối tháng (`?week=`).

### Test

Test mới `tests/Feature/Mobile/TimeReviewMobileLayoutTest.php`:
- trang có thẻ cho mỗi dòng chờ duyệt, gom theo người, có tổng giờ;
- mỗi thẻ có form duyệt / từ chối với đúng `ids[]`;
- nút nhóm có đủ `ids[]` của người đó;
- bảng máy tính vẫn còn;
- gửi form của thẻ thì duyệt đúng **một** dòng; từ chối thiếu lý do thì bị chặn như cũ.

### Kiểm bằng mắt (Gate 3)

Playwright đo trên iPhone 13 và máy tính 1440px:
- điện thoại: không còn bảng bị cắt, nút cao ≥ 44px;
- máy tính: giống hệt trước (so ảnh).

## Files (allowlist)

- `resources/views/time/review.blade.php`
- `resources/views/time/_review-cards.blade.php` (mới)
- `resources/css/operator.css`
- `tests/Feature/Mobile/TimeReviewMobileLayoutTest.php` (mới)
- `docs/owner-decisions/GAP-122/*`, `docs/audits/2026-11-11-gap-122-mobile-time-review-evidence.md`,
  `docs/superpowers/plans/2026-11-11-gap-122-mobile-time-review.md`

File nào khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung (amendment) trong hồ sơ này.

## Ngoài phạm vi

- Quy tắc duyệt, phân quyền, khoá tháng.
- Bảng trên máy tính.
- Trang khác.
- Deploy.

## Verification required at Gate 3

- Test mới đỏ trước, xanh sau. `TimeReviewTest` vẫn xanh.
- Playwright: điện thoại không còn bảng tràn, nút ≥ 44px; máy tính không đổi so với trước.
- Toàn bộ Unit + Feature + Integration, PHPStan, php-cs-fixer, ssot:lint, governance lint.

## Rollback

Revert PR. Chỉ giao diện.

## Decision Needed

Owner chọn: `APPROVE GAP-122 Gate 2 L-1 A-1 N-1` (hoặc phương án khác cho từng điểm) / Request more information / Decline /
Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
