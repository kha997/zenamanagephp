# RFI Lifecycle + Escalation History — Design Spec

**Date:** 2026-07-26
**Status:** Draft — chờ operator duyệt state machine trước khi viết implementation plan
**Nguồn gốc:** Operational Integrity Triage v2 (P1-A) → hạ từ implementation ticket xuống design ticket theo yêu cầu operator (không sao chép Submittal, không clear field escalation khi resolve, làm rõ owner/reassignment/SLA/resolve/close trước khi code)

## Context

RFI hiện không có state machine — khác hẳn Submittal (có `TRANSITIONS` const, `canTransition()`). `status` là string tự do, phần lớn action ghi đè không kiểm tra trạng thái hiện tại. Field escalation tồn tại nhưng không bao giờ được dọn dẹp, không có lịch sử. Không có cơ chế reassignment thật. Notification pipeline cho RFI **là dead code hoàn toàn** — phát hiện mới trong spec này, sửa lại nhận định sai của audit trước.

## 1. Hiện trạng đã xác nhận qua code (không suy đoán)

### 1.1 Status values

| status | Nguồn gán | Ý nghĩa suy ra |
|---|---|---|
| `open` | default khi tạo (`store()`) | Mới tạo, chưa ai xử lý |
| `pending` | **Không controller action nào set giá trị này** — chỉ xuất hiện trong `scopeOverdue`/badge color | Enum "chết", không rõ mục đích ban đầu |
| `in_progress` | `assign()` | Đã gán người phụ trách |
| `answered` | `respond()` (khi input status=answered) | Đã có câu trả lời |
| `closed` | `respond()` (khi input status=closed) HOẶC `close()` | Đóng |
| `escalated` | `escalate()` | Đang escalate |

`update()` (PUT) cho phép set thẳng `open`/`answered`/`closed` bất kể trạng thái hiện tại, bỏ qua toàn bộ luồng `respond`/`close`/`assign`.

### 1.2 Guard hiện có theo action (route → guard)

| Route | Action | Check `$rfi->status` hiện tại trước khi set? |
|---|---|---|
| `POST /rfis` | store | n/a (tạo mới) |
| `POST /rfis/{id}/assign` | assign | **KHÔNG** |
| `POST /rfis/{id}/respond` | respond | **KHÔNG** — có thể respond RFI đã `closed`/`escalated` |
| `POST /rfis/{id}/close` | close | **CÓ** — `if ($rfi->status !== 'answered') return 400` |
| `POST /rfis/{id}/escalate` | escalate | **KHÔNG** — có thể escalate RFI đã `closed` |
| `PUT /rfis/{id}` | update | **KHÔNG** |

Chỉ 1/6 action có guard. `close()` là ngoại lệ duy nhất hiện đúng chuẩn.

### 1.3 Caller

- `routes/api_zena.php`: đầy đủ CRUD + assign/respond/close/escalate, mỗi route có `rbac:rfi.*` riêng.
- `routes/web.php`: **chỉ có index/create/store/show/respond/close** — không có route web cho escalate hoặc assign (2 action này chỉ gọi được qua API trực tiếp).
- `resources/views/rfis/show.blade.php`: form respond hiện khi `status IN (open, escalated)`; form close hiện khi `status = answered`. Đây là điều kiện UI-only — API không tự enforce tương tự cho respond (chỉ close có enforce).

### 1.4 Notification — DEAD CODE (phát hiện mới, sửa nhận định audit trước)

`RfiEventListener` import `App\Events\RfiCreated/RfiUpdated/RfiResponded/RfiClosed` — **các Event class này không tồn tại trong repo**. `EventServiceProvider` không có mapping nào cho Rfi. `RfiController` không có `event(...)`/`::dispatch()` nào. **Kết luận: không ai được thông báo bất cứ điều gì khi RFI thay đổi trạng thái, kể cả escalate.** Đây không phải "notify cả team" như ghi nhận trước — listener đó chưa từng chạy.

