# Dashboard Data Trust Guardrails — Design Spec

**Date:** 2026-07-25
**Status:** Draft — chờ operator duyệt trước khi lên implementation plan
**Nguồn gốc:** Operational Integrity Triage v2 (P0-C + evidence closure A.1/A.2 + dashboard inventory mở rộng)

## Context

Audit đã kiểm chứng bằng thực thi (route:list, tinker đếm row thật, đọc công thức code, PHPUnit test thật) xác nhận: một số widget dashboard hiển thị số liệu **không phân biệt được** giữa "giá trị thật bằng 0", "không có dữ liệu", "dữ liệu cũ/không còn được ghi", và "số liệu chỉ phản ánh một phần nghiệp vụ". Không có bất kỳ tín hiệu nào trên UI hiện tại cảnh báo người xem (PM, kế toán, Giám đốc) về mức độ tin cậy của con số họ đang thấy.

Quan trọng: audit inventory mở rộng phát hiện **có ít nhất 4 công thức "tiến độ" khác nhau** trên các dashboard khác nhau, không phải 1 nguồn canonical duy nhất như đánh giá vội ở vòng trước:

1. `Api\PmDashboardController::computeOverallProgress()` — Task completed/total × 100.
2. `Api\ProjectManagerController::timeline` — đọc thẳng cột `Project.progress` (giá trị lưu sẵn, **chưa xác nhận được trigger cập nhật** — xem mục Assumptions).
3. `Api\AnalyticsController` — `Task::avg('progress_percent')` (trung bình field phần trăm mỗi task, khác hẳn tỉ lệ completed/total).
4. `Api\DashboardController` / `Api\App\DashboardController` — đếm số lượng theo status (openTasks/overdueTasks/completedToday), không phải %.

Vì vậy spec này **không tuyên bố "Task là nguồn canonical toàn hệ thống"**. Task-completion-ratio chỉ được xác nhận là công thức của `PmDashboardController`. Mỗi widget được xử lý độc lập theo formula thật của nó.

## Metric Inventory (đã xác nhận qua code, không suy đoán)

| Widget | File | Route | Công thức | Loại | Đủ evidence để thiết kế guardrail? |
|---|---|---|---|---|---|
| `overall_progress` (PM Dashboard) | `PmDashboardController.php:109-122` | `api/zena/pm/*` | Task completed/total × 100, `0.0` nếu total=0 | Tiến độ | Có |
| `milestone_progress` (PM Dashboard) | `PmDashboardController.php:145-172` | cùng trên | ProjectMilestone completed/total, nguồn không có write path sống | Tiến độ (phụ) | Có |
| `budget_progress` (PM Dashboard) | `PmDashboardController.php:176-189` | cùng trên | spent/total × 100, `0` nếu total=0 | Tài chính | Có |
| `timeline_progress` (PM Dashboard) | `PmDashboardController.php:191-218` | cùng trên | null-safe khi thiếu start/end date (pattern đúng, dùng làm mẫu) | Tiến độ | Có |
| `Project.progress` (ProjectManagerController timeline) | `ProjectManagerController.php:259-270` | `api/project-manager/dashboard/timeline` | Đọc thẳng cột lưu sẵn, không tính lại | Tiến độ | **Chưa đủ** — chưa xác nhận trigger cập nhật cột này. Đưa vào Phase 2, không trong scope Phase 1 (xem Rollout + Assumptions) |
| `avgProgress` (AnalyticsController) | `AnalyticsController.php:32,76-77` | `api/analytics/dashboard`, `api/analytics/tasks` | `Task::avg('progress_percent')` | Tiến độ | Có công thức, nhưng chưa xác nhận UI nào thực sự consume — Phase 2 |
| Count-based task metrics | `Api\DashboardController`, `Api\App\DashboardController` | `api/dashboard/*`, `app/dashboard` | Đếm status, không phải %, không có ambiguity 0-vs-no-data theo nghĩa spec này (đếm 0 task = đúng nghĩa 0, không cần trust state) | Tiến độ (đếm) | **Ngoài scope** — count không có ambiguity dạng "0 vs N/A" cần guardrail |
| `outstandingBalance` (Portal) | `PortalDashboardController.php:59-63` | `portal/{tenantSlug}/dashboard` | `SUM(amount) WHERE status != 'paid'` | Tài chính | Có |
| `outstandingDebt` + aging (BusinessKpiService/CRM Report) | `BusinessKpiService.php:60-104` | `operator/crm/reports` | Cùng công thức với Portal, cộng thêm bucket tuổi nợ | Tài chính | Có |
| Cashflow (ReportPageController) | `Web\ReportPageController.php` | `operator/reports/cashflow` | **Chưa trace chi tiết công thức** | Tài chính | **Chưa đủ evidence** — cần điều tra riêng trước khi áp guardrail, out of scope Phase 1 |
| `Api\ProjectAnalyticsController` | route `api/analytics/projects/{project}` | reachable ở backend | Milestone/risk method | Tiến độ | **Mâu thuẫn với audit trước** (từng báo dead code) — route thực ra reachable, nhưng chưa xác nhận có UI/JS nào gọi. Cần re-verify riêng, out of scope Phase 1 |
| Operator root dashboard (counts) | `ProcurementDashboardController.php` | `GET /operator` | Đếm MaterialRequest/RFI/Submittal/QC/NCR theo status | Khác | Ngoài scope — đếm thô, không có ambiguity % |
| Admin/Security dashboards | `Api\Admin\DashboardController`, `SecurityDashboardController` | `api/admin/*`, `api/auth/security/*` | Không liên quan tiến độ/tài chính | Khác | Ngoài scope |

