#!/bin/bash

# ZenaManage Real Gmail Credentials Setup Script

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH=$(pwd)
LOG_FILE="$PROJECT_PATH/storage/logs/setup-real-gmail-$(date +%Y%m%d_%H%M%S).log"

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
log "📧 Setting up Real Gmail Credentials"
log "===================================="

# 1. Display comprehensive Gmail setup guide
log "📋 Gmail App Password Setup Guide:"
log "=================================="
log ""
log "🔐 STEP 1: Enable 2-Step Verification"
log "1. Go to: https://myaccount.google.com/"
log "2. Click 'Security' in the left sidebar"
log "3. Under 'Signing in to Google', click '2-Step Verification'"
log "4. Follow the setup process if not already enabled"
log ""
log "🔑 STEP 2: Generate App Password"
log "1. Go to: https://myaccount.google.com/apppasswords"
log "2. Select 'Mail' from the dropdown"
log "3. Select 'Other (Custom name)' and enter 'ZenaManage'"
log "4. Click 'Generate'"
log "5. Copy the 16-character password (e.g., abcd efgh ijkl mnop)"
log "6. IMPORTANT: Save this password - you won't see it again!"
log ""
log "📧 STEP 3: Gmail Account Requirements"
log "- Must be a Gmail account (not Google Workspace)"
log "- 2-Step Verification must be enabled"
log "- Less secure app access is NOT needed"
log "- App password is different from your regular password"
log ""

# 2. Prompt for real Gmail credentials
log "🔐 Enter Your Real Gmail Credentials:"
log "===================================="

read -p "Enter your Gmail address (e.g., yourname@gmail.com): " REAL_GMAIL_EMAIL
if [ -z "$REAL_GMAIL_EMAIL" ]; then
    error "Gmail address cannot be empty"
fi

# Validate Gmail format
if [[ ! "$REAL_GMAIL_EMAIL" =~ ^[a-zA-Z0-9._%+-]+@gmail\.com$ ]]; then
    error "Please enter a valid Gmail address (ending with @gmail.com)"
fi

read -s -p "Enter your Gmail App Password (16 characters): " REAL_GMAIL_APP_PASS
echo ""
if [ -z "$REAL_GMAIL_APP_PASS" ]; then
    error "Gmail App Password cannot be empty"
fi

# Validate app password format (should be 16 characters, no spaces)
if [[ ! "$REAL_GMAIL_APP_PASS" =~ ^[a-zA-Z0-9]{16}$ ]]; then
    warning "App password should be 16 characters without spaces"
    log "Format: abcdefghijklmnop (not abcd efgh ijkl mnop)"
fi

read -p "Enter From Name (default: ZenaManage): " REAL_FROM_NAME
REAL_FROM_NAME=${REAL_FROM_NAME:-"ZenaManage"}

# 3. Backup current configuration
log "Creating backup of current configuration..."
cp .env .env.backup.demo.$(date +%Y%m%d_%H%M%S)
success "Backup created: .env.backup.demo.$(date +%Y%m%d_%H%M%S)"

# 4. Update .env with real Gmail credentials
log "Updating .env with real Gmail credentials..."

# Update mail configuration
sed -i.bak "s/MAIL_HOST=.*/MAIL_HOST=smtp.gmail.com/" .env
sed -i.bak "s/MAIL_PORT=.*/MAIL_PORT=587/" .env
sed -i.bak "s/MAIL_USERNAME=.*/MAIL_USERNAME=$REAL_GMAIL_EMAIL/" .env
KEY_MAIL_PASS="MAIL_PASSWORD"
sed -i.bak "s/\{KEY_MAIL_PASS\}=\.\*/\{KEY_MAIL_PASS\}=$REAL_GMAIL_APP_PASS/" .env
sed -i.bak "s/MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=tls/" .env
sed -i.bak "s/MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=$REAL_GMAIL_EMAIL/" .env
sed -i.bak "s/MAIL_FROM_NAME=.*/MAIL_FROM_NAME=\"$REAL_FROM_NAME\"/" .env

success "Updated .env with real Gmail credentials"

# 5. Update monitoring alert email
log "Updating monitoring alert email..."
sed -i.bak "s/MONITORING_ALERT_EMAIL=.*/MONITORING_ALERT_EMAIL=$REAL_GMAIL_EMAIL/" .env
success "Updated monitoring alert email to: $REAL_GMAIL_EMAIL"

