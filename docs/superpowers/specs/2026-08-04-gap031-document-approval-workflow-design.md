# GAP-031 — Document approval workflow: đóng divergence Web/API, không tạo lifecycle thứ hai

Date: 2026-08-04 (rev 3 — khoá lỗ hổng cuối: `update()`/`createVersion()` hiện đang cho phép ghi thẳng `status=approved/rejected/submitted`, bỏ qua toàn bộ policy/service/audit vừa xây ở rev 1-2)
Status: DESIGN — chờ duyệt spec, chưa viết implementation code hay implementation plan.
Nguồn gốc: xem rev 1/2 ở trên. Rev 3: phát hiện `SimpleDocumentController::update()` (PUT/PATCH `documents.update`) và `createVersion()` vẫn nhận `status` tuỳ ý và ghi thẳng — kể cả sang `approved`/`rejected`/`submitted` — **hoàn toàn không qua** `document.approve`, `DocumentPolicy::approve()`, `DocumentWorkflowService`, hay bất kỳ audit `decision_by`/`decision_at`/`decision_note` nào. 2 test hiện có (`test_can_update_document_metadata_fields`, `test_canonical_update_persists_document_metadata_fields`) đang **chủ động chứng minh** `draft → approved` qua `update()` — nghĩa là toàn bộ công sức khoá `decision()`/policy ở rev 1-2 có thể bị đi vòng qua `update()`/`createVersion()` mà không ai biết. Đây là lỗ hổng toàn vẹn chuyển trạng thái (transition integrity) thật, không phải giả thuyết.

**Thay đổi so với rev 2:** thêm "reserved workflow status" — `submitted`/`approved`/`rejected` chỉ được ghi bởi `DocumentWorkflowService`, chặn ở cả `store()`, `update()`, `createVersion()`; sửa 2 test hiện có đang minh chứng hành vi cũ (đổi `status=approved` → `status=review`); thêm test chứng minh guard; làm rõ ranh giới GAP-031/032/033 và xác nhận rõ ràng GAP-031 **không** làm Document đủ điều kiện vào Today "Action Required".

---

## 1. Bối cảnh và bằng chứng

Giữ nguyên bằng chứng rev 1/2 (mục 1), bổ sung:

- **`SimpleDocumentController::update()`** (`:517-579` theo số dòng đã xác minh ở rev 1/2) — validate `status` chỉ `'nullable|string|max:100'` (`:529`), rồi `if (array_key_exists('status', $data)) { $updatePayload['status'] = $data['status']; ... }` (`:566-569`) — **ghi thẳng bất kỳ chuỗi nào**, kể cả `approved`/`rejected`/`submitted`, không kiểm tra trạng thái hiện tại, không qua policy `approve`, không ghi `decision_by`/`decision_at`.
- **`SimpleDocumentController::createVersion()`** (`:391-491`) — cùng lỗ hổng: `'status' => $request->input('status', $document->status)` (`:480`) trong khối `forceFill()` — tạo version mới **đồng thời** có thể lén đổi `status` sang bất kỳ giá trị nào, kể cả giả lập "duyệt" 1 document đang `submitted` mà không qua `decide()`.
- **2 test hiện có đang bảo vệ chính hành vi cần khoá này** (không phải giả thuyết — đã đọc trực tiếp):
  - `tests/Feature/Api/DocumentManagementTest.php:375-413` (`test_can_update_document_metadata_fields`) — tạo document `status='draft'`, PATCH `status='approved'`, assert `data.status === 'approved'`.
  - `tests/Feature/Api/DocumentManagementTest.php:417-464` (`test_canonical_update_persists_document_metadata_fields`) — tương tự qua PUT canonical.

## 2. Phạm vi slice này

