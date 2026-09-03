#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

SQL_FILE="${1:?Usage: restore.sh <sql_backup.sql.gz> <target_db> <db_user> [<storage_tar.tar.gz> <target_storage_dir>]}"
TARGET_DB="${2:?Usage: restore.sh <sql_backup.sql.gz> <target_db> <db_user> [<storage_tar.tar.gz> <target_storage_dir>]}"
DB_USER="${3:?Usage: restore.sh <sql_backup.sql.gz> <target_db> <db_user> [<storage_tar.tar.gz> <target_storage_dir>]}"
STORAGE_FILE="${4:-}"
TARGET_STORAGE_DIR="${5:-}"

[ -f "$SQL_FILE" ] || fail "backup file not found: ${SQL_FILE}"
if [ -f "${SQL_FILE}.sha256" ]; then
  log "Verifying checksum for ${SQL_FILE}"
  (cd "$(dirname "$SQL_FILE")" && sha256sum -c "$(basename "${SQL_FILE}.sha256")")
fi

log "Restoring ${SQL_FILE} into database '${TARGET_DB}' (must already exist and be empty/disposable)"
gunzip -c "$SQL_FILE" | mysql -u "$DB_USER" "$TARGET_DB"

if [ -n "$STORAGE_FILE" ] && [ -n "$TARGET_STORAGE_DIR" ]; then
  [ -f "$STORAGE_FILE" ] || fail "storage backup file not found: ${STORAGE_FILE}"
  if [ -f "${STORAGE_FILE}.sha256" ]; then
    log "Verifying checksum for ${STORAGE_FILE}"
    (cd "$(dirname "$STORAGE_FILE")" && sha256sum -c "$(basename "${STORAGE_FILE}.sha256")")
  fi
  mkdir -p "$TARGET_STORAGE_DIR"
  log "Restoring storage into ${TARGET_STORAGE_DIR}"
  tar -xzf "$STORAGE_FILE" -C "$TARGET_STORAGE_DIR" --strip-components=1
fi

log "Restore complete."
