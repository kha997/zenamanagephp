---
work_id: OWN-2026-009
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
  spec: docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md
  plan: null
  branch: docs/OWN-2026-009-one-page-management-ssot-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/262
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-15T15:11:40+07:00"
  owner_response_reference: "Owner Gate 3 decision — CORRECTION, recorded in-session on 2026-08-15 against reviewed PR #262 head cbf70bf48ba83fd7053ec2e2ca122fd74016e7df: 'OWN-2026-009 — Gate 3 Owner Decision: CORRECTION. Tôi, Owner, yêu cầu CORRECTION đối với Gate 3 của OWN-2026-009 tại PR #262, reviewed head cbf70bf48ba83fd7053ec2e2ca122fd74016e7df. Đây không phải yêu cầu thay đổi Gate 2 design, SSOT hoặc implementation-tree evidence. Tôi xác nhận Gate 2 approval vẫn có hiệu lực và technical evidence digest hiện tại không bị stale. Cần sửa duy nhất tính chính xác của Gate 3 release packet: technical_evidence.subject_sha = e02dfe5536afe41bdd5f31a9447cacc58630fed5 đại diện cho implementation/content tree trước khi tạo Gate 3 packet; tại SHA đó có 3 payload docs files: 01-request.md, 02-design.md, và canonical SSOT. Current PR head hiện có 4 changed files, tất cả đều dưới docs/: 01-request.md, 02-design.md, 03-release.md, và canonical SSOT. Vì vậy sửa mọi statement trong 03-release.md nói current branch/current HEAD có \"3 files\", \"3 Markdown files\", hoặc \"SSOT + 2 gate packets\". mandatory_technical_gate_summary, §5, §8 và §10 phải phân biệt rõ 3 payload files tại evidence subject SHA với 4 total PR files tại current Gate 3 head. Giữ nguyên implementation_tree_digest = 9b06eef020db4f07ed07f10900ccf260c259bc0efc63ac5eee4447fd6c4d9bf9 nếu repo governance tooling tiếp tục recompute và xác nhận nó fresh sau correction. Không thay đổi canonical SSOT. Không reopen Gate 2. Không runtime implementation. Không GAP-036. Không Today Workspace. Không sửa/merge/đóng #257 hoặc #245. Sau correction, rerun Owner Governance Lint + Routes Guardrails + evidence-freshness tại head SHA mới, rồi đưa Gate 3 trở lại awaiting_owner để tôi review. Không được suy luận Gate 3 approval. Không được mark ready hoặc merge PR #262 trước quyết định Gate 3 tiếp theo.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-15T15:00:35+07:00"
  updated_at: "2026-08-15T15:11:40+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Cả 2 required check trên PR #262 đều PASS tại đúng đầu nhánh hiện tại của Gate 3. Diff toàn nhánh so với origin/main tại đầu nhánh hiện tại xác nhận CHỈ 4 file thay đổi (01-request.md, 02-design.md, 03-release.md, và canonical SSOT), tất cả trong docs/, toàn bộ là insertion — không có deletion, không có file app/, routes/, database/migrations/, hay tests/ nào xuất hiện trong diff. Evidence digest (technical_evidence) được tính tại subject_sha e02dfe5536afe41bdd5f31a9447cacc58630fed5 — thời điểm đó chỉ có 3 file payload (01-request.md, 02-design.md, canonical SSOT); 03-release.md chưa tồn tại nên không nằm trong tập 3 file đó, và cũng bị loại trừ khỏi digest computation theo thiết kế (packet-schema.yml's implementation_tree_digest_algorithm), nên việc thêm 03-release.md sau đó không làm digest lệch."
technical_evidence:
  subject_sha: "e02dfe5536afe41bdd5f31a9447cacc58630fed5"
  implementation_tree_digest: "9b06eef020db4f07ed07f10900ccf260c259bc0efc63ac5eee4447fd6c4d9bf9"
  verified_pr_head_sha: "e02dfe5536afe41bdd5f31a9447cacc58630fed5"
  verified_at: "2026-08-15T15:00:35+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Gói quyết định phát hành

**1. Vấn đề đã xảy ra là gì?** PR #257 và PR #245 mô tả kiến trúc "One-Page Management" trải dài 4 tài liệu thiết kế hội thoại (~2,300 dòng), nhưng không có tài liệu SSOT duy nhất mà các slice triển khai tương lai (CRM, Project OPPM, Contract Control, Finance Control, Treasury...) có thể tham chiếu — rủi ro mỗi slice tự diễn giải ngữ nghĩa dùng chung khác nhau.

**2. Người dùng nào bị ảnh hưởng?** Owner (ra quyết định Gate 1/2/3 cho từng slice tương lai) và các agent kỹ thuật thực hiện từng slice đó — như đã nêu ở Gate 1/Gate 2.

