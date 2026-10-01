# GAP-059 — SMTP setup exposes and corrupts the mail password: Gate-1 evidence

**Date:** 2026-09-30 (+07:00)

**Canonical base:** `943577f75e8e519dfb42109a094a2632a670fd35`

**Branch:** `docs/GAP-059-smtp-password-argv`

**Scope:** Read-only investigation and Gate-1 documentation. No script, code,
config or deployment change. Reproductions ran in a scratch directory with
fake values; the real script and command were not executed.

## Registered claim (OWN-2026-014)

`scripts/configure-production-smtp.sh:176` passes `--password="$SMTP_PASSWORD"`
to `php artisan smtp:configure`. Re-verified: true. The whole password flow has
more defects than that one line.

## Flow of `scripts/configure-production-smtp.sh` (manual operator script)

1. `:46-49` copies `.env` to `.env.backup.<timestamp>` in the project root
   (`cp`, default umask → typically 0644).
2. `:116` reads the password silently (`read -s`) — good.
3. `:130-139` `update_env_var()` writes each value with
   `sed -i.bak "s/^$key=.*/$key=\"$value\"/" "$ENV_FILE"`; `:148` calls it
   with `MAIL_PASSWORD "$SMTP_PASSWORD"`.
4. `:170-171` runs `php artisan config:clear` and **`php artisan cache:clear`**.
5. `:176` "Testing SMTP configuration" runs
   `php artisan smtp:configure … --password="$SMTP_PASSWORD" …`.

No workflow, Dockerfile, compose file or runbook references the script
(`git grep configure-production-smtp` outside docs: only
`scripts/phase2_cleanup_files.php:128` listing a `.bak` copy). Manual only.

## Defect 1 — password on process command lines (two sites)

- `:176` `--password="$SMTP_PASSWORD"` → visible in `ps`/`/proc/<pid>/cmdline`
  while `php artisan` runs (and the command then waits on a `confirm()`
  prompt, `app/Console/Commands/ConfigureSMTP.php:47-50`, keeping the process
  alive).
- `:136` the password is embedded in the `sed` expression argument, so it is on
  `sed`'s command line too.

## Defect 2 — the script corrupts or fails to write the password

`sed` substitution treats the value as sed syntax. Scratch reproduction:

| Password | Result |
|---|---|
| `ab/cd` | `sed: bad flag in substitute command` — **`.env` not updated**, script continues and reports success (`:166`) |
| `ab&cd` | written as `MAIL_PASSWORD="abMAIL_PASSWORD="old"cd"` (`&` = matched text) |
| `plain"q` | written as `MAIL_PASSWORD="plain"q"` (unbalanced quote) |

## Defect 3 — the "test" step rewrites `.env` again, unquoted

`smtp:configure` is not a test: `commandLineConfiguration()` →
`updateEnvironmentFile()` → `updateEnvValue()`
(`app/Console/Commands/ConfigureSMTP.php:178-188`) does
`preg_replace("/^{$key}=.*$/m", "{$key}={$value}", …)`:

- the value is inserted **unquoted** (spaces or `#` break `.env` parsing);
- `$n` sequences are regex back-references: password `p$1w` is written as
  `pw` (scratch reproduction with the same `preg_replace` call).

So even when the script's `sed` wrote a correct quoted value, the "test" step
overwrites it with an unquoted/corrupted one.

## Defect 4 — plaintext secret copies left in the project root

`.env.backup.<timestamp>` (step 1) and `.env.bak` (every `sed -i.bak`) are full
copies of `.env` with all secrets, created with default permissions
(scratch: `.env.bak` is `-rw-r--r--`) and never removed.

## Related, out of scope

- `:171` `php artisan cache:clear` flushes the shared cache store (all tenants'
  rate-limit counters) — same effect GAP-054/055 removed from automatic and web
  paths; here it is a manual operator step.
- `scripts/run-comprehensive-tests.sh:194` passes a literal test password
  (`test@example.com` account) — not a real credential.
- The interactive path (`php artisan smtp:configure --interactive`) reads the
  password with `secret()` (`ConfigureSMTP.php:82`) — no argv exposure, but it
  shares Defect 3's `updateEnvValue()`.

## Out of scope for this Gate 1

Any fix; rotating the SMTP credential; deciding whether to keep the script;
deployment.
