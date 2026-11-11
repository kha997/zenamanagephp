---
work_id: GAP-124
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-11-gap-124-business-notifications-evidence.md
  plan: null
  branch: docs/GAP-124-business-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/415
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
  created_at: "2026-11-11T08:09:23+07:00"
  updated_at: "2026-11-11T08:09:23+07:00"
generated_by: agent
---

# GAP-124 — Business notifications (Slack and in-app): Gate 2 design

## Owner Summary

Anh/chị chọn **4 điểm**:

1. **Chuông báo trong app** — đề xuất **B-1**:
   - chuông trên thanh trên cùng, có số chưa đọc;
   - trang "Thông báo" xem tất cả, bấm vào là mở đúng việc, có nút "Đánh dấu đã đọc hết".
2. **Kênh Slack** — đề xuất **S-1**:
   - **mỗi công ty tự dán đường link Slack** của mình (incoming webhook) trong trang "Cài đặt thông báo";
   - có nút **"Gửi thử"**;
   - đường link được lưu mã hoá;
   - chỉ nhận link của Slack (`https://hooks.slack.com/...`).

   Chưa dán link thì không gửi gì.
3. **Sự kiện nào gửi Slack** — đề xuất **E-1**, gửi các việc quan trọng:
   - yêu cầu thay đổi đã áp dụng;
   - hồ sơ trình duyệt (nộp, có kết quả duyệt);
   - phiếu không đạt (NCR) mới;
   - chứng chỉ thanh toán đã duyệt;
   - nhắc việc cuối tháng;
   - tóm tắt việc quá hạn.

   Mỗi sự kiện chỉ gửi **một tin** vào kênh, dù trong app có nhiều người nhận. Thông báo CRM (khách hàng tiềm năng, cơ hội,
   lịch hẹn, báo giá) **không** gửi Slack để kênh khỏi bị nhiễu.
4. **Tóm tắt việc quá hạn** — đề xuất **O-1**, **8:00 sáng mỗi ngày** (cùng giờ với nhắc việc cuối tháng):
   - **PM của mỗi dự án** nhận một thông báo trong app: "Dự án X có N việc quá hạn", kèm link danh sách;
   - **kênh Slack** của công ty nhận một tin tóm tắt theo dự án.

   Chạy khi đã bật `ENABLE_SCHEDULER`.

**Không làm:**
- email (chưa có nhà cung cấp gửi mail);
- Zalo (việc 5);
- thông báo đẩy về điện thoại.

**Phát hành:** một PR, một Gate 3.

## Điểm 1 — Chuông báo

| Phương án | Nội dung |
|---|---|
| **B-1 (đề xuất)** | Chuông + số chưa đọc trên thanh trên cùng (cả máy tính và điện thoại). Trang `/app/notifications`: thông báo của tôi (mới nhất trước, 50 dòng một trang, lọc "chưa đọc"), bấm một dòng thì đánh dấu đã đọc rồi mở `link_url`, nút "Đánh dấu đã đọc hết". Mục "Cập nhật chưa đọc" ở "Hôm nay" giữ nguyên. |
| B-2 | Không làm chuông; chỉ Slack và tóm tắt quá hạn. |

## Điểm 2 — Kênh Slack

| Phương án | Nội dung |
|---|---|
| **S-1 (đề xuất)** | Trang "Cài đặt thông báo" (`/app/settings/notifications`, hiện chỉ chuyển hướng) cho người có quyền mới `notification.channel.manage` (Owner có sẵn qua seeder quyền admin). Dán URL, bật / tắt, "Gửi thử". URL lưu mã hoá trong `tenants.settings`, chỉ chấp nhận `https://hooks.slack.com/services/…`. Trang chỉ hiện 6 ký tự cuối của URL. |
| S-2 | Một URL chung cho cả server trong `.env`. Đơn giản hơn nhưng mọi công ty cùng một kênh, Owner không tự đổi được. |

## Điểm 3 — Sự kiện gửi Slack

| Phương án | Nội dung |
|---|---|
| **E-1 (đề xuất)** | Yêu cầu thay đổi đã áp dụng; hồ sơ trình duyệt nộp / nộp lại / có kết quả; NCR mới; chứng chỉ thanh toán đã duyệt; nhắc việc cuối tháng (một tin cho cả công ty mỗi ngày, không phải mỗi người); tóm tắt quá hạn. |
| E-2 | Tất cả sự kiện có thông báo trong app, kể cả CRM và hạng mục thiết kế. |

## Điểm 4 — Tóm tắt việc quá hạn

| Phương án | Nội dung |
|---|---|
| **O-1 (đề xuất)** | Lệnh `reminders:overdue-tasks`, chạy 8:00 Asia/Ho_Chi_Minh mỗi ngày. Việc quá hạn = chưa xong, chưa huỷ, ngày kết thúc trước hôm nay. Mỗi PM (`projects.pm_id`) nhận một thông báo trong app cho mỗi dự án có việc quá hạn (khoá chống trùng theo ngày, giống GAP-120). Kênh Slack nhận một tin: từng dự án, số việc quá hạn, 5 việc trễ lâu nhất. Có `--dry-run`. |
| O-2 | Như O-1 nhưng chỉ trong app, không gửi Slack. |
| O-3 | Gửi thêm cho từng người được giao việc. Nhiều thông báo hơn; "Việc của tôi" đã hiện việc quá hạn cho người làm. |

