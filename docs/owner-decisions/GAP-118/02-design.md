---
work_id: GAP-118
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-10-gap-118-mobile-my-work-timesheet-evidence.md
  plan: null
  branch: docs/GAP-118-mobile-my-work-timesheet
  pr: https://github.com/kha997/zenamanagephp/pull/408
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
  created_at: "2026-11-10T08:55:56+07:00"
  updated_at: "2026-11-10T08:55:56+07:00"
generated_by: agent
---

# GAP-118 — Phone layout for "Việc của tôi" and "Bảng giờ": Gate 2 design

## Owner Summary

Anh/chị cần chọn **6 điểm**:

1. **Menu trên điện thoại** — đề xuất **N-1**: menu thu vào nút **"☰ Menu"** ở thanh trên cùng, bấm thì mở ra. Nội dung
   hiện ngay ở màn hình đầu tiên.
2. **Thanh trên cùng** — đề xuất **H-1**: trên điện thoại chỉ một dòng: nút Menu, tên trang, chữ cái đầu tên người dùng. Ô
   tìm kiếm và nút "Đăng xuất" chuyển vào trong menu.
3. **Bảng** — đề xuất **C-1**: trên điện thoại, danh sách việc và các dòng giờ hiện dạng **thẻ** (mỗi việc / mỗi dòng giờ
   một thẻ, đủ thông tin, không bị cắt). Máy tính vẫn là bảng như cũ.
4. **Bấm giờ** — đề xuất **T-1**:
   - mỗi thẻ việc có nút **"▶ Bắt đầu"** to, dễ bấm;
   - khi đồng hồ đang chạy, thanh **"Đang ghi giờ … · Dừng"** luôn hiện ở đáy màn hình trên hai trang này.
5. **Trang "Duyệt giờ" của PM** — đề xuất **R-2**: để Work ID sau. Trang này cũng sẽ đỡ hơn nhờ menu và thanh trên cùng mới.
6. **Kiểm thử** — đề xuất **V-1**:
   - test tự động kiểm tra phần HTML;
   - chạy thử trên trình duyệt giả lập iPhone 13 (390 px), chụp ảnh và đo vị trí, ghi vào hồ sơ Gate 3.

**Nguyên tắc chung:**
- Chỉ đổi khi màn hình hẹp. Giao diện máy tính giữ nguyên.
- Không dùng Alpine trong khung giao diện (quy tắc đã chốt).
- Không đổi dữ liệu, không migration.

**Phát hành:** một PR, một Gate 3.

## Điểm 1 — Menu trên điện thoại

| Phương án | Nội dung |
|---|---|
| **N-1 (đề xuất)** | Màn hình ≤1024 px: ẩn khối menu bên trái; thanh trên cùng có nút **"☰ Menu"**. Bấm thì mở danh sách menu đầy đủ (giống máy tính) ngay dưới thanh trên cùng; bấm lại hoặc chọn một trang thì đóng. Dùng thẻ `<details>` của trình duyệt, chạy cả khi chưa tải JavaScript. Máy tính giữ menu bên trái như cũ. |
| N-2 | Như N-1, thêm **thanh tab ở đáy** màn hình: Hôm nay, Việc của tôi, Bảng giờ, Menu. Tiện hơn cho nhân viên công trường, nhưng chiếm chỗ đáy màn hình trên mọi trang và phải chọn tab cho từng vai trò. |

## Điểm 2 — Thanh trên cùng

| Phương án | Nội dung |
|---|---|
| **H-1 (đề xuất)** | **Điện thoại:** một dòng gồm nút Menu, tên trang, vòng tròn chữ cái đầu tên người dùng. Dòng "Z.E.N.A — Procurement operator surface" ẩn đi. Ô tìm kiếm, tên đầy đủ và "Đăng xuất" nằm ở đầu menu khi mở. **Máy tính:** giữ nguyên. |
| H-2 | Giữ nguyên thanh trên cùng, chỉ thêm nút Menu. Vẫn chật như hiện nay. |

