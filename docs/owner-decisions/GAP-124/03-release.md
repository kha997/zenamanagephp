---
work_id: GAP-124
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/audits/2026-11-11-gap-124-business-notifications-evidence.md
  plan: docs/superpowers/plans/2026-11-11-gap-124-business-notifications.md
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
  created_at: "2026-11-11T08:48:14+07:00"
  updated_at: "2026-11-11T13:36:52+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-124 at subject 92cbb9ff1e355115f920b9486f9614d0aa66ce24 (re-presented after the second Codex review, commit 92cbb9ff: opening a notification also follows absolute URLs on the app's own scheme and host, reduced to path and query, because the month-end reminders store route() output; foreign and lookalike hosts still go to the inbox; test red before, green after. Earlier: Owner amendment 3, commit 2af13254: submittal submitted / resubmitted / approved / rejected and payment certificate approved are posted from EventRecord by the new EventRecordSlackObserver, once per record; NotificationSlackObserver keeps change request implemented, NCR assigned and month-end, keyed by change request id, NCR id and month-end key; SendSlackMessage takes tenant, event identity and message; Vietnamese messages with number and link; tests drive the real submittal service, change-request apply API, NCR API and certificate approval page, the four new ones red on the previous code. Gate 2 B-1, S-1, E-1, O-1 plus Owner amendments 1, 2 and 3; one PR, implementation commits b58693ed, 2af13254 and 92cbb9ff; no migration). B-1: bell with unread count in layouts/operator (desktop and phone) and Web\\NotificationPageController (app.notifications.index / open / read-all; own notifications in own company only; open marks read and follows in-app paths only, '//' and absolute URLs go to the list). S-1: Web\\NotificationSettingsPageController at /app/settings/notifications (rbac notification.channel.manage, new canonical permission in ZenaPermissionsSeeder so admin roles get it); Notifications\\SlackChannelService keeps the incoming-webhook URL encrypted (Crypt) in tenants.settings, accepts only https://hooks.slack.com/services/…, shows the last 6 characters, keeps the URL when switched off, removes it on request, posts with a 10 s timeout and never throws; test send; audit notification.channel.slack.updated. E-1: NotificationSlackObserver (Observers → Jobs only, deptrac clean) dispatches the queued SendSlackMessage job after commit for change_request.implemented, submittal.submitted / resubmitted / approved / rejected, ncr.assigned, payment_certificate.approved and month_end.*; the job checks the company channel, de-duplicates (company + event + link + title for 10 minutes; month-end once per key), posts one message, retries twice on failure and never throws; CRM / design-item / other events stay in-app. O-1: Reminders\\OverdueTaskDigestService + reminders:overdue-tasks (--dry-run, --date), scheduled daily 08:00 Asia/Ho_Chi_Minh behind ENABLE_SCHEDULER: open tasks (pending / in progress / on hold) with end date before today, one in-app notification per project to its active PM per day (event_key tasks.overdue_digest:<project>:<date>), one Slack summary per company per day. Go-live runbook documents the Slack step, the queue requirement and the digest. Amendment 1: the page view is notifications/inbox.blade.php (an older orphan notifications/index.blade.php is untouched). Amendment 2: RouteOwnershipReconciliationTest now expects the RBAC redirect for /app/settings/notifications. Tests: BusinessNotificationsTest (11) red before, green after; ScheduleContractTest and RouteOwnershipReconciliationTest green. Demo app: bell 5 → 4 after opening one, notifications page, overdue digest wrote 1 notification (4 tasks), Slack URL saved and shown masked, a non-Slack URL refused, the test send to the fake URL reported a clear error. PHPStan (no errors, no baseline entries), php-cs-fixer, ssot:lint, deptrac (0 violations), route guard, docs-lint, governance lint pass. Local Unit + Feature + Integration 3056 tests: only the 2 known root-only failures; Feature/Api 825 green. Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "f3066c548211932b7dd29d65e93abbb0dd2dfddd"
  subject_sha: "92cbb9ff1e355115f920b9486f9614d0aa66ce24"
  implementation_tree_digest: "3e8ee08b4d86c5bfa2c5e8a474ab6f2e57c100a076abf755be1dc78dd41bcd9c"
  verified_pr_head_sha: "92cbb9ff1e355115f920b9486f9614d0aa66ce24"
  verified_at: "2026-11-11T13:36:52+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-124 — Gate 3 release decision (business notifications)

## Trình lại lần 2 sau góp ý của Codex

