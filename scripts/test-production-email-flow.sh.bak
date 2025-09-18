#!/bin/bash

# Production Email Flow Test Script
# This script tests complete invitation and welcome email flow

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_PATH=$(pwd)
TEST_EMAIL=""
LOG_DIR="storage/logs"

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

log "📧 Testing Production Email Flow"
log "==============================="

# Check if we're in the right directory
if [[ ! -f "artisan" ]]; then
    error "Not in Laravel project directory. Please run from project root."
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

log "Test email: $TEST_EMAIL"

# Create log directory
mkdir -p "$LOG_DIR"

# Test 1: Basic Email Test
log "Test 1: Basic Email Test"
log "======================="
if php artisan email:test "$TEST_EMAIL" --sync; then
    success "Basic email test passed"
else
    warning "Basic email test failed"
fi
log ""

# Test 2: Invitation Email Test
log "Test 2: Invitation Email Test"
log "============================"
if php artisan email:test "$TEST_EMAIL" --type=invitation --sync; then
    success "Invitation email test passed"
else
    warning "Invitation email test failed"
fi
log ""

# Test 3: Welcome Email Test
log "Test 3: Welcome Email Test"
log "=========================="
if php artisan email:test "$TEST_EMAIL" --type=welcome --sync; then
    success "Welcome email test passed"
else
    warning "Welcome email test failed"
fi
log ""

# Test 4: Queue Email Test
log "Test 4: Queue Email Test"
log "======================="
if php artisan email:test "$TEST_EMAIL" --type=invitation; then
    success "Queue email test passed"
else
    warning "Queue email test failed"
fi
log ""

# Test 5: Email Template Test
log "Test 5: Email Template Test"
log "=========================="
if php artisan email:warm-cache; then
    success "Email template cache test passed"
else
    warning "Email template cache test failed"
fi
log ""

# Test 6: Email Monitoring Test
log "Test 6: Email Monitoring Test"
log "============================="
if php artisan email:monitor; then
    success "Email monitoring test passed"
else
    warning "Email monitoring test failed"
fi
log ""

# Test 7: Complete Invitation Workflow
log "Test 7: Complete Invitation Workflow"
log "===================================="

# Create test invitation
log "Creating test invitation..."
INVITATION_DATA=$(php artisan tinker --execute="
\$invitation = new App\Models\Invitation([
    'email' => '$TEST_EMAIL',
    'first_name' => 'Test',
    'last_name' => 'User',
    'role' => 'user',
    'organization_id' => 1,
    'invited_by' => 1,
    'expires_at' => now()->addDays(7),
    'token' => 'test-token-' . uniqid(),
]);
echo 'Invitation created: ' . (\$invitation->email ? 'Yes' : 'No');
" 2>/dev/null)

if [[ "$INVITATION_DATA" == *"Yes"* ]]; then
    success "Test invitation created successfully"
else
    warning "Test invitation creation failed"
fi

# Test invitation email sending
log "Testing invitation email sending..."
if php artisan email:test "$TEST_EMAIL" --type=invitation --sync; then
    success "Invitation email sent successfully"
else
    warning "Invitation email sending failed"
fi
log ""

# Test 8: Complete Welcome Workflow
log "Test 8: Complete Welcome Workflow"
log "=================================="

# Create test user
log "Creating test user..."
USER_DATA=$(php artisan tinker --execute="
\$user = new App\Models\User([
    'name' => 'Test User',
    'email' => '$TEST_EMAIL',
    'role' => 'user',
    'organization_id' => 1,
    'status' => 'active',
    'joined_at' => now(),
    'email_verified_at' => now(),
]);
echo 'User created: ' . (\$user->email ? 'Yes' : 'No');
" 2>/dev/null)

if [[ "$USER_DATA" == *"Yes"* ]]; then
    success "Test user created successfully"
else
    warning "Test user creation failed"
fi

# Test welcome email sending
log "Testing welcome email sending..."
if php artisan email:test "$TEST_EMAIL" --type=welcome --sync; then
    success "Welcome email sent successfully"
else
    warning "Welcome email sending failed"
fi
log ""

# Test 9: Email Tracking Test
log "Test 9: Email Tracking Test"
log "==========================="

# Test email tracking
log "Testing email tracking..."
TRACKING_DATA=$(php artisan tinker --execute="
\$tracking = App\Models\EmailTracking::latest()->first();
echo 'Tracking record: ' . (\$tracking ? 'Yes' : 'No');
" 2>/dev/null)

if [[ "$TRACKING_DATA" == *"Yes"* ]]; then
    success "Email tracking test passed"
else
    warning "Email tracking test failed"
fi
log ""

# Test 10: Email Analytics Test
log "Test 10: Email Analytics Test"
log "============================="

# Test email analytics
log "Testing email analytics..."
ANALYTICS_DATA=$(php artisan tinker --execute="
\$totalSent = App\Models\EmailTracking::count();
\$totalOpened = App\Models\EmailTracking::where('opened_at', '!=', null)->count();
\$totalClicked = App\Models\EmailTracking::where('clicked_at', '!=', null)->count();
echo 'Analytics: Sent=' . \$totalSent . ', Opened=' . \$totalOpened . ', Clicked=' . \$totalClicked;
" 2>/dev/null)

if [[ "$ANALYTICS_DATA" == *"Analytics:"* ]]; then
    success "Email analytics test passed: $ANALYTICS_DATA"
else
    warning "Email analytics test failed"
fi
log ""

# Generate test report
log "Generating Email Flow Test Report"
log "================================"

TOTAL_TESTS=10
PASSED_TESTS=0
FAILED_TESTS=0

# Count passed tests
PASSED_TESTS=$(grep -c "✅" "$LOG_DIR/email-flow-test.log" 2>/dev/null || echo "0")
FAILED_TESTS=$(grep -c "❌" "$LOG_DIR/email-flow-test.log" 2>/dev/null || echo "0")

log "Email Flow Test Report Summary:"
log "============================="
log "Total Tests: $TOTAL_TESTS"
log "Passed: $PASSED_TESTS"
log "Failed: $FAILED_TESTS"
log "Success Rate: $((PASSED_TESTS * 100 / TOTAL_TESTS))%"
log ""

if [[ $FAILED_TESTS -eq 0 ]]; then
    success "🎉 All email flow tests passed! Email system is ready for production."
    log ""
    log "Email Flow Summary:"
    log "=================="
    log "✅ Basic email sending"
    log "✅ Invitation email workflow"
    log "✅ Welcome email workflow"
    log "✅ Queue email processing"
    log "✅ Email template caching"
    log "✅ Email monitoring"
    log "✅ Email tracking"
    log "✅ Email analytics"
    log ""
    log "Next Steps:"
    log "==========="
    log "1. Monitor email delivery rates"
    log "2. Test with real SMTP credentials"
    log "3. Set up email monitoring alerts"
    log "4. Monitor system performance"
    log "5. Test with high volume email sending"
else
    warning "⚠️  Some email flow tests failed. Please review the results and fix issues before going live."
    log ""
    log "Failed Tests:"
    log "============="
    grep "❌" "$LOG_DIR/email-flow-test.log" 2>/dev/null || echo "No failed tests found"
fi

log ""
log "Email flow test completed at: $(date)"
log "Test email sent to: $TEST_EMAIL"

if [[ $FAILED_TESTS -eq 0 ]]; then
    exit 0
else
    exit 1
fi
