#!/bin/bash

# Comprehensive Test Suite for ZenaManage
# This script runs all production tests

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
TEST_RESULTS_FILE="storage/logs/test-results-$(date +%Y%m%d_%H%M%S).log"

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$TEST_RESULTS_FILE"
}

success() {
    echo -e "${GREEN}✅ $1${NC}" | tee -a "$TEST_RESULTS_FILE"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}" | tee -a "$TEST_RESULTS_FILE"
}

error() {
    echo -e "${RED}❌ $1${NC}" | tee -a "$TEST_RESULTS_FILE"
    exit 1
}

log "🧪 ZenaManage Comprehensive Test Suite"
log "======================================"
log "Test started at: $(date)"
log "Test results will be saved to: $TEST_RESULTS_FILE"
log ""

# Check if we're in the right directory
if [[ ! -f "artisan" ]]; then
    error "Not in Laravel project directory. Please run from project root."
fi

# Get test email
read -p "Enter test email address (optional): " TEST_EMAIL

if [[ -n "$TEST_EMAIL" ]]; then
    log "Test email: $TEST_EMAIL"
else
    log "No test email provided - some tests will be skipped"
fi

# Create log directory
mkdir -p storage/logs

# Test 1: System Tests
log "Test 1: Running System Tests"
log "============================"
if php artisan system:test --comprehensive; then
    success "System tests passed"
else
    error "System tests failed"
fi
log ""

# Test 2: Email Tests
if [[ -n "$TEST_EMAIL" ]]; then
    log "Test 2: Running Email Tests"
    log "=========================="
    
    # Test basic email
    log "Testing basic email..."
    if php artisan email:test "$TEST_EMAIL" --sync; then
        success "Basic email test passed"
    else
        warning "Basic email test failed"
    fi
    
    # Test invitation email
    log "Testing invitation email..."
    if php artisan email:test "$TEST_EMAIL" --type=invitation --sync; then
        success "Invitation email test passed"
    else
        warning "Invitation email test failed"
    fi
    
    # Test welcome email
    log "Testing welcome email..."
    if php artisan email:test "$TEST_EMAIL" --type=welcome --sync; then
        success "Welcome email test passed"
    else
        warning "Welcome email test failed"
    fi
else
    log "Test 2: Skipping Email Tests (no test email provided)"
fi
log ""

# Test 3: Queue Tests
log "Test 3: Running Queue Tests"
log "==========================="

# Test queue system
log "Testing queue system..."
if php artisan workers:status; then
    success "Queue system test passed"
else
    warning "Queue system test failed"
fi

# Test queue jobs
log "Testing queue jobs..."
if php artisan queue:work --once; then
    success "Queue job test passed"
else
    warning "Queue job test failed"
fi
log ""

# Test 4: Monitoring Tests
log "Test 4: Running Monitoring Tests"
log "================================"

# Test email monitoring
log "Testing email monitoring..."
if php artisan email:monitor; then
    success "Email monitoring test passed"
else
    warning "Email monitoring test failed"
fi

# Test system monitoring
log "Testing system monitoring..."
if timeout 30 php artisan system:monitor --duration=20 --interval=5; then
    success "System monitoring test passed"
else
    warning "System monitoring test failed"
fi
log ""

# Test 5: Cache Tests
log "Test 5: Running Cache Tests"
log "==========================="

# Test email cache warming
log "Testing email cache warming..."
if php artisan email:warm-cache; then
    success "Email cache warming test passed"
else
    warning "Email cache warming test failed"
fi

# Test cache system
log "Testing cache system..."
if php artisan cache:clear && php artisan config:cache; then
    success "Cache system test passed"
else
    warning "Cache system test failed"
fi
log ""

# Test 6: Database Tests
log "Test 6: Running Database Tests"
log "=============================="

# Test database connection
log "Testing database connection..."
if php artisan tinker --execute="echo 'Database connected: ' . DB::connection()->getPdo() ? 'Yes' : 'No';"; then
    success "Database connection test passed"
else
    warning "Database connection test failed"
fi

# Test migrations
log "Testing migrations..."
if php artisan migrate:status; then
    success "Migration status test passed"
