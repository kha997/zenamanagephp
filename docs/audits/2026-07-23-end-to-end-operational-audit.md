# End-to-End Operational Audit — ZenaManage

*Ngày: 2026-07-23. Vai trò: chuyên gia vận hành AEC + product architect + business analyst + kiểm toán hệ thống + kỹ sư phần mềm cấp cao. Không sửa code trong phiên này.*

## Ghi chú về phạm vi tài liệu đầu vào

Các tài liệu được yêu cầu đọc trước — `CLAUDE.md`, `docs/PROJECT_MISSION.md`, `docs/OPERATING_MODEL.md`, `docs/DOMAIN_MAP.md`, `docs/REAL_WORKFLOWS.md`, `docs/PRODUCT_METRICS.md`, `docs/OPERATIONAL_GAP_REGISTER.md` — **không tồn tại trong repo này** (đã xác minh bằng kiểm tra file trực tiếp, `UNKNOWN` → xác nhận là thiếu, không phải tôi bỏ qua). Thay vào đó tôi dùng các nguồn thật đang có: `PROJECT_CONSTITUTION.md`, `OPERATIONAL_GAP_REGISTER.md` (ở root), `docs/product-purpose-ssot.md`, `docs/agent-ssot-rules.md`, `docs/roadmap/canonical-roadmap.md`, `docs/roadmap/backlog.yaml`, `docs/audits/*`, và trực tiếp code (models/migrations/controllers/policies/jobs/notifications/routes/tests). Đây tự nó là một **documentation gap** cần đưa vào register (xem GAP-D01).

Phân tích được thực hiện bằng 7 lượt khảo sát song song, mỗi lượt truy vết 1-2 workflow bằng bằng chứng code trực tiếp (file:line), theo đúng yêu cầu "không sửa code, không đoán mò".

## Verification Addendum (2026-07-23, phiên sau)

Một phiên verification-only riêng đã chạy runtime thật (test evidence tạm trong `tests/Feature/Audit/`, không sửa production code) cho 4 mục `LIKELY`/`ASSUMPTION` nặng nhất: **AUD-03, AUD-04, AUD-05, AUD-22**. Kết quả — 2 mục CONFIRMED đúng như dự đoán, 1 mục CONFIRMED nhưng với cơ chế khác/nặng hơn dự đoán, 1 mục **DISPROVED theo đúng giả thuyết ban đầu nhưng lộ ra một gap khác thay thế**. Chi tiết đầy đủ (lệnh chạy, output, file:line) nằm trong 4 file test dưới đây — bảng gap Tier Critical bên dưới đã được sửa lại theo kết quả này.

| AUD | Kết luận | Tóm tắt |
|---|---|---|
| AUD-03 | **CONFIRMED** (cơ chế khác giả định) | Không phải "âm thầm hỏng dữ liệu" như báo cáo gốc suy đoán — mà là **TypeError cứng** (`Carbon + int` không hợp lệ), và `catch (\Exception $e)` bao quanh không bắt được `\TypeError` (kế thừa `\Error`, không phải `\Exception`) → request 500 không kiểm soát mỗi khi CR approve có kèm `approved_schedule_days` khác 0. Test hiện có duy nhất chạm dòng này dùng giá trị `0` (falsy) nên né được bug — giải thích vì sao chưa ai từng thấy nó. |
| AUD-04 | **DISPROVED** giả thuyết gốc, **CONFIRMED** gap khác | Controller có `authorize()`/`can()` gọi Policy không tồn tại (`app/Http/Controllers/SupportTicketController.php`) **không hề được route tới** — dead code, không phải lỗi đang chạy. Controller **thật sự đang chạy** (`Api/SupportTicketController.php`) không gọi `authorize()` nào cả — hậu quả thực tế ngược lại hoàn toàn: **bất kỳ ai có 1 role bất kỳ trong cùng tenant đều đóng được ticket của người khác**, không có kiểm tra ownership/role nào. Ngoài ra lộ thêm 1 bug thật không liên quan: thiếu `assigned_to` trong payload gây lỗi 500 `Undefined array key`. |
| AUD-05 | **CONFIRMED** | Cả 8 model (`MaterialRequest`, `QcPlan`, `Ncr`, `SiteDiary`, `ProjectMilestone`, `ProjectPhase`, `TaskDependency`, `TaskAssignment`) xác nhận **ở tầng runtime** (không chỉ grep) không có global scope `'tenant'` đăng ký. Đã tái hiện rò rỉ thật: `ProjectMilestone::all()` không filter trả về cả milestone của 2 tenant khác nhau trong cùng 1 query. |
| AUD-22 | **CONFIRMED**, nguyên nhân chính xác hơn báo cáo gốc | Không phải "chưa ai cấp quyền" mà là **lỗi seeder có hệ thống**: `PermissionSeeder.php` tạo permission rows chỉ set cột `code` (vd `change_request.approve`, gạch dưới), **không set cột `name`** — trong khi `User::hasPermission()`/RBAC middleware check theo cột `name`. Route thật dùng `rbac:change-request.approve` (gạch ngang, do `ZenaPermissionsSeeder` tạo, có set `name` đúng). Kết quả: **role "Project Manager" có quyền `change_request.approve` (gạch dưới) hiển thị nhưng vô dụng** — quyền thật sự khớp middleware (`change-request.approve`, gạch ngang) **chỉ có "System Admin"** (nhờ cơ chế đồng bộ "admin có tất cả permission", không phải do được cấp riêng cho change-request). |

**Test evidence** (tạm, dùng để chứng minh — không phải regression suite, có thể xoá sau khi patch báo cáo/code thật):
- `tests/Feature/Audit/AudChangeRequestEndDateIncrementTest.php` — AUD-03
- `tests/Feature/Audit/AudChangeRequestPermissionSeedingTest.php` — AUD-22
- `tests/Feature/Audit/AudSupportTicketAuthorizationTest.php` — AUD-04
- `tests/Feature/Audit/AudTenantScopeCoverageTest.php` — AUD-05

