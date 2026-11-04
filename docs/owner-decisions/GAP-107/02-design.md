---
work_id: GAP-107
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-11-04-gap-107-treasury-period-lock-bank-import-evidence.md
  plan: null
  branch: docs/GAP-107-treasury-period-lock-bank-import
  pr: https://github.com/kha997/zenamanagephp/pull/397
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
  created_at: "2026-11-04T11:20:00+07:00"
  updated_at: "2026-11-04T11:20:00+07:00"
generated_by: agent
---

# GAP-107 — Treasury period lock and bank statement import: Gate 2 design

## Owner Summary

Anh/chị cần chọn **5 điểm**:

1. **Phạm vi khoá kỳ** — đề xuất **L-1**: khoá theo từng dự án.
2. **Mở khoá** — đề xuất **U-1**: được mở lại, bắt buộc ghi lý do.
3. **Đối soát trong kỳ đã khoá** — đề xuất **R-1**: vẫn cho đối soát. Đối soát không đổi số tiền, mà sao kê thường về muộn.
4. **Định dạng sao kê** — đề xuất **I-1**: chỉ nhận CSV, có bước chọn cột. Repo **chưa có** thư viện đọc Excel; muốn đọc
   Excel phải thêm thư viện mới.
5. **Phát hành** — đề xuất **P-1**: một PR, hai phần (khoá kỳ trước, import sau), một Gate 3.

## Điểm 1 — Phạm vi khoá kỳ

| Phương án | Nội dung |
|---|---|
| **L-1 (đề xuất)** | Mỗi dự án có mốc "đã khoá đến hết ngày D". Từ chối mọi bút toán mới có ngày ≤ D: tiền nhận, góp vốn, chuyển ví, điều chỉnh, đảo, duyệt chi, duyệt tạm ứng, nộp lại / quyết toán tạm ứng, chặng tuyến tiền. Mốc chỉ được tiến lên khi khoá. |
| L-2 | Một mốc chung cho cả công ty. Đơn giản hơn, nhưng các dự án chốt sổ khác ngày nhau thì vướng. |

Theo L-1, các khoản đã có từ trước không thay đổi gì. Khoá chỉ chặn ghi **thêm** vào kỳ đã khoá.

Đảo một khoản thuộc kỳ đã khoá vẫn được, nhưng **ngày của bút toán đảo** phải sau D. Ví dụ: sửa sai tháng 10 sau khi đã
khoá tháng 10 thì bút toán đảo mang ngày tháng 11.

## Điểm 2 — Mở khoá

| Phương án | Nội dung |
|---|---|
| **U-1 (đề xuất)** | Người có quyền `treasury.manage_period_lock` (chỉ Owner) được lùi mốc về ngày sớm hơn hoặc bỏ khoá, bắt buộc ghi lý do. Mọi lần khoá / mở đều lưu lịch sử và ghi nhật ký. |
| U-2 | Không cho mở khoá. Sai trong kỳ đã khoá chỉ sửa bằng bút toán ở kỳ sau. |

## Điểm 3 — Đối soát trong kỳ đã khoá

| Phương án | Nội dung |
|---|---|
| **R-1 (đề xuất)** | Vẫn cho đối soát / bỏ đối soát giao dịch trong kỳ đã khoá. Đối soát chỉ xác nhận "đã khớp sao kê", không đổi số tiền hay số dư. |
| R-2 | Chặn cả đối soát trong kỳ đã khoá. Sao kê về muộn thì phải mở khoá. |

## Điểm 4 — Định dạng sao kê

| Phương án | Nội dung |
|---|---|
| **I-1 (đề xuất)** | **CSV** (UTF-8 hoặc Windows-1258/1252). Khi tải lên, chọn cột: ngày, số tiền (một cột có dấu, hoặc hai cột Nợ / Có), diễn giải, số tham chiếu. Lần sau tự gợi ý theo lần import trước của ví đó. File Excel thì người dùng lưu thành CSV từ Excel. |
| I-2 | CSV + Excel (.xlsx) bằng thư viện `phpoffice/phpspreadsheet`. Đây là **phụ thuộc mới** (thêm vào `composer.json` / `composer.lock`, phải qua kiểm tra bảo mật). |
| I-3 | Mẫu sẵn cho từng ngân hàng (Vietcombank, Techcombank, BIDV…). Cần file mẫu thật của từng ngân hàng; định dạng ngân hàng đổi thì phải sửa mã. |