**Trong scope Phase 1 (đủ evidence, ưu tiên implement guardrail trước):** `overall_progress`, `milestone_progress`, `budget_progress`, `timeline_progress` (PM Dashboard); `outstandingBalance` (Portal); `outstandingDebt`+aging (CRM Report/BusinessKpiService).

**Ngoài scope Phase 1 (cần điều tra thêm trước khi thiết kế guardrail riêng):** `Project.progress` (ProjectManagerController), `avgProgress` (AnalyticsController), cashflow (ReportPageController), `Api\ProjectAnalyticsController`.

## Trust State Model

```
enum DataTrustState {
    RELIABLE        // công thức đúng, nguồn có write path sống, dữ liệu đủ để tính
    NO_DATA         // nguồn tồn tại, write path sống, nhưng chưa có bản ghi nào (mẫu số = 0 vì chưa ai nhập, không phải vì "0% thật")
    STALE           // nguồn có write path sống nhưng dữ liệu không được cập nhật trong ngưỡng freshness của chính nguồn đó
    LIMITED         // có số liệu thật, tính đúng theo công thức, nhưng công thức chỉ phản ánh MỘT PHẦN nghiệp vụ (ví dụ: không trừ partial payment)
    NOT_APPLICABLE  // widget không áp dụng cho ngữ cảnh hiện tại (ví dụ dự án chưa có ngân sách nhập, chưa có ngày bắt đầu/kết thúc)
    ERROR           // query lỗi, exception, hoặc timeout khi tính — không được hiển thị giá trị cũ/mặc định thay thế
    LEGACY          // nguồn có dữ liệu lịch sử nhưng KHÔNG còn write path chính thức đang hoạt động (ví dụ Milestone)
}
```

### Phân biệt bắt buộc (theo yêu cầu operator)

| Tình huống | Trust state | Ví dụ trong hệ thống |
|---|---|---|
| Giá trị thật bằng 0 (có dữ liệu, tính đúng, kết quả là 0) | `RELIABLE` với value=0 | Task completed=0/total=5 → 0% thật |
| Không có dữ liệu (mẫu số = 0 vì chưa ai nhập) | `NO_DATA` | Project chưa có Task nào → total=0 |
| Dữ liệu cũ (write path sống nhưng lâu không cập nhật) | `STALE` | (áp dụng khi nguồn có write path — không áp dụng cho Milestone, xem dưới) |
| Nguồn legacy không còn write path | `LEGACY` | `milestone_progress` — không route ghi nào hoạt động (đã xác nhận qua route:list) |
| Có số liệu nhưng mô hình chỉ phản ánh một phần nghiệp vụ | `LIMITED` | `outstandingBalance`/`outstandingDebt` — không trừ partial payment |