**Trong phạm vi (bổ sung so với rev 2):**
11. Khoá 3 trạng thái workflow (`submitted`/`approved`/`rejected`) khỏi mọi đường ghi trực tiếp (`store()`, `update()`, `createVersion()`) — chỉ `DocumentWorkflowService` được ghi 3 giá trị này.
12. Sửa 2 test hiện có đang minh chứng hành vi cũ (`status=approved` qua `update()`) sang giá trị legacy hợp lệ (`review`).
13. Đăng ký `GAP-033` (thiếu approver/action-owner xác định) — tách khỏi `GAP-032`.
14. Xác nhận tường minh trong spec: GAP-031 (kể cả sau rev 3) **không** làm Document đủ điều kiện tham gia Today Workspace "Action Required" — lý do khác nhau, không tự động suy ra từ việc đóng gap này.

**Ngoài phạm vi (không đổi từ rev 2, nhắc lại cho rõ):** không đổi hợp đồng `store()`/`update()` cho **legacy status** (`draft`, `active`, `review`, ...) — client vẫn tạo/sửa được các giá trị này tuỳ ý như hiện tại; chỉ 3 giá trị workflow (`submitted`/`approved`/`rejected`) bị khoá khỏi đường ghi trực tiếp.

## 3. State machine — làm rõ phạm vi bảo vệ (sửa theo yêu cầu #7)

```
draft ──submit()──▶ submitted ──decide(APPROVED)──▶ approved
                         │
                         └──decide(REJECTED)────────▶ rejected
```

**Quan trọng — canonical workflow không chỉ là hành vi của 2 endpoint `submit`/`decision`.** Đây là phát biểu đã sai ở rev 1/2 theo nghĩa ngầm định (chỉ sửa `submit()`/`decision()` mà bỏ sót `update()`/`createVersion()`). Phát biểu đúng cho rev 3:

- 3 giá trị `submitted`/`approved`/`rejected` là **reserved workflow status** — được bảo vệ khỏi **mọi** đường ghi generic (`store()`, `update()`, `createVersion()`), không chỉ 2 endpoint `submit`/`decision`.
- Không có actor nào chỉ giữ `document.update` (không có `document.approve`) có thể tạo ra hoặc biến 1 document thành trạng thái quyết định (`approved`/`rejected`) bằng bất kỳ đường nào — kể cả tạo mới, sửa metadata, hay tạo version mới.
- `draft` **không** nằm trong danh sách reserved — client vẫn tạo/giữ `draft` tuỳ ý qua các đường generic (đây là trạng thái khởi đầu, không phải trạng thái quyết định).

## 4. Enum dùng chung — thêm helper reserved status

```php
<?php declare(strict_types=1);

namespace App\Enums;

enum DocumentWorkflowStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Chỉ DocumentWorkflowService được ghi 3 giá trị này. store()/update()/
     * createVersion() phải chặn mọi request có status đích nằm trong danh sách này.
     *
     * @return self[]
     */
    public static function reserved(): array
    {
        return [self::SUBMITTED, self::APPROVED, self::REJECTED];
    }

    /** @return string[] */
    public static function reservedValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::reserved());
    }

    public static function isReserved(string $value): bool
    {
        return in_array($value, self::reservedValues(), true);
    }
}
```

(`DocumentDecision` giữ nguyên từ rev 2, không đổi.)

## 5. `DocumentWorkflowException` — không đổi từ rev 2

(giữ nguyên nội dung rev 2 mục 5 — `reasonCode` machine-readable, message chỉ dùng log nội bộ)

## 6. `DocumentWorkflowService` — không đổi từ rev 2

(giữ nguyên nội dung rev 2 mục 6 — `DB::transaction()` + `lockForUpdate()`, `findForTenant()` không nhận actor)

## 7. `SimpleDocumentController` — refactor `submit()`/`decision()` (không đổi từ rev 2) + khoá reserved status ở 3 đường ghi generic (mới, rev 3)

### 7.1 `submit()`/`decision()` — giữ nguyên nội dung rev 2 mục 7.

### 7.2 `store()` — chặn tạo mới trực tiếp ở trạng thái workflow

