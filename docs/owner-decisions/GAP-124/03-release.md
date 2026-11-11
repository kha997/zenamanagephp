---
work_id: GAP-124
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-11-gap-124-business-notifications-evidence.md
  plan: docs/superpowers/plans/2026-11-11-gap-124-business-notifications.md
  branch: docs/GAP-124-business-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/415
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-11T09:44:58+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-11, verbatim: 'APPROVE GAP-124 Gate 3'. Given on the packet at PR head 5f4a9c065f53fa806c3044b67bda120d7ef38901; bound to implementation subject b58693edb5f5e1e5893b8f9cb3723ec04e7a2d15 and implementation-tree digest b8b3732c97dcb8f2b8c71cd179121c1d319bd2a9797c44757c082ae3b5ebf9a2 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28, after exact-head CI is green; no deployment, release, server command or Slack webhook creation authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-11T08:48:14+07:00"
  updated_at: "2026-11-11T09:44:58+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-124 at subject b58693edb5f5e1e5893b8f9cb3723ec04e7a2d15 (Gate 2 B-1, S-1, E-1, O-1 plus Owner amendments 1 and 2; one PR, implementation commit b58693ed; no migration). B-1: bell with unread count in layouts/operator (desktop and phone) and Web\\NotificationPageController (app.notifications.index / open / read-all; own notifications in own company only; open marks read and follows in-app paths only, '//' and absolute URLs go to the list). S-1: Web\\NotificationSettingsPageController at /app/settings/notifications (rbac notification.channel.manage, new canonical permission in ZenaPermissionsSeeder so admin roles get it); Notifications\\SlackChannelService keeps the incoming-webhook URL encrypted (Crypt) in tenants.settings, accepts only https://hooks.slack.com/services/…, shows the last 6 characters, keeps the URL when switched off, removes it on request, posts with a 10 s timeout and never throws; test send; audit notification.channel.slack.updated. E-1: NotificationSlackObserver (Observers → Jobs only, deptrac clean) dispatches the queued SendSlackMessage job after commit for change_request.implemented, submittal.submitted / resubmitted / approved / rejected, ncr.assigned, payment_certificate.approved and month_end.*; the job checks the company channel, de-duplicates (company + event + link + title for 10 minutes; month-end once per key), posts one message, retries twice on failure and never throws; CRM / design-item / other events stay in-app. O-1: Reminders\\OverdueTaskDigestService + reminders:overdue-tasks (--dry-run, --date), scheduled daily 08:00 Asia/Ho_Chi_Minh behind ENABLE_SCHEDULER: open tasks (pending / in progress / on hold) with end date before today, one in-app notification per project to its active PM per day (event_key tasks.overdue_digest:<project>:<date>), one Slack summary per company per day. Go-live runbook documents the Slack step, the queue requirement and the digest. Amendment 1: the page view is notifications/inbox.blade.php (an older orphan notifications/index.blade.php is untouched). Amendment 2: RouteOwnershipReconciliationTest now expects the RBAC redirect for /app/settings/notifications. Tests: BusinessNotificationsTest (7) red before, green after; ScheduleContractTest and RouteOwnershipReconciliationTest green. Demo app: bell 5 → 4 after opening one, notifications page, overdue digest wrote 1 notification (4 tasks), Slack URL saved and shown masked, a non-Slack URL refused, the test send to the fake URL reported a clear error. PHPStan (no errors, no baseline entries), php-cs-fixer, ssot:lint, deptrac (0 violations), route guard, docs-lint, governance lint pass. Local Unit + Feature + Integration 3052 tests: only the 2 known root-only failures; Feature/Api 825 green. Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "f3066c548211932b7dd29d65e93abbb0dd2dfddd"
  subject_sha: "b58693edb5f5e1e5893b8f9cb3723ec04e7a2d15"
  implementation_tree_digest: "b8b3732c97dcb8f2b8c71cd179121c1d319bd2a9797c44757c082ae3b5ebf9a2"
  verified_pr_head_sha: "b58693edb5f5e1e5893b8f9cb3723ec04e7a2d15"
  verified_at: "2026-11-11T08:48:14+07:00"
owner_decision_binding:
  implementation_tree_digest: "b8b3732c97dcb8f2b8c71cd179121c1d319bd2a9797c44757c082ae3b5ebf9a2"
  decision_recorded_at: "2026-11-11T09:44:58+07:00"
---

# GAP-124 — Gate 3 release decision (business notifications)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-11 ("APPROVE GAP-124 Gate 3"), bound to implementation subject
`b58693edb5f5e1e5893b8f9cb3723ec04e7a2d15` and implementation-tree digest `b8b3732c97dcb8f2b8c71cd179121c1d319bd2a9797c44757c082ae3b5ebf9a2`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?**
- Thông báo chỉ xem được ở trang "Hôm nay".
- Không có gì gửi ra ngoài.
- Việc quá hạn không ai được báo.

**2. Sau thay đổi (Gate 2 B-1, S-1, E-1, O-1 + bổ sung 1, 2)** — subject `b58693ed`.

**Chuông báo (B-1)**
- Chuông có số chưa đọc trên thanh trên cùng, cả máy tính và điện thoại.
- Bấm chuông mở trang **Thông báo**. Thông báo chưa đọc in đậm, có viền xanh. Có nút "Chỉ chưa đọc" và "Đánh dấu đã đọc
  hết".
