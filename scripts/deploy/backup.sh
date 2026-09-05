#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

DB_NAME="${1:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"
DB_USER="${2:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"
BACKUP_DIR="${3:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"
STORAGE_DIR="${4:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"

require_dir "$STORAGE_DIR" "storage directory"
mkdir -p "$BACKUP_DIR"
TS="$(date -u +%Y%m%dT%H%M%SZ)"

SQL_FILE="${BACKUP_DIR}/${DB_NAME}-${TS}.sql.gz"
STORAGE_FILE="${BACKUP_DIR}/${DB_NAME}-${TS}-storage.tar.gz"

log "Backing up database '${DB_NAME}' only (no --all-databases) to ${SQL_FILE}"
mysqldump --single-transaction --quick --no-tablespaces -u "$DB_USER" "$DB_NAME" | gzip > "$SQL_FILE"

log "Backing up shared storage from ${STORAGE_DIR} to ${STORAGE_FILE}"
tar -czf "$STORAGE_FILE" -C "$(dirname "$STORAGE_DIR")" "$(basename "$STORAGE_DIR")"

# Checksums are recorded with a basename-only path (via `cd` into BACKUP_DIR
# first), not the full $SQL_FILE/$STORAGE_FILE path — sha256sum embeds the
# filename it was given into the checksum file, and restore.sh verifies by
# `cd`-ing into the artifact's directory and checking the basename. If the
# checksum recorded the full backup-time path instead, verification would
# fail the moment the artifact is copied to a different directory or host
# for restore (exactly what a real restore drill does), even though the
# artifact itself is intact.
(cd "$BACKUP_DIR" && sha256sum "$(basename "$SQL_FILE")") > "${SQL_FILE}.sha256"
(cd "$BACKUP_DIR" && sha256sum "$(basename "$STORAGE_FILE")") > "${STORAGE_FILE}.sha256"

log "Backup complete:"
log "  ${SQL_FILE} ($(sha256sum "$SQL_FILE" | cut -d' ' -f1))"
log "  ${STORAGE_FILE} ($(sha256sum "$STORAGE_FILE" | cut -d' ' -f1))"
echo "$SQL_FILE"
echo "$STORAGE_FILE"
