# MailHog Setup for E2E Email Testing

MailHog is an email testing tool that captures emails sent by your application.

## Quick Start

### Option 1: Using Docker (Recommended)

```bash
# Start MailHog
./scripts/start-mailhog.sh

# Or manually:
docker run -d \
  --name mailhog \
  -p 1025:1025 \
  -p 8025:8025 \
  mailhog/mailhog

# Stop MailHog
./scripts/stop-mailhog.sh

# Or manually:
docker stop mailhog
docker rm mailhog
```

### Option 2: Using Go (If no Docker)

```bash
# Install MailHog via Go
go install github.com/mailhog/MailHog@latest

# Start MailHog
~/go/bin/MailHog

# Or if in PATH:
MailHog
```

### Option 3: Download Binary

```bash
# macOS
curl -o /usr/local/bin/MailHog https://github.com/mailhog/MailHog/releases/download/v1.0.1/MailHog_darwin_amd64
chmod +x /usr/local/bin/MailHog
MailHog

# Linux
wget https://github.com/mailhog/MailHog/releases/download/v1.0.1/MailHog_linux_amd64
chmod +x MailHog_linux_amd64
./MailHog_linux_amd64

# Windows
# Download from: https://github.com/mailhog/MailHog/releases
```

## Configuration

### Update Laravel .env

Add these settings to your `.env` file for testing:

```env
# MailHog Configuration
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS="test@zenamanage.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Verify MailHog is Running

1. **Check UI**: Open http://localhost:8025 in your browser
2. **Check API**: Visit http://localhost:8025/api/v2/messages
3. **Check Docker**: Run `docker ps | grep mailhog`

## Access MailHog

- **Web UI**: http://localhost:8025
- **SMTP Server**: localhost:1025
- **API Base**: http://localhost:8025/api

## Testing with E2E Suite

The E2E tests are configured to use MailHog automatically:

```bash
# Run auth tests (uses MailHog for email verification)
npm run test:auth

# MailHog URL is configured in tests
MAILBOX_UI=http://localhost:8025
```

## What Gets Captured

- User registration verification emails
- Password reset emails
- Invitation emails
- All emails sent during E2E testing

## Troubleshooting

### MailHog not starting

```bash
# Check if port 8025 is in use
lsof -i :8025

# Kill process using the port (macOS)
kill -9 $(lsof -t -i:8025)

# Check MailHog logs
docker logs mailhog
```

### Email not being captured

1. Verify `.env` MAIL settings are correct
2. Check that MailHog is running: `docker ps | grep mailhog`
3. Test email sending manually
4. Check MailHog UI for captured emails

### Docker issues

```bash
# If Docker is not installed
# macOS: Download Docker Desktop from https://docker.com
# Linux: Install via package manager
# Windows: Download Docker Desktop

# If Docker is not running
# macOS: Start Docker Desktop application
# Linux: sudo systemctl start docker
```

## Manual Email Test

Create a test email to verify MailHog is working:

```bash
php artisan tinker
```

Then in tinker:
```php
Mail::raw('Test email', function ($message) {
    $message->to('test@example.com')
            ->subject('Test Email');
});
```

Check MailHog UI at http://localhost:8025 - you should see the email.

## Integration with CI/CD

For CI/CD environments, use Mailpit instead (lighter alternative):

```yaml
# .github/workflows/e2e-auth.yml
- name: Start Mailpit
  run: |
    docker run -d -p 8025:8025 -p 1025:1025 axllent/mailpit
```

## Alternative: Mailpit

Mailpit is a drop-in replacement for MailHog:

```bash
# Start Mailpit
docker run -d \
  --name mailpit \
  -p 8025:8025 \
  -p 1025:1025 \
  axllent/mailpit

# UI: http://localhost:8025
# API: http://localhost:8025/api
```

## Notes

- MailHog runs on port 1025 (SMTP) and 8025 (HTTP)
- Emails are stored in memory (cleared on restart)
- No authentication required for local development
- Safe to use in development/testing environments only

