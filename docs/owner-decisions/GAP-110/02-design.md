---
work_id: GAP-110
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-05-gap-110-oppm-phase-2-3-evidence.md
  plan: docs/superpowers/plans/2026-11-05-gap-110-oppm-phase-2-3.md
  branch: docs/GAP-110-oppm-phase-2-3
  pr: https://github.com/kha997/zenamanagephp/pull/400
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-05T06:51:17+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-05, verbatim: 'APPROVE GAP-110 Gate 2 S-1 A-1 W-1 E-1 D-1 K-1 P-1 Q-1'. Reviewed design head: 0f057d6d5351e2f5c3e5d266aa702c7663b991d5. Approves S-1 (Phase 2 and 3 in one PR, three parts, one Gate 3), A-1 (per root-component overlay plus a project-level block), W-1 (weight display only; progress unchanged), E-1 (project PM or project.update holders edit; audited), D-1 (new per-project management decision list), K-1 (manual append-only snapshot with hash, history, view, compare with previous), P-1 (browser print only), Q-1 (no new permission), with the exact allowlist in this packet; one additive migration (four new tables); no new progress / delay / attention formula; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-05T06:55:00+07:00"
  updated_at: "2026-11-05T06:51:17+07:00"
generated_by: agent
---

# GAP-110 — OPPM Phase 2 (management overlay) and Phase 3 (snapshots and decisions): Gate 2 design

## OWNER GATE 2: APPROVED — S-1, A-1, W-1, E-1, D-1, K-1, P-1, Q-1

Owner approved S-1, A-1, W-1, E-1, D-1, K-1, P-1 and Q-1 in-session on 2026-11-05 against reviewed design head
`0f057d6d5351e2f5c3e5d266aa702c7663b991d5`. This authorizes only the bounded implementation defined by this packet; it does not authorize Gate 3, merge,
release, or deployment.

## Owner Summary

Anh/chị cần chọn **8 điểm**:

1. **Cách làm** — đề xuất **S-1**: làm cả giai đoạn 2 và 3 trong GAP này. Một PR chia ba phần (phần nhập, quyết định, chốt
   kỳ), một lần Gate 3.
2. **Gắn phần nhập vào đâu** — đề xuất **A-1**: mỗi nhóm công việc có ô nhập riêng, thêm một **khối cấp dự án** (tóm tắt
   quản trị và hành động tiếp theo). Dự án chưa có nhóm công việc vẫn dùng được.
3. **Trọng số** — đề xuất **W-1**: chỉ hiển thị (kèm tổng); không làm đổi % tiến độ.
4. **Ai được sửa** — đề xuất **E-1**: PM của dự án hoặc người có quyền `project.update`; mọi thay đổi ghi nhật ký.
5. **Quyết định** — đề xuất **D-1**: danh sách "quyết định cần xử lý" mới cho từng dự án.
6. **Chốt kỳ** — đề xuất **K-1**: bấm tay "Chốt báo cáo"; bản chụp không sửa / xoá được; xem lại và so sánh với kỳ trước.
7. **PDF** — đề xuất **P-1**: dùng chế độ in của trình duyệt (in ngang) cho cả trang hiện tại và bản đã chốt. Không thêm
   PDF phía máy chủ.
8. **Quyền** — đề xuất **Q-1**: không thêm quyền mới; xem dùng `project.view`, sửa dùng E-1.

## Điểm 1 — Cách làm

| Phương án | Nội dung |
|---|---|
| **S-1 (đề xuất)** | Một PR, ba commit: (a) phần nhập quản trị, (b) danh sách quyết định, (c) chốt kỳ + so sánh. Một Gate 3. Bản chốt kỳ chụp được cả phần nhập và quyết định. |
| S-2 | GAP này chỉ làm giai đoạn 2 (phần nhập + quyết định). Chốt kỳ để GAP-111, sau khi đã dùng thử phần nhập. |

## Điểm 2 — Gắn phần nhập vào đâu

| Phương án | Nội dung |
|---|---|
| **A-1 (đề xuất)** | **Mỗi nhóm công việc** (hạng mục gốc): kết quả đầu ra, trọng số, hành động tiếp theo (nội dung, người làm, hạn), ghi chú, thứ tự hiển thị. **Khối cấp dự án**: tóm tắt quản trị, hành động tiếp theo của dự án (nội dung, người làm, hạn). |
| A-2 | Chỉ nhóm công việc. Dự án chưa có hạng mục gốc thì không nhập được gì. |

