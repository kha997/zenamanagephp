# GAP-082 — Evidence: four direct dependencies one major version behind

Base: `8a961a48fd528036b015a0815dc15b0008ebc1b5` (main after GAP-081).
Source: `composer outdated --direct --major-only --locked`, `composer why-not`,
`composer require … --dry-run` (no lock or vendor change).

| Package | Locked | Latest major | Blocker | Code impact |
|---|---|---|---|---|
| `doctrine/dbal` | 3.10.6 | 4.5.0 | `carbonphp/carbon-doctrine-types` 2.1.1 conflicts with dbal ≥4; resolves when it moves to 3.2.1 (allowed by `nesbot/carbon` 3) | No code in `app`, `src`, `database`, `config`, `routes`, `tests` uses `Doctrine\DBAL` (Laravel 12 schema builder is native) |
| `guzzlehttp/guzzle` | 7.15.5 | 8.2.0 | `laravel/framework` 12 requires `guzzlehttp/guzzle ^7.8.2` | **Not installable on Laravel 12** |
| `laravel/tinker` | 2.11.1 | 3.0.2 | only our own `^2.7` constraint | Dev REPL; no code references |
| `darkaonline/l5-swagger` | 8.6.5 | 11.2.0 | needs `zircote/swagger-php` 6 (from 4.11.1); removes `doctrine/annotations` | 4 files carry 203 `@OA\…` docblock annotations (`Api/OpenApiController`, `Api/LegacyRouteMonitoringController`, `Api/ProjectManagerController`, `Api/ErrorEnvelopeSchema`); swagger-php 5+ reads PHP attributes, so these must be converted or the generated docs lose them. `storage/api-docs/api-docs.json` is committed. |

A plain `-W` dry run for tinker or l5-swagger also lifts unrelated packages
(`laravel/framework` 12.69 → 12.70, several `symfony/*` 7.4.x patches).