Lệnh chạy: `./vendor/bin/phpunit tests/Feature/Audit` — 6/6 pass, 17 assertion, thời điểm xác minh commit `b8476f61`.

---

## A. Executive Assessment

### Hệ thống đang hỗ trợ tốt điều gì (CONFIRMED)

- **Lead → Account/Opportunity → Quote → Contract**: chuỗi có thật, có state machine tường minh (`Quote::TRANSITIONS`), tổng tiền báo giá tính lại phía server (không tin input client), chấp nhận 1 báo giá tự động supersede các bản nháp khác, tạo hợp đồng có kiểm tra idempotent (không tạo trùng), có `EventRecord` cho mọi bước chuyển trạng thái quan trọng.
- **Thanh toán/công nợ**: `ContractPayment`, `PaymentCertificate` (billing theo giai đoạn, có khấu trừ tạm ứng/retention), và đặc biệt `BusinessKpiService::outstandingDebt()` **là dữ liệu thật từ DB** (không phải mock như một ghi nhận cũ trong lịch sử dự án) — đây là tin tốt, một gap cũ đã tự đóng.
- **Hồ sơ thiết kế — review**: có cổng phê duyệt thật (draft→submitted→approved/rejected, HTTP 409 nếu sai trạng thái nguồn) — tốt hơn giả định ban đầu là "chỉ upload không kiểm soát".
- **Vật tư/nghiệm thu**: Submittal, QcInspection, Ncr có checklist/pass-fail thật, gắn với `WorkInstanceStep` (bước thi công thực tế) — không chỉ là nơi lưu file.
- **Change Request**: có audit log đầy đủ (`ZenaAuditLogger`) cho mọi bước, tenant isolation "belt-and-suspenders" (TenantScope + where tường minh).
- **RBAC pattern**: double-gate (route middleware `rbac:` + Policy) khá nhất quán ở phần lớn domain.

### Workflow đã khép kín (đúng nghĩa vòng đời)

1. Lead → Account/Opportunity → Quote → Contract (+ BOQ tự sinh)
2. Submittal/Material → QC Inspection → NCR (vòng chất lượng)
3. Payment Certificate → ContractPayment → công nợ/aging KPI

### Workflow mới chỉ có giao diện/model nhưng CHƯA vận hành trọn vẹn

| Workflow | Vấn đề cốt lõi |
|---|---|
| Khảo sát → yêu cầu thiết kế | Chỉ là 1 nhãn trạng thái pipeline + lịch hẹn với 1 field `outcome_notes` tự do — không có model "yêu cầu thiết kế" thật |
| Hồ sơ thiết kế → bàn giao | Review/approve có thật, nhưng "bàn giao" (transmittal có xác nhận khách hàng) không tồn tại — chỉ là cờ `visibility` |
| Kế hoạch công việc (Milestone/Phase) | Model tồn tại nhưng API CRUD đã bị **xoá có chủ đích** ngày 22/07 — chỉ còn đọc/analytics |
| Phát sinh → điều chỉnh hợp đồng | CR có vòng đời đầy đủ nhưng **không bao giờ chạm `Contract.total_value`** — tên tính năng đánh lừa kỳ vọng |
| Bàn giao → quyết toán → bảo hành | Không tồn tại — `Project.completed` là trạng thái cụt, không có chứng từ, không đối soát, không đồng hồ bảo hành |
| Khiếu nại → xử lý → đóng vụ việc | `SupportTicket` tồn tại nhưng là helpdesk SaaS nội bộ (không `project_id`/`client_id`), và có lỗi xác thực (Policy không tồn tại) |

### 5 rủi ro vận hành lớn nhất

1. **Không có Quyết toán/Bảo hành hậu dự án** — dự án đóng mà không chứng từ, không đối soát chi phí cuối cùng, không đồng hồ bảo hành → mất căn cứ khi tranh chấp hoặc bảo hành sau bàn giao. `LIKELY`/`CONFIRMED absence`.
2. **Change Request không thực sự "điều chỉnh hợp đồng"** — approve CR chỉ tăng `Project.budget_total` (và có khả năng lỗi khi cộng ngày vào `end_date` — xem GAP-F02), không bao giờ cập nhật `Contract.total_value`/`ContractPayment`. Người dùng tin hợp đồng đã đổi nhưng thực tế không. `CONFIRMED`.
3. **Thiếu cơ chế thông báo xuyên suốt chuỗi vật tư/nghiệm thu/tài liệu** — mọi phê duyệt/từ chối là "im lặng" trong DB, không ai được báo trừ khi tự vào xem. `CONFIRMED`.
4. **`SupportTicket` dùng nhầm vai trò complaint dự án nhưng Policy không tồn tại** — mọi update/close có nguy cơ luôn bị từ chối (hoặc tệ hơn, fail-open không kiểm soát tuỳ hành vi runtime). `CONFIRMED` (thiếu Policy), hệ quả cụ thể `LIKELY` (cần xác nhận runtime).
5. **Rủi ro tenant-isolation rải rác** — nhiều model (`MaterialRequest`, `QcPlan`, `Ncr`, `SiteDiary`, `ProjectMilestone`, `ProjectPhase`, `TaskDependency`, `TaskAssignment`) không dùng `TenantScope` chuẩn, chỉ dựa filter thủ công từng nơi gọi — một chỗ quên filter là rò rỉ dữ liệu chéo tenant. `CONFIRMED` (thiếu trait), hệ quả `LIKELY` (chưa xác nhận đã khai thác được).

### 5 cơ hội cải thiện giá trị nhất

