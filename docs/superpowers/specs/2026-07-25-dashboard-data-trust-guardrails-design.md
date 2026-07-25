# Dashboard Data Trust Guardrails — Design Spec

**Date:** 2026-07-25 (rev 4)
**Status:** `DESIGN APPROVED` (rev 3) — rev 4 đóng nốt 2 consumer inventory còn mở, không đổi quyết định thiết kế. Sẵn sàng chuyển `writing-plans`.
**Nguồn gốc:** Operational Integrity Triage v2 (P0-C + evidence closure A.1/A.2 + dashboard inventory mở rộng); rev 2 sửa theo phản hồi trên rev 1 (`3d6cc41b`); rev 3 sửa theo phản hồi trên rev 2 (`ba438cd9`); rev 4 đóng evidence theo phản hồi trên rev 3 (`9d15951f`)

## Context

Audit đã kiểm chứng bằng thực thi (route:list, tinker đếm row thật, đọc công thức code, PHPUnit test thật) xác nhận: một số widget dashboard hiển thị số liệu **không phân biệt được** giữa "giá trị thật bằng 0", "không có dữ liệu", "dữ liệu cũ/không còn được ghi", và "số liệu chỉ phản ánh một phần nghiệp vụ". Có ít nhất 4 công thức "tiến độ" khác nhau tồn tại song song trên các dashboard khác nhau — spec này **không tuyên bố Task là nguồn canonical toàn hệ thống**, chỉ xử lý 6 widget đã xác nhận đủ evidence (xem Metric Inventory).

## Thay đổi so với rev 2 (commit `ba438cd9`)

| # | Rev 2 | Rev 3 |
|---|---|---|
| 1 | Field cũ trả `null` khi `availability != AVAILABLE` — mô tả là "giữ nguyên kiểu" dù giá trị đổi | Field cũ **giữ nguyên 100% hành vi cũ** (kể cả fallback 0 khi không có dữ liệu). `*_meta.value` mới là nguồn đáng tin duy nhất, field cũ đánh dấu deprecated |
| 2 | Payment: chỉ có 1 metric `LIMITED`, không tách aging | Tách 2 metric riêng: "Giá trị theo lịch chưa ghi nhận thanh toán" (gồm tương lai) và "Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán" (chỉ khoản đã tới hạn) + bucket `not_due` |
| 3 | `as_of` định nghĩa 1 kiểu (mốc dữ liệu nguồn) cho mọi metric | Tách 2 loại `as_of`: mốc dữ liệu nguồn (progress/milestone/budget) vs mốc tính báo cáo (aging) |
| — | `timeline_progress` được trích dẫn là "null-safe, pattern đúng" | **Sửa sai**: đọc lại code gốc phát hiện `percentage_elapsed` trả `0` (không phải null) khi thiếu ngày — cùng lỗi ambiguity như các widget khác, không phải mẫu tốt. Đổi tên nhãn + áp `NOT_APPLICABLE` |
| — | `milestone_progress`/`budget_progress`/`timeline_progress` được coi như "field số" tương tự `overall_progress` | **Sửa sai**: 3 field này thực ra là **object lồng nhau** (`completion_rate`, `percentage_spent`, `percentage_elapsed` là sub-field), không phải scalar. Mục 7 cập nhật đúng mapping |
| — | Aging bucket được mô tả là "cần thêm" | **Sửa sai**: đọc lại `BusinessKpiService::outstandingDebt()` phát hiện bucket `not_due` + xác định overdue theo `due_date` (không theo `status`) **đã tồn tại sẵn trong code** — chỉ cần mô tả đúng, không cần logic mới |

## Thay đổi so với rev 3 (commit `9d15951f`)

| # | Rev 3 | Rev 4 |
|---|---|---|
| 1 | Route PM Dashboard ghi chung chung `api/zena/pm/*` | **Sửa chính xác**: 4 field mục tiêu nằm ở `GET api/zena/pm/progress` (method `getProjectProgress()`), KHÔNG phải `api/zena/pm/dashboard` (method `getOverview()`, chứa field khác hoàn toàn — `pm_widget.{projects,tasks,rfis}`, ngoài scope) |
| 2 mục 3.3 | "Kiểm kê consumer là task đầu tiên, CHƯA làm" | **Đã đóng** — 2 consumer inventory (PM Dashboard, Portal/CRM) chạy xong bằng grep + đọc code thật, kết quả ở mục 12 mới |
| 8 | ERROR semantics mô tả HTTP status ngầm định (không nói rõ status code response), không nói rõ correlation ID, không phân biệt lỗi metric vs lỗi request | Chốt tường minh: partial-success luôn HTTP 200 khi chỉ 1 metric lỗi; log bắt buộc có correlation/request ID; lỗi auth/tenant/hạ tầng KHÔNG được biến thành `availability=ERROR` — đó là lỗi request, trả HTTP status lỗi tương ứng (401/403/500) như bình thường, không đi qua `MetricResult` |
| 12 (mới) | — | Thêm section Consumer Inventory — Closed, tổng hợp bằng chứng 2 nhánh điều tra |

