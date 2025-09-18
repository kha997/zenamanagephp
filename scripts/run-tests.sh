#!/bin/bash

# Test Execution Script
# Dashboard System - Final Testing & Quality Assurance

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/Applications/XAMPP/xamppfiles/htdocs/zenamanage"
TEST_RESULTS_DIR="$PROJECT_DIR/storage/app/test-results"
COVERAGE_DIR="$PROJECT_DIR/storage/app/coverage"
LOG_FILE="$PROJECT_DIR/storage/logs/test.log"

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

# Create necessary directories
create_directories() {
    log "Creating test directories..."
    
    mkdir -p "$TEST_RESULTS_DIR"
    mkdir -p "$COVERAGE_DIR"
    mkdir -p "$(dirname "$LOG_FILE")"
    
    success "Test directories created"
}

# Run unit tests
run_unit_tests() {
    log "Running unit tests..."
    
    cd "$PROJECT_DIR"
    
    if php artisan test --testsuite=Unit --coverage-html="$COVERAGE_DIR/unit" --log-junit="$TEST_RESULTS_DIR/unit-tests.xml"; then
        success "Unit tests passed"
    else
        error "Unit tests failed"
    fi
}

# Run feature tests
run_feature_tests() {
    log "Running feature tests..."
    
    cd "$PROJECT_DIR"
    
    if php artisan test --testsuite=Feature --coverage-html="$COVERAGE_DIR/feature" --log-junit="$TEST_RESULTS_DIR/feature-tests.xml"; then
        success "Feature tests passed"
    else
        error "Feature tests failed"
    fi
}

# Run integration tests
run_integration_tests() {
    log "Running integration tests..."
    
    cd "$PROJECT_DIR"
    
    if php artisan test --testsuite=Integration --coverage-html="$COVERAGE_DIR/integration" --log-junit="$TEST_RESULTS_DIR/integration-tests.xml"; then
        success "Integration tests passed"
    else
        error "Integration tests failed"
    fi
}

# Run final testing suite
run_final_tests() {
    log "Running final testing suite..."
    
    cd "$PROJECT_DIR"
    
    if php artisan test --testsuite="Final Testing" --coverage-html="$COVERAGE_DIR/final" --log-junit="$TEST_RESULTS_DIR/final-tests.xml"; then
        success "Final tests passed"
    else
        error "Final tests failed"
    fi
}

# Run performance tests
run_performance_tests() {
    log "Running performance tests..."
    
    cd "$PROJECT_DIR"
    
    if php artisan test tests/Feature/PerformanceTest.php --coverage-html="$COVERAGE_DIR/performance" --log-junit="$TEST_RESULTS_DIR/performance-tests.xml"; then
        success "Performance tests passed"
    else
        error "Performance tests failed"
    fi
}

# Run security tests
run_security_tests() {
    log "Running security tests..."
    
    cd "$PROJECT_DIR"
    
    if php artisan test tests/Feature/SecurityTest.php --coverage-html="$COVERAGE_DIR/security" --log-junit="$TEST_RESULTS_DIR/security-tests.xml"; then
        success "Security tests passed"
    else
        error "Security tests failed"
    fi
}

# Run quality assurance tests
run_qa_tests() {
    log "Running quality assurance tests..."
    
    cd "$PROJECT_DIR"
    
    if php artisan test tests/Feature/QualityAssuranceTest.php --coverage-html="$COVERAGE_DIR/qa" --log-junit="$TEST_RESULTS_DIR/qa-tests.xml"; then
        success "Quality assurance tests passed"
    else
        error "Quality assurance tests failed"
    fi
}