## Điểm 3 — Trọng số

| Phương án | Nội dung |
|---|---|
| **W-1 (đề xuất)** | Trọng số (0–100) chỉ để **hiển thị**; trang hiện tổng trọng số và cảnh báo nếu khác 100. % tiến độ vẫn theo GAP-103, đúng quy tắc SSOT §5.2. |
| W-2 | Tiến độ OPPM tính lại theo trọng số. Đây là **công thức tiến độ mới**, phải sửa SSOT (Gate 2 riêng cho OWN-2026-009); trang OPPM sẽ lệch với tiến độ ở trang dự án. |

## Điểm 4 — Ai được sửa

| Phương án | Nội dung |
|---|---|
| **E-1 (đề xuất)** | PM của dự án (`projects.pm_id`) hoặc người có quyền `project.update`, trong cùng công ty. Mỗi lần sửa / thêm / đổi trạng thái ghi `AuditLog` (giá trị cũ / mới). |
| E-2 | Chỉ người có `project.update`. |

## Điểm 5 — Quyết định

| Phương án | Nội dung |
|---|---|
| **D-1 (đề xuất)** | Danh sách mới cho từng dự án. Mỗi mục có: nội dung cần quyết, bối cảnh, **người quyết** (một người dùng trong công ty), **hạn**, trạng thái (**chờ** / **đã quyết** / **huỷ**), kết quả, ngày quyết. Ghi "đã quyết" thì bắt buộc nhập kết quả. Trang OPPM hiện tối đa 5 mục đang chờ (quá hạn lên đầu, có nhãn "Quá hạn"), kèm link "Xem tất cả". |
| D-2 | Không tạo danh sách mới; OPPM chỉ hiện Yêu cầu thay đổi / RFI đang mở. Không ghi được quyết định quản trị thuần tuý (ví dụ chọn nhà thầu, duyệt phương án). |

## Điểm 6 — Chốt kỳ

| Phương án | Nội dung |
|---|---|
| **K-1 (đề xuất)** | Người theo E-1 bấm **"Chốt báo cáo"**, nhập tên kỳ (ví dụ "Tuần 45/2026", "Tháng 11/2026"). Hệ thống lưu **bản chụp toàn bộ dữ liệu trang**: tiêu đề, chỉ số, nhóm công việc, phần nhập, quyết định, việc bị chặn / quá hạn, tài chính. Bản chụp chỉ thêm, **không sửa / xoá**, có mã kiểm tra (sha256). Trang "Lịch sử báo cáo" liệt kê các kỳ; mở một kỳ ra đúng như lúc chốt; nút **"So với kỳ trước"**. |
| K-2 | Tự chốt hằng tháng bằng lịch chạy. Cần cấu hình lịch trên máy chủ; ít linh hoạt hơn. |

Nội dung so sánh (K-1), chỉ dùng số đã có trong hai bản chụp, không tính lại:
- tiến độ tổng: kỳ trước → kỳ này;
- trạng thái tiến độ / trễ;
- số việc bị chặn / quá hạn;
- tài chính tóm tắt;
- quyết định mở thêm / đã xử lý;
- nhóm công việc: % hoàn thành từng nhóm.

## Điểm 7 — PDF

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Dùng chế độ in ngang sẵn có (giai đoạn 1) cho trang hiện tại và trang bản đã chốt. Người dùng chọn "Lưu thành PDF" trong hộp in. Không thêm gì phía máy chủ. |
| P-2 | Nút "Tải PDF" tạo file trên máy chủ bằng bộ xuất PDF hiện có (Node + Chromium ẩn). Máy chủ thật phải cài Node / Chromium; thiếu thì báo "chưa hỗ trợ". |

## Điểm 8 — Quyền

| Phương án | Nội dung |
|---|---|
| **Q-1 (đề xuất)** | Không thêm quyền mới (SSOT §11). Xem OPPM, quyết định, lịch sử: `project.view` + kiểm tra công ty như giai đoạn 1. Sửa / chốt: E-1. |
| Q-2 | Thêm quyền mới `project.oppm_manage`. |

