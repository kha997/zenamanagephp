---
work_id: GAP-108
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-11-04-gap-108-treasury-thresholds-advance-deadline-evidence.md
  plan: null
  branch: docs/GAP-108-treasury-thresholds-advance-deadline
  pr: https://github.com/kha997/zenamanagephp/pull/398
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-04T15:55:00+07:00"
  updated_at: "2026-11-04T15:55:00+07:00"
generated_by: agent
---

# GAP-108 — Treasury configurable thresholds and per-advance deadline: Gate 2 design

## Owner Summary

Anh/chị cần chọn **5 điểm**:

1. **Ngưỡng nào cấu hình được** — đề xuất **N-1**: hai ngưỡng.
   - Số ngày tiền nhận chưa đối soát thì cảnh báo (mặc định 30).
   - Số ngày cho tạm ứng **không có hạn** thì coi là quá hạn (mặc định 90).
   Nhóm tuổi nợ 30 / 60 / 90 giữ nguyên.
2. **Cấu hình theo đâu** — đề xuất **S-1**: theo công ty. Chỉ Owner đổi được, có nhật ký, giống công tắc "bắt buộc chứng
   từ".
3. **Hạn tạm ứng** — đề xuất **H-1**:
   - ô "Hạn hoàn ứng" **không bắt buộc**, nhập khi lập đề nghị;
   - sau khi chi, người duyệt chi vẫn sửa được, bắt buộc ghi lý do.
4. **Quy tắc quá hạn** — đề xuất **Q-1**:
   - có hạn: quá hạn khi qua ngày hạn mà vẫn còn nợ;
   - không có hạn: dùng ngưỡng ở điểm 1, giữ đúng như cảnh báo hiện nay.
5. **Phát hành** — đề xuất **P-1**: một PR, một Gate 3.

Mặc định 30 / 90 giữ đúng hành vi hiện nay. Chưa ai đổi cài đặt và chưa nhập hạn thì mọi màn hình và cảnh báo vẫn như cũ.

## Điểm 1 — Ngưỡng nào cấu hình được

| Phương án | Nội dung |
|---|---|
| **N-1 (đề xuất)** | Hai ngưỡng, mỗi ngưỡng từ 1 đến 365 ngày: (a) tiền nhận chưa đối soát quá **N ngày** thì cảnh báo (mặc định 30); (b) tạm ứng **không có hạn** quá hạn từ ngày thứ **M** sau ngày chi (mặc định 90). Nhóm tuổi nợ 30 / 60 / 90 trong báo cáo giữ cố định. |
| N-2 | Như N-1, thêm cấu hình ba mốc nhóm tuổi nợ (30 / 60 / 90). Phải kiểm tra mốc tăng dần; tiêu đề cột báo cáo đổi theo cài đặt. |

## Điểm 2 — Cấu hình theo đâu, ai đổi

| Phương án | Nội dung |
|---|---|
| **S-1 (đề xuất)** | Một bộ ngưỡng cho cả công ty, lưu trong cài đặt công ty (như GAP-106). Form trên trang **Ngân quỹ** chung, cạnh công tắc "bắt buộc chứng từ". Chỉ người có quyền chủ doanh nghiệp (`treasury.self_approve_expense`, như GAP-106) đổi được. Mỗi lần đổi ghi nhật ký giá trị cũ / mới. |
| S-2 | Như S-1, cộng thêm ghi đè theo từng dự án. Cần thêm chỗ lưu cho dự án và form trên trang Ngân quỹ dự án. |

## Điểm 3 — Hạn tạm ứng

| Phương án | Nội dung |
|---|---|
| **H-1 (đề xuất)** | Ô **"Hạn hoàn ứng"** (không bắt buộc) trên form lập / sửa đề nghị tạm ứng (web và API). Hạn phải từ ngày đề nghị trở đi. Khi duyệt & chi, hạn được chép sang khoản tạm ứng. Sau khi chi, người có quyền duyệt chi được **đặt / đổi / bỏ** hạn khi khoản còn nợ, **bắt buộc ghi lý do**, có nhật ký. Đổi hạn không phải bút toán nên không bị khoá kỳ chặn và không đổi số tiền. |
| H-2 | Như H-1 nhưng hạn **bắt buộc** khi lập đề nghị. Các khoản cũ vẫn không có hạn. |
| H-3 | Không nhập khi lập đề nghị; chỉ người duyệt đặt hạn lúc duyệt hoặc sau khi chi. |

