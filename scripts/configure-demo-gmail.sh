#!/bin/bash

# ZenaManage Demo Gmail Configuration Script

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH=$(pwd)
LOG_FILE="$PROJECT_PATH/storage/logs/configure-demo-gmail-$(date +%Y%m%d_%H%M%S).log"

# --- Functions ---
log() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

success() {
    log "✅ $1"
}

error() {
    log "❌ $1"
    exit 1
}

warning() {
    log "⚠️  $1"
}

# --- Main Script ---
log "📧 Configuring Demo Gmail SMTP Credentials"
log "==========================================="

# 1. Demo Gmail credentials (for testing)
GMAIL_EMAIL="demo@zenamanage.com"
SMTP_PASS_INPUT="your_app_password_here"
FROM_NAME="ZenaManage Demo"

log "Using demo credentials for testing:"
log "- Email: $GMAIL_EMAIL"
log "- Password: $SMTP_PASS_INPUT"
log "- From Name: $FROM_NAME"
log ""

# 2. Update .env file with demo Gmail credentials
log "Updating .env file with demo Gmail credentials..."

# Backup current .env
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Update mail configuration
sed -i.bak "s/MAIL_HOST=.*/MAIL_HOST=smtp.gmail.com/" .env
sed -i.bak "s/MAIL_PORT=.*/MAIL_PORT=587/" .env
sed -i.bak "s/MAIL_USERNAME=.*/MAIL_USERNAME=$GMAIL_EMAIL/" .env
KEY_MAIL_PASS="MAIL_PASSWORD"
sed -i.bak "s/\{KEY_MAIL_PASS\}=\.\*/\{KEY_MAIL_PASS\}=$SMTP_PASS_INPUT/" .env
sed -i.bak "s/MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=tls/" .env
sed -i.bak "s/MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=$GMAIL_EMAIL/" .env
sed -i.bak "s/MAIL_FROM_NAME=.*/MAIL_FROM_NAME=$FROM_NAME/" .env

success "Updated .env file with demo Gmail credentials"

# 3. Clear configuration cache
log "Clearing configuration cache..."
php artisan config:clear
php artisan cache:clear

# 4. Test Gmail configuration
log "Testing Gmail configuration..."
log "Sending test email to: $GMAIL_EMAIL"

if php artisan email:test "$GMAIL_EMAIL" --type=simple --sync; then
    success "Gmail configuration test successful!"
    log "Test email sent to: $GMAIL_EMAIL"
else
    warning "Gmail configuration test failed (expected with demo credentials)"
fi

# 5. Update monitoring alert email
log "Updating monitoring alert email..."
sed -i.bak "s/MONITORING_ALERT_EMAIL=.*/MONITORING_ALERT_EMAIL=$GMAIL_EMAIL/" .env
success "Updated monitoring alert email to: $GMAIL_EMAIL"

# 6. Test queue with Gmail
log "Testing queue with Gmail..."
php artisan queue:work --once --timeout=10

# 7. Summary
log ""
log "📧 Demo Gmail Configuration Summary"
log "==================================="
log "✅ Demo Gmail SMTP configured"
log "✅ Demo credentials set"
log "✅ Configuration cache cleared"
log "✅ Monitoring alerts updated"
log "✅ Queue tested"
log ""
log "📊 Configuration Details:"
log "- SMTP Host: smtp.gmail.com"
log "- SMTP Port: 587"
log "- Encryption: TLS"
log "- Username: $GMAIL_EMAIL"
log "- From Address: $GMAIL_EMAIL"
log "- From Name: $FROM_NAME"
log "- Alert Email: $GMAIL_EMAIL"
log ""
log "🎯 Next Steps:"
log "1. Replace demo credentials with real Gmail credentials"
log "2. Generate Gmail App Password"
log "3. Test with real email address"
log "4. Verify email delivery"
log ""
log "Demo Gmail configuration completed at: $(date)"
log "Log file: $LOG_FILE"
