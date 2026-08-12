---
work_id: GAP-033
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-12-gap033-document-approver-assignment-design.md
  plan: null
  branch: docs/GAP-033-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/258
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-12T21:43:44+07:00"
  owner_response_reference: "Owner Binding Clarification — GAP-033 Gate 2, in-session on 2026-08-12: 'Câu hỏi 1: (b) — Chỉ Quản lý dự án hoặc Admin được gán/đổi người duyệt. Câu hỏi 2: (b) — Người được gán vẫn phải có quyền document.approve; việc được gán không tự cấp quyền duyệt. Câu hỏi 3: (a) — Khi mở lại tài liệu để sửa, giữ nguyên người được gán cho tới khi Quản lý dự án/Admin chủ động thay đổi. Quy tắc bổ sung bắt buộc: chỉ được gán người thuộc đúng phạm vi tenant/project và đủ điều kiện document.approve; mọi thay đổi người duyệt phải được lưu dấu vết kiểm toán.' Also explicitly authorized: 'được phép lập implementation plan và triển khai GAP-033 trong phạm vi Gate 2 đã duyệt. Gate 3 chỉ được chuẩn bị sau khi implementation, testing, review và CI hoàn tất. Không merge/release/deploy trước Gate 3 Owner approval.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-033/02-design.md
superseded_by: null
timestamps:
  created_at: "2026-08-12T21:43:44+07:00"
  updated_at: "2026-08-12T21:43:44+07:00"
generated_by: agent
---

## Gate 2 Owner Binding Clarification — FINAL, all business rules resolved

This packet supersedes `docs/owner-decisions/GAP-033/02-design.md` per the immutability rule (that packet's `owner_decision.value` was already `approved`, so it is never edited in place). The base decision (Option C — hybrid: project-manager default with optional per-document override) is unchanged and carried forward. This packet resolves the three sub-questions that were explicitly left open in the base packet, plus one additional mandatory rule the Owner added.

### Câu hỏi 1 — Ai được quyền gán/đổi người duyệt cho một tài liệu?

**Quyết định: (b) — Chỉ Quản lý dự án hoặc Admin.** Người tạo/nộp tài liệu, biên tập viên, hay bất kỳ ai khác có quyền sửa tài liệu KHÔNG được tự gán/đổi người duyệt — chỉ Quản lý dự án hoặc Admin của đúng tenant/dự án đó mới được làm việc này.

### Câu hỏi 2 — Được gán làm người duyệt có tự động được quyền duyệt luôn không?

**Quyết định: (b) — Không.** Được gán làm người duyệt KHÔNG tự cấp quyền duyệt tài liệu. Người được gán vẫn phải độc lập có quyền `document.approve` (theo vai trò hiện tại) thì việc gán mới có tác dụng thật — nếu gán cho người chưa có quyền, việc gán đó ghi nhận "ý định phụ trách" nhưng người đó chưa duyệt được cho tới khi cũng được cấp quyền `document.approve`. **Khác với tiền lệ NCR** (nơi được gán là đủ, không cần vai trò) — GAP-033 KHÔNG áp dụng nguyên tắc đó; đây là lựa chọn an toàn hơn được Owner chọn có chủ đích, không phải mặc định theo tiền lệ.

### Câu hỏi 3 — Khi tài liệu bị mở lại để sửa, người được gán trước đó có giữ nguyên không?

**Quyết định: (a) — Giữ nguyên.** Sau khi tài liệu được mở lại để sửa (reopen, Approval reset về `not-submitted` theo GAP-032), người được gán làm người duyệt trước đó tiếp tục giữ nguyên cho lần nộp duyệt tiếp theo. Chỉ Quản lý dự án hoặc Admin (theo Câu hỏi 1) mới được chủ động đổi người khác.

### Quy tắc bổ sung bắt buộc (Owner tự thêm, không phải câu hỏi đã đặt ra ban đầu)

1. **Phạm vi hợp lệ khi gán:** chỉ được gán một người thuộc đúng tenant/dự án của tài liệu đó, VÀ người đó phải đủ điều kiện có quyền `document.approve` (theo Câu hỏi 2, quyền đó phải tồn tại độc lập, không phải do việc gán tạo ra). Gán một người ngoài phạm vi tenant/dự án, hoặc gán một người không có khả năng có quyền `document.approve`, phải bị từ chối tại thời điểm gán, không được lưu như một trạng thái "chờ hợp lệ".
2. **Dấu vết kiểm toán bắt buộc:** mọi lần thay đổi người duyệt được chỉ định (gán lần đầu, đổi người, hoặc bất kỳ thay đổi nào) phải được ghi lại — ai đã đổi, đổi cho ai, khi nào — theo đúng nguyên tắc kiểm toán đã áp dụng cho quyết định duyệt/từ chối ở GAP-031/032 (không xoá, không ghi đè lịch sử).

### Cho phép triển khai

Sau khi binding clarification này được ghi nhận và các kiểm tra governance (`owner-governance-lint`, `--enforce-gate-ordering`) đạt, đội kỹ thuật được phép lập kế hoạch triển khai (implementation plan) và triển khai GAP-033 **trong đúng phạm vi Gate 2 đã duyệt** (Hướng C + 3 câu trả lời + quy tắc bổ sung ở trên — không được tự ý mở rộng hay đổi hướng). Gate 3 chỉ được chuẩn bị sau khi implementation, testing, review, và CI hoàn tất. Không merge/release/deploy trước khi có Gate 3 Owner approval.

**Nguồn gốc quyết định:** ghi nhận nội bộ repository dựa trên phản hồi rõ ràng của Owner trong phiên làm việc ngày 2026-08-12. `trust_level: claimed_repo_record` — đây KHÔNG phải một phê duyệt được xác thực bằng mật mã hay qua Decision Center.

## Business rules finalized (carried into implementation planning)

| # | Rule | Answer |
|---|---|---|
| 1 | Ai gán/đổi người duyệt | Chỉ Quản lý dự án hoặc Admin |
| 2 | Gán có tự cấp quyền duyệt không | Không — vẫn cần `document.approve` độc lập |
| 3 | Reopen có giữ người được gán không | Có, giữ nguyên tới khi bị đổi chủ động |
| 4 | Phạm vi hợp lệ khi gán | Đúng tenant/dự án + đủ điều kiện `document.approve` |
| 5 | Kiểm toán | Mọi thay đổi người duyệt phải có dấu vết kiểm toán |

All other content from the base packet (options considered, trade-offs, acceptance scenarios, exclusions, roles affected) remains in force unchanged — see `docs/owner-decisions/GAP-033/02-design.md`.