1. Xây tối thiểu "Quyết toán dự án + Bảo hành" (1 model, 1 workflow đơn giản) — đóng khoảng trống hậu dự án lớn nhất, phục vụ trực tiếp mục tiêu "kiểm soát... nghiệm thu và bảo hành" trong sứ mệnh gốc.
2. Nối thật CR → Contract (hoặc đổi tên/tài liệu hoá lại kỳ vọng tính năng) + sửa khả năng lỗi `end_date`.
3. Thêm notification tối thiểu cho các mốc phê duyệt chính — hạ tầng Notification đã có sẵn (dùng ở quote portal), chỉ cần nối thêm, effort thấp so với giá trị.
4. Áp `TenantScope` đồng bộ cho các model còn thiếu — rẻ, rủi ro giảm mạnh.
5. Xây complaint-cho-dự-án thật (thêm `project_id`/`client_id`, cho client portal gửi) + tạo `SupportTicketPolicy` còn thiếu.

---

## B. End-to-End Workflow Matrix

| # | Workflow | Điểm bắt đầu | Điểm kết thúc | Vai trò chính | Trạng thái (states) | Bằng chứng code | Lỗ thủng chính | Ngoại lệ chưa xử lý | Mức hoàn thiện |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Lead → Customer | `LeadController::store` | `convert()`/`discard()` | `crm.manage/view` | NEW/CONVERTED/DISCARDED | `Lead.php:19-27`, `LeadController.php:95-272` | Không dedup theo SĐT/mã số thuế | Không | Khép kín, tốt |
| 2 | Khảo sát → yêu cầu thiết kế | `OpportunityAppointment` (type=survey) | `outcome_notes` free-text | `crm.manage` | scheduled/completed/cancelled/rescheduled | `OpportunityAppointment.php`, `CrmPageController.php:555-736` | Không có model "yêu cầu thiết kế" thật | Không truyền dữ liệu có cấu trúc sang bước quote | Chỉ giao diện, chưa vận hành trọn vẹn |
| 3 | Báo giá → phê duyệt → hợp đồng | `Quote` draft | `Contract` tạo | `crm.manage` + client portal | DRAFT/SENT/ACCEPTED/REJECTED/SUPERSEDED | `Quote.php:41-62`, `QuoteLifecycleService.php` | "Gửi" chỉ đổi status, không thực sự thông báo khách; `valid_until` không tự động hết hạn | Reject không có luồng tái đàm phán tự động | Khép kín phần lớn |
| 4 | Hợp đồng → dự án → kế hoạch công việc | `createContract()` | Project `planning` | `crm.convert`, `project.*` | planning/active/on_hold/completed/cancelled | `OpportunityController.php:434-591` | Milestone/Phase CRUD đã bị xoá; 4 hệ WorkTemplate song song | Không notify PM khi được gán | Một phần vận hành, một phần stub |
| 5 | Hồ sơ thiết kế → review → phát hành → bàn giao | Upload document | (không có điểm kết "bàn giao" rõ ràng) | `document.*`, Policy roles | DRAFT/SUBMITTED/APPROVED/REJECTED | `SimpleDocumentController.php` | Không có audit trail (EventRecord) cho document; không có transmittal/bàn giao thật | REJECTED là ngõ cụt, không có re-submit | Review thật, bàn giao không tồn tại |
| 6 | Thi công → vật tư → nhân công → nghiệm thu | `MaterialRequest`/`Submittal` | `QcInspection` complete / `Ncr` closed | `material.*`, `inspection.*` | draft→submitted→approved/rejected (submittal); scheduled→completed (inspection) | `Submittal.php:66-77`, `QcInspection.php` | "Nhân công" hoàn toàn không có model (chỉ 1 số `manpower_count`); không notification nào trong toàn chuỗi | Không | Vật tư/nghiệm thu khép kín; nhân công thiếu hoàn toàn |
| 7 | Chi phí → thanh toán → công nợ → đối soát | `ContractExpense`/`MaterialReceiptLine` | `outstandingDebt()` KPI | `contract.payment.*`, `payment_certificate.*` | draft→submitted→approved (certificate) | `BusinessKpiService.php:60-104`, `ContractPageController.php:704-759` | Không đối soát chéo Contract.total_value vs đã trả vs đã chi; 4/5 model tiền dùng cast `float` thay vì `decimal` | Payment tạo tay thiếu `recorded_by` | Từng mảnh tốt, không có "đối soát" tổng |
| 8 | Phát sinh → phê duyệt → điều chỉnh hợp đồng | `ChangeRequest` submit | `apply()` | `change-request.*` (chưa xác nhận role nào được gán quyền này) | draft→submitted→approved/rejected→implemented | `ChangeRequestController.php:838-912` | `apply()` không chạm `Contract`; `Baseline.linked_contract_id` = id của CR, không phải Contract thật; `increment('end_date', days)` khả năng lỗi | Không chặn CR trên dự án đã hoàn thành | Vòng đời đầy đủ, tác động tài chính/hợp đồng bị thiếu |
| 9 | Bàn giao → quyết toán → bảo hành | `Project.status=completed` | (không có) | `project.update` | (không có sub-state) | `Project.php:127-138` | Toàn bộ workflow không tồn tại | — | Không tồn tại |
| 10 | Khiếu nại → xử lý → đóng vụ việc | `SupportTicket::store` | `close()` | user nội bộ (không có client) | open/in_progress/pending_customer/resolved/closed | `SupportTicketController.php` | Không có `project_id`/`client_id`; Policy không tồn tại → authorize() luôn thất bại | Không có luồng dành cho client | Nhầm mục đích, có lỗi xác thực |

---

## C. Operational Gap Register (bổ sung — audit end-to-end)

*ID prefix `AUD-` để phân biệt với `GAP-001..028` đã có trong `OPERATIONAL_GAP_REGISTER.md` (đó là audit theo route/dead-code; đây là audit theo workflow nghiệp vụ). Đề xuất: hợp nhất 2 danh sách trong một lần làm riêng, không làm trong phiên này (ngoài stop condition).*

Thang điểm mỗi trục: 0 (không liên quan/không đáng kể) → 5 (tối đa). Trục "Công sức" liệt kê riêng để tham khảo lập roadmap — **không** cộng vào ưu tiên.

### Tier Critical (điểm ưu tiên trung bình 4 trục đầu ≥ 4.0)

