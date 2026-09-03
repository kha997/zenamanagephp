#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

DB_NAME="${1:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"
DB_USER="${2:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"
BACKUP_DIR="${3:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"
STORAGE_DIR="${4:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"

mkdir -p "$BACKUP_DIR"
TS="$(date -u +%Y%m%dT%H%M%SZ)"

SQL_FILE="${BACKUP_DIR}/${DB_NAME}-${TS}.sql.gz"
STORAGE_FILE="${BACKUP_DIR}/${DB_NAME}-${TS}-storage.tar.gz"

log "Backing up database '${DB_NAME}' only (no --all-databases) to ${SQL_FILE}"
mysqldump --single-transaction --quick --no-tablespaces -u "$DB_USER" "$DB_NAME" | gzip > "$SQL_FILE"

log "Backing up shared storage from ${STORAGE_DIR} to ${STORAGE_FILE}"
tar -czf "$STORAGE_FILE" -C "$(dirname "$STORAGE_DIR")" "$(basename "$STORAGE_DIR")"

sha256sum "$SQL_FILE" > "${SQL_FILE}.sha256"
sha256sum "$STORAGE_FILE" > "${STORAGE_FILE}.sha256"

log "Backup complete:"
log "  ${SQL_FILE} ($(sha256sum "$SQL_FILE" | cut -d' ' -f1))"
log "  ${STORAGE_FILE} ($(sha256sum "$STORAGE_FILE" | cut -d' ' -f1))"
echo "$SQL_FILE"
echo "$STORAGE_FILE"
