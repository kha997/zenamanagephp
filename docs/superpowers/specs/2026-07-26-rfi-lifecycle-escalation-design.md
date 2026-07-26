# RFI Lifecycle + Escalation History — Design Spec

**Date:** 2026-07-26 (rev 3)
**Status:** `APPROVED FOR WRITING-PLANS` (rev 2, commit `f72378eb`) — rev 3 đưa 4 ràng buộc bổ sung của operator vào spec trước khi viết plan
**Nguồn gốc:** Operational Integrity Triage v2 (P1-A); rev 2 chốt Quyết định #1 (escalation độc lập); rev 3 bổ sung 4 ràng buộc bắt buộc: legacy deployment gate, `current_escalation_id` + compatibility mirror, concurrency invariant chi tiết hơn, authorization vận hành (PM escalate/resolve) đã được duyệt thay vì để "gap"

## Thay đổi so với rev 2

| # | Rev 2 | Rev 3 |
|---|---|---|
| Migration | 1 lần chạy, report hậu-migration, không chặn cutover | **Legacy deployment gate**: rollout additive nhiều giai đoạn, operator xác nhận từng record, có stop condition, chỉ cutover khi 0 record chưa xác nhận |
| Schema | Chỉ có `rfi_escalations` + 4 field cache trên `rfis` | Thêm `rfis.current_escalation_id` (nullable FK) trỏ escalation active; 4 field cũ được gọi rõ là **compatibility mirror tạm thời**, không phải cache thường; yêu cầu inventory reader trước khi tính chuyện deprecate |
| Authorization escalate | `rfi.escalate` (chỉ admin có theo seed) — ghi nhận là "gap vận hành" | **Đã duyệt**: PM đúng project + admin được escalate — cần seed `rfi.escalate` cho role PM, không còn là "gap" mà là yêu cầu thiết kế chính thức |
| Authorization resolveEscalation | Tái dùng `rfi.escalate` | **Đã duyệt**: người được escalate tới (`escalated_to`), PM dự án, và admin đều được resolve — không giới hạn chỉ người tạo escalation |
| Actor/target check | Chỉ check tenant + project membership | Thêm: actor và target phải **đang active** (chưa bị deactivate/disable) |
| Notification | Thiết kế mới, chưa nhấn mạnh việc không tái dùng code chết | Nhấn mạnh tường minh: **KHÔNG tái sử dụng `RfiEventListener`/Event cũ dưới bất kỳ hình thức nào** (kể cả sửa lại) — xây class Notification hoàn toàn mới, wire qua `EventServiceProvider` đúng cách; bắt buộc có test xác nhận in-app notification record thực sự được tạo sau commit | 

## Thay đổi so với rev 1 (giữ nguyên từ rev 2, tham khảo)

| # | Rev 1 | Rev 2 |
|---|---|---|
| Kiến trúc | Giả định Phương án A (`escalated` là 1 giá trị `status`) | **CHỐT Phương án B** — escalation độc lập hoàn toàn với lifecycle status |
| Action mới | `deescalate()` | Đổi tên **`resolveEscalation()`** — đúng ngữ nghĩa "đóng 1 escalation cycle", không gắn với lifecycle |
| Bảng history | Gọi là "append-only" nhưng thực chất update field resolution trên cùng row | Gọi đúng tên: **history-preserving 2-phase write** (insert khi escalate, update duy nhất 1 lần khi resolve) — không dùng từ "append-only" cho model có UPDATE |
| Migration | Chưa có | Thêm mục 6: preflight + decision table cho dữ liệu legacy |
| Rule answer/close/cancel | Chưa rõ | Thêm mục 7: answer không tự resolve escalation; close bị chặn khi còn active escalation; cancel-khi-escalated chỉ PM/admin |
| Notification | "Ngoài phạm vi hoàn toàn" | Thu hẹp lại: có notification tối thiểu (in-app, after-commit), email/SLA vẫn ngoài phạm vi |
| Authorization | Chưa có | Thêm mục 8, dùng permission string thật đã grep được (không suy đoán) |
| Concurrency | Chưa có | Thêm mục 9 |
| Test matrix | Chưa có | Thêm mục 11 |

## Bằng chứng bổ sung thu thập cho rev 2 (grounding, không suy đoán)