```php
$validator = Validator::make($request->all(), [
    // ... các rule hiện có không đổi ...
    'status' => ['nullable', 'string', 'max:100', Rule::notIn(DocumentWorkflowStatus::reservedValues())],
]);
```

Nếu client gửi `status=submitted|approved|rejected` lúc tạo → `422` validation error, message rõ: `"Không thể tạo tài liệu trực tiếp ở trạng thái submitted/approved/rejected — dùng luồng gửi duyệt/quyết định."` Không đổi dòng `'status' => $data['status'] ?? 'active'` (default legacy giữ nguyên) — chỉ thêm rule chặn 3 giá trị reserved.

### 7.3 `update()` — chặn set trực tiếp, giữ nguyên trạng thái workflow hiện có

```php
$validator = Validator::make($request->all(), [
    // ... rule hiện có ...
    'status' => ['nullable', 'string', 'max:100', Rule::notIn(DocumentWorkflowStatus::reservedValues())],
]);
// ... (giữ nguyên đoạn xác thực, lấy $data = $validator->validated()) ...

if (array_key_exists('status', $data)) {
    if (DocumentWorkflowStatus::isReserved($document->status)) {
        // Document hiện đang ở trạng thái workflow (submitted/approved/rejected) —
        // generic update KHÔNG được đổi status, kể cả sang giá trị legacy hợp lệ.
        // Âm thầm bỏ qua field này — các field khác trong $data vẫn áp dụng bình thường.
    } else {
        $updatePayload['status'] = $data['status'];
        $metadata['status'] = $data['status'];
    }
}
```

**2 lớp bảo vệ độc lập:**
1. Validation rule `Rule::notIn(...)` chặn **target** là 1 trong 3 giá trị reserved — áp dụng bất kể trạng thái hiện tại của document (không thể set `approved` dù document đang `draft` hay đang gì đi nữa).
2. Kiểm tra `DocumentWorkflowStatus::isReserved($document->status)` sau khi validate xong chặn việc **đổi khỏi** 1 trạng thái workflow hiện có bằng generic update (kể cả đổi về giá trị legacy như `draft`/`review`) — request vẫn `200 OK`, các field khác (`title`, `discipline`, ...) vẫn được áp dụng, chỉ riêng `status` bị bỏ qua âm thầm (không phải lỗi — đúng yêu cầu "Non-status fields may still be updated while preserving the existing workflow status").

### 7.4 `createVersion()` — áp đúng 2 lớp bảo vệ như `update()`

```php
$validator = Validator::make($request->all(), [
    // ... rule hiện có ...
    'status' => ['nullable', 'string', 'max:100', Rule::notIn(DocumentWorkflowStatus::reservedValues())],
]);
// ...

$targetStatus = DocumentWorkflowStatus::isReserved($document->status)
    ? $document->status // giữ nguyên, không cho version mới đổi status workflow
    : $request->input('status', $document->status);

// trong forceFill():
'status' => $targetStatus,
```

**Tạo version mới không bao giờ được mô phỏng submit/approve/reject/reopen/reverse** — dù request có gửi `status` gì (đã bị validate chặn 3 giá trị reserved ở tầng target), và dù document hiện đang ở trạng thái workflow nào (bị khoá không đổi ở tầng hiện trạng). Việc tạo version mới cho 1 document đã `submitted`/`approved`/`rejected` vẫn được phép (đúng nhu cầu nghiệp vụ — cập nhật file cho tài liệu đã nộp), chỉ riêng `status` của nó không đổi qua đường này.

## 8. `DocumentWorkflowController` (Web) — không đổi từ rev 2

(giữ nguyên nội dung rev 2 mục 8 — `submit()`/`approve()`/`reject()`)

## 9. Route — không đổi từ rev 2

(giữ nguyên nội dung rev 2 mục 9)

## 10. `DocumentPolicy::approve()` — không đổi từ rev 2

(giữ nguyên nội dung rev 2 mục 10 — permission-based, xác nhận role mapping thật trước rollout, 3 test canonical cần thêm `document.approve`)

