# Dashboard Data Trust Guardrails — Design Spec

**Date:** 2026-07-25 (rev 2)
**Status:** Draft — chờ operator duyệt trước khi lên implementation plan
**Nguồn gốc:** Operational Integrity Triage v2 (P0-C + evidence closure A.1/A.2 + dashboard inventory mở rộng); rev 2 sửa theo phản hồi operator trên rev 1 (commit `3d6cc41b`)

## Context

Audit đã kiểm chứng bằng thực thi (route:list, tinker đếm row thật, đọc công thức code, PHPUnit test thật) xác nhận: một số widget dashboard hiển thị số liệu **không phân biệt được** giữa "giá trị thật bằng 0", "không có dữ liệu", "dữ liệu cũ/không còn được ghi", và "số liệu chỉ phản ánh một phần nghiệp vụ". Không có bất kỳ tín hiệu nào trên UI hiện tại cảnh báo người xem (PM, kế toán, Giám đốc) về mức độ tin cậy của con số họ đang thấy.

Audit inventory mở rộng phát hiện **có ít nhất 4 công thức "tiến độ" khác nhau** trên các dashboard khác nhau, không phải 1 nguồn canonical duy nhất:

1. `Api\PmDashboardController::computeOverallProgress()` — Task completed/total × 100.
2. `Api\ProjectManagerController::timeline` — đọc thẳng cột `Project.progress` (giá trị lưu sẵn, trigger cập nhật chưa xác nhận — xem Assumptions).
3. `Api\AnalyticsController` — `Task::avg('progress_percent')`.
4. `Api\DashboardController` / `Api\App\DashboardController` — đếm số lượng theo status, không phải %.

Spec này **không tuyên bố "Task là nguồn canonical toàn hệ thống"**. Mỗi widget xử lý độc lập theo formula thật của nó.

## Thay đổi so với rev 1 (commit `3d6cc41b`)

| # | Rev 1 | Rev 2 |
|---|---|---|
| 1 | 1 enum `DataTrustState` gộp 7 giá trị (`NO_DATA`, `STALE`, `LIMITED`, `LEGACY`, `ERROR`... cùng 1 chiều) | Tách 3 chiều độc lập: `availability`, `reliability`, `freshness` |
| 2 | Không có view model cụ thể | `MetricResult` value object cụ thể cho Phase 1 |
| 3 | `thresholdDays: ?int` để trống nhưng vẫn là field "chờ điền" trong spec đã duyệt | Phase 1 **không có** field threshold nào — `freshness` cố định trả `UNKNOWN` kèm `as_of`, không giả vờ có cơ chế threshold chưa dùng |
| 4 | Chưa tách rõ zero-denominator vs real-zero vs error trong đặc tả field-level | Chốt semantics tường minh theo 3 case, không suy ra từ ví dụ |
| 6 | Milestone: "không có data → ẩn" (quyết định UI cứng trong spec) | Milestone: định nghĩa đúng 2 tổ hợp (availability, reliability), KHÔNG quyết định "ẩn" hộ UI — đó là quyết định trình bày, không phải quyết định dữ liệu |
| 7 | Không có mục compatibility riêng | Thêm mục API Compatibility: giữ field cũ, thêm `*_meta`, hoặc chỉ áp dụng ở view Blade tuỳ namespace controller |
| 8 | `ERROR` không có yêu cầu logging tường minh | Thêm yêu cầu logging bắt buộc, cấm silent catch |
| 9 | Test matrix 10 case | Mở rộng 13 case theo yêu cầu, thêm accessibility |
| 10 | Chưa có evidence follow-up cho Site Diary duplicate-check + Work Template 500 | Đã đóng 2 việc này (xem mục Evidence Follow-up) |

## Metric Inventory Phase 1 (không đổi so với rev 1, đã xác nhận qua code)

