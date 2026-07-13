# Repo Root Cleanup Proposal

**Date**: 2026-07-13
**Branch**: `worktree-zena-project-model-consolidation` (PR #163)
**Status**: PROPOSAL — no files moved or deleted yet

---

## Overview

The repo root has accumulated **218 `.md` files**, **46 `.php` scripts**, **17 `.sh` scripts**, **9 `.txt` files**, and **3 `.html` files** — most of which are one-shot fix scripts, historical reports, and temporary test outputs from earlier development phases.

This proposal classifies each file and recommends an action. **No files will be moved or deleted without user approval.**

---

## Classification

### Category A: Keep at Root (Project Infrastructure)

These files belong at the root level:

| File | Purpose |
|------|---------|
| `README.md` | Project README |
| `AI_RULES.md` | AI coding rules |
| `PROJECT_RULES.md` | Project conventions |
| `UX_UI_DESIGN_RULES.md` | Design rules |
| `CODE_REUSABILITY_RULES.md` | Code reuse rules |
| `DEPLOYMENT_GUIDE.md` | Deployment docs |
| `DOCKER_PRODUCTION_GUIDE.md` | Docker prod guide |
| `INSTALLATION_GUIDE.md` | Setup instructions |
| `USER_DOCUMENTATION.md` | User docs |
| `DEVELOPER_DOCUMENTATION.md` | Dev docs |
| `API_DOCUMENTATION.md` | API docs |
| `GUARD_LINT_README.md` | Guard-lint docs |
| `legacy-map.json` | Legacy module mapping |
| `structure_standardization_report.json` | Structure audit data |
| `Dockerfile`, `Dockerfile.prod`, `Dockerfile.websocket` | Container definitions |
| `docker-compose.yml`, `docker-compose.prod.yml`, `docker-compose.kong.yml`, `docker-compose.loadbalancer.yml` | Container orchestration |
| `composer.json`, `composer.lock` | PHP dependencies |
| `package.json`, `package-lock.json` | JS dependencies |
| `artisan` | Laravel CLI |
| `phpunit.xml` | Test config |
| `phpstan.neon`, `phpstan-baseline.neon` | Static analysis |
| `vite.config.js`, `tailwind.config.js`, `postcss.config.js` | Frontend build |
| `deptrac.yaml` | Dependency rules |
| `.env`, `.env.example`, `env.example`, `production.env.example` | Environment config |
| `bootstrap_custom.php.disabled` | Disabled bootstrap (safe to keep) |

**Action**: Keep as-is.

---

### Category B: CI-Referenced Scripts (Keep, But Consider Moving)

These scripts are **actively referenced by GitHub Actions workflows**:

| File | Referenced By |
|------|---------------|
| `check_duplicate_imports.php` | `.github/workflows/ci-cd.yml:119` |
| `generate_button_inventory.php` | `.github/workflows/button-tests.yml:49` |

**Action**: Keep at root OR move to `scripts/` and update workflow references. Recommend moving to `scripts/` for cleanliness, but requires updating 2 workflow files.

---

### Category C: One-Shot PHP Fix Scripts (Propose Delete)

44 PHP scripts that are one-time fix/patch scripts, **not referenced** by any workflow, `composer.json`, or `package.json`:

| Files |
|-------|
| `auth-auto-fix.php` |
| `check_and_fix_vendor.php` |
| `check_ports.php` |
| `clear_cache_and_verify_jwt.php` |
| `comment_all_rbac_final.php` |
| `configure_php_path_fixed.php` |
| `disable_rbac_middleware.php` |
| `example_usage.php` |
| `fix_auth_config.php` |
| `fix_auth_facades.php` |
| `fix_auth_manager_comprehensive.php` |
| `fix_auth_service_provider.php` |
| `fix_composer_autoloader_critical.php` |
| `fix_config_provider_definitive.php` |
| `fix_config_service_error.php` |
| `fix_config_service_provider.php` |
| `fix_dashboard.php` |
| `fix_dashboard_comprehensive.php` |
| `fix_imports.php` |
| `fix_jwt_guard_registration.php` |
| `fix_laravel_complete_reinstall.php` |
| `fix_laravel_critical_error.php` |
| `fix_laravel_force_download.php` |
| `fix_laravel_ultimate.php` |
| `fix_laravel_with_php82.php` |
| `fix_log_class_error.php` |
| `fix_middleware_comments.php` |
| `fix_middleware_format.php` |
| `fix_package_discovery_bypass.php` |
| `fix_routes_syntax_final.php` |
| `fix_security_middleware.php` |
| `fix_storage_permissions.php` |
| `fix_syntax_error.php` |
| `fix_vendor_corruption_complete.php` |
| `force_fix_laravel_framework.php` |
| `reinstall_laravel_framework.php` |
| `simple_websocket_server.php` |
| `terminate_brew_and_install_php.php` |
| `test_alpine_dropdown.html` *(HTML, but same category)* |
| `test_csrf_cors_session.php` |
| `test_idempotency.php` |
| `test_tenant_isolation.php` |
| `test_rbac_middleware_basic.php.disabled` |
| `test_rbac_middleware_basic_fixed.php.disabled` |
| `test_rbac_simple.php.disabled` |
| `websocket_api_test.php.disabled` |
| `websocket_test.php.disabled` |

**Action**: Delete all. These are historical fix scripts with no ongoing reference.

---

### Category D: Shell Scripts (Classify Individually)

| File | Referenced By | Action |
|------|---------------|--------|
| `audit-system.sh` | None | **Delete** |
| `deploy-production.sh` | Workflows | **Keep** |
| `deploy.sh` | Workflows | **Keep** |
| `docker-manage.sh` | None | **Delete** |
| `install.sh` | None | **Delete** |
| `manage-cicd.sh` | Workflows | **Keep** |
| `manage-monitoring.sh` | Workflows | **Keep** |
| `run-automated-tests.sh` | Workflows | **Keep** |
| `run_button_tests.sh` | Workflows | **Keep** |
| `setup-cicd.sh` | Workflows | **Keep** |
| `setup-ssl.sh` | None | **Delete** |
| `setup_frontend.sh` | None | **Delete** |
| `start-all-services.sh` | Workflows | **Keep** |
| `stop-all-services.sh` | Workflows | **Keep** |
| `test-docker-setup.sh` | None | **Delete** |
| `test-views.sh` | None | **Delete** |
| `track-roadmap-progress.sh` | None | **Delete** |

**Action**: Delete 7 unreferenced scripts, keep 10 CI-referenced scripts.

---

### Category E: Test Output & Temp Files (Propose Delete)

| File | Content | Action |
|------|---------|--------|
| `cookies.txt` | libcurl cookie jar (no real secrets) | **Delete** |
| `cookies2.txt` | libcurl cookie jar (no real secrets) | **Delete** |
| `cookies3.txt` | libcurl cookie jar (no real secrets) | **Delete** |
| `curl-format.txt` | curl timing format template | **Delete** |
| `test_output_BACKEND_TESTS.txt` | Old test output | **Delete** |
| `test_output_BROWSER_TESTS.txt` | Old test output | **Delete** |
| `test_output_DATABASE_CHECK.txt` | Old test output | **Delete** |
| `test_output_ROUTE_CHECK.txt` | Old test output (empty) | **Delete** |
| `debug_dropdown.html` | Debug page | **Delete** |
| `test-dashboard.html` | Test page | **Delete** |

**Action**: Delete all. These are development artifacts with no ongoing use.

---

### Category F: Historical Reports/Summaries (Propose Archive)

**218 `.md` files** in root — most are historical reports, phase completion summaries, and one-time analysis docs from earlier development.

These include:
- `*_REPORT.md` (50+ files)
- `*_SUMMARY.md` (40+ files)
- `*_PLAN.md` (10+ files)
- `PHASE*_COMPLETION_REPORT.md` (14 files)
- Various `*_FIX_SUMMARY.md`, `*_COMPLETE.md`, `*_STATUS.md` files

**Action**: Move to `docs/archive/reports/` via `git mv`. This keeps history preserved but declutters the root.

**Exception**: Keep `FINAL_CHECKLIST.md`, `LAUNCH_CHECKLIST.md`, and `ROADMAP_*.md` at root if they're still actively referenced.

---

### Category G: Stray Directories (Investigate)

| Directory | Purpose | Action |
|-----------|---------|--------|
| `Applications/` | Unknown — possibly XAMPP artifact | **Investigate & delete** |
| `examples/` | Example code | **Keep** (if useful) or **delete** |
| `roadmap-progress/` | Roadmap tracking | **Move to `docs/`** or **delete** |

---

## Summary

| Category | Count | Action |
|----------|-------|--------|
| A: Keep at root | ~30 files | Keep |
| B: CI-referenced scripts | 2 files | Keep (or move to `scripts/`) |
| C: One-shot PHP fixes | 44 files | **Delete** |
| D: Shell scripts (unreferenced) | 7 files | **Delete** |
| E: Test output/temp files | 10 files | **Delete** |
| F: Historical reports | ~218 files | **Move to `docs/archive/reports/`** |
| G: Stray directories | 1-3 dirs | Investigate |

**Net effect**: Root goes from ~359 entries to ~40-50 clean, actively-used files.

---

## Implementation Plan

1. **Delete Category C** (44 PHP scripts) — `git rm` each
2. **Delete Category D unreferenced** (7 shell scripts) — `git rm` each
3. **Delete Category E** (10 temp files) — `git rm` each
4. **Move Category F** (218 .md reports) — `git mv` to `docs/archive/reports/`
5. **Investigate Category G** (stray dirs) — check `Applications/`, `examples/`, `roadmap-progress/`
6. **Update `.gitignore`** — add `cookies*.txt`, `test_output_*.txt` to prevent re-commit
7. **Commit**: `chore: clean up repo root — archive reports, remove one-shot scripts`

---

## Risk Assessment

- **Low risk**: All proposed deletions are unreferenced historical artifacts
- **CI impact**: Only 2 PHP scripts are CI-referenced (`check_duplicate_imports.php`, `generate_button_inventory.php`) — these are KEPT
- **History preservation**: Reports archived to `docs/archive/reports/`, not deleted
- **Reversibility**: All changes are via `git`, fully reversible

---

## Next Steps

1. User reviews and approves this proposal
2. Execute deletions and moves per implementation plan
3. Run full test suite to verify no breakage
4. Commit and push

---

**DECISION (2026-07-13): User APPROVED this cleanup.** Execute per `docs/superpowers/plans/2026-07-13-opencode-handoff-4.md` Task M: one commit per category, re-grep references before every `git rm`/`git mv`, run route:list + architecture tests between commits, full suite + PHPStan at the end.