## Metric Inventory Phase 1

| Widget | File | Route/Namespace | Shape response | Sub-field số | Trong scope? |
|---|---|---|---|---|---|
| `overall_progress` | `PmDashboardController.php:109-122` | `Api\`, `GET api/zena/pm/progress` (method `getProjectProgress`, route name `api.zena.pm.progress`) | scalar `float` | (chính nó) | Có |
| `milestone_progress` | `PmDashboardController.php:145-172` | cùng trên | object | `completion_rate` | Có |
| `budget_progress` | `PmDashboardController.php:176-189` | cùng trên | object | `percentage_spent` | Có |
| `timeline_progress` | `PmDashboardController.php:191-218` | cùng trên | object | `percentage_elapsed` | Có |
| `outstandingBalance` | `PortalDashboardController.php:59-63` | `Web\Portal\`, `portal/{tenantSlug}/dashboard` | scalar `float` | (chính nó) | Có |
| `outstandingDebt` (`total` + `aging`) | `BusinessKpiService.php:60-104` | `Web\CrmReportController`, `operator/crm/reports` | object (`total`, `overdue_total`, `overdue_count`, `aging{}`) | `total`, `overdue_total`, `aging.*` | Có |

**Ngoài scope Phase 1** (không đổi từ rev 2): `Project.progress` (ProjectManagerController), `avgProgress` (AnalyticsController), cashflow (ReportPageController), `Api\ProjectAnalyticsController`.

## 1. Mô hình 3 chiều (không đổi từ rev 2)

```
enum Availability { AVAILABLE | NO_DATA | NOT_APPLICABLE | ERROR }
enum Reliability  { RELIABLE | LIMITED | LEGACY | UNKNOWN }
enum Freshness    { CURRENT | STALE | UNKNOWN }   // Phase 1 luôn UNKNOWN
```

## 2. `MetricResult` — view model Phase 1 (không đổi cấu trúc, không tạo registry/rule-engine)

```php
final class MetricResult
{
    public function __construct(
        public readonly mixed $value,
        public readonly Availability $availability,
        public readonly Reliability $reliability,
        public readonly Freshness $freshness,
        public readonly ?Carbon $asOf,
        public readonly string $label,
        public readonly ?string $explanation,
    ) {}
}
```

Không tạo bảng DB, không registry, không rule engine. Mỗi phương thức tính KPI trả `MetricResult` trực tiếp.

## 3. API Compatibility (sửa theo yêu cầu — additive thật, không đổi hành vi field cũ)

**Nguyên tắc Phase 1: field số legacy giữ nguyên 100% — cả kiểu VÀ giá trị VÀ hành vi fallback cũ.** Không có nhánh nào trong Phase 1 làm field legacy trả khác với những gì nó trả hôm nay, kể cả trường hợp trước đây trả `0` khi không có dữ liệu.

### 3.1 Mapping field legacy → sub-field được `*_meta.value` phản ánh

| Field legacy (giữ nguyên, deprecated) | Sub-field số bên trong | `*_meta.value` phản ánh sub-field nào |
|---|---|---|
| `overall_progress` (scalar) | (chính nó) | `overall_progress_meta.value` |
| `milestone_progress` (object) | `milestone_progress.completion_rate` | `milestone_progress_meta.value` |
| `budget_progress` (object) | `budget_progress.percentage_spent` | `budget_progress_meta.value` |
| `timeline_progress` (object) | `timeline_progress.percentage_elapsed` | `timeline_progress_meta.value` |
| `outstandingBalance` (scalar, Portal) | (chính nó) | `outstandingBalance_meta.value` |
| `outstandingDebt.total` (CRM) | `total` | `outstandingDebt_meta.value` |
| `outstandingDebt.overdue_total` (CRM, mới thêm khái niệm, xem mục 5) | `overdue_total` | `outstandingDebtOverdue_meta.value` (metric riêng, xem mục 5) |

### 3.2 Quy tắc bắt buộc

1. **4 field legacy của `Api\PmDashboardController` không đổi** — response object cũ (`overall_progress`, `milestone_progress`, `budget_progress`, `timeline_progress`) giữ nguyên giá trị/shape hôm nay trong MỌI trường hợp, kể cả khi dữ liệu nguồn rỗng (VD `overall_progress` vẫn trả `0.0` như code hiện tại, `timeline_progress.percentage_elapsed` vẫn trả `0` như code hiện tại).
2. Thêm 4 field sibling mới `overall_progress_meta`, `milestone_progress_meta`, `budget_progress_meta`, `timeline_progress_meta` — mỗi field chứa `{value, availability, reliability, freshness, as_of, label, explanation}` theo đúng `MetricResult`.
3. **UI chính thức (bản dashboard mới) phải đọc từ `*_meta.value` + các field trust, không đọc field legacy.** Field legacy chỉ tồn tại để không phá client cũ chưa migrate.
4. **Bất biến bắt buộc có test**: khi `availability=AVAILABLE`, `legacy_subfield_value === *_meta.value` (chênh lệch = 0, cùng kiểu số). Đây là quy tắc "khi có dữ liệu thật, 2 giá trị phải bằng nhau" — nếu công thức tính `*_meta.value` khác công thức tính field legacy (dù chỉ do làm tròn khác), test phải fail và chặn merge.
5. Field legacy được đánh dấu **deprecated** trong docblock/OpenAPI (nếu repo có) — không xoá, không đổi hành vi, chỉ đánh dấu để client biết cần migrate.
6. **Không đổi field legacy thành `null`, không tạo API v2 ở Phase 1.** Việc đó chỉ được thực hiện sau khi có: (a) kiểm kê đầy đủ client thật đang gọi `Api\PmDashboardController` (JS bundle, mobile app nếu có, integration khác), (b) ghi rõ trong PR đây là **breaking API change có kế hoạch migrate**, không phải thay đổi ngầm. Việc kiểm kê + quyết định đổi field cũ **không được để implementation tự quyết** — đây là điều kiện tiên quyết (precondition) cho 1 spec/ticket riêng ở Phase 2, ngoài phạm vi Phase 1.

### 3.3 Portal/CRM (`Web\` namespace, Blade) — kiểm kê consumer ĐÃ ĐÓNG (xem mục 12)

Kết luận: cả `PortalDashboardController` và `CrmReportController` là server-rendered Blade thuần (return type `View`, không có nhánh `wantsJson()`), không có AJAX/fetch, chart script, export/PDF, hay service nào khác đọc lại cùng field. **Được phép áp `MetricResult` trực tiếp trong 2 file Blade view** (`resources/views/portal/dashboard.blade.php:115`, `resources/views/crm/report.blade.php:34-43`) mà không cần pattern field-sibling `*_meta` — vì không có JSON contract nào để bảo toàn tương thích. Bằng chứng đầy đủ ở mục 12.2.

### 3.4 JSON mẫu — `Api\PmDashboardController`, field `overall_progress`

**Case AVAILABLE** (project có 5 Task, 2 completed):

```json
{
  "overall_progress": 40.0,
  "overall_progress_meta": {
    "value": 40.0,
    "availability": "AVAILABLE",
    "reliability": "RELIABLE",
    "freshness": "UNKNOWN",
    "as_of": "2026-07-25T09:14:00+07:00",
    "label": "Tiến độ công việc (Task)",
    "explanation": null
  }
}
```

**Case NO_DATA** (project chưa có Task nào — field legacy giữ nguyên fallback `0.0` hiện tại, KHÔNG đổi thành null):

```json
{
  "overall_progress": 0.0,
  "overall_progress_meta": {
    "value": null,
    "availability": "NO_DATA",
    "reliability": "RELIABLE",
    "freshness": "UNKNOWN",
    "as_of": null,
    "label": "Tiến độ công việc (Task)",
    "explanation": "Dự án chưa có công việc (Task) nào được tạo."
  }
}
```

**Case ERROR** (ngoại lệ khi query — lưu ý field legacy KHÔNG có hành vi cũ nào để giữ, vì hiện tại 1 exception ở đây làm sập toàn bộ endpoint (không có try/catch per-field). Phase 1 thêm try/catch quanh từng trong 4 method tính để field khác vẫn trả được — đây là thay đổi cục bộ nhỏ, không phải "kiến trúc isolated widget tổng quát" bị cấm ở mục 8; field legacy của riêng field lỗi trả `null` vì không có "giá trị cũ" nào tồn tại cho nhánh này để bảo toàn):

```json
{
  "overall_progress": null,
  "overall_progress_meta": {
    "value": null,
    "availability": "ERROR",
    "reliability": "UNKNOWN",
    "freshness": "UNKNOWN",
    "as_of": null,
    "label": "Tiến độ công việc (Task)",
    "explanation": "Không thể tính được tiến độ do lỗi truy vấn dữ liệu."
  }
}
```

## 4. Semantics — Progress (`overall_progress`, `milestone_progress.completion_rate`, `budget_progress.percentage_spent`)

```
IF mẫu số (Task/ProjectMilestone count theo project) === 0:
    availability = NO_DATA, value = null, reliability = RELIABLE

