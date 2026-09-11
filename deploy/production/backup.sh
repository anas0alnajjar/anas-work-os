#!/bin/bash
set -euo pipefail

BASE="/docker/anas"
STAMP="$(date -u +%Y-%m-%d_%H-%M-%S)"
DEST="$BASE/backups/auto/$STAMP"

mkdir -p "$DEST"
chmod 700 "$DEST"

echo "[$(date -u)] Starting Anas backup..."

# Database
/usr/bin/docker exec anas-db sh -lc \
'exec mariadb-dump \
  -uroot \
  -p"$MARIADB_ROOT_PASSWORD" \
  --single-transaction \
  --quick \
  --triggers \
  --default-character-set=utf8mb4 \
  "$MARIADB_DATABASE"' \
| gzip > "$DEST/database.sql.gz"

# Application + configuration + uploaded documents
tar -czf "$DEST/app-config.tar.gz" \
  -C "$BASE" \
  .env \
  Dockerfile \
  docker-compose.yml \
  source

(
  cd "$DEST"
  sha256sum database.sql.gz app-config.tar.gz > SHA256SUMS
)

chmod 600 "$DEST"/*

# Keep 14 days
find "$BASE/backups/auto" \
  -mindepth 1 -maxdepth 1 -type d \
  -mtime +14 \
  -exec rm -rf {} +

echo "[$(date -u)] Backup completed: $DEST"
