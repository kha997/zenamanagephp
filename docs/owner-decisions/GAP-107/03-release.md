---
work_id: GAP-107
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-04-gap-107-treasury-period-lock-bank-import-evidence.md
  plan: docs/superpowers/plans/2026-11-04-gap-107-treasury-period-lock-bank-import.md
  branch: docs/GAP-107-treasury-period-lock-bank-import
  pr: https://github.com/kha997/zenamanagephp/pull/397
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-04T15:07:19+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-11-04, verbatim: 'APPROVE GAP-107 Gate 3'. Given on the packet at PR head 866981d6ae79231a363dd0a8767f3e6ccf5369b6; bound to implementation subject 38b3f79ae8899c5a85e668546bbc8324f0de64a9 and implementation-tree digest 3aed1260da129be6cd98d907ecd63683c281b938b4922eee71d6fa9891f616b5 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-04T15:05:44+07:00"
  updated_at: "2026-11-04T15:07:19+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-107 at subject 38b3f79ae8899c5a85e668546bbc8324f0de64a9 (Gate 2 L-1, U-1, R-1, I-1, P-1; exact allowlist; one PR, two commits). One migration creates treasury_period_locks, treasury_bank_statement_imports (composite FK to the wallet, unique wallet + sha256) and treasury_bank_statement_lines (composite FKs to import, ledger entry, reconciliation); no existing table changes. Period lock: TreasuryPeriodLockService lock forward only and not in the future, unlock / move back with a mandatory reason, history rows and audit, only treasury.manage_period_lock holders; requireOpen runs first in the posting transactions of funding / owner contribution, transfer, adjustment, reversal, expense approval, advance approval, advance return, settlement approve / remove, route leg record / reverse, and locks the project row FOR UPDATE so a covering lock and a posting serialize; reversing an old document dated after the lock works; reconciliation stays allowed (R-1); lock panel on the project Treasury page. Bank import: TreasuryBankImportService for bank wallets (company_bank, owner_personal, employee_bank): CSV only (comma / semicolon / tab, UTF-8 or Windows-1258/1252, signed amount or debit + credit, VN / EN number formats, dd/mm/yyyy or yyyy-mm-dd, 2 MB, 5,000 lines, errors by line), duplicate file refused, lines seen in an earlier import marked duplicate, suggestions (same direction and amount, date within 3 days, nearest first, one ledger entry each), confirmation through TreasuryReconciliationService::reconcile (bank_statement, statement reference, today), ignore with a reason; undoing a reconciliation line or a whole reconciliation reopens its statement lines; no Treasury document is ever created from a bank line; pages read under the Treasury project policy and write under treasury.reconcile. No new permission, no new dependency. Tests: TreasuryPeriodLockTest 6 and TreasuryBankImportTest 5, each red before its commit and green after; the 11 also green with DB_CONNECTION=mysql on MySQL 8; migration up, down, up on MySQL 8; a two-process MySQL race showed a lock request waiting about 2.6 s for an open posting transaction to commit (manual script, not a CI test). Treasury feature + architecture suites green. Unit + Feature + Integration 2926 tests with only the 2 known root-only failures; Feature/Api 824 green. PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, route guard, deptrac pass. Exact-head CI pending at packet time."
technical_evidence:
  base_sha: "249c8b7194400552aae88a65055c14d1df200d3c"
  subject_sha: "38b3f79ae8899c5a85e668546bbc8324f0de64a9"
  implementation_tree_digest: "3aed1260da129be6cd98d907ecd63683c281b938b4922eee71d6fa9891f616b5"
  verified_pr_head_sha: "38b3f79ae8899c5a85e668546bbc8324f0de64a9"
  verified_at: "2026-11-04T15:05:44+07:00"
owner_decision_binding:
  implementation_tree_digest: "3aed1260da129be6cd98d907ecd63683c281b938b4922eee71d6fa9891f616b5"
  decision_recorded_at: "2026-11-04T15:07:19+07:00"
---

# GAP-107 — Gate 3 release decision (Treasury period lock and bank statement import)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-04 ("APPROVE GAP-107 Gate 3"), bound to implementation subject
`38b3f79ae8899c5a85e668546bbc8324f0de64a9` and implementation-tree digest `3aed1260da129be6cd98d907ecd63683c281b938b4922eee71d6fa9891f616b5`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Số liệu tháng đã chốt vẫn có thể bị ghi thêm hoặc lùi ngày; đối soát ngân hàng phải tích từng giao
dịch bằng tay.

**2. Sau thay đổi (Gate 2 L-1, U-1, R-1, I-1, P-1)** — subject `38b3f79a`:

**Khoá kỳ**
- Trang Ngân quỹ dự án có ô **"Khoá kỳ"**.
- Owner chọn "khoá đến hết ngày D". Sau đó mọi bút toán mới có ngày ≤ D đều bị từ chối, gồm:
  - tiền nhận, góp vốn, chuyển ví, điều chỉnh, đảo;
  - duyệt chi, duyệt tạm ứng, nộp lại tạm ứng, quyết toán tạm ứng;
  - chặng tuyến tiền.
- Sửa sai kỳ cũ: đảo bằng bút toán mang ngày sau D.
- Mốc khoá:
  - chỉ tiến lên, không khoá ngày tương lai;
  - **mở khoá** (lùi mốc / bỏ khoá) phải ghi lý do.