### 1.5 Escalation field — không bao giờ được dọn

Grep toàn bộ `app/`: không có nơi nào set `escalated_to`/`escalation_reason`/`escalated_by`/`escalated_at` về `null`. Không có lịch sử — chỉ 1 bộ field duy nhất, escalate lần 2 sẽ ghi đè lần 1, mất dấu vết.

### 1.6 Reassignment — không tồn tại như 1 khái niệm riêng

`assign()` dùng chung cho gán lần đầu và gán lại, không phân biệt, không giữ lịch sử người được gán trước, không check status hiện tại.

### 1.7 Test hiện có — hợp đồng hành vi thực tế đang được bảo vệ

- `RfiApiTest`: `test_can_escalate_rfi` (từ `in_progress`), `test_can_close_rfi` (từ `answered`). **Không có test nào verify guard khi escalate/respond trên RFI đã `closed`** — nghĩa là thêm guard mới cho các action này sẽ KHÔNG phá test hiện có (nhưng cũng không có ai đang bảo vệ hành vi ngược lại).
- `OperatorRfiUiTest`: flow web UI đầy đủ create→respond→close; không test escalate qua web (vì không có route).
- `RfiWorkflowTest`: một số test gọi thẳng `$rfi->update()` trên model, bỏ qua controller — không phải test hợp đồng API thật.

**Kết luận quan trọng cho thiết kế**: có thể thêm guard vào `respond()`/`escalate()`/`assign()` mà không phá bất kỳ test nào đang xanh — nhưng đây là thay đổi hành vi thật (hiện tại cho phép), cần operator xác nhận đây là điều muốn.

## 2. Vấn đề kiến trúc cần quyết định TRƯỚC khi vẽ transition map

**`escalated` hiện là 1 giá trị của `status` (loại trừ lẫn nhau với `answered`/`closed`), nhưng về nghiệp vụ, "đang khẩn cấp" và "đang ở giai đoạn nào của vòng đời" là 2 chiều độc lập** — một RFI có thể vừa `in_progress` vừa "đã được đánh dấu khẩn" cùng lúc. Thiết kế hiện tại buộc phải chọn 1: hoặc RFI "escalated" (mất thông tin nó đang open hay in_progress), hoặc RFI "in_progress" (mất thông tin nó đang khẩn).