## Điểm 5 — Phát hành

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Một PR, commit khoá kỳ trước rồi đến import, một lần Gate 3. |
| P-2 | Hai PR, hai lần Gate 3. |

## Thiết kế chi tiết (theo L-1, U-1, R-1, I-1, P-1)

### Dữ liệu (một migration, chỉ thêm bảng mới)

**`treasury_period_locks`** — lịch sử khoá; mốc hiện hành là dòng mới nhất của dự án.
- Cột: `id`, `tenant_id`, `project_id`, `locked_through` (ngày, cho phép trống = bỏ khoá), `reason`, `created_by`,
  timestamps.

**`treasury_bank_statement_imports`**
- Cột:
  - `id`, `tenant_id`, `project_id`, `wallet_id` (composite FK);
  - `statement_reference`, `original_name`, `file_hash` (sha256);
  - `column_map` (json), `line_count`, `created_by`, timestamps.
- Ràng buộc unique (`wallet_id`, `file_hash`): không import trùng file cho cùng một ví.

**`treasury_bank_statement_lines`**
- Cột:
  - `id`, `tenant_id`, `import_id`, `line_no`;
  - `transaction_date`, `direction` (vào / ra), `amount`, `description`, `bank_reference`, `fingerprint`;
  - `status` (unmatched / matched / ignored), `ledger_entry_id` (cho phép trống), `reconciliation_id` (cho phép trống),
    timestamps.
- Dòng trùng với dòng của lần import trước (cùng ví, cùng ngày + số tiền + chiều + tham chiếu + diễn giải) được đánh
  dấu "đã có ở lần import trước", không tính lại.

### Khoá kỳ
**`TreasuryPeriodLockService` (mới)**
- `lock(project, actor, through, reason)`: chỉ cho tiến mốc.
- `unlock(project, actor, through|null, reason)`: lùi mốc hoặc bỏ khoá.
- `lockedThrough(project)`.
- `requireOpen(project, date, field)`: ném lỗi "Kỳ đến dd/mm/yyyy đã khoá".

Gọi `requireOpen` ở các lối ghi sổ:
- `TreasuryPostingService`: `declareFunding`, `transfer`, `adjust`, `reverse`;
- `TreasuryExpenseService::approve`;
- `TreasuryAdvanceService`: `approve`, `recordReturn`, `approveSettlementRequest`, `removeSettlement`;
- `TreasuryRouteService`: `recordLeg` / đảo chặng.

Kiểm tra nằm **trong** transaction đã khoá dòng, nên khoá và ghi sổ chạy đồng thời vẫn không lọt.

Trên trang Ngân quỹ dự án:
- hiện "Đã khoá đến dd/mm/yyyy";
- form khoá / mở và lịch sử chỉ hiện với người có quyền khoá kỳ.

### Import sao kê
**`TreasuryBankImportService` (mới)**

1. **`parse(file, column_map)`**
   - Đọc CSV: tự nhận dấu phân cách `,` / `;` / tab, mã hoá UTF-8 / Windows-1258.
   - Đọc số tiền kiểu `1.234.567` hoặc `1,234,567.00`; ngày kiểu `dd/mm/yyyy` hoặc `yyyy-mm-dd`.
   - Giới hạn: 2 MB, 5.000 dòng.
   - Dòng lỗi được báo kèm số dòng, không import.
2. **`import(project, actor, wallet, file, map, statement_reference)`**: chỉ dùng cho ví loại ngân hàng; lưu lần import và
   các dòng.
3. **`suggestions(import)`**: gợi ý ghép mỗi dòng chưa ghép với **một** bút toán chưa đối soát của ví đó.
   - Điều kiện ghép: cùng chiều, cùng số tiền, ngày lệch tối đa ±3 ngày.
   - Ưu tiên ngày gần nhất; mỗi bút toán chỉ ghép với một dòng.
4. **`confirm(project, actor, import, pairs)`**: gọi `TreasuryReconciliationService::reconcile`
   - loại `bank`, tham chiếu = số sao kê;
   - ngày đối soát = hôm nay;
   - các dòng được ghép chuyển "matched".
5. **`ignore(line, reason)`**: cho dòng ngân hàng không thuộc dự án, ví dụ phí khác.

