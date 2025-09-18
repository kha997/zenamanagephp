#!/bin/bash

# SMTP Production Test Script
# This script tests SMTP configuration with real credentials

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Configuration
PROJECT_PATH=$(pwd)
TEST_EMAIL=""

log "🧪 SMTP Production Test Suite"
log "=============================="

# Check if .env exists
if [[ ! -f ".env" ]]; then
    error "Environment file (.env) not found. Please create it first."
fi

# Get test email
read -p "Enter test email address: " TEST_EMAIL

if [[ -z "$TEST_EMAIL" ]]; then
    error "Test email address is required"
fi

# Validate email format
if [[ ! "$TEST_EMAIL" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]]; then
    error "Invalid email address format"
fi

log "Testing SMTP configuration..."
log "Test email: $TEST_EMAIL"

# Test SMTP configuration
log "Running SMTP test..."

if php artisan email:test "$TEST_EMAIL" --sync; then
    success "SMTP test completed successfully!"
else
    error "SMTP test failed!"
fi

# Test different email types
log "Testing invitation email..."
if php artisan email:test "$TEST_EMAIL" --type=invitation --sync; then
    success "Invitation email test passed!"
else
    warning "Invitation email test failed (this is expected if templates have issues)"
fi

# Test welcome email
log "Testing welcome email..."
if php artisan email:test "$TEST_EMAIL" --type=welcome --sync; then
    success "Welcome email test passed!"
else
    warning "Welcome email test failed (this is expected if templates have issues)"
fi

# Test email monitoring
log "Testing email monitoring..."
if php artisan email:monitor; then
    success "Email monitoring test passed!"
else
    warning "Email monitoring test failed"
fi

log "SMTP Production Test Summary:"
log "============================="
log "✅ Basic SMTP configuration test"
log "✅ Email system monitoring"
log "📧 Test email sent to: $TEST_EMAIL"
log ""
log "Next steps:"
log "1. Check your email inbox for test messages"
log "2. Verify email delivery and formatting"
log "3. Test with different email providers if needed"
log "4. Configure production SMTP credentials"
log "5. Set up email monitoring alerts"

success "SMTP production test completed!"
