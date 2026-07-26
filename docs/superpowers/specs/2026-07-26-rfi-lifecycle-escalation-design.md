# RFI Lifecycle + Escalation History — Design Spec

**Date:** 2026-07-26 (rev 2)
**Status:** Draft — chờ operator duyệt trước khi viết implementation plan
**Nguồn gốc:** Operational Integrity Triage v2 (P1-A); rev 2 sửa theo `REQUEST CHANGES BEFORE IMPLEMENTATION PLAN` trên rev 1 — chốt Quyết định #1: escalation là concern độc lập, không còn là giá trị của `status`

## Thay đổi so với rev 1

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

Bảng `rfis` **giữ nguyên 4 field cũ** (`escalated_to/at/by/reason`) làm **denormalized cache của escalation record active gần nhất** (đọc nhanh không cần join, không phá query/UI cũ đang đọc trực tiếp field này) — nhưng nguồn sự thật (source of truth) là `rfi_escalations`. Khi không có escalation active, 4 field cache này để `null` (đã resolve rồi thì cache cũng clear — cache, không phải audit trail, khác hẳn dữ liệu trong `rfi_escalations` không bao giờ mất).

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

## 6. Migration/backfill dữ liệu legacy

### 6.1 Preflight bắt buộc TRƯỚC khi chạy migration thật (chạy trên staging/production, KHÔNG dùng số liệu DB dev vì hiện đang rỗng)

1. Đếm `Rfi::where('status','escalated')->count()` và tổng `Rfi::count()`.
2. Với từng row `status='escalated'`: kiểm tra `assigned_to` (null hay không).
3. Với từng row `status != 'escalated'`: kiểm tra 4 field snapshot cũ (`escalated_to/by/at/reason`) có populated không — dấu hiệu "đã từng escalate trong quá khứ, sau đó status bị ghi đè bởi `respond()`/`close()`/`update()` (cơ chế cột đơn hiện tại cho phép ghi đè tự do)".
4. Đếm `Rfi::where('status','pending')->count()` — theo code hiện tại, KHÔNG action nào từng set giá trị này, nên bất kỳ row nào có `status='pending'` là bất thường, cần liệt kê riêng.
5. **Xác nhận lại: `EventRecord` không có dữ liệu nào cho RFI** (đã xác nhận qua code, nhưng preflight nên tự chạy `EventRecord::where('aggregate_type','rfi')->count()` trên production để chắc chắn 100%, phòng trường hợp có ghi nhận nào ngoài phạm vi code đã đọc).

### 6.2 Bảng quyết định migration (Migration Decision Table)

**Nguyên tắc nền**: cơ chế cột `status` đơn hiện tại có nghĩa là nếu 1 row hiện KHÔNG còn `status='escalated'`, nó chắc chắn đã bị `respond()`/`close()`/`update()` ghi đè SAU đó — nhưng KHÔNG có event log nào xác nhận được thời điểm/người thực hiện chính xác. Vì vậy **không có trường hợp nào đạt "high confidence" theo nghĩa xác nhận được bằng event log** — mọi row có snapshot escalation đều cần đưa vào manual-review report, chỉ khác nhau ở việc có gán lifecycle mặc định nào hay không.

| Điều kiện dữ liệu legacy | Lifecycle mới gán | `rfi_escalations` tạo ra | Vào manual-review report? |
|---|---|---|---|
| `status='escalated'`, `assigned_to` khác null | `IN_PROGRESS` | 1 row **UNRESOLVED** (copy từ 4 field snapshot cũ) | **CÓ** — mọi row, vì không xác nhận được bằng event log |
| `status='escalated'`, `assigned_to` là null | `OPEN` | 1 row **UNRESOLVED** (copy từ snapshot) | **CÓ** |
| `status != 'escalated'`, nhưng 4 field snapshot cũ populated | Giữ nguyên giá trị `status` hiện tại (map thẳng sang enum mới, xem 6.3) | 1 row **RESOLVED** suy luận: `resolved_at` = ước lượng bằng `rfi.updated_at`, `resolved_by` = null, `resolution_type` = `manually_resolved`, `resolution` = "Backfill tự động: không xác nhận được thời điểm/người resolve thật do thiếu event log — ước lượng từ updated_at." | **CÓ** — vì thời điểm/người resolve chỉ là ước lượng |
| `status = 'pending'` (bất thường, không action nào từng set) | `OPEN` (mặc định an toàn) | Không tạo | **CÓ** — đánh dấu "anomaly: trạng thái pending không rõ nguồn gốc" |
| `status IN (open, in_progress, answered, closed)`, không có snapshot escalation | Map thẳng 1-1 sang enum mới | Không tạo | Không |

