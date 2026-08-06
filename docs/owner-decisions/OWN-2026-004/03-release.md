---
work_id: OWN-2026-004
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
  spec: null
  plan: null
  branch: fix/OWN-2026-004-gap-subidentifier-governance
  pr: https://github.com/kha997/zenamanagephp/pull/242
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
  created_at: "2026-08-06T17:13:48+07:00"
  updated_at: "2026-08-06T20:46:43+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "CORRECTED: the previously-presented implementation head (b772a2822363954f51c3b78f84faddbab7963e1d) allowed partial-prefix extraction — both CI Work-ID extraction paths used an unbounded substring pattern that could match a valid-looking prefix inside an invalid token instead of requiring the whole token to be valid. Reproduced: 'GAP-010bb' extracted as 'GAP-010b'; 'GAP-0010' extracted as 'GAP-001'; 'GAP-010-b' extracted as 'GAP-010'. The corrected implementation head 58fba81de66f2aa9d86a90684a68a65c144e3dbf introduces a shared, portable extractor (scripts/ci/extract-work-id.sh, POSIX ERE only, no grep -P dependency) that tokenizes the input into maximal words (keeping hyphens/underscores/slashes attached, so an invalid token is never split into a valid-looking piece plus leftover) and requires each whole token to match the canonical pattern anchored ('^(GAP-[0-9]{3}[a-z]?|OWN-[0-9]{4}-[0-9]{3})$') before accepting it. All previously-reproduced invalid examples (GAP-010B, GAP-010bb, GAP-010-b, GAP-10, GAP-0010, GAP-0010b, GAP-010_, GAP-010/, XGAP-010b, GAP-010bX) now return no Work ID; all valid forms (GAP-010, GAP-010a, GAP-010b, GAP-014c, OWN-2026-004) extract to themselves exactly. Both scripts/ci/check-gate3-before-ready.sh and .github/workflows/owner-governance-lint.yml now delegate to the identical shared script — verified via a test asserting byte-identical invocation strings — so the two CI paths cannot structurally diverge on what a Work ID is. Verified: written-first tests confirmed red (18 failures reproducing the exact defect) against the unmodified extraction, then green after the fix; focused suite (OwnerGovernanceSchemaFixtureTest, EnforcementBoundaryTest, GapSubIdentifierWorkIdTest) 46/46 pass; full governance suite (tests/Unit/OwnerGovernance) 105/105 pass; owner_governance_lint.php (no args and --enforce-gate-ordering) both PASS with 0 violations; bash -n on both scripts OK; git diff --check clean; real CI on the implementation head (Owner Governance Lint success, test-routes-guardrails success, 0 failed); a fresh independent review (no prior context) found 0 Critical, 0 Important, 0 Minor findings, confirming the tokenizer's hyphen placement in the character class is safe (no accidental range), the three original defects are genuinely fixed (not just test-passing), scripts/ssot/owner_governance_lint.php remained unmodified, no file outside the declared allowed scope was touched, and no governance bypass was introduced. The previous digest e940511826a6af709077d9027e7502060e7d9c93f65f9bbd4cf2d2d2dbba2fb0 (bound to the now-superseded partial-prefix-extraction head) is superseded by af64503cb4f092b2996471ecf5b04f7671aaf68677fe1d9089d965ab398594cd, independently recomputed and confirmed unchanged across the packet-only preparing commit. The GAP-010b draft (docs/owner-decisions/GAP-010b/01-request.md, hash 693bcf7a3706734d37a2a1a1cf8d38cca019e08a443480702bd4a86187a524fc) remains unchanged, still uncommitted in its own worktree, and is not part of this correction."
technical_evidence:
  subject_sha: "58fba81de66f2aa9d86a90684a68a65c144e3dbf"
  implementation_tree_digest: "af64503cb4f092b2996471ecf5b04f7671aaf68677fe1d9089d965ab398594cd"
  verified_pr_head_sha: "58fba81de66f2aa9d86a90684a68a65c144e3dbf"
  verified_at: "2026-08-06T20:46:08+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary
Sửa xong lỗi tương thích trong công cụ quản trị Owner Control Layer: công cụ giờ đã chấp nhận đúng các mã con gap chính thức (GAP-010b, GAP-014c...) mà không đổi tên chúng. **Đã phát hiện và sửa thêm một lỗi thứ hai** trong lần trình lại trước: cách trích xuất Work-ID trong CI dùng mẫu "khớp một phần" thay vì "khớp trọn vẹn", khiến một mã KHÔNG hợp lệ có thể bị âm thầm biến thành một mã KHÁC hợp lệ (ví dụ `GAP-010bb` bị đọc nhầm thành `GAP-010b`). Đã sửa bằng cách bắt buộc khớp trọn vẹn từng mã, dùng chung một script cho cả 2 nơi trích xuất để không thể lệch nhau nữa. Đây thuần tuý là sửa công cụ — không đổi hành vi sản phẩm, không phê duyệt việc sửa GAP-010b. Đã kiểm chứng đầy đủ bằng test (bắt đúng lỗi trước khi sửa), lint, CI thật, và một vòng review độc lập. Sẵn sàng chờ owner quyết định lại.

## Gói quyết định phát hành — OWN-2026-004: Sửa tương thích mã con gap trong Owner Control Layer

**1. Vấn đề là gì?**
Công cụ kiểm tra hồ sơ quản trị (Owner Control Layer) chỉ chấp nhận `work_id` dạng `GAP-NNN` (3 chữ số, không hậu tố). Điều này chặn việc mở hồ sơ Gate 1 chính thức cho GAP-010b — một lỗi thật, đang mở, đã được owner xác nhận qua sổ đăng ký (OWN-2026-003).

**2. Mẫu định danh mới là gì?**
`GAP-[0-9]{3}[a-z]?` — đúng 3 chữ số, có thể theo sau bởi đúng 1 chữ cái thường. Chữ HOA, nhiều ký tự, hay dấu câu vẫn bị từ chối. GAP-010b, GAP-014c được chấp nhận; GAP-010B, GAP-010bb, GAP-010-b vẫn bị từ chối.

**3. Vì sao phải sửa 3 nơi?**
Schema (`docs/owner-governance/packet-schema.yml`), và 2 đường trích xuất Work-ID trong CI (`scripts/ci/check-gate3-before-ready.sh`, `.github/workflows/owner-governance-lint.yml`) định nghĩa độc lập với nhau — nếu chỉ sửa 1-2 nơi, CI có thể cắt cụt mã con về mã cha (`GAP-010b` → `GAP-010`), làm sai lệch bằng chứng gắn với đúng mã con. Cả 3 nơi đã được xác nhận đồng bộ.

**4. Đã kiểm chứng những gì?**
Viết test thất bại trước khi sửa (TDD) — xác nhận đỏ (7 lỗi) trên mã cũ, xanh sau khi sửa. Bộ test tập trung 31/31 qua; toàn bộ bộ test quản trị 90/90 qua. Lint cấu trúc 0 lỗi. CI thật trên đầu nhánh triển khai: tất cả các kiểm tra đều đạt. Một vòng review độc lập (không có bối cảnh trước) xác nhận: mẫu đủ hẹp (chỉ phần GAP thay đổi), 3 nơi đồng bộ, không có lỗ hổng quản trị nào bị mở ra, không file nào ngoài phạm vi cho phép bị đụng tới. 0 lỗi nghiêm trọng, 0 lỗi quan trọng.

**5. Việc này có phê duyệt sửa GAP-010b không?**
Không. Đây thuần tuý là sửa công cụ. GAP-010b vẫn đang mở, chưa được phê duyệt sửa hay triển khai gì. Hồ sơ Gate 1 nháp cho GAP-010b (đã soạn sẵn từ trước, chưa commit/mở PR) vẫn được giữ nguyên, không bị đụng tới trong lần sửa này.

**6. Hành vi sản phẩm có đổi không?**
Không. Chỉ sửa công cụ quản trị nội bộ (schema + 2 script CI) — không đụng tới mã nguồn ứng dụng, route, migration, hay sổ đăng ký gap.

**7. Có thể hoàn tác không?**
Có, hoàn toàn — chỉ cần revert đúng PR sửa công cụ này.

**8. Đề xuất của đội kỹ thuật:** Đã sẵn sàng kỹ thuật, đã kiểm chứng bằng TDD + CI thật + review độc lập. Đề xuất owner xem qua và quyết định.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu phê duyệt việc sửa GAP-010b, GAP-014b, GAP-014c hay bất kỳ gap nào khác — chỉ quyết định có phát hành việc sửa công cụ quản trị này hay không. Owner cũng không được yêu cầu đọc mã nguồn hay log CI — mọi kết luận đã được đội kỹ thuật xác minh trực tiếp.