- Bấm một thông báo thì đánh dấu đã đọc và mở đúng việc. Chỉ mở link trong app, không mở link ra ngoài.

**Kênh Slack (S-1)**
- Trang **Cài đặt thông báo** (`/app/settings/notifications`), dành cho Owner:
  - dán link Slack, bật / tắt, **Gửi thử**, gỡ kênh;
  - chỉ nhận link `https://hooks.slack.com/services/…`;
  - link được lưu mã hoá, trang chỉ hiện 6 ký tự cuối;
  - tắt thì vẫn giữ link, bật lại không cần dán lại.
- Owner có sẵn quyền mới `notification.channel.manage` sau khi chạy `production:rbac`.

**Sự kiện gửi Slack (E-1)**
- Các sự kiện được gửi:
  - yêu cầu thay đổi đã áp dụng;
  - hồ sơ trình duyệt (nộp, nộp lại, duyệt, từ chối);
  - NCR được giao;
  - chứng chỉ thanh toán đã duyệt;
  - nhắc việc cuối tháng.
- **Mỗi sự kiện một tin** dù nhiều người nhận trong app. Nhắc việc cuối tháng mỗi loại một tin mỗi ngày.
- Gửi qua hàng đợi nên không làm chậm người dùng. Slack lỗi thì thử lại 2 lần, thao tác của người dùng vẫn thành công.
- CRM và hạng mục thiết kế **không** gửi.

**Tóm tắt việc quá hạn (O-1)**
- Lệnh `reminders:overdue-tasks`, chạy **8:00 mỗi sáng** khi bật `ENABLE_SCHEDULER`.
- **PM** nhận "Dự án X có N việc quá hạn" kèm 5 việc trễ lâu nhất.
- **Kênh Slack** nhận một tin tóm tắt theo dự án.
- Chạy lại trong ngày không gửi trùng. Có `--dry-run` để xem trước.

**Bổ sung đã duyệt**
- **Bổ sung 1:** trang thông báo dùng file mới `inbox`, vì tên `index` đã có một trang cũ không dùng tới; trang cũ giữ
  nguyên.
- **Bổ sung 2:** sửa 1 dòng test cũ về chuyển hướng `/app/settings/notifications`.

**Không đổi**
- Nội dung các thông báo hiện có.
- Mục "Cập nhật chưa đọc" ở "Hôm nay".

**3. Đã kiểm thử** — base `f3066c54`; subject `b58693edb5f5e1e5893b8f9cb3723ec04e7a2d15`; digest
`b8b3732c97dcb8f2b8c71cd179121c1d319bd2a9797c44757c082ae3b5ebf9a2`.

**Trên app demo**
- Chuông hiện 5; mở một thông báo thì vào đúng dự án và còn 4.
- Tóm tắt quá hạn: PM nhận "Dự án Nhà phố Quận 7 có 4 việc quá hạn", kèm danh sách việc và số ngày trễ.
- Lưu link Slack: trạng thái "Đang bật", link chỉ hiện phần cuối.
- Link không phải Slack: bị từ chối.
- Gửi thử với link giả: báo lỗi rõ ràng, không vỡ trang.

**Test tự động mới (7), đỏ trước, xanh sau**
- chuông và trang thông báo;
- chỉ thấy thông báo của mình;
- chặn link ra ngoài;
- quyền cài đặt Slack, kiểm tra link, mã hoá, gửi thử;
- mỗi sự kiện đúng 1 tin dù 3 người nhận, CRM không gửi, Slack lỗi không ảnh hưởng, công ty khác không bị gửi;
- tóm tắt quá hạn: đúng việc, không trùng, `--dry-run` không ghi.

**Kiểm tra khác**
- Toàn bộ 3.052 test: chỉ còn 2 lỗi có sẵn do chạy bằng root. API 825 test xanh.
- PHPStan sạch, không thêm ngoại lệ. php-cs-fixer, SSOT lint, deptrac (0 vi phạm), kiểm tra route, lint đều sạch.

**4. Lưu ý vận hành** (đã ghi trong runbook go-live)
- Sau deploy, chạy `php artisan production:rbac` để Owner có quyền cài đặt Slack.
- Cần `QUEUE_CONNECTION=redis` và dịch vụ hàng đợi chạy, vì Slack gửi qua hàng đợi.
- Tóm tắt quá hạn chỉ chạy khi `ENABLE_SCHEDULER=true` và có cron.
- Owner tạo Incoming Webhook trong Slack rồi dán vào **Cài đặt thông báo**.

**5. Ngoài phạm vi**
- Email.
- Zalo (việc 5).
- Thông báo đẩy về điện thoại.
- Websocket.
- Deploy.

**6. Rủi ro còn lại** — Thấp.
- Slack chỉ gửi khi công ty tự dán link.
- Mọi lỗi Slack được nuốt và ghi log.

**7. Hoàn tác**
- Revert PR.
- Gỡ link trong cài đặt là ngừng gửi Slack ngay.

**8. Đề xuất** — Phát hành (merge).

**9. CI trên đúng head** — ghi nhận trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-124 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, chạy lệnh trên server, tạo Slack webhook.