# Generate test report
generate_test_report() {
    log "Generating test report..."
    
    cd "$PROJECT_DIR"
    
    # Generate overall coverage report
    php artisan test --coverage-html="$COVERAGE_DIR/overall" --coverage-text --coverage-clover="$TEST_RESULTS_DIR/coverage.xml"
    
    # Generate test summary
    cat > "$TEST_RESULTS_DIR/test-summary.md" << EOF
# Test Execution Summary

**Date:** $(date)
**Project:** Dashboard System
**Environment:** Testing

## Test Results

### Unit Tests
- **Status:** $(grep -c "passed" "$TEST_RESULTS_DIR/unit-tests.xml" || echo "Failed")
- **Coverage:** $(grep -o 'coverage="[^"]*"' "$TEST_RESULTS_DIR/unit-tests.xml" | head -1 | cut -d'"' -f2 || echo "N/A")

### Feature Tests
- **Status:** $(grep -c "passed" "$TEST_RESULTS_DIR/feature-tests.xml" || echo "Failed")
- **Coverage:** $(grep -o 'coverage="[^"]*"' "$TEST_RESULTS_DIR/feature-tests.xml" | head -1 | cut -d'"' -f2 || echo "N/A")

### Integration Tests
- **Status:** $(grep -c "passed" "$TEST_RESULTS_DIR/integration-tests.xml" || echo "Failed")
- **Coverage:** $(grep -o 'coverage="[^"]*"' "$TEST_RESULTS_DIR/integration-tests.xml" | head -1 | cut -d'"' -f2 || echo "N/A")

### Final Tests
- **Status:** $(grep -c "passed" "$TEST_RESULTS_DIR/final-tests.xml" || echo "Failed")
- **Coverage:** $(grep -o 'coverage="[^"]*"' "$TEST_RESULTS_DIR/final-tests.xml" | head -1 | cut -d'"' -f2 || echo "N/A")

### Performance Tests
- **Status:** $(grep -c "passed" "$TEST_RESULTS_DIR/performance-tests.xml" || echo "Failed")
- **Coverage:** $(grep -o 'coverage="[^"]*"' "$TEST_RESULTS_DIR/performance-tests.xml" | head -1 | cut -d'"' -f2 || echo "N/A")

### Security Tests
- **Status:** $(grep -c "passed" "$TEST_RESULTS_DIR/security-tests.xml" || echo "Failed")
- **Coverage:** $(grep -o 'coverage="[^"]*"' "$TEST_RESULTS_DIR/security-tests.xml" | head -1 | cut -d'"' -f2 || echo "N/A")

### Quality Assurance Tests
- **Status:** $(grep -c "passed" "$TEST_RESULTS_DIR/qa-tests.xml" || echo "Failed")
- **Coverage:** $(grep -o 'coverage="[^"]*"' "$TEST_RESULTS_DIR/qa-tests.xml" | head -1 | cut -d'"' -f2 || echo "N/A")

## Coverage Reports
- **Overall Coverage:** $COVERAGE_DIR/overall/index.html
- **Unit Coverage:** $COVERAGE_DIR/unit/index.html
- **Feature Coverage:** $COVERAGE_DIR/feature/index.html
- **Integration Coverage:** $COVERAGE_DIR/integration/index.html
- **Final Coverage:** $COVERAGE_DIR/final/index.html
- **Performance Coverage:** $COVERAGE_DIR/performance/index.html
- **Security Coverage:** $COVERAGE_DIR/security/index.html
- **QA Coverage:** $COVERAGE_DIR/qa/index.html

## Test Logs
- **Test Log:** $LOG_FILE
- **JUnit Reports:** $TEST_RESULTS_DIR/*.xml
- **Coverage XML:** $TEST_RESULTS_DIR/coverage.xml

EOF

    success "Test report generated"
}

# Run code quality checks
run_code_quality() {
    log "Running code quality checks..."
    
    cd "$PROJECT_DIR"
    
    # Run PHP CS Fixer
    if command -v php-cs-fixer > /dev/null 2>&1; then
        log "Running PHP CS Fixer..."
        php-cs-fixer fix --dry-run --diff --verbose
        success "PHP CS Fixer completed"
    else
        warning "PHP CS Fixer not installed, skipping..."
    fi
    
    # Run PHPStan
    if command -v phpstan > /dev/null 2>&1; then
        log "Running PHPStan..."
        phpstan analyse app --level=8 --no-progress
        success "PHPStan completed"
    else
        warning "PHPStan not installed, skipping..."
    fi
    
    # Run Psalm
    if command -v psalm > /dev/null 2>&1; then
        log "Running Psalm..."
        psalm --no-progress
        success "Psalm completed"
    else
        warning "Psalm not installed, skipping..."
    fi
}

# Run all tests
run_all_tests() {
    log "Running all tests..."
    
    create_directories
    run_unit_tests
    run_feature_tests
    run_integration_tests
    run_final_tests
    run_performance_tests
    run_security_tests
    run_qa_tests
    generate_test_report
    run_code_quality
    
    success "All tests completed successfully!"
}

# Main execution
main() {
    log "Starting test execution..."
    
    case "${1:-all}" in
        "unit")
            create_directories
            run_unit_tests
            ;;
        "feature")
            create_directories
            run_feature_tests
            ;;
        "integration")
            create_directories
            run_integration_tests
            ;;
        "final")
            create_directories
            run_final_tests
            ;;
        "performance")
            create_directories
            run_performance_tests
            ;;
        "security")
            create_directories
            run_security_tests
            ;;
        "qa")
            create_directories
            run_qa_tests
            ;;
        "quality")
            run_code_quality
            ;;
        "all")
            run_all_tests
            ;;
        *)
            echo "Usage: $0 [unit|feature|integration|final|performance|security|qa|quality|all]"
            exit 1
            ;;
    esac
}

# Handle script arguments
main "$@"
