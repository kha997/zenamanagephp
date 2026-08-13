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
  recorded_at: "2026-08-13T13:41:45+07:00"
  owner_response_reference: "Owner Gate 3 v2 decision — CHANGES REQUESTED, in-session on 2026-08-13: 'The implementation scope correction is accepted: the current register diff now matches Gate 2. However, Gate 3 cannot be approved because the governance packets contain impossible chronology metadata. GitHub records commit 433d3e62890198b6232b8b351149438b9c3799b8 at 2026-08-13T06:30:17Z (13:30:17+07:00), but files already contained in that commit claim creation/update/decision-recording times of 13:40, 13:45, and 13:46 +07:00. GitHub then records commit db51e8f8cb1d9cda55a7d27f708ef14e7c8c15aa at 13:32:56+07:00, while 03-release-v2.md still contains updated_at: 13:46:00+07:00. These timestamps are impossible and must not remain as authoritative governance provenance.' Do not self-approve Gate 3. Do not mark ready. Do not merge. Do not deploy."
  reconciliation_required: false
supersedes: docs/owner-decisions/OWN-2026-008/03-release.md
superseded_by: docs/owner-decisions/OWN-2026-008/03-release-v3.md
timestamps:
  created_at: "2026-08-13T13:30:17+07:00"
  updated_at: "2026-08-13T13:41:45+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Sau khi sửa 3 lỗi Owner nêu ở Gate 3 lần trước, digest triển khai được tính lại tại commit 433d3e62 (bao gồm register đã khôi phục đúng phạm vi, 02-design-v2.md, và 03-release.md đã đóng băng ở quyết định correction_requested). Governance lint xác nhận cùng giá trị digest một cách độc lập trên CI. test-routes-guardrails PASS."
technical_evidence:
  subject_sha: "433d3e62890198b6232b8b351149438b9c3799b8"
  implementation_tree_digest: "4b7300d46d9fa998e3e48c58cdc591fe629344b871970c97c2d383848c476a00"
  verified_pr_head_sha: "433d3e62890198b6232b8b351149438b9c3799b8"
  verified_at: "2026-08-13T13:32:34+07:00"
owner_decision_binding:
  implementation_tree_digest: "4b7300d46d9fa998e3e48c58cdc591fe629344b871970c97c2d383848c476a00"
  decision_recorded_at: "2026-08-13T13:41:45+07:00"
---

## OWNER GATE 3 v2 DECISION: CHANGES REQUESTED (2026-08-13T13:41:45+07:00)

Implementation scope correction accepted — register diff khớp đúng Gate 2. Nhưng Gate 3 bị từ chối vì lỗi chronology: `created_at`/`updated_at` gốc của packet này (`13:46:00+07:00`) và của `02-design-v2.md` (`13:40:00+07:00`) là timestamp trong tương lai so với chính commit Git chứa chúng (`433d3e62` @ `13:30:17+07:00` theo `gh api`/`git show -s --format=%cI`, xác nhận độc lập cả hai nguồn). Nguyên nhân: các timestamp này được ước lượng thủ công (tăng dần theo số tròn) thay vì truy vấn đồng hồ thật tại thời điểm ghi file. **`created_at`/`updated_at` phía trên đã được sửa lại bằng timestamp Git commit xác thực** (`433d3e62` @ `13:30:17+07:00` cho lần tạo, `db51e8f8` @ `13:32:56+07:00` không dùng ở đây vì digest-fill không đổi nội dung quyết định) — đây là dữ liệu có thật, không phải ước lượng. Trường `decision_provenance.recorded_at`/`updated_at` ở trên phản ánh đúng thời điểm quyết định CHANGES REQUESTED này được ghi nhận (truy vấn đồng hồ thật ngay trước khi ghi file). **Packet này giờ đóng băng tại quyết định CHANGES REQUESTED — không được viết lại thêm.** Bản sửa xem tại `03-release-v3.md` (supersedes packet này).

## Gói quyết định phát hành (bản gốc, giữ nguyên làm lịch sử — nội dung nghiệp vụ không đổi, chỉ chronology metadata phía trên đã sửa trước khi đóng băng)

Packet này supersede `docs/owner-decisions/OWN-2026-008/03-release.md` theo đúng nguyên tắc bất biến (packet đó đã nhận quyết định `correction_requested`, đóng băng làm lịch sử, không sửa lại). Ba lỗi Owner nêu tại Gate 3 lần trước đã được sửa:

1. **Register scope:** `OPERATIONAL_GAP_REGISTER.md` đã được khôi phục đúng phạm vi Gate 2 — chỉ `Status` + phần bổ sung tối thiểu vào cột Bằng chứng cho 4 dòng GAP-027/028/029/033; cột Ghi chú/Notes của cả 4 dòng đã được khôi phục **verbatim** như trên `origin/main` (xác nhận bằng diff, xem mục 5).
2. **02-design.md mâu thuẫn nội bộ:** đã sửa qua `docs/owner-decisions/OWN-2026-008/02-design-v2.md` (supersedes `02-design.md`, giữ nguyên quyết định/provenance đã duyệt, chỉ gỡ GAP-027/GAP-028 khỏi danh sách loại trừ sai).
3. **Ngôn từ Gate 3:** không còn gộp chung "resolved/merged" cho cả 4 gap — xem phân biệt theo từng gap ở mục 1 dưới đây.

**1. Vấn đề đã xảy ra là gì, và mỗi gap được xác nhận đóng bằng bằng chứng nào?**
- GAP-027: resolved theo trạng thái hiện tại của `origin/main` — `tests/Feature/DebugRouteDocumentationInvariantTest.php` đã tồn tại và thực hiện đúng invariant test được yêu cầu.
- GAP-028: resolved theo trạng thái tài liệu hiện tại của `origin/main` — `README.md` không còn nhắc Vue/microservice, `SYSTEM_DOCUMENTATION.md` đã archive.
- GAP-029: resolved qua PR #230 (`d6ca498b`) — operator web UI resubmit flow đã merge.
- GAP-033: resolved qua Gate-3-approved merge `30a609a9390524f3294a2eb579141f7d013064fb`.

`OPERATIONAL_GAP_REGISTER.md` trước reconciliation ghi sai cả 4 (UNVERIFIED/OPEN) so với 4 bằng chứng đóng khác nhau nêu trên.

**2. Người dùng nào bị ảnh hưởng?** Owner, engineering agents làm task-selection, và reviewers/planners dùng register làm nguồn xếp ưu tiên.

**3. Bây giờ người dùng có thể làm gì?** Đọc register và thấy đúng trạng thái thật + bằng chứng đóng cho từng dòng trong 4 dòng này, không cần tự tra cứu lại.

**4. Rủi ro nào đã được đóng lại?** Rủi ro chọn nhầm hàng đợi hoặc lặp lại công việc kỹ thuật đã hoàn thành cho 4 mục này, và rủi ro drift phạm vi giữa Gate 2 đã duyệt và implementation thật (đã đóng bằng lần sửa này).

**5. Đã kiểm thử những gì?** `php scripts/ssot/owner_governance_lint.php` PASS trên toàn bộ packet (`01-request.md`, `02-design.md`, `02-design-v2.md`, `03-release.md`, `03-release-v2.md`) tại đầu nhánh hiện tại, kể cả `--enforce-gate-ordering`. `git diff origin/main -- OPERATIONAL_GAP_REGISTER.md` xác nhận đúng 4 dòng GAP-027/028/029/033 thay đổi, và với mỗi dòng, cột Ghi chú (trường thứ 5) khớp byte-for-byte với `origin/main` — chỉ Status (trường thứ 3) và phần bổ sung tối thiểu ở Bằng chứng (trường thứ 4) khác biệt. Diff toàn nhánh xác nhận không file `app/`, `routes/`, `database/migrations/`, hay `tests/` nào xuất hiện. PR #259 (draft) required CI — Owner Governance Lint, test-routes-guardrails — PASS tại SHA nêu trong `technical_evidence.subject_sha` phía trên.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?** Không sửa bất kỳ dòng register nào khác. Không có application code, migration, route, test behavior, runtime behavior, hay production data nào bị đổi. Không thêm narrative "Reconciled under OWN-2026-008" vào Notes (đã gỡ theo yêu cầu Owner).

**7. Vì sao các gap liên quan vẫn để riêng?** GAP-013 (cùng entity Submittal với GAP-029, khác khía cạnh — thiếu thông báo, không phải thiếu UI) vẫn OPEN, không đổi. GAP-011 (liên quan GAP-027) vẫn OPEN, không đổi.

**8. Rủi ro còn lại là gì?** Thấp — rủi ro duy nhất là citation sai, đã đối chiếu trực tiếp `origin/main` cho từng dòng.

**9. Có thể hoàn tác không?** Có, hoàn toàn — sửa text Markdown, `git revert` bất kỳ lúc nào.

**10. Đề xuất của đội kỹ thuật:** Phát hành (Approve). Cả 3 lỗi Owner nêu ở Gate 3 lần trước đã sửa đúng theo yêu cầu, có xác minh lại bằng lint + diff review + CI.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu đọc CI log, source code, hay review comment — đã xác minh độc lập. Owner cũng không được yêu cầu quyết định mark-PR-ready hay merge timing — đó là bước kỹ thuật riêng, chỉ thực hiện sau khi Owner phê duyệt Gate 3 ở đây.