## Thiết kế chi tiết (theo S-1, A-1, W-1, E-1, D-1, K-1, P-1, Q-1)

### Dữ liệu (một migration, chỉ tạo bảng mới)
**`oppm_workstream_notes`** — mỗi hạng mục gốc tối đa một dòng.
- `id`, `tenant_id`, `project_id`, `component_id`;
- `outcome` (500), `weight` (decimal 5,2, trống được, 0–100);
- `next_action` (500), `next_action_owner_id` (người dùng, trống được), `next_action_due` (ngày, trống được);
- `note` (1000), `display_order` (số, trống được);
- `updated_by`, timestamps.
- Unique (`project_id`, `component_id`).

**`oppm_project_notes`** — mỗi dự án tối đa một dòng.
- `id`, `tenant_id`, `project_id` (unique);
- `summary` (2000);
- `next_action` (500), `next_action_owner_id`, `next_action_due`;
- `updated_by`, timestamps.

**`oppm_decisions`**
- `id`, `tenant_id`, `project_id`;
- `title` (255), `context` (2000), `decision_maker_id`, `due_date`;
- `status` (open / decided / cancelled), `outcome` (2000), `decided_at`, `decided_by`;
- `created_by`, timestamps.

**`oppm_snapshots`** — chỉ thêm.
- `id`, `tenant_id`, `project_id`;
- `period_label` (100), `payload` (json), `payload_hash` (sha256);
- `created_by`, `created_at`.
- Model chặn update / delete.

Hạng mục (`components`) không có `tenant_id`, nên mọi bảng mới đều có `tenant_id` + `project_id` và luôn đọc qua dự án
đã kiểm tra công ty. `component_id` phải là hạng mục gốc của chính dự án đó.

### Dịch vụ
**`OppmOverlayService` (mới)**
- `canEdit(user, project)` (E-1);
- `saveWorkstream(project, actor, componentId, data)`;
- `saveProject(project, actor, data)`;
- `overlayFor(project)`: 2 truy vấn cố định.

Ràng buộc:
- trọng số 0–100;
- người làm phải thuộc công ty;
- độ dài trường theo bảng trên.

**`OppmDecisionService` (mới)**
- `create`, `update` (chỉ khi đang chờ), `decide` (bắt buộc kết quả), `cancel` (bắt buộc lý do);
- `openForProject` (tối đa 5, quá hạn lên đầu), `allForProject`.

**`OppmSnapshotService` (mới)**
- `close(project, actor, label)`:
  - lấy đúng dữ liệu `ProjectOppmReadService::forProject()` + phần nhập + quyết định đang chờ;
  - chuẩn hoá thành JSON (ngày / tiền thành chuỗi), tính sha256, lưu.
- `history(project)`.
- `compare(snapshot, previous)`: chỉ trừ các số trong hai bản chụp.

Sửa nhỏ:
- `ProjectOppmReadService::forProject()` thêm `overlay` và `decisions` vào kết quả, giữ số truy vấn cố định;
- nhóm công việc sắp theo `display_order` nếu có, không thì theo ngày bắt đầu như cũ.

### Giao diện
**Trang OPPM**
- Mỗi dòng nhóm công việc thêm cột **Kết quả đầu ra / Trọng số / Hành động tiếp theo**; dưới bảng hiện tổng trọng số.
- Khối mới **"Tóm tắt quản trị"** và **"Quyết định cần xử lý"**.
- Người có quyền sửa thấy nút **"Sửa"** (form ngắn, mở tại chỗ) và **"Chốt báo cáo"**.
- Bản in ẩn các nút, hiện phần nhập và quyết định.

**Trang "Quyết định"**: tất cả quyết định của dự án; thêm / sửa / ghi kết quả / huỷ.

**Trang "Lịch sử báo cáo"**
- danh sách kỳ đã chốt;
- trang xem một kỳ dùng chung mẫu OPPM nhưng đọc từ bản chụp, có dải "Bản chốt ngày …";
- trang so sánh với kỳ trước.

### Quyền và route
- Xem: `rbac:project.view` + kiểm tra công ty như `ProjectOppmController`.
- Ghi: thêm `OppmOverlayService::canEdit` (E-1) trong controller / service.
- Không thêm quyền mới.

