# Luồng Site Ops & Event/Webhook — Nhật ký công trường, EventRecord outbox

> Sơ đồ sống cho debug. Ranh giới lớp enforce bởi `composer deptrac`.

## Nhật ký công trường (Site Diary)

```mermaid
stateDiagram-v2
    [*] --> draft: store (site_diary.create)\nunique project_id + diary_date
    draft --> draft: update (chỉ draft sửa được)
    draft --> submitted: submit (site_diary.create)
    submitted --> approved: approve (site_diary.approve)\nghi approved_by + approved_at
    approved --> [*]
```

- Controller: `Api\SiteDiaryController`; web delegate: `Web\SiteDiaryPageController`.
- Race tạo trùng ngày: DB unique constraint là nguồn chân lý — `QueryException 23000` → 422.

## EventRecord outbox → Webhook delivery

```mermaid
flowchart LR
    SRC["Bất kỳ nơi nào gọi\nEventRecord::create()\n(vd: Api\\TaskController)"]
    OBS["EventRecordObserver::created\n(đăng ký ở AppServiceProvider)"]
    MATCH{"endpoint active +\nevent_key khớp prefix?"}
    JOB["DeliverWebhook (queued)\ntries=3, backoff 10/60/300s"]
    GUARD{"resolvesToBlockedAddress?\n(SSRF: loopback/private/link-local)"}
    POST["HTTP POST + HMAC SHA-256\nX-Zena-Signature: sha256=..."]
    OK["last_delivered_at = now\nfailure_count = 0"]
    FAIL["failure_count++\nrelease nếu attempts < tries"]

    SRC --> OBS --> MATCH
    MATCH -- có --> JOB --> GUARD
    MATCH -- không --> X1[bỏ qua]
    GUARD -- bị chặn --> FAIL2["log + failure_count++\nKHÔNG gửi"]
    GUARD -- hợp lệ --> POST
    POST -- 2xx --> OK
    POST -- lỗi/timeout --> FAIL
```

## Bất biến cần nhớ khi debug

- Observer chạy **trong request** tạo EventRecord; job giao webhook chạy qua **queue** — production bắt buộc `QUEUE_CONNECTION != sync`.
- SSRF chặn 2 lớp: lúc tạo endpoint (`WebhookPageController` validation) và lúc giao (chống DNS rebinding).
- Secret webhook chỉ hiển thị 1 lần qua flash key riêng `one_time_secret` — không đi qua flash `success` chung.
- Activity Feed (`/operator/activity-feed`) đọc trực tiếp bảng `event_records` — nếu feed trống nghĩa là nơi phát nghiệp vụ chưa gọi `EventRecord::create()`.
