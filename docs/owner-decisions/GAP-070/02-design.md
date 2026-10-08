---
work_id: GAP-070
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-08-gap-070-composer-security-advisories-evidence.md
  plan: null
  branch: docs/GAP-070-composer-security-advisories
  pr: https://github.com/kha997/zenamanagephp/pull/343
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T15:50:30+07:00"
  updated_at: "2026-10-08T15:50:30+07:00"
generated_by: agent
---

# GAP-070 — Composer security advisories: Gate 2 design

## Owner Summary

Hai phương án, cả hai chỉ đổi `composer.lock`:

- **A — cập nhật đúng 6 gói (khuyến nghị):** guzzle 7.15.5, psr7 2.13.1,
  promises 2.5.3, laravel 12.69.3, commonmark 2.10.3, flysystem 3.36.0. Thay
  đổi nhỏ nhất đủ để xoá cả 20 lỗ hổng, dễ kiểm và dễ hoàn tác.
- **B — cập nhật kèm mọi gói phụ thuộc (khoảng 90 gói):** cũng xoá 20 lỗ hổng
  và nâng thêm nhiều gói (symfony, carbon, monolog…), nhưng diff lớn hơn nhiều
  và khó khoanh vùng nếu có lỗi. Việc nâng hàng loạt thuộc bước 3 của kế hoạch.

Đề xuất **Phương án A**.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Targeted** | `composer update guzzlehttp/guzzle guzzlehttp/psr7 guzzlehttp/promises league/commonmark laravel/framework league/flysystem` | **Recommended** |
| B. With dependencies | Same with `--with-dependencies` (~90 packages move) | Larger blast radius; belongs to step 3 |

## Design: Option A (exact allowlist)

1. Run the targeted `composer update` above (no `composer.json` change). The
   resulting `composer.lock` must move exactly these six packages; any other
   package moving is a stop-and-report.
2. Verification:
   - `composer audit` reports **0** advisories (red first: 20 on main).
   - Full local suites: Treasury, Architecture, Feature, Unit, Zena, Services;
     PHPStan; SSOT / governance / docs lints.
   - Exact-head CI green (all workflows, including the real-MySQL jobs and
     `browser-tests`); the bot's "Security Scan Report" shows no Composer
     advisories.
3. Files: `composer.lock`, governed plan file, this packet and 03-release. No
   application code, config, migration, Dockerfile or workflow change.

## Rollback

Revert the squash commit (restores the previous `composer.lock`).

## Out of scope

Major upgrades (Guzzle 8, DBAL 4, l5-swagger 11, tinker 3), the 75
semver-safe updates of step 3, Docker image (step 2), CI gating on
`composer audit` (step 5), deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-070 Gate 2 Option A` (khuyến nghị) / Option B /
Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
