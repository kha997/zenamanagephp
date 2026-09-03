# GAP-049 Backup/Restore Runbook

## Scope

- The ZENA production application database only — never
  `mysqldump --all-databases` (see `scripts/deploy/backup.sh`, which takes
  an explicit single `<db_name>` argument).
- Shared persistent application storage (`shared/storage`) — uploads,
  documents, logs.
- Any other mutable production state identified as necessary for recovery
  is a Gate-3/host-provisioning-time decision, not invented here.

## Mechanism

`scripts/deploy/backup.sh <db_name> <db_user> <backup_dir> <storage_dir>` —
produces a gzip'd `mysqldump` (`--single-transaction --quick --no-tablespaces`,
scoped to `<db_name>` only) and a gzip'd tar of `<storage_dir>`, each with a
`sha256sum` checksum file alongside it.

Least-privilege credential: the `<db_user>` passed to `backup.sh` should be
a dedicated backup credential scoped to `SELECT`/`LOCK TABLES`/`SHOW VIEW`
on the ZENA application database only — not a broad administrative MySQL
account. Provisioning that credential is a host-provisioning-time (external)
step, not something this repository can create for a real host.

## Durability (off-host)

`backup.sh` writes to whatever `<backup_dir>` argument it's given — it is
the caller's (production workflow's / host cron's) responsibility to pass a
path that is **not** on the same disk as the running application (a
separate volume, a remote object store synced after the local write, or a
provider-native snapshot target). The exact off-host destination is an
external Owner input (Gate-2 §5) — this repository does not fabricate one.

## Restore

`scripts/deploy/restore.sh <sql_backup.sql.gz> <target_db> <db_user> [<storage_tar.tar.gz> <target_storage_dir>]`
— verifies the `.sha256` checksum (if present) before restoring, restores
the SQL dump into `<target_db>` (which must already exist and be a
disposable/empty target, never the live production database used to
"prove" restore works), and optionally extracts the storage tarball.

## Mandatory restore-drill evidence (required before this architecture is accepted as complete)

Executed in a disposable, non-production environment — see the GAP-049
Gate-3 evidence record (`docs/owner-decisions/GAP-049/03-release.md`) for
the actual drill run: representative DB rows created, a representative
uploaded file created, `backup.sh` run, both artifacts restored into a
clean disposable MySQL database and a clean disposable storage directory
via `restore.sh`, then both the representative DB row and the representative
file are read back and their content verified byte-for-byte / value-for-value
identical to the originals. Production data is never destroyed or mutated
to produce this evidence.

## Retention / encryption / access

- Retention: a rolling N most-recent backups (exact N is a Gate-3/host
  cron-schedule decision, not fixed by this repository).
- Encryption at rest: wherever the chosen off-host storage mechanism
  supports it (e.g. server-side encryption on an object store, or an
  encrypted volume) — not implemented by `backup.sh` itself, which is
  storage-mechanism-agnostic.
- Access: restricted to the same credential-holder set as production SSH
  access (see `docs/runbooks/gap-049-host-provisioning.md`), not broadened
  separately.