- **RBAC permission thật** (`routes/api_zena.php:279-287`, `routes/web.php:842-847`, `database/seeders/ZenaPermissionsSeeder.php:14-21`): `rfi.view`, `rfi.create`, `rfi.edit`, `rfi.delete`, `rfi.assign`, `rfi.respond`, `rfi.close`, `rfi.escalate`. Chỉ role admin (`System Admin, Admin, super_admin, system_admin` qua `ZenaAdminRolePermissionSeeder.php`) được seed `rfi.escalate` mặc định — **không role PM/SiteEngineer/QC nào có quyền escalate qua seed hiện tại**. Có 1 seeder khác (`ZenaRbacSeeder.php`, permission code khác hẳn: `rfi.read/rfi.answer`) nhưng **không nằm trong `DatabaseSeeder::run()`** — dead/orphan, không dùng.
- **Field lưu câu trả lời**: `Rfi` model có CẢ HAI cặp field song song trong `$fillable`: `answer`/`answered_by`/`answered_at` VÀ `response`/`responded_by`/`responded_at` (migration `2025_09_20_133629_create_rfis_table.php:33-38`). Không có bảng/model riêng cho câu trả lời. Đây là 1 sự trùng lặp có sẵn trong schema — spec này không sửa, chỉ ghi nhận để implementation biết field nào đang thực sự được `respond()` ghi.
- **`EventRecord`**: tồn tại (`app/Models/EventRecord.php`), cấu trúc outbox-style (`aggregate_type`/`aggregate_id`/`event_key`/`payload`, không phải Eloquent polymorphic relation). **Chưa từng được dùng cho RFI** — `EventRecord::create` chỉ xuất hiện trong `TaskController.php`. Kết luận quan trọng: **không có nguồn evidence lịch sử nào (event log) cho RFI** — mục 6 (migration) phải thiết kế dựa trên giả định này, không thể "tra cứu EventRecord" như kỳ vọng ban đầu vì dữ liệu đó không tồn tại.
- **DB engine**: MySQL (`config/database.php:17`, `.env.example:11`) — ảnh hưởng cách enforce "tối đa 1 active escalation" (mục 9), vì MySQL không có partial/conditional unique index kiểu Postgres.
- **`rfi_escalations` chưa tồn tại** — xác nhận `find database/migrations -iname "*escalat*"` = 0 kết quả, `grep -rl "RfiEscalation"` = 0 kết quả. Bảng hoàn toàn mới.
- **`lockForUpdate()` đã có tiền lệ** trong chính `RfiController.php` và `SubmittalLifecycleService.php` — implementation nên đọc lại pattern đã dùng ở đó để nhất quán, không tạo pattern lock mới.
- **Số liệu RFI**: DB dev hiện có `Rfi::count() = 0` — không đại diện production, không dùng làm căn cứ quyết định.

## 1. Lifecycle status — CHỈ chứa trạng thái nghiệp vụ RFI (escalation tách riêng)

```
enum RfiLifecycleStatus {
    OPEN         // mới tạo
    IN_PROGRESS  // đã gán người phụ trách
    ANSWERED     // đã có câu trả lời
    CLOSED       // terminal — đã đóng
    CANCELLED    // terminal — huỷ (MỚI, xem mục 3.3)
}
```

**Không còn `escalated` trong enum này.** Escalate/resolveEscalation **không đổi `status`** — một RFI có thể đồng thời ở `IN_PROGRESS` VÀ có 1 escalation đang active. `pending` (giá trị cũ, chưa từng được action nào set — xác nhận lại ở rev 2) bị loại khỏi enum mới; dữ liệu legacy có `pending` được xử lý ở mục 6 (bảng quyết định migration) như 1 trường hợp bất thường, không phải trạng thái hợp lệ tiếp diễn.

## 2. Escalation — bảng `rfi_escalations` (history-preserving, KHÔNG phải append-only thuần)

Gọi đúng tên: đây là mô hình **2-phase write** — INSERT khi escalate (field gốc bất biến sau đó), UPDATE **đúng 1 lần** khi resolve (field resolution). Không gọi "append-only" vì có UPDATE thật trên cùng row.

```
rfi_escalations
  id                 ulid, PK
  rfi_id             FK → rfis.id
  tenant_id          FK → tenants.id (denormalized, cho tenant-scope query nhanh + defense-in-depth)
  escalated_to       FK → users.id            — BẤT BIẾN sau khi tạo
  escalated_by       FK → users.id            — BẤT BIẾN
  escalated_at       timestamp                — BẤT BIẾN
  escalation_reason  text                     — BẤT BIẾN
  resolved_at        timestamp, nullable      — set ĐÚNG 1 LẦN khi resolve
  resolved_by        FK → users.id, nullable  — set ĐÚNG 1 LẦN
  resolution         text, nullable           — set ĐÚNG 1 LẦN
  resolution_type    enum(manually_resolved, rfi_cancelled), nullable — set ĐÚNG 1 LẦN
  created_at, updated_at
```

**Ràng buộc bắt buộc:**
1. Field gốc (`escalated_to/by/at/reason`) **không bao giờ được UPDATE** sau khi INSERT — enforce ở tầng application (không có code path nào gọi `update()` trên các field này), không cần DB trigger.
2. Field resolution chỉ được ghi **đúng 1 lần** — enforce bằng guard ứng dụng: `resolveEscalation()` phải kiểm tra `resolved_at IS NULL` trước khi update, trong cùng transaction có lock (mục 9). Gọi lần 2 trên record đã resolve → lỗi 409/422, không ghi đè.
3. **Tối đa 1 active escalation/RFI tại 1 thời điểm** — "active" = `resolved_at IS NULL`. Enforce bằng lock+check trong transaction (mục 9), KHÔNG dựa vào unique constraint DB do giới hạn MySQL với conditional unique (có thể cân nhắc unique index trên generated column `IF(resolved_at IS NULL, rfi_id, NULL)` ở MySQL 8+ như phòng thủ bổ sung, nhưng không phải cơ chế enforce chính — implementation cần xác nhận version MySQL thật trước khi dùng generated column).
4. **Cho phép nhiều cycle theo thời gian** — 1 RFI có thể có N row trong `rfi_escalations` qua vòng đời, miễn tại mọi thời điểm chỉ 1 row có `resolved_at IS NULL`.

### 2.1 `rfis.current_escalation_id` — con trỏ chính thức tới escalation active

Thêm cột mới `rfis.current_escalation_id` (ULID, nullable, FK → `rfi_escalations.id`). Đây là cách **chính thức** để biết 1 RFI có đang active escalation hay không và trỏ tới đúng record nào — `NULL` nghĩa là không có active escalation, có giá trị nghĩa là đang có, luôn trỏ đúng record có `resolved_at IS NULL`.

