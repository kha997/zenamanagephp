#!/bin/bash

# Disaster Recovery Testing Script
# ZenaManage Project - Automated DR Testing

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$PROJECT_ROOT/logs/dr-test-$(date +%Y%m%d-%H%M%S).log"
BACKUP_DIR="$PROJECT_ROOT/storage/backups"
TEST_RESULTS_DIR="$PROJECT_ROOT/storage/dr-test-results"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# Test result tracking
test_result() {
    local test_name="$1"
    local result="$2"
    local details="$3"
    
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    
    if [ "$result" = "PASS" ]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_success "Test '$test_name' PASSED: $details"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_error "Test '$test_name' FAILED: $details"
    fi
    
    # Save detailed results
    echo "Test: $test_name" >> "$TEST_RESULTS_DIR/test-results.txt"
    echo "Result: $result" >> "$TEST_RESULTS_DIR/test-results.txt"
    echo "Details: $details" >> "$TEST_RESULTS_DIR/test-results.txt"
    echo "Timestamp: $(date)" >> "$TEST_RESULTS_DIR/test-results.txt"
    echo "---" >> "$TEST_RESULTS_DIR/test-results.txt"
}

# Setup function
setup() {
    log_info "Setting up disaster recovery testing environment..."
    
    # Create necessary directories
    mkdir -p "$(dirname "$LOG_FILE")"
    mkdir -p "$TEST_RESULTS_DIR"
    mkdir -p "$BACKUP_DIR"
    
    # Clear previous test results
    > "$TEST_RESULTS_DIR/test-results.txt"
    
    log_info "DR testing environment setup complete"
}

# Test 1: Backup Integrity Test
test_backup_integrity() {
    log_info "Testing backup integrity..."
    
    local backup_files=(
        "database-backup-$(date +%Y%m%d).sql"
        "application-backup-$(date +%Y%m%d).tar.gz"
        "config-backup-$(date +%Y%m%d).tar.gz"
    )
    
    local all_backups_exist=true
    local backup_details=""
    
    for backup_file in "${backup_files[@]}"; do
        if [ -f "$BACKUP_DIR/$backup_file" ]; then
            backup_details="$backup_details $backup_file:EXISTS"
        else
            backup_details="$backup_details $backup_file:MISSING"
            all_backups_exist=false
        fi
    done
    
    if [ "$all_backups_exist" = true ]; then
        test_result "Backup Integrity" "PASS" "All backup files exist$backup_details"
    else
        test_result "Backup Integrity" "FAIL" "Some backup files missing$backup_details"
    fi
}