**Không có row nào bị tự động map về `open` chỉ vì thiếu bằng chứng** — quy tắc trên luôn ưu tiên tín hiệu trực tiếp nhất có (`assigned_to`, `status` hiện tại) làm lifecycle mặc định, và luôn tạo bản ghi escalation (unresolved hoặc resolved-ước-lượng) thay vì âm thầm bỏ qua dấu vết escalation — nhưng MỌI row có bất kỳ tín hiệu escalation nào đều xuất hiện trong manual-review report để con người xác nhận lại, vì đây là giới hạn thật của việc thiếu event log, không phải sự tuỳ tiện.

### 6.3 Mapping `status` cũ → `RfiLifecycleStatus` mới (cho row không có snapshot escalation)

| `status` cũ | `RfiLifecycleStatus` mới |
|---|---|
| `open` | `OPEN` |
| `in_progress` | `IN_PROGRESS` |
| `answered` | `ANSWERED` |
| `closed` | `CLOSED` |
| `escalated` | Xem bảng 6.2 (dựa vào `assigned_to`) |
| `pending` | `OPEN` (xem 6.2, kèm anomaly flag) |

### 6.4 Manual-review report

Migration script xuất ra 1 report (CSV/log, không phải chỉ ghi log ẩn) liệt kê MỌI row rơi vào cột "CÓ" ở bảng 6.2, gồm: `rfi_id`, lifecycle mới gán, escalation record tạo ra (resolved/unresolved), lý do vào report. Migration **KHÔNG bị chặn chạy** bởi report này (không phải manual approval gate trước khi chạy) — report là **hậu-migration**, để PM/admin rà lại và điều chỉnh thủ công (VD tự tay resolve 1 escalation mà migration để unresolved, nếu họ biết chắc thực tế nó đã xong).

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
| `escalate` | `rfi.escalate` | Lifecycle chưa terminal; chưa có active escalation; `escalated_to` phải cùng tenant + project membership |
| `resolveEscalation` | `rfi.escalate` (tái dùng — quyền tạo escalation ngụ ý quyền đóng nó; đề xuất, có thể tách permission riêng nếu business muốn phân quyền chi tiết hơn) | Phải đang có active escalation |

**Lưu ý vận hành quan trọng** (không phải quyết định thiết kế, nhưng ảnh hưởng trực tiếp tới việc dùng được tính năng): hiện tại **chỉ admin role có `rfi.escalate`** qua seed mặc định — PM không tự escalate được trừ khi business chủ động gán thêm permission này cho role PM. Đây là quyết định seeding/role riêng, không phải quyết định kiến trúc của spec này, nhưng cần biết trước khi rollout (nếu không PM sẽ không dùng được escalate/resolveEscalation/cancel-with-escalation).

## 9. Transaction & Concurrency

- Mọi action ghi (`escalate`, `resolveEscalation`, `cancel`, và `close` khi cần check escalation) chạy trong `DB::transaction()`.
- `escalate()`: trong transaction, `Rfi::where('id',$id)->lockForUpdate()->first()` lock row RFI; `RfiEscalation::where('rfi_id',$id)->whereNull('resolved_at')->lockForUpdate()->first()` kiểm tra active escalation. Nếu đã có → **rollback, trả 409 Conflict**. Nếu không → insert row mới + update 4 field cache trên `rfis`.
- 2 request `escalate()` đồng thời: request thứ nhất lock trước, insert, commit. Request thứ hai chờ lock, sau khi acquire thấy đã có active escalation (do request 1 vừa tạo) → trả 409.
- `resolveEscalation()`: lock đúng row `rfi_escalations` đang active bằng `lockForUpdate()`; kiểm tra `resolved_at IS NULL` ngay trong transaction trước khi update (chống race 2 resolve cùng lúc — request thứ 2 sau khi acquire lock thấy `resolved_at` đã có giá trị → 409).
- `cancel()` khi có active escalation: lock CẢ row `rfis` VÀ row `rfi_escalations` active trong cùng transaction, update cả 2 (status RFI → cancelled, resolve escalation) atomic — hoặc rollback toàn bộ nếu bất kỳ bước nào lỗi.
- Không dùng optimistic locking (version column) cho phần này — dùng pessimistic lock (`lockForUpdate()`) nhất quán với pattern đã có sẵn trong `RfiController`/`SubmittalLifecycleService`.

## 10. Notification tối thiểu