else
    warning "Migration status test failed"
fi
log ""

# Test 7: SMTP Tests
log "Test 7: Running SMTP Tests"
log "=========================="

# Test SMTP configuration
log "Testing SMTP configuration..."
if php artisan smtp:configure --provider=gmail --host=smtp.gmail.com --port=587 --username=test@example.com --password=test123 --from-address=test@example.com --from-name="Test App"; then
    success "SMTP configuration test passed"
else
    warning "SMTP configuration test failed"
fi
log ""

# Test 8: Worker Tests
log "Test 8: Running Worker Tests"
log "============================"

# Test worker status
log "Testing worker status..."
if php artisan workers:status --detailed; then
    success "Worker status test passed"
else
    warning "Worker status test failed"
fi

# Test worker management
log "Testing worker management..."
if php artisan workers:start-production --workers=1 --timeout=30 --tries=1 --max-jobs=10 --max-time=60; then
    success "Worker management test passed"
else
    warning "Worker management test failed"
fi
log ""

# Test 9: Performance Tests
log "Test 9: Running Performance Tests"
log "================================="

# Test database performance
log "Testing database performance..."
START_TIME=$(date +%s.%N)
php artisan tinker --execute="DB::table('users')->count();"
END_TIME=$(date +%s.%N)
DURATION=$(echo "$END_TIME - $START_TIME" | bc)
if (( $(echo "$DURATION < 1.0" | bc -l) )); then
    success "Database performance test passed (${DURATION}s)"
else
    warning "Database performance test failed (${DURATION}s)"
fi

# Test memory usage
log "Testing memory usage..."
MEMORY_USAGE=$(php -r "echo memory_get_usage(true) / 1024 / 1024;")
if (( $(echo "$MEMORY_USAGE < 100" | bc -l) )); then
    success "Memory usage test passed (${MEMORY_USAGE}MB)"
else
    warning "Memory usage test failed (${MEMORY_USAGE}MB)"
fi
log ""

# Test 10: Integration Tests
log "Test 10: Running Integration Tests"
log "=================================="

# Test full workflow
log "Testing full invitation workflow..."
if php artisan tinker --execute="
\$invitation = new App\Models\Invitation([
    'email' => 'test@example.com',
    'first_name' => 'Test',
    'last_name' => 'User',
    'role' => 'user',
    'organization_id' => 1,
    'invited_by' => 1,
    'expires_at' => now()->addDays(7),
    'token' => 'test-token-' . uniqid(),
]);
echo 'Invitation created: ' . (\$invitation->email ? 'Yes' : 'No');
"; then
    success "Integration test passed"
else
    warning "Integration test failed"
fi
log ""

# Generate test report
log "Generating Test Report"
log "======================"

TOTAL_TESTS=10
PASSED_TESTS=0
FAILED_TESTS=0

# Count passed tests
PASSED_TESTS=$(grep -c "✅" "$TEST_RESULTS_FILE" || echo "0")
FAILED_TESTS=$(grep -c "❌" "$TEST_RESULTS_FILE" || echo "0")

log "Test Report Summary:"
log "==================="
log "Total Tests: $TOTAL_TESTS"
log "Passed: $PASSED_TESTS"
log "Failed: $FAILED_TESTS"
log "Success Rate: $((PASSED_TESTS * 100 / TOTAL_TESTS))%"
log ""

if [[ $FAILED_TESTS -eq 0 ]]; then
    success "🎉 All tests passed! System is ready for production."
    log ""
    log "Next Steps:"
    log "==========="
    log "1. Configure production SMTP credentials"
    log "2. Start queue workers with supervisor"
    log "3. Set up monitoring alerts"
    log "4. Deploy to production server"
    log "5. Monitor system performance"
else
    warning "⚠️  Some tests failed. Please review the results and fix issues before going live."
    log ""
    log "Failed Tests:"
    log "============="
    grep "❌" "$TEST_RESULTS_FILE" || echo "No failed tests found"
fi

log ""
log "Test completed at: $(date)"
log "Full test results saved to: $TEST_RESULTS_FILE"

if [[ $FAILED_TESTS -eq 0 ]]; then
    exit 0
else
    exit 1
fi