- Có lịch sử khoá / mở và nhật ký.
- Đối soát vẫn làm được trong kỳ đã khoá.

**Import sao kê**
- Ví ngân hàng có nút **"Import sao kê"**.
- Tải file CSV (Excel thì lưu thành "CSV UTF-8"), chọn số thứ tự cột: ngày, số tiền (hoặc Nợ / Có), diễn giải, số tham
  chiếu. Lần sau tự gợi ý cột theo lần trước.
- Đọc được: dấu `,` `;` tab; tiếng Việt UTF-8 hoặc Windows; số kiểu `1.500.000` hoặc `1,500,000.00`; ngày `dd/mm/yyyy`
  hoặc `yyyy-mm-dd`.
- Dòng lỗi báo đúng số dòng.
- Mỗi dòng sao kê được **gợi ý một giao dịch** chưa đối soát của ví: cùng chiều, cùng số tiền, ngày lệch tối đa 3 ngày.
- Người dùng tích các cặp đúng, bấm xác nhận; hệ thống ghi đối soát loại "sao kê ngân hàng" với số sao kê. Số dư không
  đổi.
- Dòng không thuộc dự án → "Bỏ qua" kèm lý do.
- Không import trùng file; dòng đã có ở lần import trước được đánh dấu.
- Bỏ đối soát thì dòng sao kê trở lại "chờ ghép".
- **Không bao giờ tự tạo khoản thu / chi** từ dòng sao kê.

**Quyền**
- Khoá / mở kỳ: chỉ Owner (`treasury.manage_period_lock`).
- Import / xác nhận: người có quyền đối soát.
- Xem: người có quyền Ngân quỹ dự án.

Không thêm quyền mới, không thêm thư viện mới, không đổi số liệu đã ghi sổ. Có 1 migration tạo 3 bảng mới.

**3. Đã kiểm thử** — base `249c8b71`; subject `38b3f79ae8899c5a85e668546bbc8324f0de64a9`;
digest `3aed1260da129be6cd98d907ecd63683c281b938b4922eee71d6fa9891f616b5`.
- **Khoá kỳ:** 6 test mới, hỏng khi chưa có mã, xanh khi có mã. Các test này kiểm tra:
  - từng lối ghi sổ bị chặn trong kỳ khoá và chạy được sau kỳ khoá;
  - đảo khoản cũ bằng ngày mới vẫn được;
  - đối soát vẫn được trong kỳ khoá;
  - mốc chỉ tiến lên; mở khoá cần lý do; có lịch sử và nhật ký;
  - khoá theo từng dự án;
  - trang, form và quyền.
- **Import sao kê:** 5 test mới, hỏng khi chưa có mã, xanh khi có mã. Các test này kiểm tra:
  - đọc CSV đủ kiểu: dấu phân cách, mã hoá, Nợ / Có, định dạng số / ngày, báo lỗi đúng dòng;
  - gợi ý đúng giao dịch; xác nhận → đối soát, số dư và số chứng từ không đổi; bỏ đối soát → dòng mở lại;
  - trùng file, dòng trùng, chỉ ví ngân hàng, bỏ qua cần lý do, cặp không khớp bị từ chối;
  - trang và quyền.
- **MySQL 8 thật:**
  - migration chạy lên, gỡ, rồi chạy lại đều được;
  - 11 test mới xanh khi chạy với MySQL;
  - thử **tranh chấp thật bằng hai tiến trình**: lệnh khoá phải chờ khoảng 2,6 giây cho tới khi giao dịch ghi sổ đang mở
    commit xong. Hai thao tác xếp hàng đúng như thiết kế. Đây là kiểm tra bằng script thủ công, chưa đưa vào CI.
- **Toàn bộ Ngân quỹ và test kiến trúc:** xanh.
- **Unit + Feature + Integration:** 2.926 test, chỉ 2 lỗi có sẵn do chạy bằng root. **Feature/Api:** 824 test xanh.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head: đang chạy lúc lập hồ sơ.

**4. Lưu ý vận hành**
- Chỉ nhận CSV; file Excel phải lưu thành CSV trước.
- Ghép tự động chỉ khi khớp đúng số tiền và chiều. Một giao dịch gộp nhiều dòng sao kê (hoặc ngược lại) thì vẫn đối soát
  tay như hiện nay.

**5. Ngoài phạm vi** — API ngân hàng; đọc PDF; tự tạo chứng từ; đọc Excel trực tiếp; mẫu riêng từng ngân hàng; ngưỡng /
hạn tạm ứng; deploy.

**6. Rủi ro còn lại** — Thấp:
- khoá kỳ chỉ chặn ghi mới, không sửa số đã có;
- import chỉ tạo đối soát qua chức năng sẵn có, hoàn tác được;
- khoá thêm dòng dự án làm các lần ghi sổ của cùng một dự án chạy lần lượt. Ảnh hưởng nhỏ vì mỗi lần ghi rất nhanh.

**7. Hoàn tác** — revert PR. Migration `down()` xoá 3 bảng mới (mất lịch sử khoá và các lần import); sổ cái và đối soát
không đổi.

**8. Đề xuất** — Phát hành (merge).

## Decision Needed

Owner chọn: `APPROVE GAP-107 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
