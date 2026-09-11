# Controlled production deployment

No deployment is performed by the baseline task.

## Gate

1. Require a clean, reviewed commit on a production-deployable branch.
2. Run PHP syntax, secret scan, Docker Compose validation, reminder `--dry-run`, and release verification.
3. Build with `powershell -File deployment/build_production_release.ps1`.
4. Confirm the release contains no `.env`, production `config.php`, database, backup, upload, session, cache, or log data.

## Backup → deploy → smoke → rollback

1. Run `/docker/anas/backup.sh` and verify the new backup directory contains `database.sql.gz`, `app-config.tar.gz`, and `SHA256SUMS` with valid checksums.
2. Create a code-only rollback snapshot or retain the timestamped `app-config.tar.gz`.
3. Preserve `.env`, `db`, `backups`, Work OS `config.php`, and `storage/documents`.
4. Replace only tracked portfolio and Work OS source plus intentionally changed runtime files. Do not run destructive SQL; baseline schema migrations already match production.
5. Rebuild/restart only `anas-web` if the Dockerfile changed. Do not recreate `anas-db` for a source-only deployment.
6. Smoke: `/`, `/os/login.php`, authenticated dashboard, contacts/jobs/content reads, and `php /var/www/public_html/os/cron/send_reminders.php --dry-run`. Do not send test email during automated smoke.
7. Roll back code/runtime from the pre-deploy snapshot if smoke fails. Restore the database only for proven database corruption and only after preserving the failed state.

Do not merge the baseline branch into `main` until the branch and release are reviewed. Push the branch only.
