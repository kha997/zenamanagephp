---
work_id: GAP-059
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-059/02-design.md
---

# GAP-059 — SMTP password exposure/corruption: implementation plan

Design: `docs/owner-decisions/GAP-059/02-design.md` (Option 1, approved 2026-09-30).

## Task 1 — RED
- `tests/Feature/GAP059/ConfigureSmtpWritesEnvSafelyTest.php`: temp `.env`
  via `useEnvironmentPath`; command run with an `ArrayInput` whose stream
  carries the password; 7 tricky passwords round-trip through
  `Dotenv\Parser\Parser`; other lines and file mode kept; `--password=` refused
  with nothing written.
- `tests/Feature/GAP059/SmtpScriptEnvWriterTest.php`: extracted
  `update_env_var()` in real bash with argv-recording awk/sed wrappers; values
  round-trip, no `.env.bak`, mode kept, values absent from argv; script has no
  `--password=`, pipes the password to `smtp:configure --password-stdin`, backs
  up `.env` under `umask 077`.
- `tests/Architecture/NoCommandLineSmtpPasswordTest.php`: no tracked `*.sh`
  passes `--password=` to artisan.

## Task 2 — command
Refuse `--password`; add `--password-stdin` (input stream or STDIN);
`updateEnvValue()` quotes and escapes `\`, `"`, `$` and uses
`preg_replace_callback`; write via temp file + rename keeping mode;
`environmentFilePath()`; return exit codes.

## Task 3 — scripts
`configure-production-smtp.sh`: 0600 backup; awk/ENVIRON `update_env_var()`
without sed or `.env.bak`; MAIL_* written only by one piped
`smtp:configure --password-stdin` call; old "test" rewrite removed.
`run-comprehensive-tests.sh`: pipe its test password.

## Task 4 — verify
GAP-059 + Architecture + OwnerGovernance suites; `bash -n` both scripts;
governance lint; push; full CI; Gate 3.
