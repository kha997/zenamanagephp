---
work_id: GAP-080
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-15-gap-080-code-style-evidence.md
  plan: null
  branch: docs/GAP-080-code-style
  pr: https://github.com/kha997/zenamanagephp/pull/353
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T09:16:20+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-15T09:16:20+07:00"
  updated_at: "2026-10-15T09:16:20+07:00"
generated_by: agent
---

# GAP-080 — php-cs-fixer style debt: Gate 2 design

## Owner Summary

Đã chạy thử trên bản sao của main: định dạng lại xong còn **0** file lỗi, mọi
file vẫn qua `php -l`, và **1694/1694** file PHP thay đổi có cấu trúc code
(AST) **tương đương** bản cũ — chỉ khác thứ tự/bỏ `use` thừa, `??=`, tách
`use A, B;` trong class, `const` → `public const`, và 1 chỗ dời `use Trait`
lên trước property (đều không đổi hành vi).

- **A — định dạng lại toàn bộ một lần, tắt 3 luật đổi nội dung, thêm chặn
  style mới (khuyến nghị):** tắt `no_trailing_whitespace_in_string`,
  `phpdoc_to_comment`, `self_accessor`; chạy fixer cho tất cả; job Code
  Quality **đỏ** khi PR có lỗi style trong file nó sửa; CI dùng đúng phiên bản
  php-cs-fixer trong `composer.lock`.
- **B — như A nhưng không chặn:** bot vẫn chỉ comment; style có thể xấu lại.
- **C — chia nhiều PR theo thư mục:** diff nhỏ hơn mỗi lần nhưng 5 lần qua
  đủ 3 gate.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. `.php-cs-fixer.php`: add `'no_trailing_whitespace_in_string' => false`,
   `'phpdoc_to_comment' => false`, `'self_accessor' => false`. No other rule
   changes, same paths.
2. Run `php-cs-fixer fix` once with that config over `app`, `config`,
   `database`, `routes`, `tests` — committed as a single mechanical commit
   with no hand edits (about 1694 files).
3. `.github/workflows/code-quality-security.yml`, job `code-quality`:
   - "Run PHP CS Fixer": drop `composer require --dev friendsofphp/php-cs-fixer`
     so the locked version (3.95.27) is used.
   - New step after the PR comment step, PR events only: fail when
     `cs-fixer-report.json` lists a file the PR changed, or the fixer run did
     not complete. Pushes to main and schedules are unchanged.
4. `scripts/dev/ast-equivalence-check.php`: the check used to prove item 2
   (parses old and new version of each file with nikic/php-parser, already in
   `vendor`, and compares the ASTs after the normalizations listed above).
   Dev tool only, not wired into CI.
5. Docs: plan, this packet, 03-release.

## Verification

- Fixer dry-run after the change: 0 files to fix; `php -l` on every changed
  file.
- AST-equivalence check: all changed files equivalent; any exception listed
  in Gate 3.
- PHPStan (with baseline) clean; `route:list --json` identical;
  Unit/Feature/Integration suites; governance/SSOT/docs lints; exact-head CI.
- Red/green for the new CI step: on this PR it must pass (clean); a
  throw-away commit adding one mis-formatted line must turn it red, then be
  reverted (both commits disclosed in Gate 3).

## Follow-up (not in this Work ID)

`.git-blame-ignore-revs` needs the squash-merge hash, known only after merge;
it can be a small separate Work ID.

## Rollback

Revert the squash commit.

## Out of scope

Logic changes, PHPStan baseline cleanup, the 3 disabled rules, other
workflows (e.g. `automated-testing.yml`), deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-080 Gate 2 Option A` (khuyến nghị) / Option B /
Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
