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
  updated_at: "2026-08-06T21:26:13+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "This is the THIRD and final correction round for OWN-2026-004. Round 1 fixed the schema and CI extraction to accept canonical GAP sub-identifiers (GAP-010b, GAP-014c). Round 2 fixed a partial-prefix extraction bug (invalid tokens like GAP-010bb were substring-matched into the valid-looking GAP-010b). Round 3 (this presentation) fixed an authoritative-resolution bug: the Round-2 extractor correctly rejected invalid tokens, but it still scanned the WHOLE PR body and returned the first valid-looking token found ANYWHERE — including inside narrative/disclaimer text. On PR #242's own body, this caused the extractor to resolve 'GAP-010b' (from the line 'GAP-010b implementation authorized: NO') instead of the PR's actual Work ID 'OWN-2026-004'. Consequence, confirmed in the real (now-superseded) workflow run 31107599102: Evidence Freshness printed 'No Gate 3 packet for GAP-010b — nothing to check for staleness.' and exited successfully — OWN-2026-004's own Gate 3 evidence was never checked. The corrected contract, implemented on head 49451731c8dcd04baf8511b6242b0c41749e0054: the canonical schema (docs/owner-governance/packet-schema.yml, unchanged) defines which Work-ID forms are accepted; one shared script (scripts/ci/extract-work-id.sh) is the sole authority for resolving WHICH Work ID a given PR body declares; both CI consumers (scripts/ci/check-gate3-before-ready.sh, .github/workflows/owner-governance-lint.yml) delegate to it identically and now fail closed (no `|| true`, no silent 'skipping' message) on any resolution failure instead of proceeding as if nothing needed checking. The authoritative field is the PR body's first non-empty line, required to be exactly 'Work ID: <candidate>'; incidental mentions of other Work IDs anywhere later in the body are ignored and never selected as a fallback; a missing declaration, a declaration not on the first non-empty line, more than one declaration anywhere in the body, an empty candidate, a candidate that fails the canonical pattern, or a candidate followed by extra characters (e.g. 'OWN-2026-004-extra') all fail closed — nonzero exit, no output, never a silent empty success. Corrected real workflow run 31110247676 (and reconfirmed on the packet-only preparing commit, run 31110576079) now logs: '✅ docs/owner-decisions/OWN-2026-004/03-release.md's implementation-tree digest matches the current implementation tree (4c1bcd4a4e20497f4a70e1df2ac46c6949cfb37a25cd6535e83ce13472eff599) — evidence is fresh, decision is not stale.' — Evidence Freshness now genuinely validates OWN-2026-004, not GAP-010b. Verified: written-first tests confirmed red (19 failures reproducing the exact defect, including a test built from the literal reproduced PR #242 defect body) against the unmodified resolver, then green after the fix; focused suite (OwnerGovernanceSchemaFixtureTest, EnforcementBoundaryTest, GapSubIdentifierWorkIdTest) 46/46 pass; full governance suite (tests/Unit/OwnerGovernance) 119/119 pass; owner_governance_lint.php (no args and --enforce-gate-ordering) both PASS with 0 violations; bash -n on both scripts OK; git diff --check clean; real CI on the implementation head (Owner Governance Lint success, test-routes-guardrails success, 0 failed); a fresh independent review (no prior context) found 0 Critical, 0 Important, 0 Minor findings across all three correction rounds, confirming the resolver's declaration-counting and first-line logic are correct by design (including the conservative 'multiple declarations anywhere, even in quoted text, fails closed' behavior), the reproduced PR #242 defect body is genuinely fixed (not just a synthetic analog), both CI callers genuinely fail closed without a blast-radius regression (the workflow is path-scoped to governance-relevant PRs only), scripts/ssot/owner_governance_lint.php and docs/owner-governance/packet-schema.yml remained unmodified, and no file outside the declared allowed scope was touched. The previous digest af64503cb4f092b2996471ecf5b04f7671aaf68677fe1d9089d965ab398594cd (bound to the now-superseded first-valid-token-anywhere resolver) is superseded by 4c1bcd4a4e20497f4a70e1df2ac46c6949cfb37a25cd6535e83ce13472eff599, independently recomputed and confirmed unchanged across the packet-only preparing commit. The GAP-010b draft (docs/owner-decisions/GAP-010b/01-request.md, hash 693bcf7a3706734d37a2a1a1cf8d38cca019e08a443480702bd4a86187a524fc) remains unchanged, still uncommitted in its own worktree, and is not part of this correction."