**Milestone không dùng `STALE`.** Lý do: `STALE` ngụ ý "có write path sống nhưng chưa được dùng gần đây" — sai với thực tế đã kiểm chứng (route ghi milestone không tồn tại, không phải "tồn tại nhưng ít dùng"). Milestone dùng `LEGACY` (nếu có dữ liệu lịch sử) hoặc `NO_DATA`/ẩn (nếu production không có dữ liệu) — xem quy tắc cụ thể bên dưới.

## Milestone — quy tắc cụ thể

Đã kiểm chứng: DB dev hiện có 0 record `ProjectMilestone` (không đại diện production), và static grep xác nhận **không có write path nào khác** (seeder/observer/job) ngoài API đã xoá 2026-07-22.

Quy tắc runtime (không hard-code theo ngày xoá route):

```
IF ProjectMilestone::where('project_id', $projectId)->doesntExist():
    trust_state = NO_DATA
    UI: ẩn widget milestone_progress khỏi dashboard (không hiển thị card rỗng gây hiểu lầm)
ELSE:
    trust_state = LEGACY
    UI: hiển thị số liệu kèm nhãn cố định "Dữ liệu lịch sử — không còn kênh cập nhật chính thức"
    (nhãn không tham chiếu ngày cụ thể, không hard-code, chỉ mô tả TÌNH TRẠNG: "không còn write path")
```

Điều kiện để chuyển bất kỳ dự án nào từ `LEGACY`/`NO_DATA` sang `RELIABLE`: phải có quyết định nghiệp vụ về vai trò Milestone (xem GAP-milestone-canonical trong triage v2) VÀ một write path chính thức mới được thiết kế — **không nằm trong scope spec này**, không khôi phục Milestone API ở đây.

## Payment — quy tắc cụ thể

Đổi nhãn UI cho cả `outstandingBalance` (Portal) và `outstandingDebt`/aging (CRM Report):

- Nhãn cũ: "Công nợ" / "Outstanding Balance" / "Debt Aging"
- Nhãn mới: **"Giá trị theo lịch chưa ghi nhận thanh toán"** (nguyên văn theo yêu cầu operator; cho phép biến thể ngắn "Số dư theo lịch chưa thanh toán" nếu độ dài UI không đủ chỗ, nhưng KHÔNG được dùng lại từ "công nợ", "outstanding", "debt", "confirmed" trong bất kỳ biến thể rút gọn nào)
- Trust state: **luôn `LIMITED`**, không bao giờ `RELIABLE`, cho đến khi hệ thống có mô hình Payment Receipt/Allocation (P1-B trong triage — ngoài scope spec này)
- Bắt buộc kèm giải thích (không chỉ tooltip khi nhãn vẫn sai — đây là yêu cầu operator rõ ràng): text cố định hiển thị cạnh giá trị, không ẩn trong hover-only tooltip: *"Hệ thống hiện chưa ghi nhận thanh toán từng phần. Số liệu này là tổng giá trị các khoản chưa được đánh dấu 'đã thanh toán', có thể cao hơn hoặc thấp hơn số tiền thực sự còn phải thu."*
- KHÔNG thêm field giả lập `paid_amount` hay bất kỳ mô phỏng partial-payment nào để "làm cho số đẹp hơn" — giữ nguyên công thức, chỉ sửa nhãn + trust state + giải thích.

## Freshness — theo từng nguồn, không dùng MAX(updated_at) chung

Freshness threshold KHÔNG được tự đặt trong spec này nếu chưa có quyết định nghiệp vụ (operator yêu cầu rõ). Spec chỉ định nghĩa **cơ chế** (interface), giá trị ngưỡng cụ thể để trống chờ quyết định:

```
interface FreshnessRule {
    source: string;              // tên nguồn nghiệp vụ, VD "task_progress", "budget_progress"
    referenceTimestamp: Closure; // hàm lấy mốc thời gian tham chiếu ĐÚNG NGỮ CẢNH nguồn đó
                                  // (KHÔNG phải MAX(updated_at) toàn bảng — VD: task_progress
                                  // nên dùng MAX(updated_at) của Task THUỘC project đang xem,
                                  // không phải toàn hệ thống; budget_progress nên dùng
                                  // updated_at của chính Project.budget fields)
    thresholdDays: ?int;         // NULL = chưa có quyết định nghiệp vụ → mặc định KHÔNG áp STALE,
                                  // chỉ hiển thị "cập nhật lần cuối: X" mà không gắn nhãn cảnh báo
}
```

