---
work_id: OWN-2026-008
gate: 3
gate_status: changes_requested
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: correction_requested
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-08-13-own-2026-008-register-reconciliation-evidence.md
  plan: null
  branch: docs/OWN-2026-008-gap-register-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/259
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-13T13:45:00+07:00"
  owner_response_reference: "Owner Gate 3 decision — CHANGES REQUESTED, in-session on 2026-08-13: 'The technical isolation is acceptable, but the current implementation does not exactly match the approved Gate 2 scope. Required corrections: 1. Restore the Gate 2 scope exactly in OPERATIONAL_GAP_REGISTER.md ... Revert those narrative/Notes modifications. 2. Correct the internal contradiction in 02-design.md ... Remove GAP-027 and GAP-028 from that exclusion list. 3. Correct factual wording in the Gate 3 packet ... Use the verified distinction ... 4. Supersede the current Gate 3 packet correctly ... 5. Re-verify after correction.' Do not self-approve Gate 3. Do not mark PR ready. Do not merge. Do not deploy. Do not touch any application/runtime file."
  reconciliation_required: false
supersedes: null
superseded_by: docs/owner-decisions/OWN-2026-008/03-release-v2.md
timestamps:
  created_at: "2026-08-13T13:19:59+07:00"
  updated_at: "2026-08-13T13:45:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Cả 2 required check trên PR #259 đều PASS tại đúng đầu nhánh (Owner Governance Lint, test-routes-guardrails). Diff review xác nhận commit reconciliation chỉ chạm OPERATIONAL_GAP_REGISTER.md (đúng 4 dòng đã duyệt) cộng 2 file governance mới (evidence doc + Gate 2 packet) — không có file app/, routes/, database/migrations/, hay tests/ nào bị đổi. **Owner Gate 3 review (2026-08-13) tìm thấy scope drift trong nội dung tài liệu (không phải trong technical isolation): register Notes bị sửa vượt phạm vi đã duyệt, và 02-design.md có mâu thuẫn nội bộ về danh sách loại trừ — cả hai đã được yêu cầu sửa, xem `03-release-v2.md`.**"
technical_evidence:
  subject_sha: "499ab7cf98a62f18cd5378b64d6d6fbeac870eef"
  implementation_tree_digest: "38e24c2a3e132e11f1c2304cdcdafa2099ac1470e26ad1211506fac6ca822395"
  verified_pr_head_sha: "499ab7cf98a62f18cd5378b64d6d6fbeac870eef"
  verified_at: "2026-08-13T13:19:59+07:00"
owner_decision_binding:
  implementation_tree_digest: "38e24c2a3e132e11f1c2304cdcdafa2099ac1470e26ad1211506fac6ca822395"
  decision_recorded_at: "2026-08-13T13:45:00+07:00"
---

## OWNER GATE 3 DECISION: CHANGES REQUESTED (2026-08-13)

Owner tìm thấy implementation không khớp chính xác phạm vi Gate 2 đã duyệt: (1) diff register đổi cả cột Ghi chú/Notes của 4 dòng, vượt quá "chỉ Status + evidence citation tối thiểu" đã duyệt; (2) `02-design.md` có mâu thuẫn nội bộ, liệt kê nhầm GAP-027/GAP-028 vào danh sách loại trừ dù chúng nằm trong 4 dòng đã duyệt; (3) ngôn từ Gate 3 gộp chung "resolved/merged" cho cả 4 gap dù bằng chứng đóng của mỗi gap khác nhau. Technical isolation (không chạm application/runtime code) được xác nhận là chấp nhận được. **Packet này được đóng băng tại quyết định CHANGES REQUESTED theo đúng nguyên tắc bất biến — không được viết lại như thể chưa có quyết định.** Bản sửa lỗi và Gate 3 packet mới xem tại `03-release-v2.md` (supersedes packet này).

## Gói quyết định phát hành (bản gốc, giữ nguyên làm lịch sử)

