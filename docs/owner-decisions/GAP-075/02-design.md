---
work_id: GAP-075
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-12-gap-075-quiet-bot-comments-evidence.md
  plan: null
  branch: docs/GAP-075-quiet-bot-comments
  pr: https://github.com/kha997/zenamanagephp/pull/348
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-12T23:11:03+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-12T23:11:03+07:00"
  updated_at: "2026-10-12T23:11:03+07:00"
generated_by: agent
---

# GAP-075 — Quiet repeated PR bot comments: Gate 2 design

## Owner Summary

Hai phương án:

- **A — chỉ comment khi có vấn đề, một comment cố định cho mỗi loại
  (khuyến nghị):** bot im lặng khi sạch hoặc không xấu hơn main. Khi có vấn
  đề, bot tạo **một** comment và các lần push sau chỉ cập nhật comment đó nếu
  nội dung đổi; khi vấn đề hết, bot xoá comment đó.
- **B — bỏ hết comment:** báo cáo chỉ nằm trong trang tóm tắt của job
  (Actions summary). Gọn nhất, nhưng lỗ hổng bảo mật sẽ không hiện trên PR
  (các bước quét hiện chưa làm đỏ check).

Cả hai đều bỏ comment "Coverage: 95.1%" cố định của `button-tests.yml`.
Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

Files: `.github/workflows/code-quality-security.yml`,
`.github/workflows/button-tests.yml`, plan, this packet, 03-release.

1. **Shared rule for the 5 comment steps** (`actions/github-script`):
   each step builds a `problems` body. It looks for its own earlier comment by
   a hidden marker `<!-- zena-ci:<key> -->` (key = `code-quality`, `security`,
   `dependency`, `docker`, `license`).
   - `problems` empty → delete the marked comment if one exists; post nothing.
   - `problems` non-empty → create the marked comment if missing, or update it
     only when the body changed; never a second comment.
2. **When each step reports a problem:**
   - Code quality: PHPStan errors > 0 (main is clean), or php-cs-fixer flags a
     file that this PR changes (file list from the PR API). The 1702 existing
     files that the PR does not touch are not reported.
   - Security: `composer audit` advisories > 0 or security-checker findings > 0.
   - Dependency: only when the PR makes more packages outdated than main —
     `composer outdated --locked` on the PR lock vs on the base lock (base
     `composer.json`/`composer.lock` fetched by base SHA into a temp dir); the
     comment lists only the packages that are new in the PR's list.
   - Docker: Trivy vulnerabilities > 0.
   - License step (`composer-require-checker`): its output file is non-empty.
3. `button-tests.yml`: remove the "Comment PR" step (static text, hard-coded
   coverage figure).
4. Nothing else changes: check pass/fail, artifacts, SARIF upload, schedules.

## Verification

- `actionlint`-style YAML parse of both workflows; governance/docs lints.
- Exact-head CI on this PR: all checks green and **no new bot report comments**
  on PR #348 (it is clean and does not change `composer.lock`).
- Red/positive path: a throw-away commit on this branch adding one
  mis-formatted line to one PHP file (so the PR touches it) must produce exactly one
  `code-quality` comment; reverting it must delete that comment. Both commits
  stay in history and are disclosed in Gate 3.

## Rollback

Revert the squash commit.

## Out of scope

Fixing the 1702 style files, package upgrades, making scans blocking (step 5),
other workflows' comments, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-075 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