Mỗi nguồn trong scope Phase 1 cần 1 `FreshnessRule` riêng — **không dùng chung 1 threshold cho mọi widget**. Bảng dưới liệt kê `referenceTimestamp` đề xuất (đã có đủ evidence để định nghĩa), `thresholdDays` để trống chờ operator quyết định:

| Source | referenceTimestamp đề xuất | thresholdDays |
|---|---|---|
| `task_progress` | `MAX(Task.updated_at) WHERE project_id = X` | Chờ quyết định |
| `milestone_progress` | Không áp dụng (dùng rule LEGACY/NO_DATA ở trên, không dùng STALE) | N/A |
| `budget_progress` | `Project.updated_at` (cột budget nằm trực tiếp trên Project) | Chờ quyết định |
| `timeline_progress` | `Project.start_date`/`end_date` tồn tại hay không (đã null-safe sẵn, không cần freshness) | N/A |
| `outstandingBalance`/`outstandingDebt` | `MAX(ContractPayment.updated_at) WHERE tenant_id = X` | Chờ quyết định — lưu ý: trust state đã là `LIMITED` cố định, freshness ở đây chỉ bổ sung thông tin "cập nhật lần cuối", không nâng lên `RELIABLE` dù mới cập nhật |

## Test Matrix

| Case | Widget mẫu | Input | Kỳ vọng trust_state | Kỳ vọng UI |
|---|---|---|---|---|
| Real zero | `overall_progress` | Project có 5 Task, cả 5 completed=false, tổng progress thật = 0% | `RELIABLE`, value=0 | Hiển thị "0%" bình thường, không cảnh báo |
| No data | `overall_progress` | Project chưa có Task nào | `NO_DATA` | Hiển thị "Chưa có dữ liệu" thay vì "0%" |
| Stale | `budget_progress` (nếu có threshold quyết định) | Budget không update quá thresholdDays | `STALE` | Badge "Dữ liệu cũ, cập nhật lần cuối: [ngày]" |
| Limited | `outstandingBalance` | Có ContractPayment với status khác paid | `LIMITED` | Nhãn mới + giải thích cố định, không phải tooltip ẩn |
| Legacy | `milestone_progress` | Project có ≥1 ProjectMilestone record (dữ liệu lịch sử) | `LEGACY` | Hiển thị kèm nhãn "dữ liệu lịch sử — không còn kênh cập nhật" |
| Legacy → hidden | `milestone_progress` | Project không có ProjectMilestone record nào | `NO_DATA`, widget ẩn | Không render card |
| Query error | bất kỳ widget nào | Mock exception khi query nguồn dữ liệu | `ERROR` | Hiển thị "Không thể tải dữ liệu", KHÔNG hiển thị giá trị mặc định/0/giá trị cache cũ |
| Mixed-project data | `overall_progress` | Dashboard tổng hợp nhiều project, 1 project có data, 1 project `NO_DATA` | Mỗi project giữ trust_state riêng | Không gộp trung bình giữa project có/không có dữ liệu (tránh pha loãng sai) |
| Tenant isolation | tất cả widget tài chính | User tenant A xem dashboard | Chỉ tính trên `ContractPayment`/`Task` thuộc tenant A | Test regression dùng lại pattern `TenantScope` đã xác nhận đúng ở Site Diary audit |
| API/UI compatibility | tất cả widget Phase 1 | Client cũ (nếu có) gọi API không hiểu field `trust_state` mới | API vẫn trả `value` như cũ + thêm field mới, không đổi kiểu dữ liệu field cũ | Không breaking change cho consumer hiện tại |

## Rollout

