# Dashboard Data Trust Guardrails — Design Spec

**Date:** 2026-07-25 (rev 3)
**Status:** Draft — chờ operator duyệt trước khi lên implementation plan
**Nguồn gốc:** Operational Integrity Triage v2 (P0-C + evidence closure A.1/A.2 + dashboard inventory mở rộng); rev 2 sửa theo phản hồi trên rev 1 (`3d6cc41b`); rev 3 sửa theo phản hồi trên rev 2 (`ba438cd9`)

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

## Metric Inventory Phase 1

| Widget | File | Route/Namespace | Shape response | Sub-field số | Trong scope? |
|---|---|---|---|---|---|
| `overall_progress` | `PmDashboardController.php:109-122` | `Api\`, `api/zena/pm/*` | scalar `float` | (chính nó) | Có |
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

### 3.3 Portal/CRM (`Web\` namespace, Blade)

**Kiểm kê consumer (Portal + CRM Report) là task ĐẦU TIÊN, trước bất kỳ thay đổi view/response nào** — kể cả thay đổi thuần hiển thị (đổi nhãn). Vì đây là server-rendered Blade, "client" ở đây là: (a) bất kỳ AJAX/fetch nào trên trang gọi lại cùng data dưới dạng JSON riêng (grep `routes/*.php` các route `portal.*outstanding`, `crm.*outstanding` cùng JS gọi chúng), (b) bất kỳ trang/partial Blade nào khác `@include` lại cùng view/component. Chỉ sau khi kiểm kê xong và xác nhận không có consumer ẩn mới được áp `MetricResult` trực tiếp vào view. Nếu phát hiện consumer JSON, áp quy tắc field-sibling như mục 3.2.

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

## 8. Error handling & Observability (không đổi từ rev 2, làm rõ thêm)

`ERROR` bắt buộc log đầy đủ, cấm silent catch:

```php
} catch (\Throwable $e) {
    Log::error('dashboard_metric_error', [
        'widget' => 'overall_progress',
        'project_id' => $projectId,
        'tenant_id' => $tenantId,
        'exception' => $e->getMessage(),
    ]);
    return new MetricResult(value: null, availability: Availability::ERROR, reliability: Reliability::UNKNOWN, freshness: Freshness::UNKNOWN, asOf: null, label: ..., explanation: '...');
}
```

`*_meta.value` luôn `null` khi `ERROR` — không bao giờ trả `0` để che giấu lỗi. Field legacy khi lỗi cũng trả `null` (không có "giá trị cũ" tương đương để bảo toàn cho nhánh lỗi — xem giải thích ở mục 3.4 case ERROR).

Việc thêm try/catch quanh từng phương thức tính (4 method trong `PmDashboardController`, tương tự cho Portal/CRM) là thay đổi cục bộ, nhỏ, cần thiết để hiện thực hoá `ERROR` per-widget — **không phải** kiến trúc "isolated widget" tổng quát, không tạo interface/trait dùng chung cho mọi widget tương lai, không đụng widget ngoài scope Phase 1.

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

## 11. Rollout (cập nhật thứ tự — kiểm kê consumer Portal/CRM lên đầu)

1. **Kiểm kê consumer Portal + CRM Report** (mục 3.3) — investigation, không sửa code, làm TRƯỚC mọi thay đổi view/response Portal/CRM.
2. `milestone_progress` (PM Dashboard) — rủi ro thấp nhất, chỉ thêm `_meta`, không đổi field cũ.
3. `overall_progress`/`budget_progress`/`timeline_progress` (PM Dashboard, cùng nhóm additive như #2).
4. `outstandingBalance` (Portal) — sau khi #1 xác nhận an toàn.
5. `outstandingDebt` Metric A + Metric B (CRM) — sau khi #1 xác nhận an toàn, nhạy cảm nhất vì đổi nhãn tài chính + tách 2 metric.

Rollback theo feature flag riêng từng widget. Không đổi schema tài chính. Không giả lập partial payment. Không refactor ngoài 6 widget Phase 1. Không khôi phục Milestone API.

## Self-review (rev 3)

- **Placeholder**: không còn placeholder nào — `thresholdDays` đã bỏ từ rev 2; rev 3 không thêm placeholder mới (2 loại `as_of` đều có định nghĩa cụ thể, không để trống).
- **Contradiction đã rà soát**: mục 3.2 (field legacy bất biến) ↔ mục 3.4 JSON mẫu (field legacy giữ `0.0`/`0` ở case NO_DATA/NOT_APPLICABLE) ↔ Test Matrix case 2/4/10 — nhất quán. Case ERROR là ngoại lệ duy nhất được giải thích rõ lý do (không có "giá trị cũ" để bảo toàn vì trước đây exception làm sập cả endpoint) — đã nêu tường minh, không phải mâu thuẫn ẩn.
- **Ambiguity đã xử lý**: đã sửa 2 mô tả sai từ rev 1/2 (timeline_progress không phải "pattern tốt sẵn có"; milestone/budget/timeline không phải scalar field) sau khi đọc lại code gốc — ghi rõ trong bảng "Thay đổi so với rev 2" thay vì âm thầm sửa.
- **Scope**: vẫn đúng 6 widget Phase 1, không mở rộng. Việc tách Metric A/B cho payment KHÔNG phải thêm widget mới — cùng 1 nguồn `BusinessKpiService::outstandingDebt()` đã tính sẵn cả 2, chỉ bọc `MetricResult` đúng cách.
- **Compatibility**: đã đóng chặt hơn rev 2 — field legacy không còn bất kỳ thay đổi hành vi ẩn nào (trừ nhánh ERROR đã giải thích), nên rủi ro breaking change cho `Api\PmDashboardController` gần như bằng 0 ở Phase 1. Rủi ro còn lại duy nhất: nhánh ERROR trả `null` cho field legacy trong trường hợp trước đây toàn bộ endpoint 500 — về mặt kỹ thuật đây là cải thiện (client nhận được phản hồi 1 phần thay vì mất trắng), nhưng vẫn là thay đổi shape có thể ảnh hưởng client không xử lý `null`; nêu rõ để operator cân nhắc, không tự quyết là "an toàn tuyệt đối".
- **Điều kiện tiên quyết còn mở**: kiểm kê consumer Portal/CRM (mục 3.3, Rollout bước 1) và kiểm kê client JSON thật của `Api\PmDashboardController` (để đánh giá rủi ro nhánh ERROR ở trên) vẫn là investigation chưa chạy — implementation plan không nên bắt đầu bước 2+ trước khi bước 1 xong.

## Testing

Chưa chạy — spec ở trạng thái draft, chưa implementation. Test Matrix mục 9 là kế hoạch cho giai đoạn sau khi spec được duyệt.