Cập nhật `current_escalation_id` trong CÙNG transaction với việc ghi `rfi_escalations`:
- `escalate()`: set `current_escalation_id` = id record vừa insert.
- `resolveEscalation()`/`cancel()`-atomic: set `current_escalation_id` = `null` sau khi resolve.

### 2.2 4 field cũ (`escalated_to/at/by/reason` trên `rfis`) — compatibility mirror, KHÔNG phải nguồn sự thật

**`rfi_escalations` là nguồn sự thật duy nhất.** 4 field cũ trên `rfis` chỉ là **compatibility mirror tạm thời trong giai đoạn chuyển tiếp** — tồn tại để không phá code/query cũ đang đọc trực tiếp 4 field này (nếu có), KHÔNG phải để reader mới dựa vào. Mirror được ghi đồng bộ (cùng transaction) mỗi khi `rfi_escalations`/`current_escalation_id` thay đổi:
- Khi `current_escalation_id` được set: mirror 4 field từ record active tương ứng.
- Khi `current_escalation_id` về `null`: 4 field mirror cũng về `null`.

**Trước khi bất kỳ ai được phép deprecate/xoá 4 field mirror này (KHÔNG nằm trong phạm vi implementation plan của spec này — chỉ là điều kiện tiên quyết ghi nhận trước)**: phải có 1 inventory đầy đủ liệt kê MỌI reader hiện đang đọc trực tiếp `rfis.escalated_to/at/by/reason` (Blade view, API response field, report/export nào) — việc này CHƯA làm trong spec này, chỉ ghi nhận là điều kiện bắt buộc cho 1 ticket dọn dẹp SAU NÀY. **Không reader MỚI nào (viết từ implementation plan của spec này trở đi) được phép đọc 4 field mirror làm nguồn sự thật** — mọi logic mới (guard, authorization, hiển thị escalation hiện tại) phải đọc qua `current_escalation_id`/`rfi_escalations`, mirror chỉ để tương thích ngược cho code CŨ chưa migrate.

## 3. State diagram — HAI TRỤC ĐỘC LẬP

### Trục 1 — Lifecycle (không bị escalation chi phối)

```
   store()                assign()              respond()             close()
  ────────► OPEN ─────────────────► IN_PROGRESS ─────────► ANSWERED ─────────► CLOSED
                                        │                      │              (terminal)
                    cancel() ◄──────────┴──────────────────────┘
                       │
                       ▼
                  CANCELLED
                 (terminal)
```

- `respond()`: `OPEN` hoặc `IN_PROGRESS` → `ANSWERED`. Không phụ thuộc escalation có active hay không.
- `close()`: `ANSWERED` → `CLOSED`. **Bị chặn nếu đang có active escalation** (mục 7).
- `cancel()`: `OPEN`/`IN_PROGRESS`/`ANSWERED` → `CANCELLED`. Luôn bắt buộc lý do. Nếu có active escalation, quyền hạn thắt chặt hơn (mục 7).
- `assign()` (reassign): không đổi lifecycle status, chỉ đổi `assigned_to` — hợp lệ ở mọi trạng thái chưa terminal (`OPEN`/`IN_PROGRESS`/`ANSWERED`).

### Trục 2 — Escalation cycle (độc lập, có thể chồng lên bất kỳ điểm nào của Trục 1 trừ terminal)

```
  escalate()                    resolveEscalation()
 ───────────► [ACTIVE escalation] ─────────────────► [RESOLVED escalation]
  (chỉ khi chưa có active,                            (resolved_at/by/resolution
   lifecycle chưa terminal)                            ghi đúng 1 lần)

  ...RFI có thể escalate() lại lần nữa sau khi resolve → tạo cycle mới (row mới)
```

### Ma trận kết hợp — trạng thái hợp lệ (Lifecycle × Escalation)

| Lifecycle \ Escalation | Không có escalation | Có escalation ACTIVE |
|---|---|---|
| `OPEN` | Hợp lệ (mặc định) | Hợp lệ — vừa tạo vừa bị escalate ngay |
| `IN_PROGRESS` | Hợp lệ | Hợp lệ — trường hợp phổ biến nhất |
| `ANSWERED` | Hợp lệ | Hợp lệ — đã trả lời nhưng escalation cũ chưa resolve (VD trả lời xong nhưng người escalate chưa xác nhận hài lòng) |
| `CLOSED` | Hợp lệ (điều kiện bắt buộc để vào được trạng thái này — mục 7) | **KHÔNG BAO GIỜ xảy ra** — `close()` bị chặn cứng nếu còn active escalation |
| `CANCELLED` | Hợp lệ | Hợp lệ tức thời trong transaction cancel (nhưng ngay sau đó escalation phải được resolve atomic cùng lúc — mục 7, nên trạng thái ổn định cuối cùng luôn là `CANCELLED` + escalation đã RESOLVED, không có ô "CANCELLED + ACTIVE" tồn tại lâu dài) |

## 4. Bảng transition đầy đủ

| Từ (lifecycle) | Đến | Action | Điều kiện |
|---|---|---|---|
| — | `OPEN` | `store()` | Luôn cho phép |
| `OPEN` | `IN_PROGRESS` | `assign()` | `assigned_to` khác null |
| `OPEN`/`IN_PROGRESS`/`ANSWERED` | (không đổi) | `assign()` lại (reassign) | Không giới hạn thêm ngoài permission |
| `OPEN`/`IN_PROGRESS` | `ANSWERED` | `respond()` | Không phụ thuộc escalation |
| `ANSWERED` | `CLOSED` | `close()` | **Không có active escalation** (mới, mục 7) |
| `OPEN`/`IN_PROGRESS`/`ANSWERED` | `CANCELLED` | `cancel()` | Lý do bắt buộc; nếu có active escalation → chỉ PM/admin + resolve escalation atomic (mục 7) |

