---
work_id: GAP-081
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-15-gap-081-git-blame-ignore-revs-evidence.md
  plan: docs/superpowers/plans/2026-10-15-gap-081-git-blame-ignore-revs.md
  branch: docs/GAP-081-git-blame-ignore-revs
  pr: https://github.com/kha997/zenamanagephp/pull/354
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T18:35:47+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-15, verbatim: 'APPROVE GAP-081 Gate 2 Option A'. Reviewed design head: 8d5b7c336f9b660a99a81f39570cbd89cf0ebc38. Approves Option A and its exact allowlist (new root .git-blame-ignore-revs with the comment header and the single hash 40d19ab10f51c77587127f2ab5c477724f4ab7cf; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-15T18:34:30+07:00"
  updated_at: "2026-10-15T18:35:47+07:00"
generated_by: agent
---

# GAP-081 — git blame ignore file: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-15 against reviewed design head
`8d5b7c336f9b660a99a81f39570cbd89cf0ebc38`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

- **A — chỉ thêm file `.git-blame-ignore-revs` (khuyến nghị):** file ở thư
  mục gốc, có chú thích ngắn và đúng 1 dòng `40d19ab1…` (đủ 40 ký tự).
  GitHub tự áp dụng; chú thích trong file ghi lệnh bật trên máy.
- **B — như A và thêm một đoạn trong README:** hướng dẫn bật trên máy ở
  README (README hiện không có mục cho người đóng góp).

Đề xuất **Phương án A** (nhỏ nhất; hướng dẫn nằm ngay trong file).

## Design: Option A (exact allowlist)

1. New file `.git-blame-ignore-revs`:

   ```
   # Commits that only reformat code; git blame skips them.
   # GitHub applies this file automatically. Locally:
   #   git config blame.ignoreRevsFile .git-blame-ignore-revs

   # GAP-080: php-cs-fixer applied repo-wide (squash of PR #353)
   40d19ab10f51c77587127f2ab5c477724f4ab7cf
   ```
2. Docs: plan, this packet, 03-release.

## Verification

- `git blame --ignore-revs-file .git-blame-ignore-revs` on a reformatted file
  shows pre-GAP-080 commits (e.g. `database/seeders/RoleSeeder.php` line 1);
  without the file it shows `40d19ab1`.
- `git rev-parse --verify 40d19ab1…^{commit}` succeeds (hash exists on main).
- Governance/docs lints; exact-head CI.

## Rollback

Revert the squash commit.

## Out of scope

Other commits, README changes (Option B), CI, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-081 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