**3. Bây giờ người dùng có thể làm gì?** Sau khi merge, `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` trở thành SSOT chính thức trên `main` — mọi Gate 1/2 packet của slice tương lai trích dẫn tài liệu này thay vì tự diễn giải lại 4 tài liệu nguồn.

**4. Rủi ro nào đã được đóng lại?** Rủi ro drift ngữ nghĩa giữa các slice triển khai tương lai (định nghĩa khác nhau cho "profit", "contract attention", "unique project count"...).

**5. Đã kiểm thử những gì?** `php scripts/ssot/owner_governance_lint.php` PASS trên cả 3 packet (`01-request.md`, `02-design.md`, `03-release.md`) tại đầu nhánh hiện tại. `git diff origin/main..HEAD --stat` tại đầu nhánh hiện tại của Gate 3 xác nhận đúng và chỉ đúng **4 file thay đổi** — `01-request.md`, `02-design.md`, `03-release.md` (chính packet này), và canonical SSOT — toàn bộ trong `docs/`, toàn bộ insertion, 0 deletion, 0 file `app/`/`routes/`/`database/migrations/`/`tests/`. Phân biệt rõ với evidence subject: `technical_evidence.subject_sha` = `e02dfe5536afe41bdd5f31a9447cacc58630fed5` là commit **trước khi** `03-release.md` được tạo — tại SHA đó chỉ có **3 file payload** (`01-request.md`, `02-design.md`, canonical SSOT). Trên PR #262 (draft), 2 required check (Owner Governance Lint, test-routes-guardrails) đều PASS tại cả hai SHA — `e02dfe5536afe41bdd5f31a9447cacc58630fed5` (evidence subject) và đầu nhánh hiện tại của Gate 3.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?** Không có migration, model, controller, service, route, hay UI nào. Không sửa `Opportunity.service_category` default. Không GAP-036 (báo cáo riêng). Không đóng/merge PR #257 hoặc #245 — cả hai giữ nguyên `KEEP_AS_ACTIVE_DESIGN_SOURCE`, OPEN, Draft. Không đụng Today Workspace, production, hay deployment. Gate 3 này chỉ xét việc merge tài liệu — không tự nó cấp phép bất kỳ implementation slice nào liệt kê ở SSOT §14; mỗi slice vẫn cần Work ID và vòng đời Gate 1→2→3 riêng.

**7. Vì sao các gap liên quan vẫn để riêng?** GAP-036 (Tier-5 cost/profit blind spot trong `OPERATIONAL_GAP_REGISTER.md`) được báo cáo riêng cho Owner, cố ý không gộp vào work item chỉ-tài-liệu này — đăng ký/triage của nó là quyết định Owner riêng.

**8. Rủi ro còn lại là gì?** Thấp. Thay đổi chỉ là thêm 4 file Markdown mới vào `docs/` (3 file payload tại evidence subject + chính `03-release.md`), không sửa file nào đang tồn tại, không có khả năng phá vỡ runtime hay CI khác ngoài chính governance lint (đã PASS).

**9. Có thể hoàn tác không?** Có, hoàn toàn — `git revert` bất kỳ lúc nào, không có tác động dữ liệu hay hệ thống vì không có migration/schema/runtime nào đi kèm.

**10. Đề xuất của đội kỹ thuật:** Phát hành (Approve). Toàn bộ tiêu chí sẵn sàng kỹ thuật đã đạt; đúng và chỉ đúng phạm vi Gate 2 đã duyệt được thực hiện — canonical SSOT + Gate 1 (`01-request.md`) + Gate 2 (`02-design.md`), 3 file payload tại evidence subject `e02dfe5536afe41bdd5f31a9447cacc58630fed5` — cộng với chính Gate 3 packet này (`03-release.md`, file thứ 4, tự nó không phải một phần scope Gate 2 đã duyệt mà là bản ghi quyết định release, và bị loại trừ khỏi implementation-tree digest theo thiết kế); 0 vi phạm governance lint; 0 thay đổi ngoài `docs/`.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu đọc CI log, source code, hay review comment — kết luận trên đã được xác minh độc lập (lint + diff review + CI). Owner cũng không được yêu cầu duyệt lại nội dung SSOT (đã duyệt ở Gate 2) hay bất kỳ implementation slice nào ở SSOT §14 — mỗi slice đó là quyết định Owner riêng, sau này, với Work ID và Gate lifecycle riêng. Owner cũng không được yêu cầu quyết định mark-PR-ready hay merge timing — đó là bước kỹ thuật riêng, chỉ thực hiện sau khi Owner phê duyệt Gate 3 ở đây.