technical_evidence:
  subject_sha: "49451731c8dcd04baf8511b6242b0c41749e0054"
  implementation_tree_digest: "4c1bcd4a4e20497f4a70e1df2ac46c6949cfb37a25cd6535e83ce13472eff599"
  verified_pr_head_sha: "49451731c8dcd04baf8511b6242b0c41749e0054"
  verified_at: "2026-08-06T21:26:13+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary
Đây là lần trình lại thứ BA (và là lần cuối) cho OWN-2026-004. Sau khi sửa 2 lỗi đầu (chấp nhận mã con gap; khớp trọn vẹn thay vì khớp một phần), phát hiện thêm lỗi thứ ba: công cụ CI xác định "PR này thuộc Work ID nào" bằng cách lấy MÃ HỢP LỆ ĐẦU TIÊN tìm thấy ở BẤT KỲ ĐÂU trong nội dung PR — kể cả trong một câu văn nhắc tới việc khác. Trên chính PR #242, dòng "GAP-010b implementation authorized: NO" (một câu nói rằng KHÔNG cho phép sửa GAP-010b) đã khiến công cụ hiểu nhầm PR #242 thuộc về GAP-010b thay vì OWN-2026-004 — mã Work ID thật của chính PR này. Hậu quả: bước kiểm tra "bằng chứng còn mới" (Evidence Freshness) đã chạy xong và báo thành công, nhưng thực ra KHÔNG kiểm tra gì cho OWN-2026-004 cả — log CI thật ghi nguyên văn "No Gate 3 packet for GAP-010b — nothing to check for staleness." (không tìm thấy hồ sơ Gate 3 cho GAP-010b — không có gì để kiểm tra).

**Cách sửa:** Quy định PR chỉ có đúng MỘT dòng khai báo chính thức, bắt buộc phải là dòng đầu tiên không rỗng của nội dung PR, đúng định dạng `Work ID: <mã>`. Mọi lần nhắc tới mã khác ở bất kỳ đâu sau đó trong văn bản đều bị bỏ qua, không ảnh hưởng tới kết quả. Nếu thiếu khai báo, khai báo không nằm ở dòng đầu, có nhiều hơn một khai báo, hoặc mã không hợp lệ — công cụ phải BÁO LỖI VÀ DỪNG LẠI (exit khác 0), tuyệt đối không được âm thầm "thành công" với kết quả rỗng.

**Đã xác nhận sửa đúng:** log CI thật sau khi sửa (chạy trên đúng đầu nhánh này) ghi: "✅ ... implementation-tree digest matches the current implementation tree ... — evidence is fresh, decision is not stale." — nghĩa là Evidence Freshness giờ đã thật sự kiểm tra đúng OWN-2026-004, không còn nhầm sang GAP-010b nữa.

Đây thuần tuý là sửa công cụ quản trị — không đổi hành vi sản phẩm, không phê duyệt việc sửa GAP-010b. Hồ sơ Gate 1 nháp cho GAP-010b vẫn được giữ nguyên tuyệt đối, chưa commit, chưa mở PR. Sẵn sàng chờ owner quyết định lại lần cuối.

## Gói quyết định phát hành — OWN-2026-004: Sửa việc xác định đúng Work ID chính thức của PR