**Cần operator quyết định (Quyết định #1 — chặn toàn bộ phần còn lại của spec):**

| Phương án | Mô tả | Đánh đổi |
|---|---|---|
| A. Giữ nguyên kiến trúc — `escalated` là 1 status | Ít thay đổi nhất, tương thích UI hiện có (badge theo status) | Không biểu diễn được "in_progress nhưng đang khẩn" — đúng hiện trạng, chỉ vá guard |
| B. Tách `escalation` thành field độc lập (`is_escalated: bool` hoặc `escalation_level: int`) song song với `status` | Đúng bản chất nghiệp vụ hơn, RFI vẫn giữ status chính (open/in_progress/answered/closed) trong khi escalation là 1 thuộc tính bổ sung | Đổi schema, đổi UI badge, phạm vi lớn hơn — không nằm trong "design ticket nhỏ" ban đầu |

Spec này **giả định Phương án A** (giữ `escalated` trong `status` enum) vì đó là thay đổi nhỏ nhất phù hợp phạm vi "design ticket" — nhưng đây là giả định cần operator xác nhận, không phải quyết định đã chốt. Nếu operator chọn B, phần 3-6 dưới đây cần thiết kế lại.

## 3. Transition map đề xuất (RFI-specific, không sao chép Submittal)

Khác Submittal (workflow tuyến tính draft→submitted→approved/rejected), RFI có đặc thù: `escalated` không phải trạng thái "cuối" của 1 nhánh mà là "chèn ngang" vào bất kỳ trạng thái chưa đóng nào, và có thể thoát ra để tiếp tục làm việc bình thường.

```
        ┌──────┐
        │ open │ (tạo mới)
        └──┬───┘
           │ assign()
           ▼
    ┌─────────────┐
    │ in_progress │◄────────────────┐
    └──┬──────┬───┘                 │
       │      │ escalate()          │ de-escalate (mới, xem 3.3)
       │      ▼                     │
       │  ┌───────────┐             │
       │  │ escalated │─────────────┘
       │  └─────┬─────┘
       │        │ respond()
       │        ▼
       │   ┌──────────┐
       └──►│ answered │
           └────┬─────┘
                │ close()
                ▼
           ┌────────┐
           │ closed │ (terminal)
           └────────┘
```

### 3.1 Bảng transition đầy đủ (state hiện tại → state đích → action → điều kiện)

| Từ | Đến | Action | Điều kiện bắt buộc |
|---|---|---|---|
| — | `open` | `store()` | Luôn cho phép (tạo mới) |
| `open` | `in_progress` | `assign()` | `assigned_to` phải khác null sau khi gán |
| `open`, `in_progress` | `escalated` | `escalate()` | **MỚI**: chỉ cho phép từ `open`/`in_progress`, KHÔNG cho phép từ `answered`/`closed` (đã xong việc thì không "khẩn" nữa) |
| `escalated` | `in_progress` | `deescalate()` (**action mới**, xem 3.3) | Phải có `escalation_resolution` (lý do hạ mức khẩn) |
| `in_progress`, `escalated` | `answered` | `respond()` | **MỚI**: chỉ cho phép từ `in_progress`/`escalated`, KHÔNG cho phép từ `closed` (RFI đã đóng không trả lời lại được — muốn hỏi tiếp thì tạo RFI follow-up mới, xem 6) |
| `answered` | `closed` | `close()` | Giữ nguyên guard hiện có (đã đúng) |
| bất kỳ trạng thái nào chưa `closed` | (không đổi `status`) | `assign()` gọi lại (reassign) | Xem 3.2 — reassign không đổi `status`, chỉ đổi `assigned_to` |

**Không có transition nào cho phép quay lại `open` từ bất kỳ trạng thái nào khác** (không có "reopen" — xem mục 6 về semantics đóng/mở lại).

### 3.2 Owner & Reassignment

- `assigned_to` là owner. **Assign lần đầu** (từ `assigned_to = null`) và **reassign** (từ `assigned_to` đã có giá trị) dùng CHUNG 1 action `assign()` như hiện tại — không cần tách action riêng, nhưng phải ghi lịch sử (xem 4).
- Reassign được phép ở MỌI trạng thái chưa `closed` (kể cả `escalated` — cần đổi người phụ trách giữa lúc khẩn là tình huống thực tế hợp lý).
- Reassign trên RFI đã `closed`: **không cho phép** — RFI đã xong việc không cần đổi người phụ trách.

### 3.3 Action mới cần thêm: `deescalate()`

Hiện tại KHÔNG có cách thoát khỏi `escalated` để quay lại làm việc bình thường mà không trả lời luôn (`respond()` từ `escalated` đi thẳng sang `answered`, bỏ qua `in_progress`). Nhưng thực tế: 1 RFI có thể được escalate rồi hoá ra không cần trả lời gấp — người phụ trách muốn "hạ mức khẩn" mà chưa có câu trả lời. Đề xuất thêm action `deescalate()`:
- Route mới: `POST /rfis/{id}/deescalate`.
- Chuyển `escalated → in_progress`.
- Bắt buộc field `escalation_resolution` (text, lý do hạ mức) — ghi vào lịch sử escalation (mục 4), KHÔNG xoá field escalation gốc.
- **Đây là action MỚI, chưa tồn tại trong code — cần operator xác nhận có muốn thêm hay không, hay chấp nhận "escalated chỉ thoát qua respond()" là đủ.** (Quyết định #2)

## 4. Escalation history — 2 phương án (chờ quyết định #3)

Operator đã yêu cầu: KHÔNG clear `escalated_to/escalated_at/escalation_reason` khi resolve; đề xuất `escalation_resolved_at/by/resolution` HOẶC append-only escalation events.

| Phương án | Mô tả | Đánh đổi |
|---|---|---|
| **A. Thêm field resolution vào bảng `rfis`** | Thêm `escalation_resolved_at`, `escalation_resolved_by`, `escalation_resolution` cạnh 4 field escalation hiện có. Field gốc (`escalated_to/at/by/reason`) giữ nguyên vĩnh viễn sau khi resolve — không bị ghi đè. | Đơn giản, ít di trú. **Nhược điểm nghiêm trọng**: nếu RFI escalate LẦN 2 (sau khi đã từng escalate-resolve 1 lần), field sẽ bị ghi đè, mất lịch sử lần 1 — chỉ giữ được lịch sử của lần escalate GẦN NHẤT, không phải toàn bộ |
| **B. Bảng con `rfi_escalations` (append-only)** | Mỗi lần escalate là 1 row mới: `id, rfi_id, escalated_to, escalated_by, escalated_at, escalation_reason, resolved_at, resolved_by, resolution, resolution_type (respond\|deescalate)`. Giữ TOÀN BỘ lịch sử mọi lần escalate, kể cả nhiều lần. Cùng pattern đã dùng cho `submittal_revisions` trong hệ thống (tiền lệ có sẵn, không phải kiến trúc mới lạ). | Cần 1 migration + 1 model mới, nhiều việc hơn phương án A, nhưng đúng nghĩa "audit history" như operator yêu cầu, và nhất quán với pattern Submittal đã có |

**Đề xuất của spec này: Phương án B** — vì (a) đúng nghĩa đen "escalation history" mà operator đề cập chứ không phải "resolution của lần gần nhất", (b) RFI có thể escalate nhiều lần trong vòng đời (khác Submittal chỉ có 1 chu kỳ reject→revise), (c) đã có tiền lệ kiến trúc `submittal_revisions` trong repo, giảm rủi ro pattern lạ. **Nhưng đây là đề xuất, không phải quyết định đã chốt — operator cần xác nhận (Quyết định #3), đặc biệt nếu muốn tối giản hoá effort thì phương án A vẫn là lựa chọn hợp lệ nếu chấp nhận giới hạn "chỉ giữ lịch sử lần escalate gần nhất".**

Nếu chọn B: 4 field cũ trên bảng `rfis` (`escalated_to/at/by/reason`) trở thành **denormalized cache của escalation record mới nhất** (để không phá UI/query hiện tại đang đọc trực tiếp field này), đồng thời ghi đầy đủ vào `rfi_escalations`. Không xoá 4 field cũ khỏi bảng `rfis` — chỉ bổ sung.

## 5. Semantics owner/reassignment/SLA/resolve/close (chốt theo evidence, không suy đoán thêm)

- **Owner**: `assigned_to`. Không có "watcher" hay "co-owner" — ngoài phạm vi spec này.
- **SLA**: KHÔNG có trong hiện trạng (`due_date` nullable, không bắt buộc). Spec này KHÔNG thêm SLA — đó là phạm vi của "Overdue & Escalation Engine" (P1-A trong triage, ticket riêng, phụ thuộc vào transition map này được duyệt trước).
- **Resolve** (trong ngữ cảnh escalation): là `deescalate()` (mục 3.3) hoặc `respond()` từ `escalated` — cả 2 đều đóng escalation record hiện tại (set `resolved_at/by/resolution` hoặc insert row mới tuỳ phương án 4).
- **Close**: giữ nguyên guard hiện có (`answered → closed`), không đổi.
- **Reopen**: KHÔNG thiết kế trong spec này — RFI `closed` là terminal tuyệt đối. Nếu cần hỏi thêm sau khi đóng, quy trình là tạo RFI mới (không có field "RFI liên quan" — đây là gap thật nhưng ngoài phạm vi, ghi nhận ở mục 7).

## 6. Semantics "closed không cho respond lại" — điểm khác biệt lớn nhất so với hiện trạng

Hiện tại `respond()` không hề chặn RFI đã `closed`. Spec này đề xuất chặn — đây là thay đổi hành vi thật (không phải chỉ làm rõ), cần nêu tường minh: **sau khi RFI đóng, không sửa câu trả lời qua `respond()` được nữa.** Nếu nghiệp vụ thực tế cần sửa câu trả lời sau khi đóng (ví dụ phát hiện sai sót), cần 1 luồng riêng ("reopen" hoặc "amend") — KHÔNG có trong spec này, cần quyết định (Quyết định #4: có chấp nhận giới hạn này không, hay cần thêm luồng reopen).

## 7. Ngoài phạm vi (Non-goals)

- Không thêm SLA/deadline enforcement (thuộc Overdue Engine, ticket riêng — phụ thuộc spec này).
- Không thêm notification thật (email/in-app) — pipeline hiện là dead code hoàn toàn, việc khôi phục/xây notification là ticket riêng, phụ thuộc transition map này được duyệt trước (cần biết chính xác sự kiện nào tồn tại trước khi thiết kế notify theo sự kiện đó).
- Không thêm route web cho `escalate`/`assign`/`deescalate` — quyết định UI (có expose lên web UI hay chỉ giữ API) để lại cho lúc viết implementation plan, sau khi state machine được duyệt.
- Không thiết kế "RFI liên quan" (linked follow-up RFI sau khi đóng).
- Không đổi `pending` status (giữ nguyên là "dead enum value" — quyết định xoá hay định nghĩa lại mục đích của nó để riêng, không ưu tiên).

## 8. Quyết định nghiệp vụ cần operator duyệt trước khi viết implementation plan

1. **[CHẶN TOÀN BỘ]** Escalation là 1 giá trị của `status` (Phương án A, spec giả định) hay tách thành field độc lập song song với status (Phương án B, phạm vi lớn hơn)?
2. Có cần thêm action `deescalate()` mới (thoát `escalated` mà không trả lời luôn), hay chấp nhận chỉ thoát qua `respond()`?
3. Escalation history: field resolution đơn giản trên bảng `rfis` (chỉ giữ lần gần nhất) hay bảng con `rfi_escalations` append-only (giữ toàn bộ lịch sử, đề xuất của spec)?
4. Có chấp nhận giới hạn "RFI đã `closed` không thể `respond()` lại nữa, muốn hỏi thêm phải tạo RFI mới" hay cần thiết kế luồng reopen/amend?
5. `pending` status: xoá khỏi enum (dọn dẹp) hay giữ nguyên/định nghĩa lại mục đích?

## Self-review

- **Không sao chép Submittal**: transition map RFi có nhánh escalate/de-escalate chèn ngang, khác hẳn workflow tuyến tính draft→submitted→approved/rejected→revising của Submittal — đặc thù riêng của RFI được tôn trọng.
- **Không xoá field escalation khi resolve**: cả 2 phương án ở mục 4 đều giữ nguyên field/record cũ, chỉ thêm thông tin resolution.
- **Owner/reassignment/SLA/resolve/close đã làm rõ** ở mục 5, dựa trên evidence thật (không suy đoán) — SLA cố tình để trống vì ngoài phạm vi, không phải bỏ sót.
- **Chưa chốt state machine** — đúng yêu cầu operator "chờ operator duyệt trước khi code". 5 quyết định ở mục 8 đều là quyết định nghiệp vụ thật, không phải chi tiết kỹ thuật tôi có thể tự quyết.
- **Phát hiện ngoài dự kiến đã nêu rõ, không giấu**: `RfiEventListener` là dead code hoàn toàn — sửa lại nhận định "notify cả team" của audit trước, ảnh hưởng trực tiếp tới việc thiết kế notification (ticket riêng, chưa làm ở đây).

## Testing

Chưa chạy — spec ở trạng thái draft, chưa implementation.