Lần duyệt trước (gắn với subject `2af13254`) không còn hiệu lực vì mã đã đổi.

Codex rà lại và chỉ ra thêm 1 lỗi thật: nhắc việc cuối tháng lưu link đầy đủ kèm tên miền của app. Bấm vào thì thông báo
được đánh dấu đã đọc, nhưng người dùng bị đưa về trang Thông báo thay vì tới trang Duyệt giờ / chốt lương / chi phí chung.

**Đã sửa** (commit `92cbb9ff`):
- Link cùng tên miền với app được mở đúng trang.
- Link ra ngoài, kể cả tên miền giả mạo kiểu `zena.vn.evil.example`, vẫn bị chặn.
- Test mới đỏ trên mã cũ, xanh sau khi sửa.
- Toàn bộ 3.056 test: chỉ còn 2 lỗi có sẵn do chạy bằng root.

## Trình lại sau góp ý của Codex (bổ sung 3)

Lần duyệt trước (gắn với subject `b58693ed`) không còn hiệu lực vì mã đã đổi. Codex chỉ ra 2 lỗi thật, đã sửa trong commit
`2af13254`:

1. **Hồ sơ trình duyệt và chứng chỉ thanh toán sẽ không bao giờ tới Slack.**
   - Hai việc này chỉ ghi vào nhật ký sự kiện, không tạo thông báo có mã sự kiện.
   - Nay có observer riêng (file mới, bổ sung 3) gửi Slack từ nhật ký sự kiện. Mỗi lần nộp / nộp lại / duyệt / từ chối, mỗi
     chứng chỉ được duyệt là một tin.
2. **Chống trùng không ổn định.**
   - Một yêu cầu thay đổi có thể gửi 2 tin; hai yêu cầu khác nhau có thể chặn nhầm nhau.
   - Nay chống trùng theo mã của chính sự kiện: mã yêu cầu thay đổi, mã NCR, mã nhắc việc, mã dòng nhật ký.

Tin Slack nay bằng tiếng Việt, có số hiệu và link tới đúng trang. Test mới chạy **đúng thao tác thật**:
- nộp, từ chối, nộp lại, duyệt hồ sơ;
- áp dụng 2 yêu cầu thay đổi;
- tạo NCR;
- duyệt chứng chỉ thanh toán trên trang hợp đồng.

4 test này đỏ trên mã cũ, xanh sau khi sửa.

## Gói quyết định phát hành

**1. Vấn đề là gì?**
- Thông báo chỉ xem được ở trang "Hôm nay".
- Không có gì gửi ra ngoài.
- Việc quá hạn không ai được báo.

**2. Sau thay đổi (Gate 2 B-1, S-1, E-1, O-1 + bổ sung 1, 2, 3)** — subject `92cbb9ff`.

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

**3. Đã kiểm thử** — base `f3066c54`; subject `92cbb9ff1e355115f920b9486f9614d0aa66ce24`; digest
`3e8ee08b4d86c5bfa2c5e8a474ab6f2e57c100a076abf755be1dc78dd41bcd9c`.

**Trên app demo**
- Chuông hiện 5; mở một thông báo thì vào đúng dự án và còn 4.
- Tóm tắt quá hạn: PM nhận "Dự án Nhà phố Quận 7 có 4 việc quá hạn", kèm danh sách việc và số ngày trễ.
- Lưu link Slack: trạng thái "Đang bật", link chỉ hiện phần cuối.
- Link không phải Slack: bị từ chối.
- Gửi thử với link giả: báo lỗi rõ ràng, không vỡ trang.

**Test tự động mới (11), đỏ trước, xanh sau**
- chuông và trang thông báo;
- chỉ thấy thông báo của mình;
- chặn link ra ngoài;
- quyền cài đặt Slack, kiểm tra link, mã hoá, gửi thử;
- sự kiện thật: hồ sơ (4 lần đổi trạng thái → 4 tin), 2 yêu cầu thay đổi (mỗi cái 2 người nhận → 2 tin), NCR (1 tin, có người được giao), chứng chỉ thanh toán (1 tin); CRM không gửi; Slack lỗi không ảnh hưởng; công ty khác không bị gửi;
- tóm tắt quá hạn: đúng việc, không trùng, `--dry-run` không ghi.

**Kiểm tra khác**
- Toàn bộ 3.056 test: chỉ còn 2 lỗi có sẵn do chạy bằng root. API 825 test xanh.
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
