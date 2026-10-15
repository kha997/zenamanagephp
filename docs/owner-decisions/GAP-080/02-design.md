---
work_id: GAP-080
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-15-gap-080-code-style-evidence.md
  plan: docs/superpowers/plans/2026-10-15-gap-080-code-style.md
  branch: docs/GAP-080-code-style
  pr: https://github.com/kha997/zenamanagephp/pull/353
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T09:23:41+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-15, verbatim: 'APPROVE GAP-080 Gate 2 Option A'. Reviewed design head: d73547192f22837eb19a44fbe421bb897b663ca5. Approves Option A and its exact allowlist (.php-cs-fixer.php disables no_trailing_whitespace_in_string, phpdoc_to_comment, self_accessor; one mechanical php-cs-fixer run over app, config, database, routes, tests; code-quality-security.yml code-quality job uses the locked php-cs-fixer and fails on PR-changed files with style issues or an incomplete fixer run; scripts/dev/ast-equivalence-check.php; throw-away red/green commit pair disclosed in Gate 3; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-15T09:16:20+07:00"
  updated_at: "2026-10-15T12:22:02+07:00"
generated_by: agent
---

# GAP-080 — php-cs-fixer style debt: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-15 against reviewed design head
`d73547192f22837eb19a44fbe421bb897b663ca5`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

### Owner amendment (2026-10-15)

During implementation PHPStan reported 2 errors: two `phpstan-baseline.neon`
messages embed the line number of an anonymous migration class
(`...add_missing_performance_indexes.php:8::`,
`...optimize_database_relationships_and_foreign_keys.php:8::`), which became
`:10::` after the reformat. Asked in-session, the Owner selected verbatim:
"Cho sửa 2 dòng baseline (Recommended)" — allowing exactly those two baseline
lines to change `:8:` to `:10:`, adding or removing no entries; to be disclosed
in Gate 3.

Second amendment: `composer ssot:lint` failed because five SSOT baselines
(`scripts/ssot/baselines/hardcoded_api_paths.txt`, `orphan_routes.txt`,
`raw_model_create.txt`, `raw_model_create_feature.txt`,
`raw_model_create_zena.txt`) key entries by `file:line`. Asked in-session, the
Owner selected verbatim: "Cho cập nhật số dòng (Recommended)" — allowing those
files to be regenerated with the repository tool (`SSOT_UPDATE_BASELINES=1`)
so that only line numbers change (183 lines), verified equal to the previous
baselines once line numbers are removed; the two already-stale
`ContractPdfExportTest.php:52` orphan entries are kept unchanged; to be
disclosed in Gate 3.

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
