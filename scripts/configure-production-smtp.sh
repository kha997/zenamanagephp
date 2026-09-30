#!/bin/bash

# Production SMTP Configuration Script
# This script configures SMTP for production environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_PATH=$(pwd)
ENV_FILE=".env"
BACKUP_ENV_FILE=".env.backup.$(date +%Y%m%d_%H%M%S)"

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    exit 1
}

log "📧 Configuring Production SMTP Settings"
log "======================================"

# Check if we're in the right directory
if [[ ! -f "artisan" ]]; then
    error "Not in Laravel project directory. Please run from project root."
fi

# Backup current .env file
if [[ -f "$ENV_FILE" ]]; then
    # GAP-059: the backup holds every secret, so it is owner-only (0600).
    (umask 077 && cp "$ENV_FILE" "$BACKUP_ENV_FILE")
    success "Backed up current .env file to: $BACKUP_ENV_FILE (owner-only)"
fi

# SMTP Provider Selection
log "Select SMTP Provider for Production:"
echo "1. Gmail (smtp.gmail.com:587)"
echo "2. SendGrid (smtp.sendgrid.net:587)"
echo "3. Mailgun (smtp.mailgun.org:587)"
echo "4. Outlook (smtp.office365.com:587)"
echo "5. Amazon SES (email-smtp.us-east-1.amazonaws.com:587)"
echo "6. Postmark (smtp.postmarkapp.com:587)"
echo "7. Custom SMTP"
echo ""

read -p "Enter your choice (1-7): " PROVIDER_CHOICE

case $PROVIDER_CHOICE in
    1)
        PROVIDER="gmail"
        SMTP_HOST="smtp.gmail.com"
        SMTP_PORT="587"
        SMTP_ENCRYPTION="tls"
        ;;
    2)
        PROVIDER="sendgrid"
        SMTP_HOST="smtp.sendgrid.net"
        SMTP_PORT="587"
        SMTP_ENCRYPTION="tls"
        ;;
    3)
        PROVIDER="mailgun"
        SMTP_HOST="smtp.mailgun.org"
        SMTP_PORT="587"
        SMTP_ENCRYPTION="tls"
        ;;
    4)
        PROVIDER="outlook"
        SMTP_HOST="smtp.office365.com"
        SMTP_PORT="587"
        SMTP_ENCRYPTION="tls"
        ;;
    5)
        PROVIDER="ses"
        SMTP_HOST="email-smtp.us-east-1.amazonaws.com"
        SMTP_PORT="587"
        SMTP_ENCRYPTION="tls"
        ;;
    6)
        PROVIDER="postmark"
        SMTP_HOST="smtp.postmarkapp.com"
        SMTP_PORT="587"
        SMTP_ENCRYPTION="tls"
        ;;
    7)
        read -p "Enter SMTP Host: " SMTP_HOST
        read -p "Enter SMTP Port: " SMTP_PORT
        read -p "Enter SMTP Encryption (tls/ssl/none): " SMTP_ENCRYPTION
        PROVIDER="custom"
        ;;
    *)
        error "Invalid choice. Please run the script again."
        ;;
esac

# Get SMTP credentials
log "Enter SMTP Credentials:"
read -p "SMTP Username/Email: " SMTP_USERNAME
read -s -p "SMTP Password/API Key: " SMTP_PASSWORD
echo ""
read -p "From Email Address: " FROM_ADDRESS
read -p "From Name (e.g., ZenaManage): " FROM_NAME

# Validate inputs
if [[ -z "$SMTP_HOST" || -z "$SMTP_PORT" || -z "$SMTP_USERNAME" || -z "$SMTP_PASSWORD" || -z "$FROM_ADDRESS" || -z "$FROM_NAME" ]]; then
    error "All fields are required. Please run the script again."
fi

# Update .env file
log "Updating .env file with SMTP configuration..."