### Test (đỏ trước / xanh sau)
**Phần nhập (`ProjectOppmOverlayTest`)**
- Lưu / sửa phần nhập nhóm công việc và cấp dự án.
- Hạng mục không thuộc dự án / không phải hạng mục gốc bị từ chối; trọng số ngoài 0–100 bị từ chối; người làm khác công ty
  bị từ chối.
- Tổng trọng số và cảnh báo; thứ tự hiển thị; % tiến độ **không đổi** khi đổi trọng số.
- Quyền E-1 (PM được, `project.update` được, người chỉ xem không được); nhật ký.
- Số truy vấn trang OPPM không tăng theo số nhóm công việc / phần nhập.

**Quyết định (`ProjectOppmDecisionTest`)**
- Thêm, sửa khi đang chờ, ghi đã quyết cần kết quả, huỷ cần lý do.
- Quá hạn lên đầu, tối đa 5 trên OPPM.
- Quyền; nhật ký; cách ly công ty / dự án.

**Chốt kỳ (`ProjectOppmSnapshotTest`)**
- Chốt lưu đủ dữ liệu, có mã kiểm tra.
- Đổi dữ liệu thật sau khi chốt thì bản chụp **không đổi**.
- Không sửa / xoá được bản chụp.
- So sánh đúng các chênh lệch; kỳ đầu không có kỳ trước.
- Trang lịch sử / xem / so sánh; quyền; cách ly công ty.

**Migration**: chạy lên / gỡ / lên trên MySQL 8 thật. Ba file test mới cũng chạy xanh với MySQL.

### Files (allowlist)
- Mới:
  - `database/migrations/2026_11_05_120000_create_oppm_overlay_decision_snapshot_tables.php`;
  - `app/Models/ProjectOppm/OppmWorkstreamNote.php`, `OppmProjectNote.php`, `OppmDecision.php`, `OppmSnapshot.php`;
  - `app/Services/ProjectOppm/OppmOverlayService.php`, `OppmDecisionService.php`, `OppmSnapshotService.php`,
    `OppmRuleViolation.php`;
  - `app/Http/Controllers/Web/ProjectOppmManageController.php`;
  - views `resources/views/projects/oppm-decisions.blade.php`, `oppm-history.blade.php`, `oppm-compare.blade.php`,
    `_oppm-body.blade.php` (phần thân dùng chung cho trang sống và bản chụp);
  - tests `tests/Feature/ProjectOppm/ProjectOppmOverlayTest.php`, `ProjectOppmDecisionTest.php`,
    `ProjectOppmSnapshotTest.php`.
- Sửa:
  - `app/Services/ProjectOppm/ProjectOppmReadService.php` (thêm phần nhập, quyết định, thứ tự);
  - `app/Http/Controllers/Web/ProjectOppmController.php` (truyền quyền sửa, xem bản chụp);
  - `resources/views/projects/oppm.blade.php` (dùng phần thân chung, nút và khối mới);
  - `routes/web.php`;
  - `tests/Feature/ProjectOppm/ProjectOppmPageTest.php`, chỉ khi cấu trúc trang đổi làm test cũ cần chỉnh.
- Plan `docs/superpowers/plans/2026-11-05-gap-110-oppm-phase-2-3.md`; packet và evidence GAP-110.

Không phụ thuộc mới, không quyền mới, không công thức tiến độ / trễ / cảnh báo mới, không đổi bảng cũ.

## Ngoài phạm vi

Trang tổng nhiều dự án (Control Tower); Contract / Finance Control; giao diện tạo hạng mục / mốc; tiến độ theo trọng số
(nếu chọn W-1); PDF phía máy chủ (nếu chọn P-1); chốt tự động (nếu chọn K-1); thông báo / nhắc hạn quyết định; AI;
deploy.

## Verification required at Gate 3

Đỏ trước / xanh sau; Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới; migration chạy trên MySQL thật;
số truy vấn trang OPPM cố định; PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch; CI trên đúng head xanh.

## Rollback

Revert squash commit. Migration `down()` xoá 4 bảng mới (mất phần nhập, quyết định và các bản chốt). Trang OPPM trở về
giai đoạn 1.

## Decision Needed

Owner chọn: `APPROVE GAP-110 Gate 2 S-1 A-1 W-1 E-1 D-1 K-1 P-1 Q-1` (hoặc phương án khác cho từng điểm) / Changes /
Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