| Escalation state | Action | Điều kiện |
|---|---|---|
| Không có active | `escalate()` | Lifecycle chưa terminal (`CLOSED`/`CANCELLED` không được escalate) |
| Active | `resolveEscalation()` | `resolved_at IS NULL` hiện tại (guard chống double-resolve) |

## 5. Owner & Reassignment

Không đổi so với rev 1: `assign()` dùng chung cho gán lần đầu/reassign, hợp lệ ở mọi lifecycle chưa terminal, kể cả khi đang có active escalation.

## 6. Legacy deployment gate — rollout additive nhiều giai đoạn (thay thế hoàn toàn cách tiếp cận "1 lần migrate" của rev 2)

**Nguyên tắc**: KHÔNG cutover 1 bước. Schema mới được tạo và populate song song với schema cũ còn nguyên vẹn (`status='escalated'` vẫn là giá trị hợp lệ trong DB suốt giai đoạn này), cho tới khi operator xác nhận THỦ CÔNG từng record legacy còn mơ hồ. Chỉ sau khi **0 record chưa xác nhận** mới được cutover (đổi enum `status`, loại `escalated` khỏi giá trị hợp lệ ở tầng ứng dụng).

### 6.1 Giai đoạn A — Additive (tạo schema mới, KHÔNG đổi gì ở schema cũ)

1. Migration tạo bảng `rfi_escalations` + cột `rfis.current_escalation_id` (nullable).
2. **KHÔNG sửa cột `status` hiện có, KHÔNG loại `escalated` khỏi validation/enum ở bước này.** Code ứng dụng (action mới `escalate()`/`resolveEscalation()`) triển khai và CHẠY SONG SONG với code cũ trong giai đoạn này — RFI mới escalate từ nay về sau ghi vào `rfi_escalations` + `current_escalation_id`, không đổi `status` (đúng thiết kế mục 1-2), nhưng record legacy có sẵn `status='escalated'` vẫn còn nguyên trong DB, chưa bị đụng.

### 6.2 Giai đoạn B — Preflight + Manual-review report (chạy trên staging/production thật, KHÔNG dùng số liệu DB dev vì hiện đang rỗng)

1. Đếm `Rfi::where('status','escalated')->count()` và tổng `Rfi::count()`.
2. Với từng row `status='escalated'`: kiểm tra `assigned_to` (null hay không) — tín hiệu để đề xuất lifecycle mặc định.
3. Với từng row `status != 'escalated'`: kiểm tra 4 field snapshot cũ (`escalated_to/by/at/reason`) có populated không — dấu hiệu "đã từng escalate trong quá khứ, sau đó status bị ghi đè bởi `respond()`/`close()`/`update()`".
4. Đếm `Rfi::where('status','pending')->count()` — không action nào từng set giá trị này, bất kỳ row nào có `status='pending'` là bất thường.
5. Xác nhận `EventRecord::where('aggregate_type','rfi')->count()` trên production = 0 (đã xác nhận qua code, preflight tự chạy lại để chắc chắn 100%).
6. **Xuất báo cáo manual-review** (CSV) liệt kê MỌI row rơi vào 1 trong 4 nhóm trên (escalated có/không assigned_to, snapshot cũ populated, pending bất thường) — đây KHÔNG còn là "report tham khảo hậu-migration" như rev 2, mà là **input đầu vào cho bước C**.

### 6.3 Giai đoạn C — Operator xác nhận từng record (STOP CONDITION chính của gate này)

Với MỖI record trong manual-review report, operator (hoặc PM được uỷ quyền) phải xác nhận thủ công qua 1 trong 2 cách:
- (a) Qua UI/tool riêng (phạm vi implementation plan) cho phép xem record + đề xuất mặc định của hệ thống (theo bảng quyết định 6.4) rồi bấm xác nhận/sửa lifecycle status + trạng thái escalation (resolved/unresolved) cho record đó, HOẶC
- (b) Qua thao tác trực tiếp trên `rfi_escalations`/`current_escalation_id` đã được tạo ở giai đoạn A cho record đó (nếu tool UI chưa kịp làm, cho phép xác nhận bằng thao tác DB có kiểm soát + ghi log ai xác nhận).

**Đề xuất mặc định của hệ thống (không tự áp dụng nếu chưa xác nhận) — bảng quyết định giữ nguyên tinh thần rev 2:**

| Điều kiện dữ liệu legacy | Lifecycle đề xuất | `rfi_escalations` đề xuất tạo |
|---|---|---|
| `status='escalated'`, `assigned_to` khác null | `IN_PROGRESS` | 1 row **UNRESOLVED** (copy từ 4 field snapshot cũ) |
| `status='escalated'`, `assigned_to` là null | `OPEN` | 1 row **UNRESOLVED** (copy từ snapshot) |
| `status != 'escalated'`, nhưng 4 field snapshot cũ populated | Giữ nguyên giá trị `status` hiện tại (map theo 6.5) | 1 row **RESOLVED** suy luận (`resolution_type=manually_resolved`, `resolved_at` ước lượng = `updated_at`, ghi rõ "ước lượng, không xác nhận bằng event log") |
| `status = 'pending'` | `OPEN` (đề xuất) | Không tạo, đánh dấu anomaly |

