---
work_id: GAP-076
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-076/02-design.md
---

# GAP-076 — Production php.ini: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-076/02-design.md`).

1. **Red first** — add the CI PHP-runtime assertions; locally they fail against the current `php.ini`/`opcache.ini`.
2. **php.ini** — drop `disable_functions` and the `[security]` duplicate, `extension=` lines, `[imagick]`, `[opcache]`,
   redis session handler, `error_log` path, duplicated and removed directives.
3. **opcache.ini** — `save_comments=1`, `interned_strings_buffer=32`; drop `file_cache*`, `error_log`, `huge_code_pages`,
   `fast_shutdown`.
4. **Verify** — local run on the base image, then exact-head CI including the extended Docker smoke.