| ID | Mô tả | Bằng chứng | Nhãn | Người ảnh hưởng | Hậu quả thực tế | Mục tiêu | Tần suất | Thiệt hại | Đau người dùng | Chắc chắn | Công sức | Giải pháp nhỏ nhất | Giải pháp dài hạn |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| AUD-01 | Không có Quyết toán dự án + Bảo hành | `Project.php:127-138` (completed = dead-end); `warranty_period` chỉ có trong 1 factory seed, không phải schema field | CONFIRMED (absence) | Kế toán, Giám đốc, khách hàng | Không có căn cứ đối soát/khiếu nại sau bàn giao; không nhắc hạn bảo hành | 5 | 3 | 5 | 4 | 5 | 3 | Thêm 1 bảng `ProjectSettlement` (tổng chi phí cuối, tổng đã thu, chênh lệch) + 1 field `warranty_expires_at` trên Project, không cần UI phức tạp | Model `Warranty`/`WarrantyClaim` đầy đủ, nhắc việc tự động trước hạn |
| AUD-02 | Change Request `apply()` không chạm `Contract.total_value`; `Baseline.linked_contract_id` lưu id của CR, không phải Contract | `ChangeRequestController.php:838-912`, `:1101-1136` | CONFIRMED | PM, kế toán, khách hàng (gián tiếp) | Hợp đồng "trông như" chưa từng bị điều chỉnh dù CR đã approved — sai lệch giữa kỳ vọng tên tính năng và dữ liệu thật | 5 | 3 | 5 | 4 | 5 | 2 | Sửa `linked_contract_id` trỏ đúng Contract; ghi thêm dòng lịch sử điều chỉnh trên Contract khi CR applied | Model `ContractAmendment` chính thức, version hoá `Contract.total_value` |
| AUD-03 | `Project::increment('end_date', $days)` trên cột `date`-cast (Carbon) ném **TypeError cứng** ("Carbon + int" không hợp lệ), không phải hỏng âm thầm; `catch (\Exception $e)` bao quanh không bắt được TypeError (kế thừa `\Error`) → 500 không kiểm soát | `ChangeRequestController.php:700,746`; verified runtime: `tests/Feature/Audit/AudChangeRequestEndDateIncrementTest.php` | **CONFIRMED** (runtime, 2026-07-23) | PM, mọi CR có `approved_schedule_days` khác 0 | Duyệt CR có đổi lịch → request 500 crash hoàn toàn (không phải "ngày sai âm thầm" như suy đoán ban đầu) | 5 | 2 | 5 | 5 | 5 | 1 | Sửa thành `$project->end_date = \Carbon\Carbon::parse($project->end_date)->addDays($days); $project->save();`; đổi `catch (\Exception $e)` thành `catch (\Throwable $e)` nếu muốn chặn luôn các lỗi tương tự | Audit toàn bộ `increment()/decrement()` trên các cột có cast ngày/giờ |
| AUD-04 | **Không phải** "Policy thiếu khiến luôn từ chối" — controller có `authorize()` gọi Policy không tồn tại (`app/Http/Controllers/SupportTicketController.php`) là **dead code, không được route tới**. Controller thật đang chạy (`Api/SupportTicketController.php`) **không có bất kỳ kiểm tra ownership/role nào** — bất kỳ ai có 1 role hợp lệ trong tenant đóng được ticket của người khác | `AuthServiceProvider.php:14-42` (không map SupportTicket); verified runtime: `tests/Feature/Audit/AudSupportTicketAuthorizationTest.php` (0 route trỏ tới controller có Policy check; user "viewer" không sở hữu ticket vẫn đóng được ticket người khác, HTTP 200) | **DISPROVED** giả thuyết gốc, **CONFIRMED** gap khác, nghiêm trọng hơn (quá lỏng thay vì quá chặt) | Mọi khách hàng nội bộ dùng ticket | Nhân viên bất kỳ role có thể đóng/nhận sai ticket của đồng nghiệp — mất trách nhiệm giải trình | 3 | 3 | 4 | 4 | 5 | 1 | Thêm kiểm tra ownership/role thật trong `Api\SupportTicketController::update()` (vd chỉ `assigned_to`/creator/admin mới đóng được); xoá hoặc dọn controller chết còn lại; sửa luôn bug phụ `Undefined array key "assigned_to"` khi field bị bỏ trống | Tạo `SupportTicketPolicy` thật, đăng ký trong `AuthServiceProvider`, dùng ở cả 2 nơi |
| AUD-05 | Tenant isolation không đồng bộ: `MaterialRequest`, `QcPlan`, `Ncr`, `SiteDiary`, `ProjectMilestone`, `ProjectPhase`, `TaskDependency`, `TaskAssignment` không dùng `TenantScope` trait | Verified runtime (không chỉ grep): `tests/Feature/Audit/AudTenantScopeCoverageTest.php` — cả 8 model xác nhận không có global scope `'tenant'`; tái hiện rò rỉ thật với `ProjectMilestone::all()` trả về dữ liệu của cả 2 tenant | **CONFIRMED** (runtime, 2026-07-23) | Mọi tenant | Một truy vấn mới quên `whereHas`/`forTenant()` là rò rỉ chéo tenant — đã tái hiện được, không còn là rủi ro lý thuyết | 5 | 2 | 5 | 2 | 5 | 2 | Thêm `TenantScope` cho 8 model này (đã có pattern sẵn để copy). Đã sửa 2026-07-23 cho 5/8 model (QcPlan, Ncr, SiteDiary, TaskDependency, TaskAssignment) — có sẵn cột tenant_id, chỉ cần thêm trait. 3 model còn lại (MaterialRequest, ProjectPhase, ProjectMilestone) KHÔNG có cột tenant_id trong schema thật (xác nhận qua Schema::getColumnListing(), không chỉ grep) — cần migration thêm cột + backfill từ project_id trước khi thêm trait được, việc lớn hơn "thêm trait" nên tách thành plan riêng, chưa làm trong lần sửa này. | Viết test bất biến tenant-isolation cho từng model, như đã làm cho RBAC/Tenant Invariants CI job |
| AUD-22 | *(chuyển từ Tier Moderate sau verification — độ nghiêm trọng thực tế cao hơn xếp loại ban đầu)* Lỗi seeder có hệ thống: `PermissionSeeder.php` tạo permission rows chỉ set `code` (vd `change_request.approve`, gạch dưới), không set cột `name` mà `User::hasPermission()` dùng để check; route thật cần `change-request.approve` (gạch ngang, do `ZenaPermissionsSeeder` set đúng `name`) | `PermissionSeeder.php:86-89`, `app/Models/User.php:196`; verified runtime: `tests/Feature/Audit/AudChangeRequestPermissionSeedingTest.php` | **CONFIRMED** (runtime, 2026-07-23) | Project Manager (và mọi role không phải System Admin) | Role "Project Manager" thấy quyền `change_request.approve` trong danh sách nhưng **vô dụng** — chỉ System Admin thực sự approve/apply được Change Request qua RBAC chuẩn (nhờ cơ chế "admin có tất cả permission", không phải cấp riêng) | 5 | 3 | 5 | 4 | 5 | 1 | Sửa `PermissionSeeder.php` để set `name` = `code` cho mọi permission nó tạo (hoặc gán thẳng permission `change-request.*` hyphen cho role Project Manager) | Hợp nhất 2 hệ đặt tên permission (gạch dưới cũ vs gạch ngang mới) về 1 convention duy nhất, kiểm tra toàn bộ 40+ permission khác do `PermissionSeeder.php` tạo có cùng lỗi không |

