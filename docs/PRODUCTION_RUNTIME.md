# Production runtime

- Host: `masar-vps`
- Root: `/docker/anas`
- Public bind mount: `/docker/anas/source/public_html` → `/var/www/public_html`
- Portfolio: `/var/www/public_html`
- Work OS: `/var/www/public_html/os`
- Containers: `anas-web`, `anas-db`
- Runtime: PHP 8.2/Apache and MariaDB 11.8
- Database bind mount: `/docker/anas/db` → `/var/lib/mysql`
- External proxy network: `nginx-proxy`

Persistent/config paths that deployment must preserve:

- `/docker/anas/.env`
- `/docker/anas/db`
- `/docker/anas/backups`
- `/docker/anas/source/public_html/os/config.php`
- `/docker/anas/source/public_html/os/storage/documents`

`deploy/production/` is sanitized from the actual runtime. `.env.example` contains variable names only. Production secrets remain on the VPS and are not Git content.

The reminder log shows one run per hour. One enabled account currently fails because its reminder recipient is not configured; other accounts continue processing. The worker uses per-user/day or per-user/hour dispatch keys backed by a unique database key, and `flock` prevents overlapping VPS executions.

The old Hostinger/hPanel cron is not accessible from this workspace and remains **NOT VERIFIED**. No claim is made that it is disabled.