- **Phạm vi**: chỉ in-app notification cho `escalated_to` khi 1 escalation mới được tạo. KHÔNG có email, KHÔNG có auto-escalation theo SLA (thuộc Overdue Engine, ticket riêng — engine đó khi làm PHẢI gọi qua lifecycle/escalation service của spec này, không tự ý update trực tiếp bảng, xem mục 13).
- **Timing**: dispatch SAU KHI transaction commit thành công (Laravel: đặt lời gọi notify ngoài closure `DB::transaction()`, hoặc dùng `ShouldQueue` + cấu hình `afterCommit`). Không dispatch notification nếu transaction rollback (VD do conflict 409) — vì action chưa thực sự xảy ra.
- **Lỗi notification**: nếu gửi notify thất bại (exception), **log lỗi (`Log::error`) nhưng KHÔNG rollback** hành động escalate đã commit — escalation đã xảy ra thật, việc thông báo thất bại là vấn đề vận hành riêng, không nên làm mất dữ liệu escalation đã ghi.

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
| 20 | Notification chỉ dispatch sau commit | Mock transaction rollback (VD force 409 bằng race) → verify notify KHÔNG được gọi |
| 21 | Migration: row `status='escalated'`, có `assigned_to` | Sau migration: lifecycle=`IN_PROGRESS`, 1 escalation unresolved được tạo, xuất hiện trong manual-review report |
| 22 | Migration: row `status='pending'` | Sau migration: lifecycle=`OPEN`, đánh dấu anomaly trong report |
| 23 | Migration: row có snapshot escalation cũ nhưng `status` hiện tại đã là `closed` | Sau migration: lifecycle=`CLOSED`, 1 escalation resolved-ước-lượng được tạo, vào report |

## 12. Gap vận hành cần lưu ý (không phải quyết định thiết kế, nhưng ảnh hưởng rollout)

- Cần xác nhận middleware RBAC hiện tại có enforce project-membership cho action RFI hay chỉ tenant-level — nếu chỉ tenant-level, đây là lỗ hổng có sẵn (không phải do spec này tạo ra) cần vá cùng lúc khi implement guard escalate/assign.
- `rfi.cancel` là permission hoàn toàn mới — cần quyết định gán cho role nào (assignee mặc định? hay phải seed riêng?).
- `rfi.escalate` hiện chỉ admin có — nếu muốn PM tự escalate/resolve/cancel-with-escalation, cần seed thêm cho role PM, đây là quyết định business/vận hành riêng.

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

## Self-review (rev 2)

- **Đúng yêu cầu #1-3**: lifecycle chỉ còn 5 trạng thái nghiệp vụ thuần, escalate/resolveEscalation không đổi status; action đổi tên đúng ngữ nghĩa; bảng history không còn gọi sai là "append-only".
- **Migration (#4) đã thiết kế trung thực với giới hạn evidence thật** — vì `EventRecord` xác nhận KHÔNG có dữ liệu RFI nào, spec không giả vờ có "high confidence" dựa trên event log không tồn tại; mọi row có tín hiệu escalation đều vào manual-review report, không tự map về `open`.
- **Rule #5 đã chốt tường minh**: answer không tự resolve, close chặn khi active escalation, cancel-khi-escalated chỉ PM/admin + atomic.
- **Notification #6 thu hẹp đúng yêu cầu**: có in-app tối thiểu, after-commit, log-not-rollback khi lỗi gửi; email/SLA vẫn ngoài phạm vi.
- **Authorization matrix #7 dùng permission thật** đã grep được, không bịa tên mới trừ `rfi.cancel` (được đánh dấu rõ là MỚI cần seed) — đồng thời phát hiện thêm gap vận hành thật (chỉ admin có `rfi.escalate` mặc định) đưa vào mục 12 thay vì giấu.
- **Concurrency #8 dùng lockForUpdate() nhất quán với pattern có sẵn** trong `RfiController`/`SubmittalLifecycleService`, không phát minh cơ chế mới; có ghi rõ giới hạn MySQL (không dùng partial unique index).
- **Test matrix #9**: 23 case bao phủ lifecycle thuần, escalation độc lập, nhiều cycle, concurrency, cross-tenant/project target, notification after-commit, và cả 3 nhánh migration.
- **Overdue Engine #10 giữ ngoài phạm vi + ràng buộc rõ**: phải gọi qua service của spec này, không tự update trực tiếp — tránh lặp lại kiểu nợ kỹ thuật "nhiều nơi tự ý ghi đè status" đã thấy ở hiện trạng.

## Testing

Chưa chạy — spec ở trạng thái draft, chưa implementation.