### Tier Important (điểm ưu tiên trung bình 3.0–3.9)

| ID | Mô tả | Bằng chứng | Nhãn | Người ảnh hưởng | Hậu quả | Ưu tiên (TB 4 trục) | Công sức | Giải pháp nhỏ nhất | Giải pháp dài hạn |
|---|---|---|---|---|---|---|---|---|---|
| AUD-06 | Không có notification nào cho toàn chuỗi Submittal/Inspection/Material/NCR (vẫn đúng tính đến 23/07, không chỉ 07/07) | `SubmittalController.php`, `InspectionController.php`, `MaterialRequestController.php` — 0 lệnh gọi Notification/Mail | CONFIRMED | PM, kỹ sư, nhà cung cấp | Người liên quan phải tự vào hệ thống kiểm tra, chậm phản ứng | 3.75 | 2 | Thêm `Notification::create()` tại điểm approve/reject (đã có pattern ở `PortalQuoteController`) | Event-driven notification layer chung cho toàn app |
| AUD-07 | "Nhân công" hoàn toàn không có model — chỉ 1 số `manpower_count` mỗi ngày, không có timesheet/subcontractor/chi phí nhân công | Grep toàn repo không có model nào | CONFIRMED (absence) | PM, kế toán chi phí | Không kiểm soát được chi phí/tiến độ nhân công qua hệ thống | 4 | 3 | Thêm bảng `LaborEntry` (ngày, số người, giờ công, đơn giá) tối thiểu | Model đầy đủ: nhân sự, nhà thầu phụ, chấm công |
| AUD-08 | Milestone/Phase: model tồn tại, API CRUD đã xoá có chủ đích 22/07, chỉ còn đọc | `routes/api.php:275` comment tự nhận | CONFIRMED | PM | "Kế hoạch công việc" cấp cao hơn task không thể tạo/sửa qua hệ thống | 3.5 | 3 | Quyết định business: có cần Milestone thật không, nếu có thì làm lại CRUD có chủ đích, không phục hồi mù | Gắn Milestone vào WorkTemplate/WorkInstance đang là hệ mạnh nhất |
| AUD-09 | 4 hệ WorkTemplate song song, 2 API đang mount cùng lúc (`App\...` có UI thật, `Src\CoreProject\...` không) | `routes/web.php:371-373`, `routes/api_zena.php:241-249`, `routes/api.php:594-599,1029` | CONFIRMED | Dev tương lai, PM dùng nhầm API | Rủi ro sửa nhầm hệ chết, dữ liệu phân mảnh giữa 2-4 hệ | 3.25 | 2 | Archive rõ ràng các API không có UI, giữ 1 API canonical | Hợp nhất về 1 hệ WorkTemplate duy nhất |
| AUD-10 | Contract↔Quote/BOQ line-item copy-not-reference (an toàn hiện tại vì Quote ACCEPTED là terminal, nhưng tiềm ẩn nếu tương lai mở lại quote đã accept) | `OpportunityController.php:546-572` | LIKELY (latent) | Kế toán, PM | Nếu tương lai cho sửa quote đã duyệt, Contract/BOQ sẽ lệch âm thầm | 3 | 1 | Ghi rõ trong code/docs: không được phép reopen ACCEPTED quote nếu chưa thêm đồng bộ | FK `quote_line_item_id` trên BoqLineItem |
| AUD-11 | Document domain không có audit trail thật (EventRecord/ZenaAuditLogger) — trạng thái duyệt bị ghi đè trong `metadata` JSON, không có lịch sử | `SimpleDocumentController.php` (store/submit/decision không gọi audit) | CONFIRMED (absence) | Kiểm toán, khách hàng tranh chấp hồ sơ | Không chứng minh được "ai duyệt gì, khi nào" cho hồ sơ thiết kế — rủi ro pháp lý | 4 | 2 | Thêm `ZenaAuditLogger::log()` vào submit/decision (pattern đã có ở Submittal) | Model `DocumentApproval` riêng, immutable |
| AUD-12 | Không đối soát Contract.total_value vs tổng đã thanh toán vs tổng chi phí thực tế — 3 con số tồn tại độc lập | `BusinessKpiService.php`, `ContractPageController.php` — không có hàm nào so sánh cả 3 | CONFIRMED (absence) | Giám đốc, kế toán | Không có 1 màn hình "lời/lỗ hợp đồng" đáng tin | 3.75 | 3 | 1 API tổng hợp 3 con số (dữ liệu đã có sẵn, chỉ thiếu phép cộng/so sánh) | Dashboard đối soát hợp đồng thời gian thực |
| AUD-13 | 4/5 model tiền dùng cast `float` thay vì `decimal:2` (chỉ `ChangeRequest.impact_cost` dùng decimal) | `ContractPayment.php:55`, `ContractExpense.php:45`, `PaymentCertificate.php:77-80` | CONFIRMED | Kế toán | Rủi ro lệch số lẻ khi cộng dồn nhiều kỳ thanh toán | 3.25 | 1 | Đổi cast sang `decimal:2` cho các model còn lại | Audit toàn bộ money field trong hệ thống |