# Function to update or add environment variable (GAP-059).
# The value is double-quoted with \, " and $ escaped as .env parsing expects,
# and handed to awk through the environment, never on a command line. The file
# is rewritten via a temp file that keeps .env's mode; no .env.bak is created.
update_env_var() {
    local key=$1
    local value=$2
    local escaped tmp mode

    escaped=${value//\\/\\\\}
    escaped=${escaped//\"/\\\"}
    escaped=${escaped//\$/\\\$}
    mode=$(stat -c %a "$ENV_FILE" 2>/dev/null || stat -f %Lp "$ENV_FILE")
    tmp=$(umask 077 && mktemp "${ENV_FILE}.XXXXXX")

    ENV_KEY="$key" ENV_LINE="$key=\"$escaped\"" awk '
        BEGIN { key = ENVIRON["ENV_KEY"] "="; line = ENVIRON["ENV_LINE"]; done = 0 }
        index($0, key) == 1 { if (!done) { print line; done = 1 } next }
        { print }
        END { if (!done) print line }
    ' "$ENV_FILE" > "$tmp"

    chmod "$mode" "$tmp"
    mv "$tmp" "$ENV_FILE"
}

# Update SMTP settings (GAP-059): smtp:configure is the single writer of the
# MAIL_* values; the password goes in on STDIN (printf is a shell builtin), so
# it never appears on a command line.
if ! printf '%s\n' "$SMTP_PASSWORD" | php artisan smtp:configure --no-interaction --password-stdin --provider="$PROVIDER" --host="$SMTP_HOST" --port="$SMTP_PORT" --encryption="$SMTP_ENCRYPTION" --username="$SMTP_USERNAME" --from-address="$FROM_ADDRESS" --from-name="$FROM_NAME"; then
    error "Failed to write the SMTP settings to $ENV_FILE"
fi

# Update queue settings for production
update_env_var "MAIL_QUEUE_ENABLED" "true"
update_env_var "MAIL_QUEUE_CONNECTION" "redis"
update_env_var "MAIL_QUEUE_INVITATION" "emails-high"
update_env_var "MAIL_QUEUE_WELCOME" "emails-welcome"
update_env_var "MAIL_QUEUE_DEFAULT" "emails-low"

# Update monitoring settings
update_env_var "MONITORING_ENABLED" "true"
update_env_var "MONITORING_ALERT_EMAIL" "$FROM_ADDRESS"
update_env_var "MONITORING_CHECK_INTERVAL" "300"
update_env_var "MONITORING_ALERT_THRESHOLD" "50"

success "SMTP configuration updated in .env file"

# Clear Laravel caches
log "Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
success "Laravel caches cleared"


# Test email sending
log "Testing email sending..."
read -p "Enter test email address: " TEST_EMAIL

if [[ -n "$TEST_EMAIL" ]]; then
    if php artisan email:test "$TEST_EMAIL" --sync; then
        success "Test email sent successfully to: $TEST_EMAIL"
    else
        warning "Test email failed - please check your SMTP settings"
    fi
else
    warning "Skipping email test - no test email provided"
fi

log "Production SMTP Configuration Summary:"
log "====================================="
log "Provider: $PROVIDER"
log "Host: $SMTP_HOST"
log "Port: $SMTP_PORT"
log "Encryption: $SMTP_ENCRYPTION"
log "Username: $SMTP_USERNAME"
log "From Address: $FROM_ADDRESS"
log "From Name: $FROM_NAME"
log "Queue Enabled: Yes"
log "Monitoring Enabled: Yes"
log ""
log "Backup File: $BACKUP_ENV_FILE"
log "Environment File: $ENV_FILE"
log ""
log "Next Steps:"
log "==========="
log "1. Start queue workers: ./scripts/start-workers.sh"
log "2. Set up monitoring: ./scripts/setup-monitoring-alerts.sh"
log "3. Test email flow: php artisan email:test"
log "4. Monitor performance: php artisan system:monitor"

success "Production SMTP configuration completed!"