**Đây chỉ là ĐỀ XUẤT hiển thị cho operator xem, KHÔNG được tự động ghi vào DB chính thức trước khi operator xác nhận.** Không có record nào được "auto-map về `open`" mà không qua bước xác nhận này, kể cả trường hợp `pending` (đề xuất `open` cũng cần operator bấm xác nhận, không tự áp).

**STOP CONDITION**: Giai đoạn D (cutover) **KHÔNG được phép bắt đầu** khi còn ≥1 record trong manual-review report chưa có xác nhận operator. Implementation plan phải có 1 lệnh/report kiểm tra "còn bao nhiêu record chưa xác nhận" trả về số 0 mới được tiếp tục — đây là gate cứng, không phải khuyến nghị.

### 6.4 Giai đoạn D — Cutover (chỉ chạy khi giai đoạn C đạt 0 record chưa xác nhận)

1. Đổi validation tầng ứng dụng: loại `escalated`/`pending` khỏi danh sách giá trị `status` hợp lệ (không cần đổi kiểu cột DB ngay, chỉ cần tầng ứng dụng ngừng chấp nhận 2 giá trị này).
2. Với mọi record đã xác nhận ở giai đoạn C: ghi `status` = lifecycle đã xác nhận, ghi/giữ `rfi_escalations`/`current_escalation_id` theo xác nhận.
3. Sau cutover, `status='escalated'`/`'pending'` không còn xuất hiện trong dữ liệu — nhưng **KHÔNG drop giá trị này khỏi bất kỳ enum DB level nào trong đợt đầu** (nếu cột dùng DB enum, giữ nguyên định nghĩa cột, chỉ chặn ở application layer) — việc dọn schema DB level để sau, ngoài phạm vi đợt đầu.

### 6.5 Mapping `status` cũ → `RfiLifecycleStatus` mới (cho record không có snapshot escalation, áp dụng ngay ở giai đoạn D)

| `status` cũ | `RfiLifecycleStatus` mới |
|---|---|
| `open` | `OPEN` |
| `in_progress` | `IN_PROGRESS` |
| `answered` | `ANSWERED` |
| `closed` | `CLOSED` |
| `escalated` | Theo xác nhận operator (giai đoạn C) |
| `pending` | Theo xác nhận operator (giai đoạn C, mặc định đề xuất `OPEN`) |

## 7. Rule cho answer / close / cancel

- **`respond()` (answer) KHÔNG tự động resolve escalation.** Trả lời câu hỏi và đóng escalation cycle là 2 hành động độc lập hoàn toàn — người trả lời không mặc nhiên "đã xử lý xong việc khẩn". Muốn đóng escalation phải gọi `resolveEscalation()` riêng.
- **`close()` bị chặn nếu còn active escalation.** Guard mới: `if (RfiEscalation::where('rfi_id', $rfi->id)->whereNull('resolved_at')->exists()) { return 409/422 "Không thể đóng RFI khi còn escalation đang active — resolve escalation trước." }`.
- **`cancel()` khi có active escalation**: chỉ actor có quyền PM/admin (xem mục 8) mới được thực hiện; bắt buộc field `reason`; **phải resolve escalation cycle trong CÙNG transaction** (`resolution_type = 'rfi_cancelled'`, `resolution` = tham chiếu tới lý do cancel, `resolved_by` = actor, `resolved_at` = now) — không được để lại escalation active sau khi RFI đã cancelled.
- **`cancel()` khi KHÔNG có active escalation**: quyền hạn lỏng hơn (assignee hoặc PM/admin, xem mục 8), vẫn bắt buộc `reason`.

## 8. Authorization matrix

Dùng đúng permission string thật đã xác nhận (`rfi.view/create/edit/delete/assign/respond/close/escalate`) — không suy đoán tên mới trừ khi ghi rõ là permission MỚI cần seed.

| Action | Permission | Điều kiện bổ sung |
|---|---|---|
| `assign` (kể cả reassign) | `rfi.assign` | Target `assigned_to` phải cùng tenant + có membership trong project của RFI (cần xác nhận middleware hiện tại đã enforce project-membership hay chỉ tenant-level — nếu chưa, đây là gap cần vá cùng lúc, ghi ở mục 12) |
| `respond` | `rfi.respond` | Lifecycle phải `OPEN`/`IN_PROGRESS` (không đổi ai được respond so với hiện tại — permission-based, không giới hạn theo `assigned_to`, giữ nguyên hành vi cũ) |
| `close` | `rfi.close` | Lifecycle `ANSWERED`; không còn active escalation |
| `cancel` — không có active escalation | **`rfi.cancel` (permission MỚI, chưa tồn tại, cần thêm vào `ZenaPermissionsSeeder` và gán role)** | Lifecycle chưa terminal; `reason` bắt buộc |
| `cancel` — có active escalation | `rfi.cancel` **VÀ** actor có vai trò PM/admin cho project đó (kiểm tra qua `UserRoleProject` với `Role::name = 'project_manager'`, HOẶC actor có `rfi.escalate` — vì hiện tại chỉ role admin có `rfi.escalate`, dùng nó làm proxy "đủ thẩm quyền quản lý escalation" nếu business không muốn tạo permission riêng) | Lý do bắt buộc; atomic resolve escalation |
| `escalate` | `rfi.escalate` **VÀ** (actor là PM của project chứa RFI đó, qua `UserRoleProject` + `Role::name='project_manager'`, **HOẶC** actor có role admin) | Lifecycle chưa terminal; chưa có active escalation; `escalated_to` cùng tenant + project membership; actor và target đều đang **active** (chưa deactivate) |
| `resolveEscalation` | Actor là 1 trong 3: (a) chính người được escalate tới (`escalation.escalated_to == actor.id`), (b) PM của project đó, (c) admin | Phải đang có active escalation; actor đang active |