## 11. Web `store()` ép `status=draft` — không đổi từ rev 2

(giữ nguyên nội dung rev 2 mục 11 — lưu ý: `draft` không nằm trong danh sách reserved nên không bị chặn bởi rule mới ở mục 7.2, luồng Web upload không bị ảnh hưởng)

## 12. `approvals()` — không lộ exception — không đổi từ rev 2

(giữ nguyên nội dung rev 2 mục 12)

## 13. Đọc audit trong list, không N+1 — không đổi từ rev 2

(giữ nguyên nội dung rev 2 mục 13)

## 14. UI — không đổi từ rev 2

(giữ nguyên nội dung rev 2 mục 14 — nút "Gửi duyệt" cho `draft` ở `index.blade.php`, nút Duyệt/Từ chối cho `submitted` ở `approvals.blade.php`)

## 15. Tương thích dữ liệu và rủi ro vận hành (bổ sung so với rev 2)

Giữ nguyên rev 2 mục 15, bổ sung:

- **2 test hiện có phải sửa** (không phải "giữ nguyên" như rev 2 từng nói cho `DocumentManagementTest.php` nói chung — rev 2 chỉ nói 3 test cần thêm permission, rev 3 xác nhận thêm 2 test khác cần đổi **giá trị status mục tiêu**): `test_can_update_document_metadata_fields` và `test_canonical_update_persists_document_metadata_fields` đổi `status: 'approved'` → `status: 'review'` (giá trị legacy hợp lệ, không phải reserved) — mục đích 2 test này (chứng minh generic update sửa được metadata) không đổi, chỉ đổi giá trị status dùng để minh hoạ, vì giá trị cũ (`approved`) giờ là hành vi bị cấm có chủ đích.
- **Rủi ro tương thích ngược cho API consumer đang dựa vào hành vi cũ:** bất kỳ consumer nào hiện đang gọi `update()`/`createVersion()` với `status=approved/rejected/submitted` để "duyệt tài liệu" theo cách không chính thức sẽ nhận `422` (nếu target reserved) hoặc bị âm thầm bỏ qua field status (nếu current đã là reserved) kể từ khi deploy — đây là thay đổi có chủ đích để đóng lỗ hổng toàn vẹn, không phải tác dụng phụ, nhưng **là breaking change thật cho pattern sử dụng sai đó** — cần nêu trong changelog/PR description khi implement.

## 16. Ranh giới gap — GAP-031 / GAP-032 / GAP-033 (làm rõ theo yêu cầu #6)

**GAP-031 (đang sửa trong spec này) giải quyết đúng 4 việc, không hơn:**
1. Divergence giữa Web approval surface (dead code cũ) và canonical workflow API.
2. Authorization cho hành động quyết định (`document.approve`, đồng bộ middleware + policy).
3. **Toàn vẹn chuyển trạng thái** (transition integrity) — reserved status không thể bị ghi tắt qua `store()`/`update()`/`createVersion()` (mục mới của rev 3).
4. Audit của quyết định (`metadata.decision_by/decision_at/decision_note`).

**GAP-032 (đăng ký từ rev 1/2, KHÔNG đóng bởi GAP-031) — phạm vi thu hẹp lại còn:** điều tra rộng hơn về chồng lấn ngữ nghĩa "generic status" (`active`, `review`, và bất kỳ giá trị legacy nào khác client từng tạo) vs "workflow status", và việc có nên chuẩn hoá/migrate dữ liệu cũ hay không. GAP-031 (rev 3) đã đóng phần **cấp bách nhất** của GAP-032 (chặn được đường ghi tắt vào 3 giá trị workflow) nhưng **không** giải quyết: dữ liệu cũ đang ở `active`/`review` có nên được coi là gì trong workflow, có cần 1 bước "nhập lại vào workflow" (VD `active → draft`) hay không — vẫn để ngỏ, thuộc GAP-032.