### Tier Moderate/Minor (điểm ưu tiên < 3.0, hoặc cần xác nhận thêm trước khi đầu tư)

| ID | Mô tả | Nhãn | Ưu tiên | Ghi chú |
|---|---|---|---|---|
| AUD-14 | Không dedup Lead theo `contact_hint` / Account theo phone/mã số thuế | LIKELY | 2.75 | Cần hỏi sale: có thực sự gặp lead trùng không |
| AUD-15 | `Quote.valid_until` không có cron enforce hết hạn | CONFIRMED absence | 2.5 | Effort thấp nếu ưu tiên |
| AUD-16 | "Gửi báo giá" chỉ đổi status, không thực sự đẩy thông báo cho khách (khách chỉ thấy khi tự đăng nhập portal) | CONFIRMED | 2.75 | Liên quan AUD-06 |
| AUD-17 | Site diary field `materials_delivered`/`equipment_used` là free-text, không FK — không join được với Material/Task | CONFIRMED | 2.5 | Ảnh hưởng báo cáo, không ảnh hưởng vận hành hàng ngày |
| AUD-18 | `file_hash` trên Document là ULID ngẫu nhiên khi không có input, không phải hash nội dung thật | CONFIRMED | 2 | Không dùng để verify tính toàn vẹn file được |
| AUD-19 | `is_current_version` trên Document có thể là cờ chết (version tracking thật qua `current_version_id`) | LIKELY | 1.5 | Cần dọn nếu đúng |
| AUD-20 | Document domain có 4+ controller/model song song đã chết (bao gồm cả `src/DocumentManagement` cùng bind vào bảng `documents`) | CONFIRMED | 2.5 | Rủi ro giống AUD-09, nên dọn cùng đợt |
| AUD-21 | 2 hệ ChangeRequest song song (nhưng có tài liệu hoá rõ ràng "compatibility surface only") | CONFIRMED, rủi ro thấp vì đã document | 1.5 | Không khẩn |
| AUD-23 | Không có phân quyền tách bạch (separation of duties) — cùng 1 role PM vừa tạo vừa duyệt CR | LIKELY | 2.5 | Hỏi operator: có cần tách vai trò không |
| AUD-24 | Không chặn CR/apply trên dự án đã `completed` | CONFIRMED absence | 2.25 | |
| AUD-25 | Không có transmittal/xác nhận bàn giao hồ sơ thiết kế cho khách (chỉ cờ visibility) | CONFIRMED absence | 2.75 | Liên quan AUD-01 |
| AUD-26 | Payment tạo thủ công (không qua certificate) thiếu cột `recorded_by` | CONFIRMED absence | 2 | |
| AUD-27 | Không có antivirus/ClamAV thật cho upload tài liệu, chỉ có kiểm tra pattern/signature | CONFIRMED | 2.5 | Rủi ro bảo mật, không phải rủi ro nghiệp vụ |
| AUD-28 | *(phát hiện mới khi sửa AUD-22, 2026-07-23)* `RoleSeeder.php` có đúng lỗi giống AUD-22 (`firstOrCreate` không set cột `name`) cho 2 permission `project.read` và `user.manage`, không seeder nào khác sau đó backfill lại — 2 dòng này vẫn `name = NULL` sau khi đã sửa AUD-22 | CONFIRMED, cô lập bằng `tests/Feature/Audit/AudChangeRequestPermissionSeedingTest.php` (allowlist 2 exception, ghi rõ lý do) | 1.5 | `user.manage` xác nhận đã chết (chỉ còn 1 tham chiếu string cứng ở `SimpleSidebarBuilderController.php:126`, không có RBAC check thật nào dùng); `project.read` có thể vẫn cần (khác `project.view` mà `ZenaPermissionsSeeder` dùng) — cần hỏi operator trước khi xoá hay sửa |
| AUD-D01 | Các tài liệu SSOT được kỳ vọng (`CLAUDE.md`, `PROJECT_MISSION.md`, `OPERATING_MODEL.md`, `DOMAIN_MAP.md`, `REAL_WORKFLOWS.md`, `PRODUCT_METRICS.md`) không tồn tại | CONFIRMED absence | 2 (documentation gap) | Nên quyết định: tạo mới hay xác nhận `PROJECT_CONSTITUTION.md` + `docs/product-purpose-ssot.md` đã đủ thay thế |

**Test/mô phỏng cho Tier Critical — đã thực hiện 2026-07-23** (xem Verification Addendum ở đầu file): cả 4 mục AUD-03/04/05/22 đã được verify bằng test evidence thật trong `tests/Feature/Audit/`, không còn là "cần làm" — kết quả đã phản ánh vào các dòng tương ứng ở trên. AUD-02 (Baseline.linked_contract_id) và AUD-01 (Quyết toán/Bảo hành) vẫn ở dạng CONFIRMED-by-code-reading, chưa có test runtime riêng — có thể là mục verify tiếp theo nếu cần.

---

## D. Questions for Real Operators

*Theo tình huống cụ thể, không hỏi chung chung.*