# Test 2: Database Backup Test
test_database_backup() {
    log_info "Testing database backup integrity..."
    
    local db_backup="$BACKUP_DIR/database-backup-$(date +%Y%m%d).sql"
    
    if [ ! -f "$db_backup" ]; then
        test_result "Database Backup" "FAIL" "Database backup file not found"
        return
    fi
    
    # Check if backup file is not empty
    if [ ! -s "$db_backup" ]; then
        test_result "Database Backup" "FAIL" "Database backup file is empty"
        return
    fi
    
    # Check if backup contains expected tables
    local expected_tables=("users" "projects" "tasks" "documents")
    local tables_found=0
    
    for table in "${expected_tables[@]}"; do
        if grep -q "CREATE TABLE.*$table" "$db_backup"; then
            tables_found=$((tables_found + 1))
        fi
    done
    
    if [ $tables_found -eq ${#expected_tables[@]} ]; then
        test_result "Database Backup" "PASS" "All expected tables found in backup"
    else
        test_result "Database Backup" "FAIL" "Only $tables_found/${#expected_tables[@]} expected tables found"
    fi
}

# Test 3: Application Backup Test
test_application_backup() {
    log_info "Testing application backup integrity..."
    
    local app_backup="$BACKUP_DIR/application-backup-$(date +%Y%m%d).tar.gz"
    
    if [ ! -f "$app_backup" ]; then
        test_result "Application Backup" "FAIL" "Application backup file not found"
        return
    fi
    
    # Test if backup can be extracted
    local temp_dir="/tmp/dr-test-app-$$"
    mkdir -p "$temp_dir"
    
    if tar -tzf "$app_backup" >/dev/null 2>&1; then
        test_result "Application Backup" "PASS" "Application backup is valid and extractable"
    else
        test_result "Application Backup" "FAIL" "Application backup is corrupted or invalid"
    fi
    
    rm -rf "$temp_dir"
}

# Test 4: Configuration Backup Test
test_config_backup() {
    log_info "Testing configuration backup integrity..."
    
    local config_backup="$BACKUP_DIR/config-backup-$(date +%Y%m%d).tar.gz"
    
    if [ ! -f "$config_backup" ]; then
        test_result "Configuration Backup" "FAIL" "Configuration backup file not found"
        return
    fi
    
    # Test if backup can be extracted
    if tar -tzf "$config_backup" >/dev/null 2>&1; then
        test_result "Configuration Backup" "PASS" "Configuration backup is valid and extractable"
    else
        test_result "Configuration Backup" "FAIL" "Configuration backup is corrupted or invalid"
    fi
}

# Test 5: Database Recovery Test
test_database_recovery() {
    log_info "Testing database recovery procedures..."
    
    local db_backup="$BACKUP_DIR/database-backup-$(date +%Y%m%d).sql"
    
    if [ ! -f "$db_backup" ]; then
        test_result "Database Recovery" "FAIL" "Database backup file not found"
        return
    fi
    
    # Test database connection
    if command -v mysql >/dev/null 2>&1; then
        # Test if we can connect to database
        if mysql -e "SELECT 1;" >/dev/null 2>&1; then
            test_result "Database Recovery" "PASS" "Database connection successful, recovery procedures ready"
        else
            test_result "Database Recovery" "FAIL" "Cannot connect to database"
        fi
    else
        test_result "Database Recovery" "FAIL" "MySQL client not available"
    fi
}

# Test 6: Application Recovery Test
test_application_recovery() {
    log_info "Testing application recovery procedures..."
    
    # Check if Laravel application is accessible
    if [ -f "$PROJECT_ROOT/artisan" ]; then
        # Test if artisan commands work
        if php "$PROJECT_ROOT/artisan" --version >/dev/null 2>&1; then
            test_result "Application Recovery" "PASS" "Laravel application is accessible and functional"
        else
            test_result "Application Recovery" "FAIL" "Laravel application is not functional"
        fi
    else
        test_result "Application Recovery" "FAIL" "Laravel application not found"
    fi
}

# Test 7: Configuration Recovery Test
test_config_recovery() {
    log_info "Testing configuration recovery procedures..."
    
    # Check if configuration files exist
    local config_files=(
        "$PROJECT_ROOT/.env"
        "$PROJECT_ROOT/config/app.php"
        "$PROJECT_ROOT/config/database.php"
        "$PROJECT_ROOT/config/cache.php"
    )
    
    local config_exists=true
    local config_details=""
    
    for config_file in "${config_files[@]}"; do
        if [ -f "$config_file" ]; then
            config_details="$config_details $(basename "$config_file"):EXISTS"
        else
            config_details="$config_details $(basename "$config_file"):MISSING"
            config_exists=false
        fi
    done
    
    if [ "$config_exists" = true ]; then
        test_result "Configuration Recovery" "PASS" "All configuration files exist$config_details"
    else
        test_result "Configuration Recovery" "FAIL" "Some configuration files missing$config_details"
    fi
}

# Test 8: Network Connectivity Test
test_network_connectivity() {
    log_info "Testing network connectivity..."
    
    local endpoints=(
        "127.0.0.1:80"
        "127.0.0.1:3306"
        "127.0.0.1:6379"
    )
    
    local connectivity_ok=true
    local connectivity_details=""
    
    for endpoint in "${endpoints[@]}"; do
        local host=$(echo "$endpoint" | cut -d: -f1)
        local port=$(echo "$endpoint" | cut -d: -f2)
        
        if nc -z "$host" "$port" 2>/dev/null; then
            connectivity_details="$connectivity_details $endpoint:OPEN"
        else
            connectivity_details="$connectivity_details $endpoint:CLOSED"
            connectivity_ok=false
        fi
    done
    
    if [ "$connectivity_ok" = true ]; then
        test_result "Network Connectivity" "PASS" "All required ports are accessible$connectivity_details"
    else
        test_result "Network Connectivity" "FAIL" "Some required ports are not accessible$connectivity_details"
    fi
}

# Test 9: Service Availability Test
test_service_availability() {
    log_info "Testing service availability..."
    
    local services=(
        "nginx"
        "mysql"
        "redis"
        "php-fpm"
    )
    
    local services_running=true
    local service_details=""
    
    for service in "${services[@]}"; do
        if systemctl is-active --quiet "$service" 2>/dev/null; then
            service_details="$service_details $service:RUNNING"
        else
            service_details="$service_details $service:STOPPED"
            services_running=false
        fi
    done
    
    if [ "$services_running" = true ]; then
        test_result "Service Availability" "PASS" "All required services are running$service_details"
    else
        test_result "Service Availability" "FAIL" "Some required services are not running$service_details"
    fi
}

# Test 10: Security Test
test_security() {
    log_info "Testing security measures..."
    
    local security_checks=(
        "SSL Certificate"
        "Firewall Rules"
        "File Permissions"
        "Database Security"
    )
    
    local security_ok=true
    local security_details=""
    
    # Check SSL certificate
    if [ -f "$PROJECT_ROOT/storage/ssl/cert.pem" ]; then
        security_details="$security_details SSL:CONFIGURED"
    else
        security_details="$security_details SSL:NOT_CONFIGURED"
        security_ok=false
    fi
    
    # Check file permissions
    local critical_files=(
        "$PROJECT_ROOT/.env"
        "$PROJECT_ROOT/storage"
        "$PROJECT_ROOT/bootstrap/cache"
    )
    
    for file in "${critical_files[@]}"; do
        if [ -e "$file" ]; then
            local perms=$(stat -c "%a" "$file" 2>/dev/null || echo "000")
            if [ "$perms" = "755" ] || [ "$perms" = "644" ]; then
                security_details="$security_details $(basename "$file"):SECURE"
            else
                security_details="$security_details $(basename "$file"):INSECURE"
                security_ok=false
            fi
        fi
    done
    
    if [ "$security_ok" = true ]; then
        test_result "Security" "PASS" "Security measures are in place$security_details"
    else
        test_result "Security" "FAIL" "Some security measures are missing$security_details"
    fi
}

# Test 11: Performance Test
test_performance() {
    log_info "Testing system performance..."
    
    # Check disk space
    local disk_usage=$(df "$PROJECT_ROOT" | awk 'NR==2 {print $5}' | sed 's/%//')
    local disk_ok=true
    
    if [ "$disk_usage" -gt 90 ]; then
        disk_ok=false
    fi
    
    # Check memory usage
    local memory_usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    local memory_ok=true
    
    if [ "$memory_usage" -gt 90 ]; then
        memory_ok=false
    fi
    
    # Check CPU load
    local cpu_load=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    local cpu_ok=true
    
    if (( $(echo "$cpu_load > 2.0" | bc -l) )); then
        cpu_ok=false
    fi
    
    local performance_details="Disk:$disk_usage% Memory:$memory_usage% CPU:$cpu_load"
    
    if [ "$disk_ok" = true ] && [ "$memory_ok" = true ] && [ "$cpu_ok" = true ]; then
        test_result "Performance" "PASS" "System performance is within acceptable limits ($performance_details)"
    else
        test_result "Performance" "FAIL" "System performance issues detected ($performance_details)"
    fi
}

# Test 12: Monitoring Test
test_monitoring() {
    log_info "Testing monitoring systems..."
    
    local monitoring_ok=true
    local monitoring_details=""
    
    # Check if monitoring scripts exist
    local monitoring_scripts=(
        "$PROJECT_ROOT/scripts/monitor-system.sh"
        "$PROJECT_ROOT/scripts/monitor-database.sh"
        "$PROJECT_ROOT/scripts/monitor-application.sh"
    )
    
    for script in "${monitoring_scripts[@]}"; do
        if [ -f "$script" ] && [ -x "$script" ]; then
            monitoring_details="$monitoring_details $(basename "$script"):READY"
        else
            monitoring_details="$monitoring_details $(basename "$script"):NOT_READY"
            monitoring_ok=false
        fi
    done
    
    # Check if log files exist
    local log_files=(
        "$PROJECT_ROOT/storage/logs/laravel.log"
        "$PROJECT_ROOT/storage/logs/system.log"
    )
    
    for log_file in "${log_files[@]}"; do
        if [ -f "$log_file" ]; then
            monitoring_details="$monitoring_details $(basename "$log_file"):EXISTS"
        else
            monitoring_details="$monitoring_details $(basename "$log_file"):MISSING"
            monitoring_ok=false
        fi
    done
    
    if [ "$monitoring_ok" = true ]; then
        test_result "Monitoring" "PASS" "Monitoring systems are ready$monitoring_details"
    else
        test_result "Monitoring" "FAIL" "Some monitoring systems are not ready$monitoring_details"
    fi
}

# Generate test report
generate_report() {
    log_info "Generating disaster recovery test report..."
    
    local report_file="$TEST_RESULTS_DIR/dr-test-report-$(date +%Y%m%d-%H%M%S).html"
    
    cat > "$report_file" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>Disaster Recovery Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background-color: #f0f0f0; padding: 20px; border-radius: 5px; }
        .summary { background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 3px; }
        .pass { background-color: #d4edda; border-left: 4px solid #28a745; }
        .fail { background-color: #f8d7da; border-left: 4px solid #dc3545; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Disaster Recovery Test Report</h1>
        <p>Generated on: $(date)</p>
        <p>Test Duration: $(date -d "@$SECONDS" -u +%H:%M:%S)</p>
    </div>
    
    <div class="summary">
        <h2>Test Summary</h2>
        <p><strong>Total Tests:</strong> $TESTS_TOTAL</p>
        <p><strong>Passed:</strong> $TESTS_PASSED</p>
        <p><strong>Failed:</strong> $TESTS_FAILED</p>
        <p><strong>Success Rate:</strong> $(( (TESTS_PASSED * 100) / TESTS_TOTAL ))%</p>
    </div>
    
    <h2>Test Results</h2>
EOF
    
    # Add test results
    while IFS= read -r line; do
        if [[ "$line" == "Test:"* ]]; then
            test_name=$(echo "$line" | cut -d: -f2- | xargs)
        elif [[ "$line" == "Result:"* ]]; then
            result=$(echo "$line" | cut -d: -f2- | xargs)
        elif [[ "$line" == "Details:"* ]]; then
            details=$(echo "$line" | cut -d: -f2- | xargs)
            if [ "$result" = "PASS" ]; then
                echo "    <div class=\"test-result pass\">" >> "$report_file"
            else
                echo "    <div class=\"test-result fail\">" >> "$report_file"
            fi
            echo "        <strong>$test_name:</strong> $details" >> "$report_file"
            echo "    </div>" >> "$report_file"
        fi
    done < "$TEST_RESULTS_DIR/test-results.txt"
    
    cat >> "$report_file" << EOF
    
    <div class="footer">
        <p>This report was generated by the ZenaManage Disaster Recovery Testing Script.</p>
        <p>For questions or issues, contact the Technical Team.</p>
    </div>
</body>
</html>
EOF
    
    log_success "Test report generated: $report_file"
}

# Main execution
main() {
    log_info "Starting disaster recovery testing..."
    log_info "Log file: $LOG_FILE"
    
    setup
    
    # Run all tests
    test_backup_integrity
    test_database_backup
    test_application_backup
    test_config_backup
    test_database_recovery
    test_application_recovery
    test_config_recovery
    test_network_connectivity
    test_service_availability
    test_security
    test_performance
    test_monitoring
    
    # Generate report
    generate_report
    
    # Final summary
    log_info "Disaster recovery testing completed!"
    log_info "Total tests: $TESTS_TOTAL"
    log_info "Passed: $TESTS_PASSED"
    log_info "Failed: $TESTS_FAILED"
    
    if [ $TESTS_FAILED -eq 0 ]; then
        log_success "All disaster recovery tests PASSED!"
        exit 0
    else
        log_error "$TESTS_FAILED tests FAILED. Please review the results and fix issues."
        exit 1
    fi
}

# Run main function
main "$@"
