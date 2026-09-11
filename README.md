# ANAS / Work OS

Production baseline for [anas.masar-up.com](https://anas.masar-up.com): the static portfolio and the PHP/MariaDB Work OS served under `/os`.

## Source of truth

- Local workspace: development and release assembly.
- GitHub: authoritative source and review history.
- VPS `/docker/anas`: production runtime only; do not edit application source there except an emergency hotfix that is immediately reconciled back to Git.

Branches: `main` is production-deployable, `develop` is integration, and work uses `feature/*` or `hotfix/*`. The first reconciled baseline is prepared on `chore/production-baseline-2026-09` and is not merged by this task.

## Layout

- `portfolio/`: static public root source.
- Root PHP files plus `assets/`, `includes/`, `sql/`, `cron/`: Work OS source assembled under `/os`.
- `deploy/production/`: sanitized copies of the actual Docker, backup, environment-variable, and cron runtime files.
- `deployment/build_production_release.ps1`: reproducible release assembler.
- `storage/documents/`: persistent user uploads; contents are never tracked.

See [production runtime](docs/PRODUCTION_RUNTIME.md), [deployment workflow](docs/DEPLOYMENT.md), and [current status](PROJECT_STATUS.md).