**Cho Giám đốc / Ban lãnh đạo:**
1. Khi một dự án hoàn thành trên thực tế, ai là người xác nhận "đã bàn giao" — có ký giấy tay/email không, hay chỉ nói miệng? Hiện hệ thống không ghi nhận gì ở bước này.
2. Có quy định bảo hành theo hợp đồng (vd 12 tháng) không, và ai theo dõi hạn đó hiện nay — Excel, lịch cá nhân, hay không ai theo dõi chủ động?
3. Khi một Change Request (phát sinh) được duyệt, anh/chị có kỳ vọng giá trị hợp đồng (Contract) tự động cập nhật không? (Hiện tại nó KHÔNG cập nhật — chỉ tăng ngân sách dự án nội bộ.)
4. Có bao giờ 2 khách hàng khác nhau tra ra là cùng 1 công ty/số điện thoại (do sale tạo lead trùng) chưa?

**Cho quản lý dự án (PM):**
5. Khi một Change Request được duyệt và làm lệch ngày kết thúc dự án, anh/chị có từng thấy ngày kết thúc hiển thị sai một cách kỳ lạ (vd nhảy hàng chục nghìn ngày) chưa? (Có khả năng lỗi kỹ thuật ở bước cộng ngày.)
6. Anh/chị có đang dùng tính năng "Milestone" (mốc dự án) trên hệ thống không? Nếu không, đây có phải vì nó không hoạt động, hay vì chưa cần?
7. Khi vật tư được duyệt/từ chối, anh/chị có được thông báo trong hệ thống không, hay phải tự vào kiểm tra mỗi ngày?
8. Anh/chị có theo dõi chi phí nhân công (nhân sự, nhà thầu phụ) qua hệ thống này không, hay hoàn toàn bằng công cụ khác?

**Cho kiến trúc sư / kỹ sư thiết kế:**
9. Trước khi báo giá, có bước "khảo sát hiện trạng + ghi nhận yêu cầu khách hàng" chính thức không, và kết quả đó có được nhập vào hệ thống nào không, hay chỉ ghi tay/note riêng?
10. Khi hồ sơ thiết kế bị từ chối (reject), quy trình tiếp theo là gì — có nộp lại bản sửa qua hệ thống không? (Hiện hệ thống không có đường quay lại từ "rejected".)
11. Khi bàn giao bộ hồ sơ thiết kế cho khách hàng, có yêu cầu khách xác nhận đã nhận đủ hồ sơ không (ký nhận/email xác nhận)?

**Cho kỹ sư công trường:**
12. "Nhật ký công trình" (site diary) hiện điền số nhân công mỗi ngày — có cần biết CHÍNH XÁC ai làm việc hôm đó, hay chỉ cần tổng số người là đủ?
13. Vật tư giao đến công trường có được đối chiếu với đơn đặt hàng (Material Request) một cách hệ thống, hay chỉ ghi chú bằng tay trong nhật ký?

**Cho kế toán:**
14. Có bảng nào hiện tại cho anh/chị biết tổng: giá trị hợp đồng, đã thu, đã chi, chênh lệch — trong cùng 1 màn hình? Nếu không, hiện đang làm việc này bằng cách nào (Excel riêng)?
15. Khi một khoản thanh toán được tạo thủ công (không qua chứng từ nghiệm thu/certificate), có cần biết ai là người tạo khoản đó không? (Hiện hệ thống không lưu thông tin này.)
16. Số tiền phát sinh (Change Request) có bao giờ cần phản ánh vào hợp đồng chính thức để xuất hóa đơn/báo cáo thuế không?

**Cho nhân viên hành chính / CSKH:**
17. Khi khách hàng gọi điện phàn nàn (khiếu nại) về chất lượng thi công hay tiến độ, anh/chị ghi nhận việc đó ở đâu hiện nay — có hệ thống nào không, hay chỉ note tay/Zalo?
18. Khách hàng có kênh nào để tự gửi khiếu nại qua hệ thống (cổng khách hàng) không, hay luôn phải gọi/nhắn cho nhân viên trước?

---

## E. Recommended Roadmap

*Mỗi mục nêu rõ lý do phục vụ mục tiêu tối thượng (giảm thất thoát thông tin, kiểm soát tài chính/tiến độ, dữ liệu quản trị chính xác, giảm phụ thuộc trao đổi miệng, truy vết được).*

### Critical integrity fixes (làm trước tiên — đúng mục 5.1-5.2 hiến pháp: toàn vẹn dữ liệu, bảo mật; cả 4 mục dưới đây đã CONFIRMED bằng test runtime 2026-07-23, không còn là suy đoán)
- AUD-03: Sửa `Project::increment('end_date', ...)` — hiện tại ném TypeError không kiểm soát (500), không phải hỏng âm thầm như suy đoán ban đầu, nhưng vẫn chặn hoàn toàn tính năng đổi lịch qua Change Request.
- AUD-05: Thêm `TenantScope` cho 8 model còn thiếu — đúng ưu tiên #1 của hiến pháp (tenant isolation); rò rỉ đã được tái hiện thật, không còn là rủi ro lý thuyết.
- AUD-22: Sửa `PermissionSeeder.php` để set cột `name` cho mọi permission nó tạo — hiện tại role Project Manager không thể approve Change Request qua RBAC chuẩn dù thấy quyền trong danh sách (chỉ System Admin làm được, nhờ cơ chế cấp toàn bộ permission chứ không phải được cấp riêng).
- AUD-04: Thêm kiểm tra ownership/role thật vào `Api\SupportTicketController::update()` — hiện tại BẤT KỲ ai có 1 role hợp lệ trong tenant đều đóng được ticket của người khác (gap ngược hướng với giả thuyết ban đầu: quá lỏng, không phải quá chặt); dọn luôn controller chết còn lại và bug phụ `Undefined array key "assigned_to"`.

### Core workflow closure (đóng vòng đời nghiệp vụ còn dang dở — phục vụ trực tiếp "kiểm soát... nghiệm thu và bảo hành" trong sứ mệnh)
- AUD-01: Quyết toán + Bảo hành tối thiểu.
- AUD-02: Nối CR → Contract thật (hoặc làm rõ lại kỳ vọng tính năng với business).
- AUD-06: Notification tối thiểu cho các mốc phê duyệt (Submittal/Inspection/Material/Document/CR).
- Xây complaint-cho-dự-án thật (project_id/client_id + client portal) thay vì dùng nhầm SupportTicket.