## Điểm 4 — Quy tắc quá hạn

| Phương án | Nội dung |
|---|---|
| **Q-1 (đề xuất)** | Khoản **có hạn**: quá hạn từ ngày sau ngày hạn nếu còn nợ; số ngày quá hạn = hôm nay − ngày hạn. Khoản **không có hạn**: quá hạn từ ngày thứ M kể từ ngày chi (mặc định 90, đúng như cảnh báo hiện nay). |
| Q-2 | Chỉ khoản có hạn mới có thể quá hạn. Khoản không có hạn không bao giờ cảnh báo. Mặc định, khoản cũ sẽ **mất** cảnh báo hiện có. |

Theo Q-1, các chỗ hiển thị:
- Bảng tạm ứng trên trang Ngân quỹ dự án và trang chi tiết tạm ứng: thêm "Hạn" và nhãn **"Quá hạn X ngày"**.
- Báo cáo tuổi nợ tạm ứng: thêm hai cột "Hạn" và "Quá hạn (ngày)". Nhóm tuổi nợ vẫn tính theo số ngày từ ngày chi.
- Cảnh báo `overdue_advances` (trang Ngân quỹ dự án, trang tổng): đếm theo quy tắc trên. Tên cảnh báo đổi thành "Tạm ứng
  quá hạn".
- Cảnh báo tiền nhận chưa đối soát: dùng ngưỡng N. Tên hiện đúng số ngày đang cài.

## Điểm 5 — Phát hành

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Một PR: commit ngưỡng trước, rồi hạn tạm ứng. Một lần Gate 3. |
| P-2 | Hai PR, hai lần Gate 3. |

## Thiết kế chi tiết (theo N-1, S-1, H-1, Q-1, P-1)

### Dữ liệu (một migration, chỉ thêm cột cho phép trống)
- `treasury_financial_documents.due_date` (date, trống được): hạn ghi trên đề nghị tạm ứng. Chỉ dùng cho loại tạm ứng.
- `treasury_advances.due_date` (date, trống được): hạn đang hiệu lực của khoản tạm ứng.
- SQLite: thêm cột bằng `ALTER TABLE ADD COLUMN`, giữ nguyên các CHECK constraint của GAP-038, như GAP-105. MySQL: `after`
  bình thường. Có `down()` xoá hai cột.

Ngưỡng nằm trong `tenants.settings`, khoá `treasury_unreconciled_funding_days` và `treasury_advance_default_term_days`.
Không thêm bảng.

### Ngưỡng
**`TreasurySettingsService` (mới)**
- `thresholds(tenantId)`: trả hai ngưỡng; thiếu hoặc sai thì dùng mặc định 30 / 90.
- `setThresholds(actor, fundingDays, advanceDays)`:
  - kiểm tra số nguyên 1–365;
  - chỉ người có `treasury.self_approve_expense`;
  - khoá dòng tenant, lưu, ghi `AuditLog` `treasury.thresholds.changed` kèm giá trị cũ / mới.

`TreasuryDashboardService` đọc ngưỡng N thay hằng số 30, và đếm tạm ứng quá hạn theo trường `overdue` của dòng tuổi nợ
thay vì nhóm `over_90`.

Form trên trang Ngân quỹ chung (`treasury/index.blade.php`), chỉ hiện với người được đổi:
- route web `POST /treasury/settings/thresholds`;
- middleware `rbac:treasury.self_approve_expense`.

### Hạn tạm ứng
**`TreasuryAdvanceService`**
- `createDraft` / `updateDraft` / `copyToDraft` nhận và chép `due_date`; hạn phải ≥ ngày đề nghị.
- `approve` chép `due_date` sang dòng `treasury_advances`.
- `changeDueDate(project, actor, document, ?date, reason)`:
  - chỉ khi khoản đã chi và còn nợ;
  - quyền `treasury.approve-expense` trên dự án;
  - lý do bắt buộc;
  - khoá dòng tạm ứng; ghi audit `treasury.advance.due_date_changed` kèm giá trị cũ / mới và lý do.
- `summaryRow` thêm `due_date`, `effective_due_date`, `overdue` (bool) và `overdue_days`.
  - Có hạn: hạn hiệu lực = ngày hạn.
  - Không có hạn: hạn hiệu lực = ngày chi + (M − 1) ngày. Như vậy với M = 90, khoản quá hạn đúng từ ngày thứ 90, như hiện
    nay.

