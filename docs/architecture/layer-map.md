# Bản đồ phân lớp (Layer Map) — enforce bởi Deptrac

> Đây không phải hình vẽ tham khảo: mọi mũi tên dưới đây là **rule thực thi được**.
> Vi phạm mới → `composer deptrac` fail. Cấu hình tại `deptrac.yaml`.

```mermaid
flowchart TD
    WEB["WebControllers\nApp\\Http\\Controllers\\Web\\*\n(delegate, không business logic)"]
    API["ApiControllers\nApp\\Http\\Controllers\\Api\\*\n(business logic + policy)"]
    SVC["Services\nApp\\Services\\*"]
    JOB["Jobs\nApp\\Jobs\\*"]
    OBS["Observers\nApp\\Observers\\*"]
    POL["Policies\nApp\\Policies\\*"]
    MDL["Models\nApp\\Models\\*\n(không phụ thuộc lớp trên)"]

    WEB --> API
    WEB --> SVC
    WEB --> JOB
    WEB --> MDL
    API --> SVC
    API --> JOB
    API --> MDL
    SVC --> JOB
    SVC --> MDL
    OBS --> JOB
    OBS --> MDL
    JOB --> SVC
    JOB --> MDL
    POL --> MDL
```

## Nợ kỹ thuật đã baseline (không thêm mới)

| Vi phạm | Ghi chú |
|---|---|
| `Models\Task` → `Services\TaskDependencyService` | Model gọi Service — cần tách logic dependency ra khỏi model khi refactor Task |
| `Models\ZenaTask` → `Services\TaskDependencyService` | Như trên |

## Sơ đồ luồng nghiệp vụ chi tiết

- [Procurement: Material Request → Receipt → Cost](flow-procurement.md)
- [Site Ops: Site Diary + EventRecord → Webhook](flow-site-ops-events.md)
- Route/module ownership: [module-ownership-ssot.md](module-ownership-ssot.md)