### Management visibility (dữ liệu quản trị cho Ban lãnh đạo — mục tiêu "cung cấp dữ liệu quản trị chính xác")
- AUD-12: 1 màn hình đối soát Contract vs Payment vs Cost.
- Quyết định số phận Milestone/Phase (AUD-08) — nếu giữ, làm lại có chủ đích; nếu bỏ, xoá hẳn để không gây nhầm lẫn.

### Automation (giảm phụ thuộc thao tác thủ công/nhắc việc bằng trí nhớ)
- AUD-15: Cron enforce hết hạn báo giá.
- AUD-16: Thông báo thật khi "gửi báo giá" (không chỉ đổi status).
- Escalation thật cho SLA ticket/task quá hạn (hiện chỉ hiển thị, không hành động).

### Optimization (giá trị thấp hơn, làm sau khi các mục trên ổn định)
- AUD-07: Model nhân công tối thiểu (LaborEntry).
- AUD-09/AUD-20: Dọn các hệ WorkTemplate/Document song song đã chết.
- AUD-13: Đổi cast tiền tệ sang `decimal:2`.
- AUD-14: Dedup Lead/Account.
- AUD-17/18/19: Data-hygiene nhỏ (FK hoá site diary field, bỏ file_hash giả, dọn cờ version chết).

---

## STOP REPORT

**Đã khảo sát:** 7 lượt nghiên cứu song song (subagent, chỉ đọc) truy vết đầy đủ 10 workflow theo yêu cầu, dựa trên code thật (models, migrations, controllers, policies, RBAC seeders, routes, tests, EventRecord/audit log usage) tại repo `zenamanage-golden`, nhánh `main`, commit `b8476f61` (thời điểm bắt đầu audit). Không đọc được các tài liệu SSOT được yêu cầu (`CLAUDE.md`, `docs/PROJECT_MISSION.md`, `docs/OPERATING_MODEL.md`, `docs/DOMAIN_MAP.md`, `docs/REAL_WORKFLOWS.md`, `docs/PRODUCT_METRICS.md`) vì chúng không tồn tại — đã dùng `PROJECT_CONSTITUTION.md`, `docs/product-purpose-ssot.md`, `docs/agent-ssot-rules.md`, `OPERATIONAL_GAP_REGISTER.md` (root), `docs/roadmap/*`, `docs/audits/*` làm nguồn thay thế.

**Bằng chứng đã dùng:** trích dẫn file:line trực tiếp cho phần lớn nhận định (xem chi tiết trong từng mục B/C). Một số phát hiện có mâu thuẫn nhẹ giữa 2 lượt khảo sát độc lập (vd chi phí vật tư có/không chảy vào cost tracking) — đã đối chiếu và ghi chú rõ trong AUD-12/mục Construction: `MaterialReceiptLine.unit_cost` CÓ chảy vào `Api\ContractController::costSummary()` (xác nhận bởi lượt khảo sát Cost/Payment) nhưng KHÔNG chảy vào `BusinessKpiService` dashboard (xác nhận bởi lượt khảo sát Construction) — cả hai đều đúng ở phạm vi khác nhau.

**[Cập nhật 2026-07-23, phiên verification-only riêng]** 4 mục AUD-03/04/05/22 đã được xác nhận bằng test runtime thật (xem "Verification Addendum" ở đầu file và `tests/Feature/Audit/`). Tóm tắt: AUD-03 và AUD-05 CONFIRMED đúng hướng nghi ngờ ban đầu (nhưng AUD-03 với cơ chế lỗi khác — TypeError cứng, không phải hỏng âm thầm); AUD-22 CONFIRMED với nguyên nhân gốc chính xác hơn (lỗi seeder không set cột `name`, không phải "chưa cấp quyền"); AUD-04 **DISPROVED** giả thuyết gốc nhưng lộ ra một gap khác nghiêm trọng không kém (thiếu kiểm soát ownership, không phải luôn từ chối). Đây là bằng chứng cho nguyên tắc "Evidence Before Claims": 2/4 giả thuyết ban đầu chỉ đúng một phần hoặc sai hẳn về cơ chế khi verify thật.

**Chưa thể xác nhận (còn lại sau vòng verification này):**
- AUD-02 (`Baseline.linked_contract_id` = id của CR): CONFIRMED bằng đọc code, chưa có test runtime riêng xác nhận hậu quả cụ thể khi vận hành thật.
- AUD-01 (không có Quyết toán/Bảo hành): CONFIRMED absence bằng đọc code, không cần test runtime (bản chất là thiếu tính năng, không phải hành vi runtime cần verify).
- Toàn bộ mục D (câu hỏi operator) — đây là giả định cần người vận hành thật xác nhận, không phải bằng chứng code.

**Rủi ro lớn nhất (sau verification):** AUD-22 tăng mức nghiêm trọng thật sự (Critical, không phải Moderate như báo cáo gốc xếp) — chức năng phê duyệt Change Request gần như không ai dùng được ngoài System Admin do lỗi seeder, ảnh hưởng trực tiếp workflow "phát sinh → phê duyệt → điều chỉnh hợp đồng" mà AUD-02 đã nói là có vấn đề khác. Cùng với AUD-01 (quyết toán/bảo hành), đây là 2 khu vực chạm trực tiếp tài chính/pháp lý hậu dự án mà sứ mệnh gốc của ZENA đặt ưu tiên cao.

**Bước tiếp theo được khuyến nghị:** đưa danh sách AUD-01..27 (đặc biệt 5 mục Tier Critical đã verify) vào một phiên brainstorm riêng với người vận hành thật để trả lời mục D, sau đó lập plan sửa riêng cho từng mục Tier Critical theo `superpowers:writing-plans` — không tự ý sửa code trong phiên verification này theo đúng yêu cầu.

**Không đã làm** (đúng stop condition): không sửa code, không tạo migration, không tạo feature, không đổi task status, không tự mở rộng phạm vi ngoài báo cáo này.
