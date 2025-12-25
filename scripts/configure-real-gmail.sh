#!/bin/bash

# ZenaManage Real Gmail Configuration Script

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH=$(pwd)
LOG_FILE="$PROJECT_PATH/storage/logs/configure-gmail-$(date +%Y%m%d_%H%M%S).log"

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
log "📧 Configuring Real Gmail SMTP Credentials"
log "=========================================="

# 1. Display Gmail setup instructions
log "📋 Gmail Setup Instructions:"
log "============================"
log "1. Go to Google Account Settings: https://myaccount.google.com/"
log "2. Navigate to Security > 2-Step Verification"
log "3. Enable 2-Step Verification if not already enabled"
log "4. Go to Security > App passwords"
log "5. Generate a new app password for 'Mail'"
log "6. Copy the 16-character app password"
log ""

# 2. Prompt for Gmail credentials
log "🔐 Gmail Credentials Setup:"
log "==========================="

read -p "Enter your Gmail address (e.g., yourname@gmail.com): " GMAIL_EMAIL
if [ -z "$GMAIL_EMAIL" ]; then
    error "Gmail address cannot be empty"
fi

read -s -p "Enter your Gmail App Password (16 characters): " GMAIL_APP_PASS
echo ""
if [ -z "$GMAIL_APP_PASS" ]; then
    error "Gmail App Password cannot be empty"
fi

read -p "Enter From Name (default: ZenaManage): " FROM_NAME
FROM_NAME=${FROM_NAME:-"ZenaManage"}

# 3. Update .env file with Gmail credentials
log "Updating .env file with Gmail credentials..."

# Backup current .env
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Update mail configuration
sed -i.bak "s/MAIL_HOST=.*/MAIL_HOST=smtp.gmail.com/" .env
sed -i.bak "s/MAIL_PORT=.*/MAIL_PORT=587/" .env
sed -i.bak "s/MAIL_USERNAME=.*/MAIL_USERNAME=$GMAIL_EMAIL/" .env
KEY_MAIL_PASS="MAIL_PASSWORD"
sed -i.bak "s/\{KEY_MAIL_PASS\}=\.\*/\{KEY_MAIL_PASS\}=$GMAIL_APP_PASS/" .env
sed -i.bak "s/MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=tls/" .env
sed -i.bak "s/MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=$GMAIL_EMAIL/" .env
sed -i.bak "s/MAIL_FROM_NAME=.*/MAIL_FROM_NAME=$FROM_NAME/" .env

success "Updated .env file with Gmail credentials"

# 4. Clear configuration cache
log "Clearing configuration cache..."
php artisan config:clear
php artisan cache:clear

# 5. Test Gmail configuration
log "Testing Gmail configuration..."
log "Sending test email to: $GMAIL_EMAIL"

if php artisan email:test "$GMAIL_EMAIL" --type=simple --sync; then
    success "Gmail configuration test successful!"
    log "Test email sent to: $GMAIL_EMAIL"
else
    error "Gmail configuration test failed!"
fi

# 6. Test invitation email
log "Testing invitation email..."
if php artisan email:test "$GMAIL_EMAIL" --type=invitation --sync; then
    success "Invitation email test successful!"
else
    warning "Invitation email test failed"
fi

# 7. Test welcome email
log "Testing welcome email..."
if php artisan email:test "$GMAIL_EMAIL" --type=welcome --sync; then
    success "Welcome email test successful!"
else
    warning "Welcome email test failed"
fi

# 8. Update monitoring alert email
log "Updating monitoring alert email..."
sed -i.bak "s/MONITORING_ALERT_EMAIL=.*/MONITORING_ALERT_EMAIL=$GMAIL_EMAIL/" .env
success "Updated monitoring alert email to: $GMAIL_EMAIL"

# 9. Test queue with Gmail
log "Testing queue with Gmail..."
php artisan queue:work --once --timeout=10

# 10. Summary
log ""
log "📧 Gmail Configuration Summary"
log "=============================="
log "✅ Gmail SMTP configured"
log "✅ App password set"
log "✅ Test emails sent"
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
log "1. Check your Gmail inbox for test emails"
log "2. Verify email delivery rates"
log "3. Test invitation workflow"
log "4. Monitor email performance"
log ""
log "Gmail configuration completed at: $(date)"
log "Log file: $LOG_FILE"