## Điểm 3 — Bảng

| Phương án | Nội dung |
|---|---|
| **C-1 (đề xuất)** | **Màn hình <768 px:** "Việc của tôi" và "Các dòng giờ" hiện dạng **thẻ**. **Thẻ việc:** tên việc, dự án, loại, hạn, nhãn trạng thái (quá hạn / bị chặn), nút Bắt đầu. **Thẻ dòng giờ:** ngày, việc, công trình, giờ, thời lượng, trạng thái, ghi chú, "Sửa / xoá". **≥768 px:** giữ bảng như cũ. Bảng dùng chung của trang "Khối lượng" (`app/_workload-items-table`) không đổi; thẻ là một partial mới. |
| C-2 | Giữ bảng, thêm dòng gợi ý "← kéo ngang để xem thêm". Ít sửa, nhưng vẫn khó dùng. |

## Điểm 4 — Bấm giờ

| Phương án | Nội dung |
|---|---|
| **T-1 (đề xuất)** | **Điện thoại:** mỗi thẻ việc (loại "Công việc") có nút **"▶ Bắt đầu"** cao ít nhất 44 px. Thẻ "Ghi giờ" riêng ở cuối trang ẩn đi trên điện thoại, máy tính giữ nguyên. Khi đang có đồng hồ chạy, cả hai trang hiện một **thanh cố định ở đáy** màn hình: "Đang ghi giờ: <việc> từ HH:MM" và nút **Dừng**, cũng dùng route "Dừng" đang có. Không đổi cách tính giờ. |
| T-2 | Chỉ đưa nút "Bắt đầu" lên thẻ, không có thanh cố định. |

## Điểm 5 — Trang "Duyệt giờ" của PM

| Phương án | Nội dung |
|---|---|
| **R-2 (đề xuất)** | Để Work ID sau. Trang này vẫn được hưởng menu và thanh trên cùng mới (N-1, H-1). |
| R-1 | Làm luôn trong PR này: thẻ cho từng dòng giờ chờ duyệt, nút Duyệt / Từ chối to. |

## Điểm 6 — Kiểm thử

| Phương án | Nội dung |
|---|---|
| **V-1 (đề xuất)** | **Test tự động (PHPUnit, chạy trong CI)** kiểm tra HTML: có nút Menu, ô tìm kiếm và "Đăng xuất" trong menu điện thoại, thẻ việc có nút Bắt đầu, thẻ dòng giờ đủ thông tin, thanh đồng hồ khi đang chạy (và không có khi không chạy). Guard "không Alpine" vẫn xanh. **Trình duyệt:** chạy Playwright giả lập iPhone 13 (390 px) và một khổ máy tính (1440 px), đo các số dưới đây và chụp ảnh. Kết quả ghi vào hồ sơ Gate 3. |
| V-2 | Như V-1, thêm một test trình duyệt chạy trong CI. Bộ test trình duyệt trong CI hiện phức tạp (Dusk, phải dựng tài nguyên), nên tốn công hơn nhiều. |

**Các số đo (iPhone 13, 390 px):**
- nội dung bắt đầu trong 150 px đầu;
- không bảng nào bị tràn;
- không cuộn ngang;
- nút Bắt đầu / Dừng cao ít nhất 44 px.

## Thiết kế chi tiết (theo N-1, H-1, C-1, T-1, R-2, V-1)

### Khung giao diện (`layouts/operator.blade.php`, `resources/css/operator.css`)

**Thanh trên cùng**
- Giữ class `operator-topbar`, vì test trình duyệt sẵn có đang dùng.
- Thêm `<details class="operator-mobile-nav">` với nút "☰ Menu" (`<summary>`). Nội dung gồm ô tìm kiếm, tên người dùng,
  "Đăng xuất" và cùng danh sách menu (cùng `OperatorNavigationComposer`).