**GAP-033 (mới, đăng ký trong spec này) — thiếu approver/action-owner xác định:** `Document` không có cách xác định trước "tài liệu này cần đúng người X duyệt" — `document.approve` là permission cấp tenant/role, không phải gán theo từng document. Đây chính là lý do đã ghi trong `docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md` §6.4 khi loại Document approval khỏi Today "Action Required". GAP-031 (kể cả sau rev 3) **không đổi sự thật này** — GAP-031 làm cho workflow *đúng và an toàn* (ai có `document.approve` mới quyết định được, quyết định không bị ghi tắt), nhưng **không** thêm cơ chế xác định *đúng cá nhân nào* là approver được chỉ định cho 1 document cụ thể. Do đó:

> **Xác nhận tường minh: đóng GAP-031 (kể cả rev 3) không làm Document đủ điều kiện tham gia Today Workspace "Action Required".** Điều kiện gia nhập Action Required (đã chốt ở `docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md` §7) đòi hỏi "actor xác định được bằng 1 điều kiện truy vấn cụ thể" — `document.approve` là permission toàn tenant/role, trả về "bất kỳ ai có quyền này trong tenant", không phải "đúng 1 người được chỉ định cho document X". Muốn Document tham gia Action Required, cần đóng GAP-033 trước (thêm cơ chế approver/action-owner xác định theo từng document), việc đó **không nằm trong phạm vi GAP-031**.

## 17. Kế hoạch test (bổ sung so với rev 2)

Giữ nguyên toàn bộ test đã liệt kê ở rev 2 mục 17, **sửa và bổ sung:**

**Sửa 2 test hiện có (mới trong rev 3, khác với "thêm permission" đã nêu ở rev 2):**
- `test_can_update_document_metadata_fields`: đổi `'status' => 'approved'` → `'status' => 'review'`, đổi assertion tương ứng (`assertJsonPath('data.status', 'review')`, `assertDatabaseHas([..., 'status' => 'review'])`).
- `test_canonical_update_persists_document_metadata_fields`: tương tự.

**Test mới — chứng minh guard `update()` (mới trong rev 3):**
1. `update()` với `status=approved` trên document `draft` → `422`, `assertJsonValidationErrors(['status'])` (hoặc tương đương `ErrorEnvelopeService::validationError` format), DB không đổi.
2. `update()` với `status=submitted`/`status=rejected` trên document bất kỳ → tương tự, `422`.
3. `update()` với `status=review` trên document đang `submitted` → `200 OK`, nhưng `assertDatabaseHas(['status' => 'submitted'])` (KHÔNG đổi thành `review`) — chứng minh "current reserved → status field bị bỏ qua âm thầm, không lỗi".
4. `update()` với `title='Tên mới'` (không gửi `status`) trên document đang `approved` → `200 OK`, `title` đổi, `status` vẫn `approved` — chứng minh "field khác vẫn sửa được, không cần né tránh update() hoàn toàn chỉ vì document đã ở trạng thái workflow".
5. `update()` với `status=review` trên document đang `draft` (current KHÔNG reserved, target KHÔNG reserved) → `200 OK`, `status` đổi thành `review` bình thường — chứng minh guard không chặn nhầm trường hợp hợp lệ (legacy-to-legacy).

**Test mới — chứng minh guard `createVersion()` (mới trong rev 3):**
6. `createVersion()` với `status=approved` trên document `draft` → `422`.
7. `createVersion()` (không gửi `status`) trên document đang `submitted` → version mới được tạo thành công (`201`), `status` document vẫn `submitted` (không đổi, không bị suy ra thành gì khác).
8. `createVersion()` với `status=review` trên document đang `approved` → version mới tạo thành công, `status` vẫn `approved` (bị bỏ qua âm thầm, đúng như `update()`).

