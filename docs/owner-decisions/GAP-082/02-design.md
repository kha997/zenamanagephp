---
work_id: GAP-082
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-15-gap-082-major-package-upgrades-evidence.md
  plan: null
  branch: docs/GAP-082-major-package-upgrades
  pr: https://github.com/kha997/zenamanagephp/pull/355
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T22:27:35+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-15T22:27:35+07:00"
  updated_at: "2026-10-15T22:27:35+07:00"
generated_by: agent
---

# GAP-082 — Major package upgrades: Gate 2 design

## Owner Summary

Guzzle 8 bị Laravel 12 chặn nên không nằm trong thiết kế. Hai phương án:

- **A — làm cả 3 gói trong một PR, mỗi gói một commit (khuyến nghị):**
  dbal 4 (+ carbon-doctrine-types 3), tinker 3, l5-swagger 11 (+ swagger-php
  6, chuyển 203 chú thích `@OA` sang PHP attribute). Kiểm chứng chính cho
  swagger: tài liệu API sinh ra **trước và sau** phải giống nhau.
- **B — chỉ dbal + tinker:** nhỏ, nhanh; l5-swagger để Work ID sau.

Phát hiện thêm: `storage/api-docs/api-docs.json` đang commit đã cũ — thiếu
các endpoint `/api/v1/legacy-routes/*` dù code đã có chú thích. Phương án A
sẽ sinh lại file này (thêm các endpoint đó).

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. `composer.json` / `composer.lock` (via `composer require … -w`, only the
   named packages and their own dependencies; no blanket update):
   - `doctrine/dbal: ^4.5` (pulls `carbonphp/carbon-doctrine-types` 3.x);
   - `laravel/tinker: ^3.0`;
   - `darkaonline/l5-swagger: ^11.2` (pulls `zircote/swagger-php` 6.x,
     drops `doctrine/annotations`).
   Any other package version the resolver must move is listed in Gate 3.
2. The four files with `@OA` docblocks
   (`app/Http/Controllers/Api/OpenApiController.php`,
   `LegacyRouteMonitoringController.php`, `ProjectManagerController.php`,
   `ErrorEnvelopeSchema.php`): convert annotations to `#[OA\…]` attributes
   (tool-assisted, e.g. Rector; the tool is not added to the repo).
   No other code change.
3. `config/l5-swagger.php`: only the keys v11 requires (e.g. removed
   annotation options), if any.
4. `storage/api-docs/api-docs.json`: regenerated with `l5-swagger:generate`.
5. Docs: plan, this packet, 03-release.

## Verification

- **API docs equivalence:** generate with the current stack (8.6/4.11) and with
  the new stack; the two JSON documents, keys sorted, must be identical apart
  from fields the new generator version defines differently (each listed in
  Gate 3). The committed file's diff then only adds the legacy-route paths.
- `composer validate`, `composer audit`, PHPStan, php-cs-fixer on changed
  files, `route:list` identical, `php artisan tinker --execute` smoke,
  Unit/Feature/Integration suites, governance/SSOT/docs lints, exact-head CI.

## Rollback

Revert the squash commit (restores `composer.json`/`composer.lock`, the four
files and `api-docs.json`).

## Out of scope

Guzzle 8 / Laravel upgrade, other outdated packages, new API documentation
content, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-082 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