**Đã duyệt (rev 3, không còn là "gap" chờ quyết định)**: `rfi.escalate` phải được seed cho role PM (`project_manager`), không chỉ admin như hiện trạng mặc định — đây là điều kiện triển khai bắt buộc của implementation plan, không phải tuỳ chọn vận hành để sau. `resolveEscalation` KHÔNG giới hạn chỉ người tạo escalation ban đầu — mở rộng cho đúng 3 nhóm actor liệt kê ở trên, vì thực tế người được escalate tới thường là người xử lý và tự đóng escalation, không cần đợi người khác.

**Kiểm tra "đang active" (mới, rev 3)**: `escalate()`/`resolveEscalation()`/`cancel()` phải xác nhận cả actor lẫn target (`escalated_to`) đều là user chưa bị deactivate — cần xác định chính xác field/trạng thái "active" dùng ở đâu trong `User` model khi viết implementation plan (chưa grep trong spec này, cần làm ở bước đầu implementation).

## 9. Transaction & Concurrency

- Mọi action ghi (`escalate`, `resolveEscalation`, `cancel`, và `close` khi cần check escalation) chạy trong `DB::transaction()`.
- `escalate()`: trong transaction, `Rfi::where('id',$id)->lockForUpdate()->first()` lock row RFI; `RfiEscalation::where('rfi_id',$id)->whereNull('resolved_at')->lockForUpdate()->first()` kiểm tra active escalation. Nếu đã có → **rollback, trả 409 Conflict**. Nếu không → insert row mới + update 4 field cache trên `rfis`.
- 2 request `escalate()` đồng thời: request thứ nhất lock trước, insert, commit. Request thứ hai chờ lock, sau khi acquire thấy đã có active escalation (do request 1 vừa tạo) → trả 409.
- `resolveEscalation()`: **lock CẢ row `rfis` (theo yêu cầu rev 3: escalate() và resolveEscalation() đều phải lock RFI, không chỉ lock escalation row) VÀ row `rfi_escalations` đang active** bằng `lockForUpdate()` trong cùng transaction; kiểm tra `resolved_at IS NULL` ngay trước khi update (chống race 2 resolve cùng lúc — request thứ 2 sau khi acquire lock thấy `resolved_at` đã có giá trị → 409); sau khi resolve, set `rfis.current_escalation_id = null` + đồng bộ mirror (mục 2.2) trong CÙNG transaction.
- `cancel()` khi có active escalation: lock CẢ row `rfis` VÀ row `rfi_escalations` active trong cùng transaction, update cả 2 (status RFI → cancelled, resolve escalation) atomic — hoặc rollback toàn bộ nếu bất kỳ bước nào lỗi.
- Không dùng optimistic locking (version column) cho phần này — dùng pessimistic lock (`lockForUpdate()`) nhất quán với pattern đã có sẵn trong `RfiController`/`SubmittalLifecycleService`.

## 10. Notification tối thiểu

**KHÔNG tái sử dụng `RfiEventListener`/`App\Events\RfiCreated/RfiUpdated/RfiResponded/RfiClosed` dưới bất kỳ hình thức nào** — kể cả "sửa lại cho chạy". Các class này chết vì lý do kiến trúc (Event class không tồn tại, không có mapping trong `EventServiceProvider`), không phải bug nhỏ — implementation phải viết class Notification MỚI hoàn toàn (VD `App\Notifications\RfiEscalatedNotification implements ShouldQueue`), đăng ký/dispatch trực tiếp từ `RfiEscalationService` (không qua Event/Listener indirection nếu không cần thiết — dispatch thẳng notification, đơn giản hơn và tránh lặp lại kiểu "wiring qua Event nhưng quên đăng ký" đã xảy ra với code cũ).

- **Phạm vi**: chỉ in-app notification (channel `database`, không email) cho `escalated_to` khi 1 escalation mới được tạo. KHÔNG có auto-escalation theo SLA (thuộc Overdue Engine, ticket riêng — xem mục 13).
- **Timing**: dispatch SAU KHI transaction commit thành công — đặt lời gọi `Notification::send()`/`->notify()` NGOÀI closure `DB::transaction()` (sau khi closure return thành công), không dispatch nếu transaction rollback (VD do conflict 409).
- **Lỗi notification**: nếu gửi thất bại (exception), `Log::error` nhưng KHÔNG rollback hành động escalate đã commit.
- **Bắt buộc có test** (không phải tuỳ chọn) xác nhận: (a) sau khi `escalate()` thành công và commit, có đúng 1 record notification (VD bảng `notifications` chuẩn Laravel hoặc bảng tự định nghĩa) được tạo cho user `escalated_to`; (b) nếu request `escalate()` thất bại/rollback (VD do 409 concurrency), KHÔNG có notification nào được tạo — test phải thực sự query bảng notification sau khi gọi action, không mock giả định.

## 11. Test matrix

