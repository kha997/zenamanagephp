---
work_id: GAP-105
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/audits/2026-11-03-gap-105-treasury-boq-fund-chains-receivable-evidence.md
  plan: docs/superpowers/plans/2026-11-03-gap-105-treasury-boq-fund-chains-receivable.md
  branch: docs/GAP-105-treasury-boq-fund-chains-receivable
  pr: https://github.com/kha997/zenamanagephp/pull/395
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
  created_at: "2026-11-03T23:35:22+07:00"
  updated_at: "2026-11-03T23:35:22+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-105 at subject 386af8e59c7e90a7342c9168ea05e30540aae4ca (Gate 2 R-1, B-1, F-1, P-1; exact allowlist; one PR, three commits). R-1: TreasuryReceivableService - per non-cancelled contract value, paid (ContractPayment paid), Treasury arrivals via completed contract-payment routes (informational) and outstanding (negative marked overpaid); unlinked posted Treasury funding reported apart, never subtracted; no contracts = no data; dashboard cards contract_value / receivable_outstanding (null without contracts); report investor_receivable. F-1: TreasuryFundChainService - create chain (unique reference per project), add posted funding/owner_contribution/internal_transfer/expense/advance/advance_return documents or non-cancelled routes of the same project, remove members; one chain per member (existing unique indexes, concurrent duplicate caught); metadata only, wallet balances unchanged; every change audited (entity_type treasury_fund_chain); pages under /app/projects/{project}/treasury/fund-chains (view: rbac treasury.view + treasury.view-project; edit: rbac treasury.manage_routes + treasury.manage-routes policy); report fund_chain_trace; register filter by fund chain. B-1: migration 2026_11_03_120000 adds nullable treasury_financial_documents.boq_line_item_id, indexed, FK to boq_line_items ON DELETE RESTRICT (SQLite: column-level REFERENCES via ALTER TABLE ADD COLUMN so GAP-038 CHECK constraints are not lost to a table rebuild); TreasuryExpenseService sets it only on draft create/update (and copy-to-draft) after checking the line belongs to a BOQ of the same tenant and project; expense form selector; report expense_by_boq (posted, non-reversed expenses per line; budget = quantity x unit price only when the price is set, else no data; unlinked row); dashboard alert boq_overrun; register filter by BOQ line. No new permission; ContractPayment, receivables aging and posting unchanged. Tests: TreasuryReceivableTest 7, TreasuryFundChainTest 5, TreasuryBoqLinkTest 5 - each red against the code before its slice, green after; the 17 also pass with DB_CONNECTION=mysql on MySQL 8; migration up, down, up verified on MySQL 8 with the FK and all CHECK constraints present. Treasury feature suite 200 green. Unit + Feature + Integration 2907 tests with only the 2 known root-only failures; Feature/Api 824 green. PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, route guard, deptrac pass. Exact-head CI pending at packet time."
technical_evidence:
  base_sha: "9e758cb89f8a398fc14868601ac9c5bc23fc2a29"
  subject_sha: "386af8e59c7e90a7342c9168ea05e30540aae4ca"
  implementation_tree_digest: "b0ec9eecaf98e755dc967355df64b26cb48e704a757fa931e427231ea5fc7511"
  verified_pr_head_sha: "386af8e59c7e90a7342c9168ea05e30540aae4ca"
  verified_at: "2026-11-03T23:35:22+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-105 — Gate 3 release decision (Treasury: BOQ link, fund chains, investor receivable)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Ngân quỹ chưa trả lời được ba câu:
- chi cho dòng BOQ nào;
- tiền đi qua những bước nào (chuỗi quỹ);
- nhà đầu tư còn nợ bao nhiêu.

Ba phần này S6 (GAP-097) đã để lại.

**2. Sau thay đổi (Gate 2 R-1, B-1, F-1, P-1)** — subject `386af8e5`:

**Phải thu nhà đầu tư:**
- Báo cáo mới "Phải thu nhà đầu tư", tính theo từng hợp đồng: giá trị, đã thu (các đợt thanh toán đã trả), phần đã về ví
  Ngân quỹ (chỉ để tham khảo), còn phải thu.