**Test mới — chứng minh `store()` vẫn nhận legacy, chặn reserved (mới trong rev 3):**
9. `store()` với `status=review` → `201`, `data.status === 'review'` (không đổi hành vi cũ).
10. `store()` với `status=draft` → `201`, `data.status === 'draft'`.
11. `store()` với `status=submitted`/`approved`/`rejected` → `422` cho cả 3 giá trị.

**Cập nhật mục "Không viết lại/xoá test canonical" (rev 2) thành:** không viết lại/xoá bất kỳ test canonical nào **ngoài** 2 test đã nêu ở trên (đổi giá trị status minh hoạ) + 3 test đã nêu ở rev 2 mục 10 (thêm permission fixture) — 4 test canonical còn lại trong `DocumentManagementTest.php` (submit/decision, invalid transitions, tenant-safe) phải xanh nguyên trạng không sửa gì.

## Self-review (rev 3)

1. **Placeholder scan:** không còn `TODO`/`TBD`.
2. **Reserved status được khoá ở cả 3 đường ghi generic (`store`/`update`/`createVersion`), không chỉ `submit`/`decision`:** mục 3 (state machine làm rõ lại), mục 7.2-7.4 (code cụ thể từng hàm), đúng yêu cầu #1, #7.
3. **`store()` tiếp tục nhận `draft` và legacy status, chặn 3 giá trị reserved lúc tạo:** mục 7.2, test #9-11 mục 17 — đúng yêu cầu #2.
4. **`update()`: chặn target reserved (422); nếu current đã reserved thì giữ nguyên status, field khác vẫn sửa được:** mục 7.3, test #1-5 mục 17 — đúng yêu cầu #3, cả 2 nhánh (target-reserved và current-reserved) đều có test riêng phân biệt rõ.
5. **`createVersion()` áp đúng 2 lớp bảo vệ như `update()`, không mô phỏng bất kỳ transition nào:** mục 7.4, test #6-8 mục 17 — đúng yêu cầu #4.
6. **2 test hiện có đổi từ `approved` sang `review`; test mới cho cả 2 nhánh update/createVersion; test store legacy-vs-reserved:** mục 15, mục 17 — đúng yêu cầu #5.
7. **Ranh giới GAP-031/032/033 tách bạch, GAP-033 mới cho approver/action-owner, xác nhận tường minh không đủ điều kiện Today Action Required:** mục 16 — đúng yêu cầu #6, trích dẫn ngược lại đúng điều kiện đã chốt ở spec Today Workspace (§7) thay vì tự đặt tiêu chí mới.
8. **Wording state machine sửa đúng: canonical không chỉ là hành vi 2 endpoint, reserved status được bảo vệ khỏi mọi đường ghi generic, không actor nào chỉ có `document.update` tạo/biến document thành trạng thái quyết định được:** mục 3 — đúng yêu cầu #7, viết lại nguyên văn ý đó thay vì diễn giải khác đi.
9. **Giữ nguyên toàn bộ quyết định đã duyệt ở rev 1/2** (không đổi service, không đổi route/permission, không đổi enum `DocumentDecision`, không đổi UI, không đổi cách audit) — chỉ thêm lớp guard mới và làm rõ phạm vi gap, không mục nào bị đảo ngược.
10. **Không mâu thuẫn nội bộ:** đối chiếu mục 2 (phạm vi) ↔ mục 3 (state machine) ↔ mục 7 (code 4 hàm) ↔ mục 15 (2 test cần sửa) ↔ mục 16 (ranh giới gap) ↔ mục 17 (test) — nhất quán: đúng 3 giá trị reserved, đúng 4 hàm bị khoá/refactor, đúng 2+3 test hiện có cần sửa (không trùng, không thiếu), đúng 3 gap tách bạch không chồng chéo.
11. **Mọi claim kỹ thuật có bằng chứng repo:** `update()`/`createVersion()`, 2 test minh chứng hành vi cũ — đọc trực tiếp trong phiên sửa spec này, kèm số dòng chính xác.

---

Spec (rev 3) sẵn sàng để duyệt. Chưa viết implementation code, chưa viết implementation plan.
