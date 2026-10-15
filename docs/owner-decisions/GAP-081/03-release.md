---
work_id: GAP-081
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-15-gap-081-git-blame-ignore-revs-evidence.md
  plan: docs/superpowers/plans/2026-10-15-gap-081-git-blame-ignore-revs.md
  branch: docs/GAP-081-git-blame-ignore-revs
  pr: https://github.com/kha997/zenamanagephp/pull/354
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T19:15:40+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-15T19:15:40+07:00"
  updated_at: "2026-10-15T19:15:40+07:00"
generated_by: agent
residual_risk_rating: none
mandatory_technical_gate_summary: "GAP-081 at subject 1a058295: adds the root .git-blame-ignore-revs with a comment header and the single hash 40d19ab10f51c77587127f2ab5c477724f4ab7cf (GAP-080 squash). The hash resolves to a commit on main; git blame --ignore-revs-file on database/seeders/RoleSeeder.php attributes lines 1 and 3 to f913f040 instead of 40d19ab1 (line 2, a blank line the reformat added, stays on 40d19ab1). No code, CI or history change. Governance and docs lints pass; exact-head CI 34/34 green at 1a058295."
technical_evidence:
  base_sha: "40d19ab10f51c77587127f2ab5c477724f4ab7cf"
  subject_sha: "1a0582958aea62b6f972a4482b1bd5b6e888e199"
  implementation_tree_digest: "2a55892145ba09577e589369fa20886bdf08806e4cb791d681a1dc3e6d55e785"
  verified_pr_head_sha: "1a0582958aea62b6f972a4482b1bd5b6e888e199"
  verified_at: "2026-10-15T19:15:40+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-081 — Gate 3 release decision (.git-blame-ignore-revs)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Sau GAP-080, `git blame` trên hầu hết dòng PHP chỉ hiện
commit định dạng lại `40d19ab1` (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):** file `.git-blame-ignore-revs` ở thư
mục gốc, chú thích ngắn + đúng 1 mã `40d19ab10f51c77587127f2ab5c477724f4ab7cf`.
GitHub tự dùng; trên máy bật bằng
`git config blame.ignoreRevsFile .git-blame-ignore-revs`.

**3. Bằng chứng**

- Base `40d19ab1`; subject `1a0582958aea62b6f972a4482b1bd5b6e888e199`; digest `2a55892145ba09577e589369fa20886bdf08806e4cb791d681a1dc3e6d55e785`.
- Mã commit tồn tại trên main.
- `RoleSeeder.php`: không có file → dòng 1, 3 hiện `40d19ab1`; có file →
  hiện `f913f040` (bản gốc). Dòng 2 (dòng trống mới) vẫn là `40d19ab1`.
- CI exact head `1a05829` 34/34 xanh; lint governance, docs đạt.

**4. Ảnh hưởng** — Chỉ cách hiển thị blame; không đổi code, CI hay lịch sử.
Những dòng không phải định dạng trong `40d19ab1` (cấu hình, CI, script) cũng
sẽ hiện bản trước khi blame; `git log` vẫn thấy.

**5. Rủi ro còn lại** — Không đáng kể.

**6. Hoàn tác** — Revert squash commit.

**7. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-081 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy.