Nguyên tắc:
- **Không bao giờ** tự tạo khoản thu / chi từ dòng sao kê.
- Bỏ đối soát (`undoEntry` / `undoReconciliation`) thì dòng trở lại "unmatched".

Trang "Import sao kê" của ví ngân hàng:
- tải file, chọn cột, xem trước 10 dòng;
- danh sách dòng kèm gợi ý; tích các cặp đúng, bấm "Xác nhận đối soát";
- lịch sử các lần import.

### Quyền
- Khoá / mở kỳ: `rbac:treasury.manage_period_lock` + chính sách Ngân quỹ dự án.
- Import / xác nhận / bỏ qua dòng: `rbac:treasury.reconcile` + chính sách đối soát sẵn có (`treasury.reconcile`).
- Xem: `treasury.view-project`.
- Không thêm quyền mới.

### Test (đỏ trước / xanh sau)
**Khoá kỳ**
- Từng lối ghi sổ bị chặn khi ngày ≤ mốc, và vẫn chạy khi ngày > mốc.
- Đảo khoản cũ bằng bút toán ngày mới thì được.
- Chỉ người có quyền mới khoá được; mốc chỉ tiến khi khoá; mở khoá cần lý do; có lịch sử và nhật ký.
- Đối soát trong kỳ khoá vẫn được (R-1).
- Khoá và ghi sổ đồng thời trên MySQL thật.

**Import**
- Phân tích CSV: dấu phân cách, mã hoá, Nợ / Có, định dạng số / ngày; báo lỗi đúng dòng.
- Import trùng file bị chặn; dòng trùng lần trước được đánh dấu.
- Gợi ý ghép đúng (chiều, số tiền, ±3 ngày, mỗi bút toán một lần).
- Xác nhận → đối soát loại `bank`, số dư không đổi; bỏ đối soát → dòng trở lại "unmatched".
- Không tạo chứng từ nào; chỉ ví ngân hàng; cách ly tenant / dự án.

**Migration**: chạy trên MySQL thật (lên / gỡ / lên).

### Files (allowlist)
- Mới:
  - `database/migrations/2026_11_04_120000_create_treasury_period_lock_and_bank_import_tables.php`;
  - models `app/Models/Treasury/TreasuryPeriodLock.php`, `TreasuryBankStatementImport.php`,
    `TreasuryBankStatementLine.php`;
  - services `app/Services/Treasury/TreasuryPeriodLockService.php`, `TreasuryBankImportService.php`;
  - controller `app/Http/Controllers/Web/Treasury/TreasuryPeriodPageController.php`;
  - views `resources/views/treasury/bank-import.blade.php`, `_period-lock.blade.php`.
- Sửa:
  - `app/Services/Treasury/TreasuryPostingService.php`, `TreasuryExpenseService.php`, `TreasuryAdvanceService.php`,
    `TreasuryRouteService.php` (gọi `requireOpen`);
  - `TreasuryReconciliationService.php` (bỏ đối soát → mở lại dòng sao kê);
  - `app/Http/Controllers/Web/Treasury/TreasuryPageController.php`, `routes/web.php`,
    `resources/views/treasury/project.blade.php`.
- Test mới: `tests/Feature/Treasury/TreasuryPeriodLockTest.php`, `TreasuryBankImportTest.php`; nhóm MySQL cho khoá
  đồng thời nếu cần.
- Plan `docs/superpowers/plans/2026-11-04-gap-107-treasury-period-lock-bank-import.md`; packet và evidence GAP-107.

Không phụ thuộc mới (theo I-1), không quyền mới, không đổi số liệu đã ghi sổ.

## Ngoài phạm vi

API ngân hàng; OCR PDF; tự tạo chứng từ từ dòng sao kê; Excel (nếu chọn I-1); mẫu theo ngân hàng; ngưỡng / hạn tạm ứng;
deploy.

## Verification required at Gate 3

Đỏ trước / xanh sau; Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới; migration và khoá đồng thời chạy
trên MySQL thật; PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch; CI trên đúng head xanh.

## Rollback

Revert squash commit; migration `down()` xoá 3 bảng mới (mất lịch sử khoá và các lần import; sổ cái và đối soát không đổi).

## Decision Needed

Owner chọn: `APPROVE GAP-107 Gate 2 L-1 U-1 R-1 I-1 P-1` (hoặc phương án khác cho từng điểm) / Changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
