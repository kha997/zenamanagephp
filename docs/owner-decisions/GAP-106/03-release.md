---
work_id: GAP-106
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
  spec: docs/audits/2026-11-04-gap-106-treasury-evidence-attachments-evidence.md
  plan: docs/superpowers/plans/2026-11-04-gap-106-treasury-evidence-attachments.md
  branch: docs/GAP-106-treasury-evidence-attachments
  pr: https://github.com/kha997/zenamanagephp/pull/396
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
  created_at: "2026-11-04T09:31:00+07:00"
  updated_at: "2026-11-04T09:31:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-106 at subject b3f088090d773a1cdb364212a5ef12e7438d521c (Gate 2 S-2, E-1, M-1, D-1; allowlist plus Owner amendment 1). New table treasury_document_attachments (composite FK (tenant_id, financial_document_id) to treasury_financial_documents, unique (financial_document_id, file_hash)); files on the existing private default disk under treasury/{tenant}/{project}/{document}/, read and downloaded only through Treasury routes guarded by rbac treasury.view plus the treasury.view-project policy, downloads as attachment with X-Content-Type-Options nosniff; Treasury files never appear in /app/documents (test). TreasuryEvidenceService: attach (nine evidence types; pdf/jpg/jpeg/png/webp/xlsx/xls/docx/doc checked on client and detected extension; max 10 MB; adder = document creator, treasury.approve-expense or treasury.reconcile holder; duplicate sha256 refused, concurrent duplicate caught on the unique index; not on reversal documents), remove (uploader only, while draft/submitted/rejected), counts per document in one query, missing evidence = posted non-reversed expenses without a file; tenant switch tenants.settings.treasury_evidence_required_for_expenses (default off; only treasury.self_approve_expense holders; audited). TreasuryExpenseService::submit refuses an expense without evidence when the switch is on. UI: evidence page per Treasury document (GET .../treasury/documents/{doc}/evidence) with list, attach form, download and remove; 'Chung tu (n)' links on register and pending-expense rows; switch on the Treasury index; dashboard card and alert missing_evidence; report missing_evidence; register filter. Every attach/remove/switch change audited. No new permission; posting and the general document module unchanged. Owner amendment 1: TreasuryReportServiceTest overview alert count 3 -> 4. Architecture inventory (DocumentMutationOwnershipTest) passes after renaming the controller's Treasury document variable. TreasuryEvidenceTest 8 tests: red before (8 failing), green after; also green with DB_CONNECTION=mysql; migration up, down, up verified on MySQL 8 with both FKs and the unique index. Treasury feature suite 208 green; tests/Architecture green. Unit + Feature + Integration 2915 tests at 414ceb86 with the 2 known root-only failures plus the architecture failure fixed in b3f08809; Feature/Api 824 green. PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, route guard, deptrac pass. Exact-head CI pending at packet time."
technical_evidence:
  base_sha: "71bc0c946c3b1338723658a24adae6e55572905c"
  subject_sha: "b3f088090d773a1cdb364212a5ef12e7438d521c"
  implementation_tree_digest: "ba2dbfc8b855dfb98643872404a2ed6271a4569db8e0b1a5feb56c45f5bdd611"
  verified_pr_head_sha: "b3f088090d773a1cdb364212a5ef12e7438d521c"
  verified_at: "2026-11-04T09:31:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-106 — Gate 3 release decision (Treasury evidence attachments)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Khoản tiền trong Ngân quỹ chưa gắn được chứng từ; không biết khoản chi nào thiếu chứng từ.

**2. Sau thay đổi (Gate 2 S-2, E-1, M-1, D-1)** — subject `b3f08809`:

**Nút "Chứng từ (n)"**
- Mỗi dòng sổ giao dịch và mỗi khoản chi đang chờ có nút này, mở trang chứng từ của khoản đó.
- Trang chứng từ có danh sách file, nút tải về và form đính thêm (loại chứng từ, file, ghi chú).

**Chọn loại chứng từ** (9 loại)
- ảnh chuyển khoản, sao kê, hoá đơn, phiếu thu, phiếu chi;
- xác nhận tiền mặt, xác nhận tổ đội;
- hợp đồng / đơn hàng, khác.

