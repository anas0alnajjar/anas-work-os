# Project status — production baseline 2026-09

- Production behavior preserved; no feature, design, dependency, or schema changes are part of this baseline.
- Work OS comparison before reconciliation: 42 identical, 4 different, 8 local-only, 0 VPS-only.
- Static portfolio comparison before reconciliation: 24 VPS-only files; all were copied byte-for-byte into `portfolio/`.
- VPS hotfixes reconciled: reverse-proxy HTTPS detection, `/os` action-link routing, and reminder worker run observability.
- Production `config.php` is environment-specific and intentionally not imported.
- Production schema has 9 expected migrations with no migration mismatch.
- Reminder runs observed: 168 runs across 168 unique hourly buckets; no duplicate execution evidence and no duplicate dispatch keys.
- Hostinger/hPanel reminder cron status: NOT VERIFIED.
- Backup completed daily through 2026-09-11 and includes DB, application/config, uploads, and SHA-256 checksums.
- Controlled deployment has not been executed.
