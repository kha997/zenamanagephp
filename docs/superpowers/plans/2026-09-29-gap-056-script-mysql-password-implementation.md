---
work_id: GAP-056
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-056/02-design.md
---

# GAP-056 — Shell-script MySQL password: implementation plan

Design: `docs/owner-decisions/GAP-056/02-design.md` (Option 1, approved 2026-09-29).

## Task 1 — RED guards
- Extend `tests/Architecture/NoCommandLineDatabasePasswordTest.php` with a
  line-based scan of every git-tracked `*.sh`: `-p$…`/`-p"$…"`/`--password=` on
  a mysql/mysqldump line, `:-root_password`, and database password variables
  defaulted to a non-empty literal. Expect 34 offenders at base (32 argv sites —
  one more than Gate 1 counted, lowercase `-p"$password"` in
  `setup-replication.sh:29` — plus two `"password"` defaults).
- `tests/Feature/GAP056/MysqlCredentialsHelperTest.php`: helper with stub
  clients; expect failure while the helper does not exist.

## Task 2 — helper `scripts/lib/mysql-credentials.sh`
`mysql_option_file VAR user pass [host] [port]` (0600, printf builtin,
registered for removal on EXIT, fails closed), `mysql_container_client`,
`mysql_compose_root_client` (option file built inside the container from its
own `MYSQL_ROOT_PASSWORD`).

## Task 3 — replace sites
Host-side scripts: `mysql_option_file DB_CNF …` before each call and
`--defaults-extra-file="$DB_CNF"` as the first client option. Container root:
`docker-manage.sh`, `scripts/deploy.sh`. Container app user:
`scripts/backup-database.sh`. Remove `"password"` defaults
(`backup-system.sh`, `setup-replication.sh`). Monitoring scripts skip the DB
metric instead of aborting when credentials are missing.

## Task 4 — `setup-production.sh` + `docker-manage.sh` backups
Root-only 0600 option file, credential-free 0700 cron script in root's
crontab, 0700 backup dir, `.env` excluded from tar; `docker-manage.sh` stops
copying `production.env`. Test: `tests/Feature/GAP056/SetupProductionBackupScriptTest.php`.

## Task 5 — verify
All GAP-056 tests green; `bash -n` on every touched script; governance lint +
`tests/Unit/OwnerGovernance`; push; full CI; Gate 3 with canonical digest.