**1. Ba lỗi đã tìm thấy và sửa qua ba vòng, tóm tắt:**
- Vòng 1: công cụ chỉ chấp nhận mã gap dạng `GAP-NNN` (3 chữ số), không chấp nhận mã con như `GAP-010b` — đã sửa mẫu định danh thành `GAP-[0-9]{3}[a-z]?`.
- Vòng 2: cách trích xuất mã trong CI dùng kiểu "khớp một phần chuỗi", khiến mã KHÔNG hợp lệ (ví dụ `GAP-010bb`) bị đọc nhầm thành mã KHÁC hợp lệ (`GAP-010b`) — đã sửa bằng cách bắt buộc khớp trọn vẹn từng mã qua một script dùng chung.
- Vòng 3 (lần này): script dùng chung vẫn quét toàn bộ nội dung PR và lấy mã hợp lệ ĐẦU TIÊN tìm thấy, kể cả trong câu văn không liên quan — đã sửa bằng cách bắt buộc chỉ có đúng một khai báo chính thức ở dòng đầu tiên.

**2. Vì sao lỗi vòng 3 nghiêm trọng hơn 2 lỗi trước?**
Hai lỗi trước chỉ khiến công cụ từ chối nhầm hoặc chấp nhận nhầm MỘT MÃ. Lỗi vòng 3 khiến công cụ chạy xong và báo "thành công" (✅) trong khi thực ra KHÔNG kiểm tra gì cho đúng PR đang xét — một dạng lỗi nguy hiểm hơn vì nó che giấu việc không có kiểm tra thật, thay vì báo lỗi rõ ràng.

**3. Đã kiểm chứng những gì (số liệu thật, chạy lại ngay trước khi trình lại):**
Viết test thất bại trước khi sửa (TDD) — xác nhận đỏ (19 lỗi, bao gồm 1 test dựng lại chính xác nội dung PR #242 lúc còn lỗi) trên mã cũ, xanh sau khi sửa. Bộ test tập trung: 46/46 qua. Toàn bộ bộ test quản trị: 119/119 qua. Lint cấu trúc: 0 lỗi. `bash -n` cả 2 script: hợp lệ. CI thật trên đầu nhánh triển khai: tất cả kiểm tra bắt buộc đều đạt, và log CI xác nhận đúng OWN-2026-004 được kiểm tra (không còn nhầm sang GAP-010b). Ba vòng review độc lập (không có bối cảnh trước, mỗi vòng một phiên riêng) đều xác nhận: 0 lỗi nghiêm trọng, 0 lỗi quan trọng.

**4. Việc này có phê duyệt sửa GAP-010b không?**
Không. Đây thuần tuý là sửa công cụ. GAP-010b vẫn đang mở, chưa được phê duyệt sửa hay triển khai gì. Hồ sơ Gate 1 nháp cho GAP-010b vẫn được giữ nguyên, không bị đụng tới trong cả 3 vòng sửa.

**5. Hành vi sản phẩm có đổi không?**
Không. Chỉ sửa công cụ quản trị nội bộ (2 script CI + 1 workflow) — không đụng tới mã nguồn ứng dụng, route, migration, hay sổ đăng ký gap.

**6. Có thể hoàn tác không?**
Có, hoàn toàn — chỉ cần revert đúng PR sửa công cụ này.

**7. Đề xuất của đội kỹ thuật:** Đã sẵn sàng kỹ thuật, đã kiểm chứng bằng TDD + CI thật + 3 vòng review độc lập. Đề xuất owner xem qua và quyết định lần cuối.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Owner không được yêu cầu phê duyệt việc sửa GAP-010b, GAP-014b, GAP-014c hay bất kỳ gap nào khác — chỉ quyết định có phát hành việc sửa công cụ quản trị này hay không. Owner cũng không được yêu cầu đọc mã nguồn hay log CI — mọi kết luận đã được đội kỹ thuật xác minh trực tiếp qua CI thật và review độc lập.
