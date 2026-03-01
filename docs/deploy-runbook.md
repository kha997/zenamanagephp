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

## Export Deliverables (HTML/PDF/ZIP) - Smoke Check
Use these commands from the application host after deploy. They exercise the API directly without browser UI.

Provision PDF runtime when the deploy target is expected to generate PDFs:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden || exit 1
npm ci
npx playwright install chromium
```

Export HTML and inspect the download headers:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden || exit 1
BASE_URL="https://your-host.example.com"
TOKEN="replace-with-api-token"
TENANT_ID="replace-with-tenant-ulid"
WORK_INSTANCE_ID="replace-with-work-instance-ulid"
TEMPLATE_VERSION_ID="replace-with-template-version-ulid"

curl -sS \
  -D /tmp/work-instance-export-html.headers \
  -o /tmp/work-instance-export.html \
  -X POST "$BASE_URL/api/zena/work-instances/$WORK_INSTANCE_ID/export" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -F "deliverable_template_version_id=$TEMPLATE_VERSION_ID"

sed -n '1,20p' /tmp/work-instance-export-html.headers
```

Expected result: `HTTP/1.1 200`, `Content-Type: text/html; charset=utf-8`, and `Content-Disposition: attachment; filename="deliverable-...html"`.

Export PDF and allow either supported or deploy-safe behavior:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden || exit 1
BASE_URL="https://your-host.example.com"
TOKEN="replace-with-api-token"
TENANT_ID="replace-with-tenant-ulid"
WORK_INSTANCE_ID="replace-with-work-instance-ulid"
TEMPLATE_VERSION_ID="replace-with-template-version-ulid"

curl -sS \
  -D /tmp/work-instance-export-pdf.headers \
  -o /tmp/work-instance-export.pdf-or-error.json \
  -X POST "$BASE_URL/api/zena/work-instances/$WORK_INSTANCE_ID/export" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -F "deliverable_template_version_id=$TEMPLATE_VERSION_ID" \
  -F "format=pdf"

sed -n '1,20p' /tmp/work-instance-export-pdf.headers
```

Expected result when PDF runtime is available: `HTTP/1.1 200`, `Content-Type: application/pdf`, and `Content-Disposition: attachment; filename="deliverable-...pdf"`.

Expected result when Chromium or Playwright runtime is missing: `HTTP/1.1 501` and a JSON body with the deploy-safe error message. This is acceptable for PDF-only smoke on hosts that have not installed the PDF runtime yet.

Export the ZIP bundle and verify the archive headers:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden || exit 1
BASE_URL="https://your-host.example.com"
TOKEN="replace-with-api-token"
TENANT_ID="replace-with-tenant-ulid"
WORK_INSTANCE_ID="replace-with-work-instance-ulid"
TEMPLATE_VERSION_ID="replace-with-template-version-ulid"

curl -sS \
  -D /tmp/work-instance-export-bundle.headers \
  -o /tmp/work-instance-export-bundle.zip \
  -X POST "$BASE_URL/api/zena/work-instances/$WORK_INSTANCE_ID/export-bundle" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -F "deliverable_template_version_id=$TEMPLATE_VERSION_ID"

sed -n '1,20p' /tmp/work-instance-export-bundle.headers
unzip -l /tmp/work-instance-export-bundle.zip
```

Expected result: `HTTP/1.1 200`, `Content-Type: application/zip`, and `Content-Disposition: attachment; filename=work-instance-...zip`.

The ZIP listing must contain `manifest.json`, `deliverable.html`, and at least one `attachments/...` entry. `deliverable.pdf` may exist when PDF runtime is installed. If it does not exist, `manifest.json` must still show `"available": false` for `pdf` and include the reason string.

Troubleshooting:
- If `POST /export` with `format=pdf` returns `501`, install dependencies with `npm ci` and `npx playwright install chromium`, then rerun the smoke command.
- If ZIP export still returns `200` while PDF is unavailable, that is the correct deploy-safe contract as long as `manifest.json` reports `pdf.available=false` and includes the reason.
