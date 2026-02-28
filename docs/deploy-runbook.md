# Deploy Runbook (Ops)

## Required Production Environment
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` set (non-empty)

## Queue (Required for production)
Local/dev can use `QUEUE_CONNECTION=sync`, but production must use an async driver:
- Recommended: `QUEUE_CONNECTION=database` (or `redis`)
- Run a worker process:
  - `php artisan queue:work` (use supervisor/systemd in real deployments)

## Scheduler / Cron (Required)
Provision cron to run Laravel scheduler every minute:
- `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`

Scheduled tasks include backups, maintenance, queue monitor/restart, and cache optimization.

## Backups
Backups are scheduled via `backup:run` commands in `app/Console/Kernel.php`.

Retention is configurable via `config/backup.php`:
- `backup.max_backups` (default 10)
- `backup.max_age_days` (default 30)
- `backup.disk` (default filesystem disk)
- `backup.path` (default `backups`)

Environment overrides:
- `BACKUP_MAX_BACKUPS`
- `BACKUP_MAX_AGE_DAYS`
- `BACKUP_DISK`
- `BACKUP_PATH`

## Health Endpoints (Public)
- `GET /api/v1/public/health`
- `GET /api/v1/public/health/liveness`
- `GET /api/v1/public/health/readiness`

## Logging / Storage / Mail
- Logging: configure `LOG_CHANNEL` for production aggregation
- Storage: configure `FILESYSTEM_DISK` (local/s3, etc.)
- Mail: configure `MAIL_MAILER` and credentials

## PDF Export Runtime
- Install Node dependencies at the repo root with `npm ci`
- Install Chromium for Playwright with `npx playwright install chromium`
- Linux hosts may also need Playwright system packages; if Chromium launch fails during provisioning, run `npx playwright install --with-deps chromium` or install the equivalent distro packages documented by Playwright
