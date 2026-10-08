---
work_id: GAP-072
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-08-gap-072-composer-semver-safe-updates-evidence.md
  plan: docs/superpowers/plans/2026-10-08-gap-072-composer-semver-safe-updates.md
  branch: docs/GAP-072-composer-semver-safe-updates
  pr: https://github.com/kha997/zenamanagephp/pull/345
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T20:49:53+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-08, verbatim: 'APPROVE GAP-072 Gate 2 Option A'. Reviewed design head: 35fb3ea546bf5febbcb17d5ddd9c2520123aa19c. Approves Option A and its exact allowlist (composer update with phpstan/phpstan held at 2.2.5; composer.lock moves exactly the 76 in-constraint updates of the trial; no composer.json, code, config, migration, Dockerfile or workflow change); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T20:34:12+07:00"
  updated_at: "2026-10-08T20:49:53+07:00"
generated_by: agent
---

# GAP-072 — Outdated Composer packages: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-08 against reviewed design head
`35fb3ea546bf5febbcb17d5ddd9c2520123aa19c`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Chạy thử trên máy (không commit) cho thấy:

- Cập nhật **76 gói** trong giới hạn hiện tại (giữ PHPStan 2.2.5): `composer
  audit` 0, PHPStan sạch, Unit 894 đạt, Feature + Integration 1774 đạt; chỉ 2
  test lỗi do máy chạy bằng root (cũng lỗi trên main).
- Nếu nâng thêm **PHPStan 2.3**: PHPStan báo **10 lỗi mới** ở code có sẵn (biến
  chưa khai báo trong 2 file — bug thật đang bị ẩn trong baseline; `use
  $validator` thừa; một lệnh `Redis::set` truyền tham số sai kiểu).

Hai phương án:

- **A — 76 gói, giữ PHPStan 2.2.5 (khuyến nghị):** chỉ đổi `composer.lock`,
  không sửa code. Nâng PHPStan 2.3 và sửa 10 lỗi làm ở Work ID riêng.
- **B — 77 gói và sửa 10 lỗi code ngay trong việc này:** phạm vi lớn hơn (sửa
  4 file code ứng dụng), khó khoanh vùng nếu có sự cố.

Đề xuất **Phương án A**.

## Trial evidence (local, not committed)

| Variant | composer audit | PHPStan | Unit | Feature + Integration |
|---|---|---|---|---|
| 77 updates (PHPStan 2.3.0) | 0 | **10 errors** | — | — |
| 76 updates (PHPStan held at 2.2.5) | 0 | clean | 894 passed, 1 failed* | 1774 passed, 1 failed* |

\* `GrandfatherLoaderTest::test_unreadable_file_throws` and
`BackupStorageLocationTest::test_unwritable_disk_fails_and_deletes_nothing`:
both fail only because this environment runs as root (they fail identically on
main here; CI is green).

The 10 PHPStan 2.3 findings:

- `Admin/BasicSidebarController.php` 81–82 and
  `Requests/UpdateInteractionLogRequest.php` 81, 92: undefined `$dbConfig` /
  `$interactionLog` (already in the baseline with a lower count; real bugs).
- `Api/App/SettingsController.php` 116, 189, 286: closure `use ($validator)`
  unused.
- `Services/HealthCheckService.php` 255: `Redis::set($k, $v, 'EX', 60)` — the
  phpredis signature takes an options array.
- 2 `ignore.count` entries tied to the first two.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. 76 updates, PHPStan held** | `composer update` with `phpstan/phpstan` kept at 2.2.5; lockfile only | **Recommended** |
| B. 77 updates + code fixes | Also PHPStan 2.3.0 and fix the 10 findings in 4 app files | Larger scope; mixes dependency and code changes |

## Design: Option A (exact allowlist)

1. `composer update --with phpstan/phpstan:2.2.5` (temporary constraint, no
   `composer.json` change). The resulting `composer.lock` must contain exactly
   the 76 in-constraint updates of the trial (the 77 listed in the Gate-1
   evidence minus `phpstan/phpstan`); anything else moving is stop-and-report.
2. Verification:
   - `composer audit` 0; `composer validate`.
   - PHPStan clean; full Unit, Feature, Integration suites; SSOT, governance,
     docs lints.
   - Exact-head CI green (all workflows incl. real-MySQL jobs and
     `browser-tests`); bot "Dependency Scan Report" drops by about 72.
3. Files: `composer.lock`, governed plan file, this packet and 03-release. No
   application code, config, migration, Dockerfile or workflow change.

## Follow-ups (not in this Work ID)

- PHPStan 2.3 upgrade + the 10 findings (real bugs in two files).
- The 15 major upgrades (dbal 4, Guzzle 8, tinker 3, l5-swagger 11 …).

## Rollback

Revert the squash commit (restores the previous `composer.lock`).

## Out of scope

Majors, PHPStan 2.3, any code change, CI changes, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-072 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