| # | Case | Kỳ vọng |
|---|---|---|
| 1 | Lifecycle happy path | `store→assign→respond→close` thành công tuần tự, không có escalation liên quan |
| 2 | Respond 2 lần | Lần 2 bị chặn (lifecycle không còn `OPEN`/`IN_PROGRESS`) |
| 3 | Close khi chưa `ANSWERED` | 400/422 (giữ nguyên guard cũ) |
| 4 | Escalate trên `OPEN` | Thành công, lifecycle KHÔNG đổi, escalation record active được tạo |
| 5 | Escalate trên `IN_PROGRESS` | Thành công, tương tự #4 |
| 6 | Escalate trên `CLOSED`/`CANCELLED` | Bị chặn (lifecycle terminal) |
| 7 | Escalate lần 2 khi đã có active | 409 Conflict |
| 8 | `resolveEscalation()` hợp lệ | `resolved_at/by/resolution` được set đúng 1 lần, field gốc không đổi |
| 9 | `resolveEscalation()` lần 2 trên cùng record | 409 (không double-resolve) |
| 10 | Nhiều cycle theo thời gian | escalate→resolve→escalate lại→resolve lại → 2 row riêng biệt trong `rfi_escalations`, row đầu không bị đổi sau khi row 2 được tạo |
| 11 | `respond()` không tự resolve escalation | Sau khi respond thành công, escalation active vẫn còn active |
| 12 | `close()` bị chặn khi còn active escalation | 409/422, kèm message rõ lý do |
| 13 | `cancel()` không có active escalation | Thành công với permission thường (`rfi.cancel`), lifecycle → `CANCELLED` |
| 14 | `cancel()` có active escalation, actor KHÔNG phải PM/admin | 403 |
| 15 | `cancel()` có active escalation, actor LÀ PM/admin | Thành công, escalation được resolve atomic (`resolution_type=rfi_cancelled`) trong cùng transaction |
| 16 | Concurrency: 2 request `escalate()` đồng thời | 1 thành công (201), 1 nhận 409 |
| 17 | Concurrency: 2 request `resolveEscalation()` đồng thời trên cùng record | 1 thành công, 1 nhận 409 |
| 18 | Escalate với `escalated_to` khác tenant | 403/422 — chặn cross-tenant target |
| 19 | Escalate với `escalated_to` cùng tenant nhưng không thuộc project của RFI | 403/422 — chặn thiếu project membership (nếu middleware hiện tại chưa enforce, cần vá cùng lúc — xem mục 8) |
| 20 | Notification thực sự được tạo sau commit | Gọi `escalate()` thành công qua HTTP thật → query bảng notification, xác nhận đúng 1 record cho `escalated_to` (KHÔNG mock) |
| 20b | Notification KHÔNG tạo khi rollback | Force 409 (2 request đồng thời) → request thua cuộc rollback → query bảng notification, xác nhận KHÔNG có record nào cho request đó |
| 21 | Preflight report (giai đoạn B) | Chạy trên fixture có row `status='escalated'` với/không `assigned_to`, row có snapshot cũ nhưng status khác, row `pending` → xác nhận report liệt kê đủ, đúng nhóm |
| 22 | Stop condition chặn cutover | Với ≥1 record chưa xác nhận trong report → verify lệnh/check cutover trả về "KHÔNG được cutover", không cho chạy giai đoạn D |
| 23 | Cutover chỉ chạy khi 0 record chưa xác nhận | Xác nhận hết toàn bộ record trong fixture → verify cutover chạy được, `status` không còn giá trị `escalated`/`pending` nào sau đó |
| 24 | `current_escalation_id` đồng bộ đúng | Sau `escalate()`: `rfis.current_escalation_id` trỏ đúng record vừa tạo. Sau `resolveEscalation()`: về `null` |
| 25 | Mirror 4 field cũ đồng bộ với `current_escalation_id` | Escalate → 4 field mirror khớp record active. Resolve → 4 field mirror về `null` |
| 26 | `escalate()` bởi PM đúng project | PM có `UserRoleProject`/`project_manager` cho project của RFI → thành công (dù không phải admin) |
| 27 | `escalate()` bởi user không phải PM/admin | 403, dù có permission `rfi.escalate` gán nhầm (kiểm tra đủ điều kiện project-role, không chỉ permission string) |
| 28 | `resolveEscalation()` bởi chính `escalated_to` | Thành công (không cần là PM/admin) |
| 29 | `resolveEscalation()` bởi PM khác `escalated_to` | Thành công |
| 30 | `resolveEscalation()` bởi user không liên quan (không phải target/PM/admin) | 403 |
| 31 | Actor bị deactivate | Mọi action (`escalate`/`resolveEscalation`/`cancel`) bị chặn, dù permission/role đúng |
| 32 | Target (`escalated_to`) bị deactivate | `escalate()` bị chặn khi chọn target đã deactivate |

## 12. Gap vận hành còn lại (đã thu hẹp sau rev 3)

- Cần xác nhận middleware RBAC hiện tại có enforce project-membership cho action RFI hay chỉ tenant-level — nếu chỉ tenant-level, đây là lỗ hổng có sẵn cần vá cùng lúc khi implement guard escalate/assign.
- `rfi.cancel` là permission hoàn toàn mới — cần quyết định gán cho role nào ngoài PM/admin (assignee mặc định có được `cancel()` khi KHÔNG có active escalation không? Đề xuất: có, vì đó là trường hợp "lỏng hơn" theo mục 7 — cần xác nhận khi viết implementation plan).
- ~~`rfi.escalate` hiện chỉ admin có~~ — **đã chốt ở rev 3**: seed thêm cho role PM là yêu cầu bắt buộc của implementation plan, không còn là gap mở.
- Field/cột xác định user "đang active" (dùng cho check actor/target active ở mục 8) chưa được xác nhận cụ thể trong spec này — cần grep `User` model khi viết implementation plan.