ELSE IF budget_total là null (chưa nhập ngân sách — riêng cho budget_progress):
    availability = NOT_APPLICABLE, value = null, reliability = RELIABLE

ELSE IF query thành công, có dữ liệu:
    value = <công thức gốc, có thể ra đúng 0 nếu tử số = 0 — GIÁ TRỊ THẬT>
    availability = AVAILABLE, reliability = RELIABLE (hoặc LEGACY cho milestone, xem mục 6)

CATCH Throwable:
    availability = ERROR, value = null, reliability = UNKNOWN   // xem mục 8, bắt buộc log
```

## 5. Semantics — `timeline_progress` (đổi nhãn, sửa semantics sai ở rev 1/2)

**Đổi nhãn**: "Tỷ lệ thời gian kế hoạch đã trôi qua" — KHÔNG gọi là "tiến độ" (tránh nhầm với tiến độ hoàn thành công việc thật, vì đây thuần là phép tính lịch: `elapsedDays / totalDays`, không liên quan gì đến việc thi công đã xong bao nhiêu).

**Sửa semantics** (code gốc `computeTimelineProgress()` hiện trả `percentage_elapsed = 0` khi thiếu `start_date`/`end_date` — cùng dạng ambiguity 0-vs-no-data như các widget khác, KHÔNG phải pattern tốt như rev 1/2 từng mô tả nhầm):

```
IF !project.start_date OR !project.end_date:
    availability = NOT_APPLICABLE   // dự án chưa nhập đủ ngày kế hoạch để tính tỉ lệ thời gian
    value = null
    reliability = RELIABLE