## Thiết kế chi tiết (theo B-1, S-1, E-1, O-1)

### Gửi Slack

- **Khi nào gửi:** `NotificationSlackObserver` theo dõi `Notification::created`. Thông báo có `event_key` thuộc danh sách
  E-1, và công ty đã bật Slack → đưa job `SendSlackMessage` vào hàng đợi.
- **Chống trùng:** nhiều người nhận cùng một sự kiện → cùng tenant + `event_key` + `link_url` + tiêu đề → chỉ một tin, trong
  10 phút (cache lock).
- **Job:** gửi `POST` JSON `{text}` tới URL đã giải mã, thử lại 3 lần.
- **Khi lỗi:** ghi log, **không** làm hỏng thao tác của người dùng. Tin nhắn chỉ có tiêu đề, nội dung ngắn và link về app,
  không có dữ liệu nhạy cảm như lương.
- **URL Slack:** đặt trong `SlackChannelService`:
  - kiểm tra đúng tên miền `hooks.slack.com` (chống gửi tới địa chỉ nội bộ);
  - mã hoá / giải mã bằng `Crypt`.

### Chuông và trang thông báo

- **Controller:** `Web\NotificationPageController`:
  - `index`: thông báo của người đang đăng nhập, trong công ty của họ;
  - `open`: đánh dấu đã đọc rồi chuyển tới `link_url` (chỉ link nội bộ bắt đầu bằng `/`, còn lại về trang thông báo);
  - `readAll`.
- **Số chưa đọc:** tính trong layout bằng một truy vấn đếm.

### Tóm tắt quá hạn

- `App\Services\Reminders\OverdueTaskDigestService`: `plan()` chỉ đọc, `send()` ghi.
- `OverdueTaskDigestCommand`.
- Lịch chạy trong `Console\Kernel`, sau `ENABLE_SCHEDULER` như GAP-120.

### Test

`tests/Feature/Notifications/BusinessNotificationsTest.php`:
- **Chuông:** số chưa đọc đúng; trang chỉ có thông báo của mình; mở thì đánh dấu đã đọc; link ngoài bị chặn.
- **Cài đặt Slack:**
  - chỉ người có quyền;
  - URL sai miền bị từ chối;
  - URL lưu mã hoá, không lộ trên trang;
  - "Gửi thử" gọi đúng URL (giả lập HTTP).
- **Sự kiện:**
  - sự kiện E-1 gửi đúng một tin dù 3 người nhận;
  - sự kiện CRM không gửi;
  - Slack lỗi thì thao tác vẫn thành công;
  - công ty khác không bị gửi.
- **Quá hạn:**
  - PM nhận đúng số;
  - việc xong / huỷ / chưa đến hạn không tính;
  - chạy 2 lần trong ngày không trùng;
  - Slack nhận một tin;
  - `--dry-run` không ghi.
- **`ScheduleContractTest`:** thêm `reminders:overdue-tasks`.

## Files (allowlist)

- `app/Services/Notifications/SlackChannelService.php` (mới), `app/Jobs/SendSlackMessage.php` (mới),
  `app/Observers/NotificationSlackObserver.php` (mới), `app/Providers/AppServiceProvider.php` (đăng ký observer)
- `app/Services/Reminders/OverdueTaskDigestService.php` (mới), `app/Console/Commands/OverdueTaskDigestCommand.php` (mới),
  `app/Console/Kernel.php`
- `app/Http/Controllers/Web/NotificationPageController.php` (mới),
  `app/Http/Controllers/Web/NotificationSettingsPageController.php` (mới), `routes/web.php`
- `resources/views/notifications/index.blade.php` (mới), `resources/views/settings/notifications.blade.php` (mới),
  `resources/views/layouts/operator.blade.php` (chuông), `resources/css/operator.css`
- `database/seeders/ZenaPermissionsSeeder.php` (quyền `notification.channel.manage`)
- `tests/Feature/Notifications/BusinessNotificationsTest.php` (mới), `tests/Feature/Console/ScheduleContractTest.php`
- `docs/runbooks/go-live.md` (dán URL Slack, lệnh tóm tắt quá hạn)
- `docs/owner-decisions/GAP-124/*`, `docs/audits/2026-11-11-gap-124-business-notifications-evidence.md`,
  `docs/superpowers/plans/2026-11-11-gap-124-business-notifications.md`

Các danh mục / test kiểm tra quyền và route hiện có (ví dụ danh sách quyền chuẩn, SSOT lint) có thể cần thêm dòng. File
khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung.

## Ngoài phạm vi

- Email.
- Zalo.
- Thông báo đẩy.
- Websocket.
- Đổi nội dung các thông báo trong app hiện có.
- Deploy.

## Verification required at Gate 3

- Test mới đỏ trước, xanh sau.
- Gửi thử thật tới một URL giả lập trên app demo; ảnh chuông và trang thông báo.
- Toàn bộ Unit + Feature + Integration, PHPStan, php-cs-fixer, ssot:lint, deptrac, governance lint.

## Rollback

Revert PR. Gỡ URL Slack trong cài đặt là ngừng gửi ngay.

## Decision Needed

Owner chọn: `APPROVE GAP-124 Gate 2 B-1 S-1 E-1 O-1` (hoặc phương án khác cho từng điểm) / Request more information /
Decline / Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