- Thu vượt thì số còn phải thu là số âm, ghi kèm "thu vượt".
- Tiền nhận khác vào Ngân quỹ hiện ở một dòng riêng và **không trừ** vào phải thu, nên không đếm trùng.
- Trang Ngân quỹ có thêm 2 thẻ "Giá trị hợp đồng" và "Còn phải thu". Không có hợp đồng thì ghi "Chưa có dữ liệu".

**Chuỗi quỹ:**
- Nút "Chuỗi quỹ" trên trang Ngân quỹ.
- Kế toán tạo chuỗi, rồi thêm hoặc bỏ chứng từ đã ghi sổ và tuyến tiền của dự án.
- Mỗi chứng từ hoặc tuyến chỉ thuộc một chuỗi.
- Việc gắn vào chuỗi **không đổi số dư**, và có ghi nhật ký.
- Có báo cáo "Truy vết chuỗi quỹ" và bộ lọc sổ giao dịch theo chuỗi.
- Quyền: xem cần `treasury.view`; sửa cần `treasury.manage_routes`.

**BOQ:**
- Form khoản chi có ô "Dòng BOQ". Ô này chỉ đổi được khi khoản chi còn là nháp, và dòng BOQ phải thuộc dự án.
- Báo cáo "Chi theo BOQ" gồm đã chi, ngân sách và còn lại.
  - Ngân sách = khối lượng × đơn giá, chỉ tính khi có đơn giá; thiếu đơn giá thì ghi "Chưa có dữ liệu".
  - Có dòng "Chưa gắn BOQ".
- Cảnh báo "Dòng BOQ chi vượt ngân sách".
- Bộ lọc sổ giao dịch theo dòng BOQ.
- Dòng BOQ đang được chứng từ dùng thì không xoá được.

Không thêm quyền mới. Không đổi đợt thanh toán hợp đồng, công nợ hiện có hay cách ghi sổ. Một migration thêm 1 cột cho phép
trống.

**3. Đã kiểm thử** — base `9e758cb8`; subject `386af8e59c7e90a7342c9168ea05e30540aae4ca`;
digest `b0ec9eecaf98e755dc967355df64b26cb48e704a757fa931e427231ea5fc7511`.

- **Đỏ trước / xanh sau** cho cả 3 phần; mỗi phần đỏ khi chạy với mã trước phần đó:
  - phải thu: 7 test;
  - chuỗi quỹ: 5 test;
  - BOQ: 5 test.
- **MySQL 8 thật:**
  - migration chạy lên, gỡ, rồi chạy lại đều được;
  - khoá ngoại "chặn xoá" và toàn bộ ràng buộc CHECK cũ còn nguyên;
  - 17 test mới đều xanh khi chạy với MySQL.
- Trên SQLite, cột mới được thêm theo cách không dựng lại bảng, nên không mất các ràng buộc CHECK.
- **Toàn bộ Ngân quỹ:** 200 test xanh.
- **Unit + Feature + Integration:** 2.907 test, chỉ 2 lỗi có sẵn do chạy bằng root. **Feature/Api:** 824 test xanh.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head: đang chạy lúc lập hồ sơ.

**4. Lưu ý vận hành**
- Nhà đầu tư trả thẳng vào Ngân quỹ thì vẫn cần ghi đợt thanh toán hợp đồng như hiện nay, khi đó phải thu mới giảm.
- Dòng BOQ thiếu đơn giá thì không tính được vượt ngân sách.

**5. Ngoài phạm vi** — chia một khoản chi cho nhiều dòng BOQ; gắn BOQ cho tạm ứng / phân bổ chi phí; tự động tạo chuỗi; chứng
từ đính kèm; khoá kỳ / import sao kê; ngưỡng cấu hình; lợi nhuận; deploy.

**6. Rủi ro còn lại** — Thấp:
- phần lớn là đọc dữ liệu;
- chỉ ghi nhãn (BOQ khi còn nháp, thành viên chuỗi), không đổi sổ cái;
- migration chỉ thêm cột.

**7. Hoàn tác** — revert PR. Migration có `down()` để xoá cột; khi đó chỉ mất nhãn BOQ đã gắn.

**8. Đề xuất** — Phát hành (merge).

## Decision Needed

Owner chọn: `APPROVE GAP-105 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
