#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <path-to-backup.sql.gz>"
  exit 1
fi

BACKUP_FILE="$1"
DB_NAME="${DB_DATABASE:-unisell}"
DB_USER="${DB_USERNAME:-unisell}"
DB_PASS="${DB_PASSWORD:-unisell_secret}"
DB_HOST="${DB_HOST:-127.0.0.1}"

gunzip -c "$BACKUP_FILE" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
echo "Restore completed from: $BACKUP_FILE"