- **Theo widget, không đại trà.** Thứ tự đề xuất: (1) `milestone_progress` (rủi ro thấp nhất — chỉ ẩn/label, không đổi công thức) → (2) `overall_progress`/`budget_progress`/`timeline_progress` (thêm `NO_DATA` handling) → (3) `outstandingBalance`/`outstandingDebt` (đổi nhãn + `LIMITED`, nhạy cảm nhất vì user quen thuật ngữ cũ, cần thông báo trước khi đổi nhãn UI tài chính).
- **Có rollback**: mỗi widget bọc trong feature flag riêng (theo widget, không phải 1 flag chung) để có thể tắt guardrail cho từng widget độc lập nếu phát sinh vấn đề, không cần rollback toàn bộ.
- **Không thay đổi schema tài chính.** Guardrail chỉ ở tầng hiển thị/label/trust-state tính toán thêm — không đụng `contract_payments`, `payment_certificates`.
- **Không giả lập partial payment.** Không thêm field/logic mô phỏng số tiền đã trả từng phần dưới bất kỳ hình thức nào trong phạm vi spec này.
- **Không refactor dashboard ngoài phạm vi cần thiết.** Không đụng `Project.progress` (ProjectManagerController), `avgProgress` (AnalyticsController), cashflow, `ProjectAnalyticsController` — các widget này ở Phase 2, chờ điều tra riêng đóng đủ evidence trước.

## Assumptions & Open Questions (chưa đủ evidence, không tự quyết)

1. **`Project.progress` (cột lưu sẵn, dùng bởi `ProjectManagerController::timeline`)**: chưa xác nhận được nơi/thời điểm cột này được cập nhật (job? observer? thủ công?). Đây là nguồn tiến độ độc lập nguy hiểm nhất theo inventory — nhưng KHÔNG đưa vào Phase 1 vì thiếu evidence về trigger cập nhật. Cần 1 investigation ticket riêng trước khi thiết kế guardrail cho widget này.
2. **`Api\ProjectAnalyticsController`**: route:list cho thấy reachable (`api/analytics/projects/{project}`...), mâu thuẫn với audit vòng trước (từng kết luận dead code do method không được route wire). Chưa xác nhận có UI/JS thật nào gọi các route này. Cần re-verify trước khi xếp vào bất kỳ phase nào.
3. **Cashflow (`ReportPageController`)**: công thức chưa được trace chi tiết trong audit này. Không đưa vào Phase 1.
4. **Ngưỡng `thresholdDays` cho STALE**: để trống hoàn toàn, chờ operator quyết định cho từng nguồn riêng — spec này chỉ định nghĩa cơ chế, không tự đặt số ngày.
5. **Milestone dữ liệu production**: DB dev hiện rỗng (0 record), không đại diện production. Rule LEGACY/NO_DATA ở trên được thiết kế để tự động đúng ở cả 2 trường hợp (chạy runtime theo dữ liệu thật của từng project) nên không cần biết trước số liệu production để implement — nhưng nên verify lại trên staging/production trước khi rollout Phase 1 phần milestone.

## Self-review (mâu thuẫn / placeholder / ambiguity / scope creep)

- **Mâu thuẫn đã rà soát**: không có widget nào được gán 2 trust state khác nhau trong 2 phần khác nhau của spec (đối chiếu bảng Inventory ↔ Test Matrix ↔ Milestone/Payment section — nhất quán).
- **Placeholder đã loại bỏ**: `thresholdDays` để `NULL`/"chờ quyết định" một cách tường minh, không phải giá trị giả định che giấu dưới dạng số cụ thể (VD không viết "7 ngày" như thể đã chốt).
- **Ambiguity đã xử lý**: đã tách rõ "count-based metrics" (ngoài scope, không cần trust state) khỏi "percentage-based metrics" (trong scope) — tránh áp dụng máy móc guardrail cho nơi không cần.
- **Scope creep đã chặn**: mục Rollout liệt kê tường minh 4 việc KHÔNG làm (đổi schema, giả lập partial payment, refactor ngoài phạm vi, khôi phục Milestone API) đúng theo yêu cầu operator.
- **Rủi ro còn lại cần lưu ý cho người duyệt**: label payment mới ("Giá trị theo lịch chưa ghi nhận thanh toán") dài hơn nhãn cũ — cần UI review riêng cho các layout hẹp (mobile/card nhỏ) trước khi rollout Phase 1 phần 3; không thuộc phạm vi kỹ thuật của spec này nhưng cần operator/design lưu ý.

## Testing

Chưa chạy — spec ở trạng thái draft chờ duyệt, chưa có implementation nên chưa có test thật để báo cáo. Test Matrix ở trên là kế hoạch test cho giai đoạn implementation sau khi spec được duyệt.