**Ai được làm gì**
- Đính: người lập khoản đó, người duyệt chi hoặc kế toán đối soát.
- Xem / tải: ai có quyền Ngân quỹ của dự án.
- Gỡ: chỉ người tải lên, và chỉ khi khoản chưa ghi sổ.

**Quy tắc file**
- Chỉ nhận PDF, ảnh, Word, Excel; tối đa 10 MB.
- Cùng một file không đính hai lần vào cùng một khoản.
- Tải về dạng file, không mở trực tiếp.

**Giữ kín**
- File nằm trong bảng riêng của Ngân quỹ, trên ổ riêng tư.
- **Không** hiện ở trang "Tài liệu" chung (có test).

**Công tắc ở trang Ngân quỹ tổng** "Khoản chi phải có ít nhất một chứng từ mới gửi duyệt được"
- Mặc định tắt.
- Chỉ Owner / Admin đổi được, có ghi nhật ký.

**Thiếu chứng từ** = khoản chi đã ghi sổ (chưa đảo) không có file nào. Có:
- thẻ trên trang Ngân quỹ;
- cảnh báo;
- báo cáo "Thiếu chứng từ";
- bộ lọc sổ giao dịch.

Mọi lần đính, gỡ và đổi công tắc đều ghi nhật ký. Không thêm quyền mới; không đổi cách ghi sổ hay module tài liệu chung. Có 1
migration tạo bảng mới.

**3. Khác với bản thiết kế (cần anh/chị biết)**
- Danh sách chứng từ là **một trang riêng** cho mỗi khoản, mở từ nút "Chứng từ (n)". Bản thiết kế viết "ô mở ra danh sách",
  nên có thêm 1 route xem trang này, cùng quyền.
- **Owner amendment 1**: sửa 1 dòng trong test S6 cũ. Số cảnh báo tăng từ 3 lên 4 vì có thêm cảnh báo "thiếu chứng từ".
- Test kiến trúc của hệ tài liệu chung báo nhầm, vì controller mới dùng tên biến `$document`. Tôi đã đổi tên biến (commit
  `b3f08809`); test đó giờ xanh.

**4. Đã kiểm thử** — base `71bc0c94`; subject `b3f088090d773a1cdb364212a5ef12e7438d521c`;
digest `ba2dbfc8b855dfb98643872404a2ed6271a4569db8e0b1a5feb56c45f5bdd611`.

- **Đỏ trước / xanh sau:** 8 test mới hỏng khi chưa có mã, xanh khi có mã. Các test này kiểm tra:
  - quyền xem / tải, người ngoài và dự án khác bị chặn;
  - loại file, cỡ file, trùng file, ai được đính;
  - chỉ gỡ được trước khi ghi sổ;
  - công tắc chặn gửi duyệt và chỉ Owner đổi được;
  - thẻ, cảnh báo, báo cáo, bộ lọc "thiếu chứng từ";
  - file không lộ ra trang "Tài liệu" chung;
  - số truy vấn không tăng khi có nhiều file.
- **MySQL 8 thật:** migration tạo bảng, gỡ, rồi tạo lại đều được; đủ khoá ngoại và ràng buộc unique. 8 test xanh khi chạy với
  MySQL.
- **Toàn bộ Ngân quỹ:** 208 test xanh. **Test kiến trúc:** xanh.
- **Unit + Feature + Integration** (tại `414ceb86`): 2.915 test, gồm:
  - 2 lỗi có sẵn do chạy bằng root;
  - 1 lỗi kiến trúc nói ở mục 3, đã sửa ở `b3f08809`.
- **Feature/Api:** 824 test xanh.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head: đang chạy lúc lập hồ sơ.

**5. Ngoài phạm vi** — OCR; import sao kê; khoá kỳ; ngưỡng / hạn tạm ứng; xem trước ảnh; đính kèm qua API; deploy.

**6. Rủi ro còn lại** — Thấp:
- tính năng thêm mới, không đổi sổ cái;
- rủi ro chính là lộ file, đã chặn bằng quyền Ngân quỹ trên mọi lối đọc và có test.

**7. Hoàn tác** — revert PR. Migration `down()` xoá bảng; file đã lưu vẫn nằm trong ổ, xoá tay nếu cần.

**8. Đề xuất** — Phát hành (merge).

## Decision Needed

Owner chọn: `APPROVE GAP-106 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