| Widget | File | Route/Namespace | Công thức | Trong scope Phase 1? |
|---|---|---|---|---|
| `overall_progress` | `PmDashboardController.php:109-122` | `Api\`, `api/zena/pm/*` | Task completed/total × 100 | Có |
| `milestone_progress` | `PmDashboardController.php:145-172` | cùng trên | ProjectMilestone completed/total, không có write path sống | Có |
| `budget_progress` | `PmDashboardController.php:176-189` | cùng trên | spent/total × 100 | Có |
| `timeline_progress` | `PmDashboardController.php:191-218` | cùng trên | null-safe khi thiếu start/end date (mẫu đúng có sẵn) | Có |
| `outstandingBalance` | `PortalDashboardController.php:59-63` | `Web\`, `portal/{tenantSlug}/dashboard` | `SUM(amount) WHERE status != 'paid'` | Có |
| `outstandingDebt` + aging | `BusinessKpiService.php:60-104` | `Web\CrmReportController`, `operator/crm/reports` | Cùng công thức trên + bucket tuổi nợ | Có |

**Ngoài scope Phase 1** (giữ nguyên từ rev 1, chưa đủ evidence): `Project.progress` (ProjectManagerController), `avgProgress` (AnalyticsController), cashflow (ReportPageController), `Api\ProjectAnalyticsController`. Không đụng các widget này trong Phase 1.

## 1. Mô hình 3 chiều (thay cho enum gộp)

```
enum Availability {
    AVAILABLE        // có giá trị để hiển thị (kể cả khi giá trị đó là 0 thật)
    NO_DATA           // mẫu số/nguồn dữ liệu rỗng — không có gì để tính
    NOT_APPLICABLE    // widget không áp dụng cho ngữ cảnh này (VD: dự án không dùng budget tracking)
    ERROR             // query lỗi/exception — không có giá trị đáng tin để trả
}

enum Reliability {
    RELIABLE   // công thức phản ánh đúng và đầy đủ nghiệp vụ, nguồn có write path sống
    LIMITED    // công thức đúng về mặt tính toán nhưng chỉ phản ánh MỘT PHẦN nghiệp vụ
    LEGACY     // nguồn không còn write path chính thức đang hoạt động
    UNKNOWN    // chưa đủ evidence để xếp loại reliability (không dùng trong Phase 1 — mọi widget
               // Phase 1 đã được xếp loại rõ ràng; giữ giá trị này cho Phase 2/nguồn mới)
}

enum Freshness {
    CURRENT   // cập nhật trong ngưỡng được xác định là "còn mới" — CHƯA áp dụng ở Phase 1
    STALE     // vượt ngưỡng — CHƯA áp dụng ở Phase 1 (chưa có ngưỡng)
    UNKNOWN   // chưa có threshold được quyết định nghiệp vụ, hoặc chưa có bằng chứng đủ để đánh giá —
              // ĐÂY LÀ GIÁ TRỊ DUY NHẤT Freshness TRẢ VỀ Ở PHASE 1
}
```

3 chiều độc lập — 1 widget có thể là `AVAILABLE + LIMITED + UNKNOWN` (VD: outstandingBalance có giá trị, nhưng chỉ phản ánh 1 phần nghiệp vụ, và chưa biết độ mới) cùng lúc, không ép vào 1 nhãn duy nhất. UI được chọn 1 badge chính để hiển thị (ví dụ ưu tiên hiển thị `reliability` nếu khác `RELIABLE`, hoặc `availability` nếu khác `AVAILABLE`) nhưng **object trả về từ backend luôn giữ đủ 3 field**, không rút gọn ở tầng tính toán.

## 2. `MetricResult` — view model Phase 1

Không tạo bảng DB, không tạo registry, không tạo rule engine generic. Đây là 1 value object PHP đơn giản (`readonly class` hoặc DTO), được từng method tính KPI tự dựng trực tiếp — không có lớp trừu tượng "engine" đứng giữa.

```php
final class MetricResult
{
    public function __construct(
        public readonly mixed $value,            // number|null — null khi availability != AVAILABLE
        public readonly Availability $availability,
        public readonly Reliability $reliability,
        public readonly Freshness $freshness,     // Phase 1: luôn Freshness::UNKNOWN
        public readonly ?Carbon $asOf,            // mốc thời gian dữ liệu nguồn được tính tại đó (không phải threshold, chỉ là mốc quan sát)
        public readonly string $label,            // nhãn hiển thị, đã áp dụng nhãn mới cho payment
        public readonly ?string $explanation,     // text cố định giải thích khi reliability != RELIABLE, null khi RELIABLE
    ) {}
}
```

Mỗi phương thức tính KPI (VD `computeOverallProgress()`) trả về `MetricResult` trực tiếp — không đăng ký qua config/registry, không có bảng `metric_definitions`. Giữ đúng yêu cầu "không tạo generic rule engine".

## 3. Freshness Phase 1 — không có threshold, không placeholder

Phase 1 **không implement** cơ chế threshold dưới bất kỳ hình thức nào (không field `thresholdDays: ?int`, không interface `FreshnessRule` — rev 1 định nghĩa cơ chế này đã bị coi là placeholder che giấu quyết định chưa có, rev 2 bỏ hoàn toàn).

Quy tắc Phase 1, áp dụng đồng nhất cho mọi widget trong scope:

```
freshness = Freshness::UNKNOWN   // luôn luôn, không có ngoại lệ ở Phase 1
asOf = <mốc thời gian dữ liệu nguồn thực tế, VD MAX(Task.updated_at) WHERE project_id = X>
```

`asOf` vẫn được tính và hiển thị (VD: "Dữ liệu tính tại: 2026-07-25 10:32") — đây là **thông tin quan sát được**, không phải **đánh giá** (không kèm theo bất kỳ nhãn "cũ"/"mới" nào). Khi operator quyết định threshold cho từng nguồn ở Phase 2, `freshness` mới chuyển sang trả `CURRENT`/`STALE` thật — thay đổi đó nằm ngoài phạm vi implementation Phase 1.

`asOf` reference (per-source, đã đủ evidence để định nghĩa mốc — không phải `MAX(updated_at)` chung):

| Widget | `asOf` |
|---|---|
| `overall_progress` | `MAX(Task.updated_at) WHERE project_id = X` (nếu có Task; null nếu `NO_DATA`) |
| `milestone_progress` | `MAX(ProjectMilestone.updated_at) WHERE project_id = X` (nếu có record; null nếu `NO_DATA`) |
| `budget_progress` | `Project.updated_at` |
| `timeline_progress` | không cần `asOf` — widget này không có ambiguity thời gian, chỉ có null-safe cho ngày thiếu |
| `outstandingBalance`/`outstandingDebt` | `MAX(ContractPayment.updated_at) WHERE tenant_id = X` |

## 4. Semantics — Progress

Chốt tường minh (không suy ra từ ví dụ):

```
IF Task::where('project_id', $id)->count() === 0:
    availability = NO_DATA
    value = null
    reliability = RELIABLE      // công thức tự nó không sai, chỉ là không có gì để tính
    // KHÔNG trả value=0

ELSE IF query thành công, có Task:
    completed = Task::where('project_id', $id)->where('status','completed')->count()
    total = Task::where('project_id', $id)->count()
    value = round(completed / total * 100, 1)   // có thể ra đúng 0 nếu completed=0 — đây là GIÁ TRỊ THẬT
    availability = AVAILABLE
    reliability = RELIABLE

CATCH (QueryException | Throwable $e):
    availability = ERROR
    value = null
    reliability = UNKNOWN
    // xem mục 8 — bắt buộc log, không âm thầm nuốt exception
```

Không có nhánh nào trả `value = 0` khi mẫu số là 0. Không có nhánh nào trả `value` khác `null` khi `availability = ERROR`.

## 5. Semantics — Payment

**Công thức giữ nguyên** (không đổi schema, không đổi logic tính): `SUM(amount) WHERE status != 'paid'`.

**Status được cộng** (xác nhận qua `app/Models/ContractPayment.php`, enum thật chỉ có 3 giá trị): cộng cả `planned` và `overdue`. Chỉ loại trừ `paid`.

**Có bao gồm kỳ tương lai không — CÓ.** `ContractPayment` có field `due_date` riêng biệt với `status`. Trạng thái `planned` không phân biệt theo `due_date` đã tới hay chưa — một khoản thanh toán có `due_date` còn 6 tháng nữa nhưng đã tạo sẵn với `status=planned` **vẫn được cộng vào** giá trị hiển thị. Đây là điểm phải nêu rõ trong `explanation`, vì đây là lý do chính khiến giá trị này không thể gọi là "công nợ" (công nợ theo nghĩa thông thường chỉ tính khoản đã đến/qua hạn).

**Nhãn UI mới**: "Giá trị theo lịch chưa ghi nhận thanh toán" (giữ nguyên từ rev 1, đã được operator xác nhận đúng hướng — không đổi thêm).

**`explanation` cố định** (thay bản rev 1, làm rõ thêm việc gồm kỳ tương lai):
> *"Số liệu này cộng tất cả các khoản thanh toán theo lịch hợp đồng chưa được đánh dấu 'đã thanh toán', kể cả các khoản chưa tới hạn. Hệ thống hiện chưa ghi nhận thanh toán từng phần (một khoản trả 60% vẫn hiển thị đầy đủ 100% cho tới khi được đánh dấu hoàn tất), nên số liệu này không phải công nợ thực tế đã xác nhận."*

**`reliability` luôn `LIMITED`** — không có nhánh nào trong Phase 1 trả `RELIABLE` cho widget payment, kể cả khi tất cả `ContractPayment` liên quan đều đã cập nhật gần đây (freshness không nâng reliability). Điều kiện duy nhất để chuyển `LIMITED` → `RELIABLE`: có mô hình Payment Receipt/Allocation (P1-B trong triage, ngoài scope spec này).

**KHÔNG** dùng làm hoặc gọi là "confirmed outstanding balance" hay "debt aging" ở bất kỳ đâu trong code/label/comment mới thêm.

## 6. Semantics — Milestone

Xác nhận qua code: không có write path chính thức (route ghi đã xoá 2026-07-22, static grep xác nhận không seeder/observer/job khác ghi `project_milestones`).

```
IF ProjectMilestone::where('project_id', $id)->doesntExist():
    availability = NO_DATA
    reliability = LEGACY     // widget này LUÔN legacy — vì bản thân nguồn không có write path,
                              // bất kể project cụ thể có record hay không
    value = null
    freshness = UNKNOWN

ELSE:
    availability = AVAILABLE
    reliability = LEGACY
    value = <completed/total của project đó>
    freshness = UNKNOWN
```

`reliability = LEGACY` xuất hiện ở CẢ HAI nhánh — vì đây là thuộc tính của nguồn dữ liệu (không còn write path), không phải thuộc tính của từng project riêng lẻ. Điểm khác nhau giữa 2 nhánh chỉ là `availability` (có/không có record để hiển thị).

**Không hard-code theo ngày 2026-07-22** — điều kiện là `doesntExist()` chạy runtime, không phải so sánh ngày.

**Quyết định trình bày (ẩn widget hay hiển thị kèm nhãn legacy) là quyết định UI, không phải quyết định dữ liệu** — spec này chỉ định nghĩa đúng `MetricResult`, không ép buộc 1 cách trình bày duy nhất trong tầng dữ liệu. Đề xuất (không bắt buộc, do UI/design quyết định khi implement): `NO_DATA + LEGACY` → ẩn card; `AVAILABLE + LEGACY` → hiện kèm nhãn "dữ liệu lịch sử, không còn kênh cập nhật chính thức".

Không khôi phục Milestone API. Điều kiện chuyển sang `RELIABLE`: quyết định nghiệp vụ về vai trò Milestone + write path mới — ngoài scope.

## 7. API Compatibility

Đã kiểm kê namespace controller cho 6 widget Phase 1:

| Widget | Controller namespace | Loại response | Rủi ro breaking change |
|---|---|---|---|
| `overall_progress`, `milestone_progress`, `budget_progress`, `timeline_progress` | `Api\PmDashboardController` | JSON API (`Api\` namespace — có khả năng có JS/SPA client tiêu thụ, chưa kiểm kê toàn bộ client thật) | Có — không được đổi kiểu field cũ |
| `outstandingBalance` | `Web\Portal\PortalDashboardController` | Server-rendered Blade (`Web\` namespace, route không có tiền tố `api/`) | Thấp — nếu xác nhận không có endpoint AJAX riêng gọi cùng data, có thể áp `MetricResult` trực tiếp ở tầng view |
| `outstandingDebt`+aging | `Web\CrmReportController` | Server-rendered Blade | Thấp — tương tự, cần xác nhận không có AJAX consumer trước khi áp trực tiếp ở view |

**Quy tắc bắt buộc cho Phase 1:**

1. **`Api\PmDashboardController`**: KHÔNG đổi kiểu field số hiện có (`overall_progress`, `milestone_progress`, `budget_progress`, `timeline_progress` giữ nguyên là number|null như cũ). Thêm field mới dạng sibling: `overall_progress_meta`, `milestone_progress_meta`, `budget_progress_meta`, `timeline_progress_meta`, mỗi field chứa object `{availability, reliability, freshness, as_of, label, explanation}`. Field cũ tiếp tục được set bằng `MetricResult->value` (giữ hành vi cũ khi `availability=AVAILABLE`; khi `availability` khác `AVAILABLE`, field cũ trả `null` — **đây là thay đổi hành vi so với hiện tại** vì hiện tại field cũ có thể trả `0` giả khi không có dữ liệu; cần ghi rõ trong changelog/PR khi implement, không phải "không đổi gì").
2. **`Web\Portal\PortalDashboardController`, `Web\CrmReportController`**: được áp `MetricResult` trực tiếp trong Blade view (không cần field `_meta` vì không phải JSON API), NHƯNG bắt buộc xác nhận trước khi implement: không có route AJAX/JSON riêng nào (`routes/*.php` grep `portal.*outstanding`, `crm.*outstanding`) đang phục vụ cùng data cho JS trên trang — nếu có, áp dụng quy tắc field-sibling tương tự mục 1.
3. **Regression test bắt buộc** (xem Test Matrix mục "API compatibility"): assert field số cũ giữ nguyên tên, kiểu dữ liệu, và giá trị khi `availability=AVAILABLE` — chạy trên response thật của `Api\PmDashboardController` trước và sau khi thêm `MetricResult`.

## 8. Error handling & Observability

`ERROR` (availability) **bắt buộc đi kèm log**, không được catch exception rồi lặng lẽ đổi thành badge:

```php
} catch (\Throwable $e) {
    Log::error('dashboard_metric_error', [
        'widget' => 'overall_progress',
        'project_id' => $projectId,
        'tenant_id' => $tenantId,
        'exception' => $e->getMessage(),
        'trace_id' => ..., // theo convention logging hiện có của repo, nếu có middleware request-id
    ]);
    return new MetricResult(value: null, availability: Availability::ERROR, ...);
}
```

Không dùng `@` suppress, không dùng `try { } catch (\Throwable) { return 0; }` kiểu rút gọn. Log level `error` (không phải `debug`/`info`) vì đây là tín hiệu cho vận hành theo dõi, không phải log thông thường.

**Không refactor toàn bộ dashboard thành kiến trúc "isolated widget" tổng quát ở Phase 1.** `MetricResult` chỉ áp dụng cho 6 widget trong Metric Inventory Phase 1 nêu trên — không đụng vào cấu trúc controller/response tổng thể của các dashboard khác, không tạo interface/trait chung cho "mọi widget trong tương lai".

## 9. Test Matrix (mở rộng)

| # | Case | Widget mẫu | Input | Kỳ vọng |
|---|---|---|---|---|
| 1 | Real zero | `overall_progress` | 5 Task, completed=0/5 | `AVAILABLE + RELIABLE`, value=0 |
| 2 | Zero denominator | `overall_progress` | 0 Task | `NO_DATA`, value=null (không phải 0) |
| 3 | No data | `milestone_progress` | 0 ProjectMilestone record | `NO_DATA + LEGACY`, value=null |
| 4 | Not applicable | `budget_progress` | Project chưa nhập `budget_total` (null) | `NOT_APPLICABLE`, value=null |
| 5 | Available + Limited | `outstandingBalance` | Có ContractPayment status khác paid | `AVAILABLE + LIMITED`, explanation không rỗng |
| 6 | Available + Legacy | `milestone_progress` | ≥1 ProjectMilestone record | `AVAILABLE + LEGACY` |
| 7 | No-data + Legacy | `milestone_progress` | 0 record (nhắc lại case 3, kiểm tra riêng để chắc reliability không đổi giữa 2 nhánh) | `NO_DATA + LEGACY` — cùng `reliability` với case 6, khác `availability` |
| 8 | Freshness unknown | bất kỳ widget Phase 1 | mọi input hợp lệ | `freshness === Freshness::UNKNOWN` luôn đúng ở Phase 1, không có input nào tạo ra `CURRENT`/`STALE` |
| 9 | Query error có log | `overall_progress` | Mock DB exception | `availability=ERROR`, value=null, VÀ assert `Log::shouldReceive('error')->once()->with('dashboard_metric_error', ...)` được gọi |
| 10 | API compatibility | `Api\PmDashboardController` response | So sánh response trước/sau khi thêm MetricResult | Field số cũ giữ nguyên tên+kiểu; field `_meta` mới là additive, không breaking |
| 11 | Mixed projects | `overall_progress` trên dashboard tổng hợp nhiều project | 1 project có Task, 1 project không | Mỗi project giữ `MetricResult` riêng, không gộp trung bình giữa `AVAILABLE` và `NO_DATA` |
| 12 | Tenant isolation | `outstandingBalance`, `outstandingDebt` | User tenant A | Chỉ tính trên `ContractPayment` thuộc tenant A (dùng lại pattern `TenantScope` đã xác nhận đúng ở Site Diary audit) |
| 13 | Accessibility không chỉ dựa màu | mọi widget có badge `reliability`/`availability` khác trạng thái mặc định | render UI | Badge phải có text/icon/aria-label phân biệt được, không chỉ đổi màu nền (kiểm tra bằng snapshot test hoặc axe-core nếu có sẵn trong CI) |

## 10. Evidence Follow-up (đóng trước khi duyệt spec)

**Site Diary duplicate-check — đã kiểm chứng lại:**

`projects.id` là `ulid('id')->primary()` (migration tạo bảng `projects`, xác nhận qua đọc trực tiếp) — **primary key của bảng `projects`, unique toàn bảng theo ràng buộc DB, không theo tenant**. Vì `SiteDiaryController::store()` validate `project_id` bằng `Rule::exists('projects','id')->where('tenant_id', $tenantId)`, và mỗi `project_id` chỉ có thể thuộc đúng 1 project (do primary key unique toàn cục), nên **qua luồng ứng dụng bình thường không thể xảy ra** trạng thái "2 tenant khác nhau cùng có SiteDiary trỏ tới cùng 1 `project_id` nhưng project đó thuộc tenant khác nhau" — vì `project_id` tự nó đã xác định duy nhất 1 tenant sở hữu.

Kết luận: đây **không phải correctness bug reachable trong thực tế** qua ứng dụng — bản test trước (INV A.1) tạo được collision chỉ vì thao tác trực tiếp qua factory/DB (bypass validation), không phải qua API thật. Hạ mức độ từ "bug thật" xuống **defense-in-depth hardening đề xuất** (vẫn giữ nguyên đề xuất kỹ thuật: thêm `->where('tenant_id', $tenantId)` vào exists-check tại `SiteDiaryController.php:169-172`, để phòng ngừa nếu có bug khác ở nơi khác từng phá vỡ tính toàn vẹn `project_id`↔tenant trong tương lai) — không phải fix khẩn cấp, không chặn spec Dashboard này.

**Work Template dead-on-write route — ticket riêng (không thuộc scope Dashboard spec):**

> **Ticket: Work Template — deprecate/disable route ghi luôn trả 500**
> - **Hiện trạng đã xác nhận**: `Src\CoreProject\Controllers\WorkTemplateController` (routes `api/work-templates`, `api/v1/work-templates`) crash 500 100% mọi lần gọi `store`/`update` (SQLSTATE lỗi cột `version` không tồn tại trên bảng `work_templates` thật). Writer sống thật duy nhất là `App\Models\WorkTemplate` qua `Api\WorkTemplateController` (route `api/zena/work-templates`).
> - **Việc cần làm trước khi đóng ticket** (không làm trong phạm vi Dashboard spec này): (1) xác nhận caller — có FE/JS/API consumer bên ngoài nào đang gọi `api/work-templates`/`api/v1/work-templates` và nhận 500 hay không (log production nếu có quyền truy cập); (2) nếu không có caller thật, deprecate route (trả 410 Gone hoặc disable) thay vì để 500 âm thầm; (3) nếu có caller, redirect/chuyển caller đó sang canonical writer (`api/zena/work-templates`) trước khi tắt route cũ; (4) **không xoá `Src\CoreProject\Models\WorkTemplate`/`WorkTemplateApplicationService` tuỳ tiện** — nhiều nơi vẫn `use` model này dù không ghi được, cần audit read-path riêng trước khi xoá bất cứ gì.
> - **Ưu tiên**: P2 hygiene (không chặn roadmap chính) — route hiện tại gây lỗi ồn ào (500) chứ không gây hỏng dữ liệu âm thầm, ít khẩn cấp hơn dữ liệu bẩn.

## 11. Rollout (không đổi so với rev 1, nhắc lại ranh giới)

- Theo widget, thứ tự: milestone_progress → overall/budget/timeline_progress → outstandingBalance/outstandingDebt (nhạy cảm nhất, cần thông báo đổi nhãn tài chính trước khi bật).
- Rollback theo feature flag riêng từng widget.
- Không đổi schema tài chính. Không giả lập partial payment dưới bất kỳ hình thức nào (kể cả field tạm/demo).
- Không refactor dashboard ngoài 6 widget Phase 1.
- Không khôi phục Milestone API.

## Self-review (rev 2)

- **Placeholder**: đã loại bỏ hoàn toàn `thresholdDays`/`FreshnessRule` (rev 1 để trống nhưng vẫn là "cơ chế chưa dùng" — operator đúng khi coi đó là placeholder). Phase 1 giờ chỉ có `UNKNOWN` cố định + `asOf` quan sát được, không có cơ chế threshold nửa vời.
- **Contradiction đã rà soát**: Milestone mục 6 và Test Matrix case 6/7 nhất quán (`reliability=LEGACY` ở cả 2 nhánh availability). Payment mục 5 và Rollout nhất quán (không bao giờ `RELIABLE`). API Compatibility mục 7 và Test Matrix case 10 nhất quán (field cũ giữ tên+kiểu, thêm `_meta`).
- **Scope**: 6 widget Phase 1 giữ nguyên từ rev 1, không mở rộng thêm. Ticket Work Template và note Site Diary hardening đặt ở mục 10 làm rõ **không thuộc scope implementation Dashboard** — tránh lẫn 2 việc khác nhau vào 1 plan.
- **Compatibility risk chưa đóng hoàn toàn**: mục 7 nêu rõ `Api\PmDashboardController` **có khả năng** có client JSON thật chưa kiểm kê đầy đủ — đây là điều kiện tiên quyết (precondition) trước khi viết implementation plan cho riêng nhóm 4 widget PM Dashboard, không phải điều đã đóng. Portal/CRM (Blade) rủi ro thấp hơn nhưng vẫn cần xác nhận không có AJAX consumer trước khi implement.
- **Hành vi thay đổi cần khai báo rõ khi implement** (không phải "tương thích ngược hoàn toàn"): field số cũ ở `Api\PmDashboardController` hiện có thể trả `0` giả khi không có dữ liệu; sau Phase 1 sẽ trả `null` khi `availability != AVAILABLE`. Đây LÀ một thay đổi hành vi API (dù field/tên/kiểu không đổi, giá trị trả về trong 1 số trường hợp thay đổi từ `0` sang `null`) — phải ghi rõ trong PR/changelog khi implement, không được mô tả là "không breaking".

## Testing

Chưa chạy — spec ở trạng thái draft, chưa implementation. Test Matrix mục 9 là kế hoạch cho giai đoạn sau khi spec được duyệt.