Route đổi hạn:
- web `POST /projects/{project}/treasury/advances/{treasuryAdvance}/due-date` (`rbac:treasury.approve_expense`);
- API `PUT /api/zena/projects/{project}/treasury/advances/{treasuryAdvance}/due-date` (`rbac:treasury.approve_expense`).

API lập / sửa đề nghị nhận `due_date` (`nullable|date_format:Y-m-d`). Dữ liệu trả về có `due_date` và các trường quá hạn.

### Quyền
Không thêm quyền mới:
- đổi ngưỡng: `treasury.self_approve_expense`;
- đổi hạn: `treasury.approve_expense` cộng chính sách duyệt chi của dự án;
- xem: `treasury.view-project`.

### Test (đỏ trước / xanh sau)
**Ngưỡng (`TreasuryThresholdsTest`)**
- Mặc định 30 / 90 cho kết quả giống hiện nay.
- Đổi N thì cảnh báo tiền nhận đổi theo; đổi M thì tạm ứng không có hạn đổi theo.
- Giá trị ngoài 1–365 bị từ chối.
- Chỉ Owner đổi được; có nhật ký.
- Cài đặt riêng từng công ty.
- Form và quyền trên web.

**Hạn tạm ứng (`TreasuryAdvanceDueDateTest`)**
- Nhập hạn khi lập / sửa (web, API); hạn trước ngày đề nghị bị từ chối.
- Duyệt thì chép hạn sang khoản tạm ứng.
- Quá hạn và số ngày quá hạn đúng: có hạn, không có hạn, đã trả hết thì không quá hạn.
- Đổi hạn:
  - cần quyền duyệt chi và lý do; có nhật ký;
  - không đổi số tiền hay số dư;
  - vẫn được trong kỳ đã khoá.
- Bảng, trang chi tiết, báo cáo và cảnh báo hiển thị đúng.
- Cách ly tenant / dự án.

**Migration**: chạy lên / gỡ / lên trên MySQL 8 thật. Hai file test mới cũng chạy xanh với MySQL.

### Files (allowlist)
- Mới:
  - `database/migrations/2026_11_04_160000_add_due_date_to_treasury_advances.php`;
  - `app/Services/Treasury/TreasurySettingsService.php`;
  - `app/Http/Controllers/Web/Treasury/TreasurySettingsPageController.php`;
  - `tests/Feature/Treasury/TreasuryThresholdsTest.php`, `tests/Feature/Treasury/TreasuryAdvanceDueDateTest.php`.
- Sửa:
  - `app/Models/Treasury/TreasuryAdvance.php`, `app/Models/Treasury/TreasuryFinancialDocument.php` (fillable / cast);
  - `app/Services/Treasury/TreasuryAdvanceService.php`, `TreasuryDashboardService.php`, `TreasuryReportService.php`;
  - `app/Http/Controllers/Api/Treasury/TreasuryAdvanceController.php`;
  - `app/Http/Controllers/Web/Treasury/TreasuryAdvancePageController.php`, `TreasuryPageController.php`;
  - `routes/web.php`, `routes/api_zena.php`;
  - `resources/views/treasury/index.blade.php`, `_advances-card.blade.php`, `advance.blade.php`, `_dashboard.blade.php`.
- Plan `docs/superpowers/plans/2026-11-04-gap-108-treasury-thresholds-advance-deadline.md`; packet và evidence GAP-108.

Không phụ thuộc mới, không quyền mới, không đổi số liệu đã ghi sổ.

## Ngoài phạm vi

Nhắc hạn tự động (email / Slack / Zalo); phạt / lãi trễ hạn; ghi đè ngưỡng theo dự án (nếu chọn S-1); cấu hình nhóm tuổi
nợ (nếu chọn N-1); deploy.

## Verification required at Gate 3

Đỏ trước / xanh sau; Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới; migration chạy trên MySQL thật;
PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch; CI trên đúng head xanh.

## Rollback

Revert squash commit. Migration `down()` xoá hai cột `due_date`, nên mất các hạn đã nhập; sổ cái không đổi. Hai khoá ngưỡng
còn lại trong cài đặt công ty nhưng không được đọc nữa.

## Decision Needed

Owner chọn: `APPROVE GAP-108 Gate 2 N-1 S-1 H-1 Q-1 P-1` (hoặc phương án khác cho từng điểm) / Changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