ELSE:
    value = round(min(elapsedDays/totalDays*100, 100), 2)   // có thể là 0 nếu hôm nay = start_date, GIÁ TRỊ THẬT
    availability = AVAILABLE
    reliability = RELIABLE
```

## 6. Semantics — Milestone (không đổi từ rev 2)

```
IF ProjectMilestone::where('project_id', $id)->doesntExist():
    availability = NO_DATA, reliability = LEGACY, value = null, freshness = UNKNOWN
ELSE:
    availability = AVAILABLE, reliability = LEGACY, value = completion_rate, freshness = UNKNOWN
```

`reliability = LEGACY` ở cả hai nhánh (thuộc tính của nguồn, không phải của từng project). Không hard-code theo ngày route bị xoá — điều kiện chạy runtime. Quyết định ẩn/hiện là quyết định UI, không phải quyết định dữ liệu. Không khôi phục Milestone API.

## 7. Semantics — Payment (tách 2 metric, sửa theo yêu cầu)

Xác nhận qua code (`app/Models/ContractPayment.php`, `app/Services/BusinessKpiService.php:60-104`):
- `status` chỉ có 3 giá trị: `planned | paid | overdue`.
- `due_date` là field riêng, độc lập với `status`.
- `BusinessKpiService::outstandingDebt()` **đã tính sẵn** cả tổng gộp lẫn phân loại theo `due_date` — không cần logic tính mới, chỉ cần bọc đúng `MetricResult`.

### 7.1 Metric A — "Giá trị theo lịch chưa ghi nhận thanh toán" (Portal + CRM `total`)

- Công thức: `SUM(amount) WHERE status != 'paid'` — cộng cả `planned` (kể cả chưa tới `due_date`) và `overdue`.
- **Widget áp dụng**: `outstandingBalance` (Portal, chỉ có metric này, không có aging) và `outstandingDebt.total` (CRM).
- `reliability`: luôn `LIMITED` (chưa hỗ trợ receipt/allocation từng phần).
- `as_of`: `MAX(ContractPayment.updated_at) WHERE tenant_id = X` — mốc dữ liệu nguồn (giống progress/milestone/budget).
- `explanation`: *"Số liệu này cộng tất cả các khoản thanh toán theo lịch hợp đồng chưa được đánh dấu 'đã thanh toán', kể cả các khoản chưa tới hạn. Hệ thống hiện chưa ghi nhận thanh toán từng phần, nên số liệu này không phải công nợ thực tế đã xác nhận."*

### 7.2 Metric B — "Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán" (chỉ CRM, mới tách riêng)

- Công thức: đã có sẵn trong `outstandingDebt()` — `overdue_total` = `SUM(amount)` của các payment có `due_date < report_date` VÀ `status != 'paid'`. **Xác định overdue dựa trên `due_date < report_date`, không dựa vào giá trị field `status='overdue'`** — đúng yêu cầu operator, và đây **đã là hành vi thật của code hiện tại** (dòng `$overdue = $payments->filter(fn ($p) => $p->due_date < now())`), không phải thay đổi logic mới.
- Bucket đi kèm (đã có sẵn, mô tả lại chính xác theo code):

| Bucket | Điều kiện (theo code `BusinessKpiService.php:81-94`) | Ý nghĩa |
|---|---|---|
| `not_due` | `due_date >= report_date` (chưa tới hạn) | Kỳ tương lai — KHÔNG tính vào Metric B, chỉ xuất hiện trong Metric A |
| `due_1_30` | quá hạn 1–30 ngày | Mới quá hạn |
| `due_31_60` | quá hạn 31–60 ngày | |
| `due_61_90` | quá hạn 61–90 ngày | |
| `due_over_90` | quá hạn > 90 ngày | Quá hạn lâu |

  Metric B (`overdue_total`) = `due_1_30 + due_31_60 + due_61_90 + due_over_90` (loại trừ `not_due`).
- `reliability`: luôn `LIMITED` — cùng lý do Metric A (một khoản "quá hạn" có thể đã được thu một phần ngoài hệ thống nhưng vẫn hiển thị đủ 100%, vì chưa có receipt/allocation).
- **`as_of` cho Metric B khác Metric A**: `as_of = report_date` (chính là thời điểm `now()` được dùng để tính `due_date < now()` trong lúc chạy — tức mốc tính báo cáo, KHÔNG phải mốc dữ liệu nguồn thay đổi lần cuối). Do bucket phụ thuộc vào so sánh với "hôm nay", 2 lần tính vào 2 ngày khác nhau trên cùng dữ liệu có thể cho kết quả khác nhau (1 khoản có thể chuyển từ `not_due` sang `due_1_30` chỉ vì thời gian trôi qua, không phải vì dữ liệu đổi) — `as_of` phải phản ánh đúng điều này.
- Nhãn: "Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán" — không dùng lại "công nợ"/"outstanding"/"debt"/"confirmed".
- `explanation`: *"Số liệu này chỉ tính các khoản đã tới hoặc quá hạn thanh toán theo lịch hợp đồng (dựa trên ngày đến hạn, không dựa vào nhãn trạng thái thủ công), chưa được đánh dấu 'đã thanh toán'. Chưa phản ánh các khoản đã thu một phần."*

### 7.3 KHÔNG dùng làm hoặc gọi "confirmed outstanding balance"/"debt aging" cho cả 2 metric.

## 8. Error handling & Observability (chốt đầy đủ theo yêu cầu rev 3→4)

### 8.1 Phạm vi áp dụng `ERROR` — chỉ lỗi TÍNH TOÁN của 1 metric

`availability=ERROR` **chỉ** áp dụng cho exception xảy ra trong lúc tính giá trị 1 metric cụ thể (VD: query timeout, lỗi SQL khi tính `overall_progress` cho riêng project đó). **KHÔNG được dùng `ERROR` cho:**

- Lỗi xác thực (chưa đăng nhập, token hết hạn) — vẫn trả **401** ở tầng middleware/response, không chạm tới `MetricResult`.
- Lỗi phân quyền (không có quyền xem dashboard/project đó) — vẫn trả **403**, không chạm tới `MetricResult`.
- Lỗi tenant isolation (project không thuộc tenant của user) — vẫn trả **403/404** như hành vi hiện tại, không chạm tới `MetricResult`.
- Lỗi hạ tầng làm sập toàn bộ request (DB mất kết nối hoàn toàn, request timeout ở tầng framework, out-of-memory) — đây là lỗi request-level, vẫn trả **500** cho toàn bộ response như hành vi hiện tại của framework, không "biến thành" 1 field `ERROR` bên trong response 200.

Nói cách khác: `ERROR` là trạng thái của **1 con số**, không phải trạng thái của **1 HTTP request**. Nếu bản thân request không hợp lệ hoặc hạ tầng chết hoàn toàn, response vẫn phải là mã lỗi HTTP tương ứng như trước Phase 1 — Phase 1 không thay đổi hành vi lỗi ở tầng request.

### 8.2 Khi 1 metric lỗi nhưng các metric khác trong cùng response vẫn tính được

**HTTP status vẫn là 200 (partial-success)** — response trả về đầy đủ 4 field (hoặc 6 field tuỳ endpoint), field nào tính lỗi thì `<field>_meta.availability = ERROR` và `<field>_meta.value = null`, các field khác tính bình thường. Đây là lý do bắt buộc phải có try/catch bọc RIÊNG từng phương thức tính (xem mục 8.4) — nếu không, 1 exception ở `computeMilestoneProgress()` sẽ làm sập cả response, kể cả khi `overall_progress`/`budget_progress` tính hoàn toàn bình thường.

```php
} catch (\Throwable $e) {
    Log::error('dashboard_metric_error', [
        'widget' => 'overall_progress',
        'project_id' => $projectId,
        'tenant_id' => $tenantId,
        'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(), // dùng request ID có sẵn nếu middleware repo đã gắn, tự sinh nếu chưa có — cần xác nhận middleware convention thật khi viết plan
        'exception' => $e->getMessage(),
        'exception_class' => $e::class,
    ]);
    return new MetricResult(
        value: null,
        availability: Availability::ERROR,
        reliability: Reliability::UNKNOWN,
        freshness: Freshness::UNKNOWN,
        asOf: null,
        label: '...',
        explanation: 'Không thể tính được [tên metric] do lỗi truy vấn dữ liệu.',
    );
}
```

Log bắt buộc có `request_id`/correlation ID để nối được với log request tổng (giúp vận hành tra cứu 1 exception cụ thể gắn với request nào). Nếu repo đã có middleware gắn request ID chuẩn (VD `ErrorEnvelopeMiddleware` theo route Site Diary từng thấy) thì dùng lại convention đó thay vì tự sinh mới — đây là điểm cần xác nhận chính xác tên field/middleware khi viết implementation plan, spec chỉ yêu cầu **phải có** correlation ID, không cố định tên field.

### 8.3 `*_meta.value` không bao giờ là `0` khi `ERROR`

`*_meta.value` luôn `null` khi `availability=ERROR` — không bao giờ trả `0` để che giấu lỗi (phân biệt rõ với case `NO_DATA`/`NOT_APPLICABLE` cũng trả `value=null` nhưng khác `availability`, UI có thể hiển thị thông điệp khác nhau cho từng trường hợp dù cùng `value=null`).

### 8.4 Không refactor kiến trúc tổng quát

Việc thêm try/catch quanh từng phương thức tính (4 method trong `PmDashboardController::getProjectProgress()`, tương tự cho Portal/CRM nếu áp dụng) là thay đổi cục bộ, nhỏ, cần thiết để hiện thực hoá partial-success theo đúng mục 8.2 — **không phải** kiến trúc "isolated widget" tổng quát, không tạo interface/trait dùng chung cho mọi widget tương lai, không đụng widget ngoài 6 widget scope Phase 1, không đổi cách endpoint xử lý lỗi request-level (401/403/404/500 giữ nguyên như hiện tại).

## 9. Test Matrix (bổ sung case cho aging + bất biến legacy==meta)

| # | Case | Widget mẫu | Input | Kỳ vọng |
|---|---|---|---|---|
| 1 | Real zero | `overall_progress` | 5 Task, completed=0/5 | `AVAILABLE+RELIABLE`, `*_meta.value=0`, legacy field vẫn `0.0` |
| 2 | Zero denominator | `overall_progress` | 0 Task | `NO_DATA`, `*_meta.value=null`, legacy field vẫn `0.0` (không đổi) |
| 3 | No data | `milestone_progress` | 0 ProjectMilestone | `NO_DATA+LEGACY`, `*_meta.value=null` |
| 4 | Not applicable | `timeline_progress` | thiếu `start_date` | `NOT_APPLICABLE`, `*_meta.value=null`, legacy `percentage_elapsed` vẫn `0` (không đổi) |
| 5 | Available + Limited | `outstandingBalance` | có ContractPayment != paid | `AVAILABLE+LIMITED`, explanation không rỗng |
| 6 | Available + Legacy | `milestone_progress` | ≥1 record | `AVAILABLE+LEGACY` |
| 7 | No-data + Legacy | `milestone_progress` | 0 record | `NO_DATA+LEGACY` — cùng reliability với case 6 |
| 8 | Freshness unknown | mọi widget | mọi input | `freshness===UNKNOWN` luôn đúng |
| 9 | Query error có log | `overall_progress` | mock exception | `ERROR`, `*_meta.value=null`, legacy field=`null`, `Log::error` được gọi đúng 1 lần với đủ context |
| 10 | API compatibility — legacy field bất biến | `Api\PmDashboardController` | so sánh response trước/sau | field legacy giữ nguyên tên/kiểu/giá trị trong MỌI trường hợp (kể cả NO_DATA/NOT_APPLICABLE), `_meta` là field mới thêm |
| 11 | Legacy == meta khi AVAILABLE | tất cả 6 widget | có dữ liệu thật | `legacy_subfield_value === *_meta.value` (assert bằng nhau chính xác, không chỉ "gần bằng") |
| 12 | Mixed projects | `overall_progress` | 1 project có Task, 1 không | Mỗi project giữ `MetricResult` riêng, không gộp trung bình |
| 13 | Tenant isolation | `outstandingBalance`/`outstandingDebt` | user tenant A | Chỉ tính trên data tenant A |
| 14 | Aging — not_due tách khỏi overdue | `outstandingDebt` (Metric B) | 1 payment `due_date` tương lai, `status=planned` | Nằm trong `aging.not_due`, KHÔNG cộng vào `overdue_total` |
| 15 | Aging — due_date thắng status | `outstandingDebt` (Metric B) | 1 payment `status=planned` nhưng `due_date` đã qua | Vẫn được tính vào `overdue_total`/bucket tương ứng (không bị bỏ sót chỉ vì `status` chưa cập nhật thành `overdue`) |
| 16 | Accessibility không chỉ dựa màu | mọi badge trust | render UI | Có text/icon/aria-label phân biệt, không chỉ đổi màu |

## 10. Evidence Follow-up (không đổi từ rev 2)

Giữ nguyên nội dung rev 2: Site Diary duplicate-check hạ xuống defense-in-depth hardening (đã xác nhận `projects.id` là ULID primary key unique toàn cục); ticket Work Template dead-on-write route tách riêng, chưa xoá gì.

## 11. Rollout (cập nhật — bước kiểm kê consumer đã đóng, không còn là investigation mở)

1. ~~Kiểm kê consumer Portal + CRM Report~~ — **ĐÃ ĐÓNG** (mục 12.2), kết luận an toàn, không cần rào chắn thêm trước khi vào bước 2 trở đi.
2. `milestone_progress` (PM Dashboard) — rủi ro thấp nhất, chỉ thêm `_meta`, không đổi field cũ.
3. `overall_progress`/`budget_progress`/`timeline_progress` (PM Dashboard `getProjectProgress()`, cùng nhóm additive như #2).
4. `outstandingBalance` (Portal) — áp `MetricResult` trực tiếp trong view, đã xác nhận an toàn ở mục 12.2.
5. `outstandingDebt` Metric A + Metric B (CRM) — áp trực tiếp trong view, nhạy cảm nhất vì đổi nhãn tài chính + tách 2 metric, cần thông báo trước khi bật dù kỹ thuật đã an toàn.

Rollback theo feature flag riêng từng widget. Không đổi schema tài chính. Không giả lập partial payment. Không refactor ngoài 6 widget Phase 1. Không khôi phục Milestone API.

## 12. Consumer Inventory — Closed

### 12.1 `Api\PmDashboardController::getProjectProgress()` (route `GET api/zena/pm/progress`)

**Route thật đã xác nhận qua `route:list`**: route chứa 4 field mục tiêu là `api/zena/pm/progress` (`getProjectProgress()`) — KHÔNG phải `api/zena/pm/dashboard` (`getOverview()`, trả `pm_widget.{projects,tasks,rfis}`, hoàn toàn khác field, ngoài scope spec này).

**Consumer thật duy nhất tìm được**: `tests/Feature/Api/PmDashboardApiTest.php:238,248,255,258` — `assertJsonPath('data.overall_progress', 25)` và tương tự cho 3 field còn lại, so sánh giá trị số chính xác trên happy path (project có dữ liệu, không phải case NO_DATA/ERROR).

**Không tìm thấy** (grep toàn bộ `resources/views/`, `resources/js/`, không có `resources/ts/`): 0 Blade tham chiếu route/field này; 0 JS/TS gọi route hoặc đọc field `overall_progress`/`milestone_progress`/`budget_progress`/`timeline_progress`/`completion_rate`/`percentage_spent`/`percentage_elapsed`; 0 file `.d.ts` mô tả shape response; 0 entry trong Postman collection (`docs/ZENA_API.postman_collection.json`) hay OpenAPI annotation cho controller này; không mobile app/webhook/external integration nào trong repo.

**Kết luận compatibility**: 
- Nếu Phase 1 giữ nguyên 100% field legacy (đúng như mục 3.2 đã chốt) → **không consumer nào vỡ**, kể cả test hiện có (test chỉ assert field cũ, `assertJsonPath` không kiểm tra field lạ nên thêm `*_meta` là an toàn tuyệt đối).
- Rủi ro compatibility duy nhất còn lại là lý thuyết, không phải thực tế quan sát được: vì **0 UI thật tồn tại** để đọc field này, lo ngại ban đầu về `.toFixed()`/`Math.round()` crash trên `null` là **không áp dụng được trong repo hiện tại** — không có code nào làm việc đó. Ghi nhận để nếu tương lai có UI mới tiêu thụ route này, UI đó phải tự tuân theo mục 3 (đọc `*_meta.value`, không đọc field legacy).

### 12.2 `PortalDashboardController` + `CrmReportController` (`Web\` namespace)

| Consumer | Loại | JSON contract riêng? | Rủi ro |
|---|---|---|---|
| `PortalDashboardController::index()` (`routes/web.php:1045`, route `portal/{tenantSlug}/dashboard`) | Blade (`View`, không `wantsJson()`) | Không | Thấp |
| `resources/views/portal/dashboard.blade.php:115` | Render trực tiếp `number_format($outstandingBalance,...)` | — | An toàn để đổi nhãn/wrap `MetricResult` tại chỗ |
| `CrmReportController` (`routes/web.php:1035`, route `operator/crm/reports`) | Blade (`View`) | Không | Thấp |
| `resources/views/crm/report.blade.php:34-43` | Render trực tiếp `$outstandingDebt['total'\|'overdue_total'\|'overdue_count'\|'aging'][...]` | — | An toàn để đổi nhãn/wrap tại chỗ |
| `app/Services/LaunchChecklistService.php:467` | Chỉ `file_exists()` kiểm tra file tồn tại | — | Không liên quan, không đọc data |

**Không tìm thấy**: nhánh JSON trong 2 controller; AJAX/fetch trong `resources/js/` tới 2 route này hoặc field `outstandingBalance`/`outstandingDebt`/`aging`; chart script (`Chart(`/`ApexCharts`/canvas) trong 2 view; class Export/Pdf/Excel/Csv nào dùng lại field này; service nào khác gọi `BusinessKpiService::outstandingDebt()` ngoài `CrmReportController`; route JSON/API song song.

**Kết luận compatibility**: cả hai là Blade thuần 100%, không có hidden JSON/AJAX/chart/export consumer. **Được áp `MetricResult` trực tiếp trong 2 file Blade view, không cần pattern field-sibling `*_meta`** — quyết định này khác PM Dashboard (nơi vẫn giữ `*_meta` sibling vì response là JSON contract, dù hiện chưa có consumer thật, để phòng ngừa tương lai) vì Portal/CRM không bao giờ là JSON contract cho bên ngoài (server-rendered Blade không có khái niệm "client cũ gọi lại API" theo cách JSON có).

## Self-review (rev 4)

- **Placeholder**: không còn placeholder nào.
- **Contradiction đã rà soát**: mục 3.2/3.4/8 (field legacy bất biến, ERROR là ngoại lệ duy nhất có giải thích) ↔ Test Matrix ↔ mục 12 (kết luận consumer) — nhất quán. Mục 11 Rollout bước 1 đã cập nhật khớp với mục 12 (đánh dấu đã đóng, không còn mô tả là "chưa làm" ở nơi khác trong spec.
- **Ambiguity đã xử lý**: route PM Dashboard đã chính xác hoá (`api/zena/pm/progress`/`getProjectProgress`, không còn ghi chung chung `api/zena/pm/*`) — sửa xuyên suốt Metric Inventory và mục 8.4.
- **Scope**: vẫn đúng 6 widget Phase 1, không mở rộng. Việc chốt ERROR semantics (mục 8) làm rõ ranh giới: `ERROR` chỉ áp dụng per-metric, không lấn sang lỗi request-level (401/403/404/500 giữ nguyên hành vi cũ) — đây là làm rõ ranh giới đã có, không phải mở rộng scope.
- **Compatibility — cả 2 precondition đã đóng**: 
  - `Api\PmDashboardController`: consumer thật duy nhất là 1 test file assert happy-path, 0 UI thật. Rủi ro breaking gần như bằng 0 cho field legacy (giữ nguyên 100%); rủi ro duy nhất còn lại (nhánh ERROR trả `null` cho field legacy, trước đây toàn endpoint 500) vẫn được giữ nguyên nhận định từ rev 3 — đây là cải thiện thực chất (partial-success thay vì mất trắng cả response), nhưng nêu rõ để operator biết đây là thay đổi hành vi có chủ đích, không phải "không đổi gì".
  - `PortalDashboardController`/`CrmReportController`: xác nhận Blade thuần, không JSON/AJAX/chart/export consumer nào — an toàn áp `MetricResult` trực tiếp trong view, không cần `*_meta` sibling.
- **Không còn precondition nào mở.** Cả 2 consumer inventory đã đóng bằng grep + đọc code thật (không suy đoán). Sẵn sàng chuyển `writing-plans`.

## Testing

Chưa chạy — spec ở trạng thái draft, chưa implementation. Test Matrix mục 9 là kế hoạch cho giai đoạn sau khi spec được duyệt.