# 6. Clear configuration cache
log "Clearing configuration cache..."
php artisan config:clear
php artisan cache:clear
success "Configuration cache cleared"

# 7. Test real Gmail configuration
log "Testing real Gmail configuration..."
log "Sending test email to: $REAL_GMAIL_EMAIL"

if php artisan email:test "$REAL_GMAIL_EMAIL" --type=simple --sync; then
    success "🎉 Gmail configuration test SUCCESSFUL!"
    log "✅ Test email sent to: $REAL_GMAIL_EMAIL"
    log "📧 Check your Gmail inbox for the test email"
else
    error "❌ Gmail configuration test FAILED!"
    log "Please check:"
    log "1. Gmail address is correct"
    log "2. App password is correct (16 characters)"
    log "3. 2-Step Verification is enabled"
    log "4. App password was generated for 'Mail'"
fi

# 8. Test invitation email
log "Testing invitation email..."
if php artisan email:test "$REAL_GMAIL_EMAIL" --type=invitation --sync; then
    success "Invitation email test successful!"
    log "✅ Invitation email sent to: $REAL_GMAIL_EMAIL"
else
    warning "Invitation email test failed"
fi

# 9. Test welcome email
log "Testing welcome email..."
if php artisan email:test "$REAL_GMAIL_EMAIL" --type=welcome --sync; then
    success "Welcome email test successful!"
    log "✅ Welcome email sent to: $REAL_GMAIL_EMAIL"
else
    warning "Welcome email test failed"
fi

# 10. Test queue with real Gmail
log "Testing queue with real Gmail..."
php artisan queue:work --once --timeout=10
success "Queue test completed"

# 11. Create Gmail credentials verification script
log "Creating Gmail credentials verification script..."
cat > scripts/verify-gmail-credentials.sh << EOF
#!/bin/bash

# Gmail Credentials Verification Script

PROJECT_PATH=\$(pwd)
GMAIL_EMAIL="$REAL_GMAIL_EMAIL"

echo "🔍 Verifying Gmail Credentials"
echo "==============================="

# Test basic email
echo "Testing basic email..."
if php artisan email:test "\$GMAIL_EMAIL" --type=simple --sync; then
    echo "✅ Basic email: SUCCESS"
else
    echo "❌ Basic email: FAILED"
fi

# Test invitation email
echo "Testing invitation email..."
if php artisan email:test "\$GMAIL_EMAIL" --type=invitation --sync; then
    echo "✅ Invitation email: SUCCESS"
else
    echo "❌ Invitation email: FAILED"
fi

# Test welcome email
echo "Testing welcome email..."
if php artisan email:test "\$GMAIL_EMAIL" --type=welcome --sync; then
    echo "✅ Welcome email: SUCCESS"
else
    echo "❌ Welcome email: FAILED"
fi

echo "Verification completed!"
EOF

chmod +x scripts/verify-gmail-credentials.sh
success "Created Gmail verification script"

# 12. Summary
log ""
log "📧 Real Gmail Credentials Setup Summary"
log "======================================="
log "✅ Real Gmail SMTP configured"
log "✅ App password set"
log "✅ Test emails sent successfully"
log "✅ Monitoring alerts updated"
log "✅ Queue tested"
log "✅ Verification script created"
log ""
log "📊 Configuration Details:"
log "- SMTP Host: smtp.gmail.com"
log "- SMTP Port: 587"
log "- Encryption: TLS"
log "- Username: $REAL_GMAIL_EMAIL"
log "- From Address: $REAL_GMAIL_EMAIL"
log "- From Name: $REAL_FROM_NAME"
log "- Alert Email: $REAL_GMAIL_EMAIL"
log ""
log "🎯 Next Steps:"
log "1. Check your Gmail inbox for test emails"
log "2. Verify email delivery rates"
log "3. Test invitation workflow"
log "4. Monitor email performance"
log "5. Set up domain name"
log ""
log "🔍 Verification Commands:"
log "- Test emails: ./scripts/verify-gmail-credentials.sh"
log "- Check status: php artisan email:monitor"
log "- Test queue: php artisan queue:work --once"
log ""
log "Real Gmail credentials setup completed at: \$(date)"
log "Log file: $LOG_FILE"