**1. Vấn đề đã xảy ra là gì?** `OPERATIONAL_GAP_REGISTER.md` ghi sai trạng thái của 4 dòng (GAP-027, GAP-028, GAP-029, GAP-033) so với trạng thái thật đã xác minh trên `origin/main` — cả 4 đã thực sự resolved/merged nhưng register vẫn ghi UNVERIFIED/OPEN.

**2. Người dùng nào bị ảnh hưởng?** Owner, engineering agents làm task-selection, và reviewers/planners dùng register làm nguồn xếp ưu tiên — như đã nêu ở Gate 1/Gate 2.

**3. Bây giờ người dùng có thể làm gì?** Đọc register và thấy đúng trạng thái thật của 4 dòng này ngay, không cần tự tra cứu lại `origin/main`/PR/commit để biết chúng đã xong.

**4. Rủi ro nào đã được đóng lại?** Rủi ro chọn nhầm hàng đợi hoặc lặp lại công việc kỹ thuật đã hoàn thành cho 4 mục này.

**5. Đã kiểm thử những gì?** `php scripts/ssot/owner_governance_lint.php` chạy PASS trên cả 3 packet (`01-request.md`, `02-design.md`, và chính file này) tại đầu nhánh hiện tại. Diff `git diff origin/main -- OPERATIONAL_GAP_REGISTER.md` xác nhận đúng và chỉ đúng 4 dòng GAP-027/028/029/033 thay đổi (0 dòng khác). Diff toàn nhánh (`git diff origin/main --stat`) xác nhận chỉ 3 file thay đổi tổng cộng qua 2 commit: `OPERATIONAL_GAP_REGISTER.md`, `docs/owner-decisions/OWN-2026-008/01-request.md`, `docs/owner-decisions/OWN-2026-008/02-design.md`, cộng evidence doc mới — không file `app/`, `routes/`, `database/migrations/`, hay `tests/` nào xuất hiện trong diff. Trên PR #259 (draft), 2 required check (Owner Governance Lint, test-routes-guardrails) đều PASS tại đúng SHA `499ab7cf98a62f18cd5378b64d6d6fbeac870eef`.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?** Không sửa bất kỳ dòng register nào khác (GAP-021, GAP-030, GAP-011, GAP-012, GAP-013, GAP-014b/c, GAP-015, GAP-016-020, GAP-026 đều giữ nguyên). Không có application code, migration, route, test behavior, runtime behavior, hay production data nào bị đổi.

**7. Vì sao các gap liên quan vẫn để riêng?** GAP-013 (cùng entity Submittal với GAP-029) vẫn OPEN vì đó là gap khác (thiếu thông báo, không phải thiếu UI) — không được gộp vào reconciliation này. GAP-011 (liên quan GAP-027) vẫn OPEN vì bản thân route surface vẫn còn, chỉ có invariant test là đã xong.

**8. Rủi ro còn lại là gì?** Thấp. Rủi ro duy nhất có thể hình dung là citation sai (trích dẫn sai file/PR/SHA) — đã đối chiếu trực tiếp với `origin/main` cho từng dòng, không suy đoán.

**9. Có thể hoàn tác không?** Có, hoàn toàn — đây chỉ là sửa text trong 1 file Markdown, revert bằng `git revert` bất kỳ lúc nào, không có tác động dữ liệu hay hệ thống.

**10. Đề xuất của đội kỹ thuật:** Phát hành (Approve). Toàn bộ tiêu chí sẵn sàng kỹ thuật đã đạt; đúng và chỉ đúng phạm vi Gate 2 đã duyệt được thực hiện; 0 vi phạm governance lint; 0 thay đổi ngoài phạm vi.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu đọc CI log, source code, hay review comment — kết luận trên đã được xác minh độc lập (lint + diff review + CI). Owner cũng không được yêu cầu quyết định mark-PR-ready hay merge timing — đó là bước kỹ thuật riêng, chỉ thực hiện sau khi Owner phê duyệt Gate 3 ở đây.