## 13. Ngoài phạm vi (Non-goals)

- SLA/deadline enforcement, auto-escalation theo thời gian quá hạn — thuộc "Overdue & Escalation Engine" (P1-A, ticket riêng). **Khi engine đó được thiết kế, nó PHẢI gọi qua lifecycle/escalation service của spec này** (VD gọi `RfiEscalationService::escalate()`) thay vì tự ý `update()` trực tiếp lên `rfis`/`rfi_escalations` — để không phá vỡ transaction/concurrency/notification rule đã thiết kế ở đây.
- Email notification.
- Route web cho `escalate`/`resolveEscalation`/`cancel` — quyết định UI để lại cho implementation plan.
- "RFI liên quan" (linked follow-up sau khi closed/cancelled) — không thiết kế reopen/amend trong spec này; giới hạn "closed/cancelled không respond lại được, muốn hỏi tiếp thì tạo RFI mới" vẫn giữ như rev 1.
- Sửa sự trùng lặp field `answer`/`response` song song trong schema `rfis` — ghi nhận là oddity có sẵn, không phải phạm vi spec này.

## 5 Quyết định nghiệp vụ ban đầu — kết quả sau rev 2

| # | Quyết định | Phương án đã chọn |
|---|---|---|
| 1 | Escalation là giá trị `status` hay field độc lập | **Field/bảng độc lập (`rfi_escalations`), tách hoàn toàn khỏi lifecycle** — CHỐT bởi operator |
| 2 | Có cần action thoát escalation không qua respond | **Có — `resolveEscalation()`** (đổi tên từ `deescalate()`, đúng ngữ nghĩa "đóng escalation cycle" vì không còn gắn với lifecycle `in_progress`) |
| 3 | History: field đơn hay bảng riêng | **Bảng riêng `rfi_escalations`, mô hình history-preserving 2-phase write** (không gọi append-only vì có update resolution 1 lần) |
| 4 | Closed có respond lại được không | **Không** — giữ nguyên như rev 1, `closed`/`cancelled` (mới) đều terminal tuyệt đối, không respond lại |
| 5 | `pending` status | **Không giữ trong enum mới** — dữ liệu legacy có `pending` được coi là bất thường, map về `OPEN` + gắn anomaly flag trong manual-review report (mục 6.2) |

## Self-review (rev 3)

- **Ràng buộc #1 (legacy deployment gate)**: đã thiết kế đủ 4 giai đoạn (Additive → Preflight/Report → Operator xác nhận từng record → Cutover) với stop condition tường minh (mục 6.3: "KHÔNG được phép bắt đầu giai đoạn D khi còn ≥1 record chưa xác nhận") — không còn là "1 lần migrate + report tham khảo" như rev 2. Không record nào tự động map về `open`, kể cả `pending`.
- **Ràng buộc #2 (source of truth + compatibility)**: `current_escalation_id` là con trỏ chính thức, `rfi_escalations` là nguồn sự thật, 4 field cũ gọi đúng tên "compatibility mirror tạm thời" (không phải "cache" mơ hồ như rev 2) + yêu cầu tường minh "không reader mới nào được dùng mirror làm nguồn sự thật" + ghi nhận việc inventory reader là điều kiện tiên quyết cho ticket dọn dẹp sau (không tự ý drop field ở đây).
- **Ràng buộc #3 (concurrency)**: `resolveEscalation()` giờ lock cả `rfis` lẫn `rfi_escalations` (rev 2 chỉ lock escalation row) — khớp yêu cầu "escalate() và resolveEscalation() phải lock RFI". Resolution field vẫn set đúng 1 lần, notification vẫn after-commit — không đổi so với rev 2, chỉ củng cố.
- **Ràng buộc #4 (quyền vận hành)**: authorization matrix (mục 8) đã cập nhật đúng theo đề xuất được duyệt — PM đúng project được escalate (không chỉ admin), 3 nhóm actor được resolveEscalation (target/PM/admin, không giới hạn chỉ người tạo), actor/target phải active. Không còn ghi là "gap chờ quyết định" cho phần `rfi.escalate` — đã là yêu cầu thiết kế chính thức.
- **Điểm #5 (không tái dùng code chết)**: mục 10 nhấn mạnh tường minh cấm tái sử dụng `RfiEventListener`/Event cũ dưới mọi hình thức, yêu cầu class Notification mới hoàn toàn, và bắt buộc test query bảng notification thật (không mock) để xác nhận record được tạo sau commit.
- **Test matrix mở rộng lên 32 case** (thêm preflight report, stop condition, cutover, current_escalation_id sync, mirror sync, PM/target authorization, actor/target active check) — vẫn giữ nguyên 20 case gốc từ rev 2 không đổi nội dung, chỉ case #20 tách thành 2 (20/20b) để phản ánh đúng yêu cầu "query bảng notification thật".
- **5 quyết định nghiệp vụ ban đầu** (bảng trước Self-review) giữ nguyên như rev 2 — 4 ràng buộc rev 3 là bổ sung nằm NGOÀI 5 quyết định gốc, không thay đổi kết quả đã chốt của chúng.

## Testing

Chưa chạy — spec ở trạng thái draft, chưa implementation.
