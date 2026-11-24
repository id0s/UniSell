#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/home/dos/projek/backups}"
DB_NAME="${DB_DATABASE:-unisell}"
DB_USER="${DB_USERNAME:-unisell}"
DB_PASS="${DB_PASSWORD:-unisell_secret}"
DB_HOST="${DB_HOST:-127.0.0.1}"

mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d_%H%M%S)"
OUT_FILE="${BACKUP_DIR}/${DB_NAME}_${STAMP}.sql.gz"

mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --single-transaction --routines --triggers "$DB_NAME" | gzip > "$OUT_FILE"

# Retain last 14 daily backups
find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -type f -mtime +14 -delete

echo "Backup created: $OUT_FILE"