- Hiển thị theo khổ màn hình:
  - ≤1024 px: ẩn `operator-sidebar`, hiện nút Menu, ẩn dòng meta / ô tìm kiếm / khối người dùng ở thanh trên, hiện chữ
    cái đầu;
  - >1024 px: ẩn `operator-mobile-nav`, mọi thứ như cũ.
- Một đoạn JS nhỏ trong layout (vanilla): đóng menu khi chọn link. Không có JS thì menu vẫn chạy.

**CSS**
- `.operator-mobile-only` / `.operator-desktop-only` và các class cho thẻ, đặt trong `operator.css`.

### "Việc của tôi"
- Partial mới `app/_workload-items-cards.blade.php`: thẻ việc; nút Bắt đầu cho loại "Công việc" (cùng form
  `app.time.start`).
- `app/my-work.blade.php`:
  - bảng chỉ hiện ≥768 px, thẻ chỉ hiện <768 px;
  - thẻ "Ghi giờ" chỉ hiện ≥768 px;
  - thanh đồng hồ ở đáy khi có `$running`.

### "Bảng giờ" (`time/timesheet.blade.php`)
- Thẻ dòng giờ <768 px, cùng nội dung và cùng form "Sửa / xoá" như bảng.
- Nút tuần trước / sau gọn hơn.
- Thanh đồng hồ ở đáy khi có `$running`.

### Thanh đồng hồ
- Partial mới `time/_running-bar.blade.php`, dùng chung cho hai trang.

### Test (đỏ trước / xanh sau)
`tests/Feature/Mobile/OperatorMobileLayoutTest.php` (mới):
- **Khung giao diện:** nút Menu có trong `operator-mobile-nav`, danh sách menu, ô tìm kiếm, "Đăng xuất" và chữ cái đầu tên
  người dùng.
- **"Việc của tôi":** có thẻ việc, nút Bắt đầu trên thẻ loại "Công việc"; thanh đồng hồ chỉ có khi đang chạy.
- **"Bảng giờ":** thẻ dòng giờ đủ thông tin; "Sửa / xoá" chỉ với dòng được sửa; thanh đồng hồ.

Các test sẵn có của hai trang (`MyWorkPageTest`, `TimeTracking/TimeEntryTest`) và guard "không Alpine" phải vẫn xanh.

## Files (allowlist)

- `resources/views/layouts/operator.blade.php`
- `resources/css/operator.css`
- `resources/views/app/my-work.blade.php`
- `resources/views/app/_workload-items-cards.blade.php` (mới)
- `resources/views/time/timesheet.blade.php`
- `resources/views/time/_running-bar.blade.php` (mới)
- `tests/Feature/Mobile/OperatorMobileLayoutTest.php` (mới)
- `docs/owner-decisions/GAP-118/*`, `docs/audits/2026-11-10-gap-118-mobile-my-work-timesheet-evidence.md`,
  `docs/superpowers/plans/2026-11-10-gap-118-mobile-my-work-timesheet.md`

File nào khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung (amendment) trong hồ sơ này.

## Ngoài phạm vi

- Trang "Duyệt giờ" (R-2).
- Các trang khác, ngoài phần khung dùng chung.
- Thanh tab ở đáy (N-2).
- Ứng dụng riêng.
- Chạy không cần mạng.
- Thông báo đẩy.
- Đổi giao diện máy tính.
- Đổi cách tính giờ.
- Deploy.

## Verification required at Gate 3

- Test mới: đỏ trước khi làm, xanh sau.
- Toàn bộ Unit + Feature + Integration và Feature/Api.
- Kết quả đo Playwright và ảnh chụp ở 390 px và 1440 px. Máy tính phải giống trước.
- `npm run build`, PHPStan, php-cs-fixer, `ssot:lint`, governance lint.
- CI trên đúng head.

## Rollback

Revert PR. Không migration, không đổi dữ liệu.

## Decision Needed

Owner chọn: `APPROVE GAP-118 Gate 2 N-1 H-1 C-1 T-1 R-2 V-1` (hoặc phương án khác cho từng điểm) / Request more information
/ Decline / Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
