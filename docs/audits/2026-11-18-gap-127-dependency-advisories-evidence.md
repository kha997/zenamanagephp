# GAP-127 — Security Vulnerability Scan red on main: evidence

Date: 2026-11-18. Base: `main` at `a3743ac6289e4ba4c21ee6d6352c4922a1628c81` (after GAP-126).

Research only. Nothing in the product, the lock files or CI was changed for this document.

## What is red

Job **Security Vulnerability Scan** of workflow **Code Quality & Security** (`.github/workflows/code-quality-security.yml`).
Its last step, "Fail on Composer advisories or npm high/critical vulnerabilities" (GAP-083 / GAP-086), fails when:

- `composer audit` exits non-zero (any advisory, any severity), or
- `npm audit` lists a high/critical advisory whose GHSA id is not in `.npm-audit-ignore` (file does not exist on `main`).

| Run | Event | Date | Head | Composer advisories | npm high/critical |
|---|---|---|---|---|---|
| 43410358204 | schedule | 2026-11-13 | `ca7a878b` | — | — (job green) |
| 43582451908 | schedule | 2026-11-14 | `ca7a878b` | 1 (low) | 0 |
| 43851070840 | schedule | 2026-11-16 | `ca7a878b` | 1 (low) | 0 |
| 44074592786 | schedule | 2026-11-17 | `ca7a878b` | 1 (low) | 0 |
| 44276912771 | push (GAP-126 merge) | 2026-11-18 | `a3743ac6` | 1 (low) | 1 GHSA (6 packages) |

So the job has been red since the **2026-11-14** nightly because of Composer; npm became a second cause on 2026-11-18.
No code change caused either: the head was the same `ca7a878b` from 11-13 to 11-17. Both are new advisories against packages already locked.

Source: the `security-reports` artifact of each run (`composer-audit.json`, `npm-audit.json`) and the job log.

## Advisory 1 — laravel/framework (Composer)

| Field | Value |
|---|---|
| Ids | PKSA-yxbx-3c9s-cjt9, CVE-2026-128083, [GHSA-63hm-g96r-2m2q](https://github.com/advisories/GHSA-63hm-g96r-2m2q) |
| Title | Laravel: Task Output Injection - Concurrency Process |
| Severity | low |
| Published | 2026-11-13 |
| Affected | `< 12.71.0`, `>= 13.0.0 < 13.40.0` |
| Patched | 12.71.0 (12.x line) |
| Locked here | `v12.69.3` (`composer.json`: `^12.0`) |
| Pulled in by | direct dependency of the app |

**What it is.** When app code calls `Concurrency::run()` with the process driver and a task prints user-controlled text to stdout, an attacker can forge the framework's result message. The advisory lists both as non-default prerequisites that need app code.

**Reachable in production: no.** No `Concurrency::` call and no `Illuminate\Support\Facades\Concurrency` import in `app/`, `src/`, `routes/`, `config/` or `bootstrap/`.
The files under `app/Console/Commands/Testing/*Concurrency*` and the comment in `TreasuryPeriodLockService` are about database row locking, not this facade.
The vulnerable framework code is still shipped in `vendor/` on the server, so it would become reachable if someone later used the facade.

**Fix available: yes.** 12.71.0 (Nov 3), 12.72.0 (Nov 10), 12.73.0 (Nov 17), all in `^12.0`, PHP `^8.2` (CI and `config.platform.php` are 8.2).

## Advisory 2 — picomatch (npm)

| Field | Value |
|---|---|
| Id | [GHSA-fc5q-39xc-vjpf](https://github.com/advisories/GHSA-fc5q-39xc-vjpf) |
| Title | picomatch vulnerable to ReDoS through single-branch repeated extglobs |
| Severity | high (CVSS 7.5) |
| Published / last updated | 2026-10-19 / 2026-11-17 |
| Affected | `<= 4.0.7` |
| Patched | **none** — 4.0.7 (2026-08-24) is the latest published version |

`npm audit` counts it six times (one per package on the path). Every one is `dev: true` in `package-lock.json`:

| Package | Locked | Direct? | How picomatch comes in |
|---|---|---|---|
| vite | 8.3.4 | yes (dev) | `vite/node_modules/picomatch@4.0.7` |
| laravel-vite-plugin | 3.2.0 | yes (dev) | via tinyglobby and vite-plugin-full-reload |
| tinyglobby | 0.2.17 | no | `picomatch@4.0.5`, and via fdir 6.5.0 |
| fdir | 6.5.0 | no | `picomatch@4.0.5` (peer, deduped) |
| vite-plugin-full-reload | 1.2.0 | no | `picomatch@2.3.2` |
| picomatch | 2.3.2 / 4.0.5 / 4.0.7 | no | — |

`npm audit` proposes `vite@6.2.7` and `laravel-vite-plugin@0.2.4` as "fixes". Both are major **downgrades** that only drop the path by going back before picomatch was used. They are not real fixes.

**Reachable in production: no.** picomatch only runs inside `npm run build` / `vite dev`:

- `production.yml` runs `npm ci && npm run build` on the GitHub runner, and the tarball excludes `node_modules`.
- The patterns it matches come from our own `vite.config.*` and the plugins' defaults, not from users.
- Nothing from `node_modules` runs on `app.zena.com.vn`; only the built files in `public/build` are served.

The worst case is a slow or stuck build on a developer machine or a CI runner, and only if someone commits a hostile glob.

**Fix available: no.** Until picomatch publishes a patched version, the only way to turn the gate green without a downgrade is a reasoned `.npm-audit-ignore` entry, the mechanism GAP-086 created for this case.

## Other findings in the same artifact

- `composer-audit.json` `abandoned`: none.
- `npm audit`: no low/moderate/critical findings.
- Security Checker and php-cs-fixer security steps are `continue-on-error` and not part of the gate.

## What is not affected

- Production runtime: neither advisory is reachable today (above).
- Other CI jobs on `main`: only this job and, as a consequence, Owner Governance Lint on PR heads.
