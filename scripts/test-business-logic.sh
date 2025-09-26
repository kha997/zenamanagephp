#!/bin/bash

# Business Logic Testing Script
# ZenaManage Project - Automated Business Logic Testing

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$PROJECT_ROOT/logs/business-logic-test-$(date +%Y%m%d-%H%M%S).log"
TEST_RESULTS_DIR="$PROJECT_ROOT/storage/test-results"
API_BASE_URL="http://localhost:8000/api/v1"

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

# Test data
TEST_USER_EMAIL="test@zenamanage.com"
TEST_USER_PASSWORD="TestPassword123!"
TEST_TENANT_ID="01HZ123456789ABCDEFGHIJKLMN"
TEST_PROJECT_CODE="TEST001"
TEST_PROJECT_NAME="Test Project"

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
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
    log_info "Setting up business logic testing environment..."
    
    # Create necessary directories
    mkdir -p "$(dirname "$LOG_FILE")"
    mkdir -p "$TEST_RESULTS_DIR"
    
    # Clear previous test results
    > "$TEST_RESULTS_DIR/test-results.txt"
    
    # Check if Laravel application is running
    if ! curl -s "$API_BASE_URL/health" >/dev/null 2>&1; then
        log_error "Laravel application is not running. Please start the application first."
        exit 1
    fi
    
    log_info "Business logic testing environment setup complete"
}

# Helper function to make API calls
api_call() {
    local method="$1"
    local endpoint="$2"
    local data="$3"
    local headers="$4"
    
    local curl_cmd="curl -s -X $method"
    
    if [ -n "$headers" ]; then
        curl_cmd="$curl_cmd -H \"$headers\""
    fi
    
    if [ -n "$data" ]; then
        curl_cmd="$curl_cmd -d '$data'"
    fi
    
    curl_cmd="$curl_cmd \"$API_BASE_URL$endpoint\""
    
    eval "$curl_cmd"
}

# Helper function to get authentication token
get_auth_token() {
    local email="$1"
    local password="$2"
    
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"$email\",\"password\":\"$password\"}" "Content-Type: application/json")
    
    if echo "$response" | grep -q '"success":true'; then
        echo "$response" | grep -o '"token":"[^"]*"' | cut -d'"' -f4
    else
        echo ""
    fi
}

# Test 1: Authentication Tests
test_authentication() {
    log_info "Testing authentication functionality..."
    
    # Test 1.1: Valid login
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"$TEST_USER_EMAIL\",\"password\":\"$TEST_USER_PASSWORD\"}" "Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Valid Login" "PASS" "User can login with valid credentials"
        local token=$(echo "$response" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Valid Login" "FAIL" "User cannot login with valid credentials"
        return
    fi
    
    # Test 1.2: Invalid login
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"$TEST_USER_EMAIL\",\"password\":\"wrongpassword\"}" "Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Invalid Login" "PASS" "System correctly rejects invalid credentials"
    else
        test_result "Invalid Login" "FAIL" "System should reject invalid credentials"
    fi
    
    # Test 1.3: Get user profile
    if [ -n "$token" ]; then
        local response=$(api_call "GET" "/auth/me" "" "Authorization: Bearer $token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Get User Profile" "PASS" "User can retrieve their profile"
        else
            test_result "Get User Profile" "FAIL" "User cannot retrieve their profile"
        fi
    fi
    
    # Test 1.4: Logout
    if [ -n "$token" ]; then
        local response=$(api_call "POST" "/auth/logout" "" "Authorization: Bearer $token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Logout" "PASS" "User can logout successfully"
        else
            test_result "Logout" "FAIL" "User cannot logout successfully"
        fi
    fi
}

# Test 2: User Management Tests
test_user_management() {
    log_info "Testing user management functionality..."
    
    # Get admin token
    local admin_token=$(get_auth_token "$TEST_USER_EMAIL" "$TEST_USER_PASSWORD")
    if [ -z "$admin_token" ]; then
        log_error "Cannot get admin token for user management tests"
        return
    fi
    
    # Test 2.1: Create user
    local new_user_data="{\"name\":\"Test User\",\"email\":\"newuser@test.com\",\"password\":\"NewPassword123!\",\"password_confirmation\":\"NewPassword123!\"}"
    local response=$(api_call "POST" "/users" "$new_user_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Create User" "PASS" "Admin can create new user"
        local user_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Create User" "FAIL" "Admin cannot create new user"
        return
    fi
    
    # Test 2.2: Get users list
    local response=$(api_call "GET" "/users" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Users List" "PASS" "Admin can retrieve users list"
    else
        test_result "Get Users List" "FAIL" "Admin cannot retrieve users list"
    fi
    
    # Test 2.3: Update user
    if [ -n "$user_id" ]; then
        local update_data="{\"name\":\"Updated Test User\"}"
        local response=$(api_call "PUT" "/users/$user_id" "$update_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Update User" "PASS" "Admin can update user information"
        else
            test_result "Update User" "FAIL" "Admin cannot update user information"
        fi
    fi
    
    # Test 2.4: Delete user
    if [ -n "$user_id" ]; then
        local response=$(api_call "DELETE" "/users/$user_id" "" "Authorization: Bearer $admin_token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Delete User" "PASS" "Admin can delete user"
        else
            test_result "Delete User" "FAIL" "Admin cannot delete user"
        fi
    fi
}

# Test 3: Project Management Tests
test_project_management() {
    log_info "Testing project management functionality..."
    
    # Get admin token
    local admin_token=$(get_auth_token "$TEST_USER_EMAIL" "$TEST_USER_PASSWORD")
    if [ -z "$admin_token" ]; then
        log_error "Cannot get admin token for project management tests"
        return
    fi
    
    # Test 3.1: Create project
    local project_data="{\"code\":\"$TEST_PROJECT_CODE\",\"name\":\"$TEST_PROJECT_NAME\",\"description\":\"Test project description\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-12-31\",\"status\":\"planning\"}"
    local response=$(api_call "POST" "/projects" "$project_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Create Project" "PASS" "Admin can create new project"
        local project_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Create Project" "FAIL" "Admin cannot create new project"
        return
    fi
    
    # Test 3.2: Get projects list
    local response=$(api_call "GET" "/projects" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Projects List" "PASS" "Admin can retrieve projects list"
    else
        test_result "Get Projects List" "FAIL" "Admin cannot retrieve projects list"
    fi
    
    # Test 3.3: Update project
    if [ -n "$project_id" ]; then
        local update_data="{\"name\":\"Updated Test Project\",\"status\":\"active\"}"
        local response=$(api_call "PUT" "/projects/$project_id" "$update_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Update Project" "PASS" "Admin can update project information"
        else
            test_result "Update Project" "FAIL" "Admin cannot update project information"
        fi
    fi
    
    # Test 3.4: Delete project
    if [ -n "$project_id" ]; then
        local response=$(api_call "DELETE" "/projects/$project_id" "" "Authorization: Bearer $admin_token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Delete Project" "PASS" "Admin can delete project"
        else
            test_result "Delete Project" "FAIL" "Admin cannot delete project"
        fi
    fi
}

# Test 4: Task Management Tests
test_task_management() {
    log_info "Testing task management functionality..."
    
    # Get admin token
    local admin_token=$(get_auth_token "$TEST_USER_EMAIL" "$TEST_USER_PASSWORD")
    if [ -z "$admin_token" ]; then
        log_error "Cannot get admin token for task management tests"
        return
    fi
    
    # First create a project for tasks
    local project_data="{\"code\":\"TASK001\",\"name\":\"Task Test Project\",\"description\":\"Project for task testing\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-12-31\",\"status\":\"active\"}"
    local response=$(api_call "POST" "/projects" "$project_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    local project_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    
    if [ -z "$project_id" ]; then
        log_error "Cannot create project for task testing"
        return
    fi
    
    # Test 4.1: Create task
    local task_data="{\"project_id\":\"$project_id\",\"name\":\"Test Task\",\"description\":\"Test task description\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-01-31\",\"status\":\"pending\",\"priority\":\"medium\"}"
    local response=$(api_call "POST" "/tasks" "$task_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Create Task" "PASS" "Admin can create new task"
        local task_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Create Task" "FAIL" "Admin cannot create new task"
        return
    fi
    
    # Test 4.2: Get tasks list
    local response=$(api_call "GET" "/tasks" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Tasks List" "PASS" "Admin can retrieve tasks list"
    else
        test_result "Get Tasks List" "FAIL" "Admin cannot retrieve tasks list"
    fi
    
    # Test 4.3: Update task
    if [ -n "$task_id" ]; then
        local update_data="{\"name\":\"Updated Test Task\",\"status\":\"in_progress\"}"
        local response=$(api_call "PUT" "/tasks/$task_id" "$update_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Update Task" "PASS" "Admin can update task information"
        else
            test_result "Update Task" "FAIL" "Admin cannot update task information"
        fi
    fi
    
    # Test 4.4: Delete task
    if [ -n "$task_id" ]; then
        local response=$(api_call "DELETE" "/tasks/$task_id" "" "Authorization: Bearer $admin_token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Delete Task" "PASS" "Admin can delete task"
        else
            test_result "Delete Task" "FAIL" "Admin cannot delete task"
        fi
    fi
    
    # Clean up project
    api_call "DELETE" "/projects/$project_id" "" "Authorization: Bearer $admin_token"
}

# Test 5: Document Management Tests
test_document_management() {
    log_info "Testing document management functionality..."
    
    # Get admin token
    local admin_token=$(get_auth_token "$TEST_USER_EMAIL" "$TEST_USER_PASSWORD")
    if [ -z "$admin_token" ]; then
        log_error "Cannot get admin token for document management tests"
        return
    fi
    
    # First create a project for documents
    local project_data="{\"code\":\"DOC001\",\"name\":\"Document Test Project\",\"description\":\"Project for document testing\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-12-31\",\"status\":\"active\"}"
    local response=$(api_call "POST" "/projects" "$project_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    local project_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    
    if [ -z "$project_id" ]; then
        log_error "Cannot create project for document testing"
        return
    fi
    
    # Test 5.1: Create document
    local document_data="{\"project_id\":\"$project_id\",\"title\":\"Test Document\",\"description\":\"Test document description\",\"visibility\":\"internal\"}"
    local response=$(api_call "POST" "/documents" "$document_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Create Document" "PASS" "Admin can create new document"
        local document_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Create Document" "FAIL" "Admin cannot create new document"
        return
    fi
    
    # Test 5.2: Get documents list
    local response=$(api_call "GET" "/documents" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Documents List" "PASS" "Admin can retrieve documents list"
    else
        test_result "Get Documents List" "FAIL" "Admin cannot retrieve documents list"
    fi
    
    # Test 5.3: Update document
    if [ -n "$document_id" ]; then
        local update_data="{\"title\":\"Updated Test Document\",\"visibility\":\"client\"}"
        local response=$(api_call "PUT" "/documents/$document_id" "$update_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Update Document" "PASS" "Admin can update document information"
        else
            test_result "Update Document" "FAIL" "Admin cannot update document information"
        fi
    fi
    
    # Test 5.4: Delete document
    if [ -n "$document_id" ]; then
        local response=$(api_call "DELETE" "/documents/$document_id" "" "Authorization: Bearer $admin_token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Delete Document" "PASS" "Admin can delete document"
        else
            test_result "Delete Document" "FAIL" "Admin cannot delete document"
        fi
    fi
    
    # Clean up project
    api_call "DELETE" "/projects/$project_id" "" "Authorization: Bearer $admin_token"
}

# Test 6: Change Request Management Tests
test_change_request_management() {
    log_info "Testing change request management functionality..."
    
    # Get admin token
    local admin_token=$(get_auth_token "$TEST_USER_EMAIL" "$TEST_USER_PASSWORD")
    if [ -z "$admin_token" ]; then
        log_error "Cannot get admin token for change request management tests"
        return
    fi
    
    # First create a project for change requests
    local project_data="{\"code\":\"CR001\",\"name\":\"Change Request Test Project\",\"description\":\"Project for change request testing\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-12-31\",\"status\":\"active\"}"
    local response=$(api_call "POST" "/projects" "$project_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    local project_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    
    if [ -z "$project_id" ]; then
        log_error "Cannot create project for change request testing"
        return
    fi
    
    # Test 6.1: Create change request
    local cr_data="{\"project_id\":\"$project_id\",\"title\":\"Test Change Request\",\"description\":\"Test change request description\",\"impact\":\"low\",\"priority\":\"medium\",\"status\":\"draft\"}"
    local response=$(api_call "POST" "/change-requests" "$cr_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Create Change Request" "PASS" "Admin can create new change request"
        local cr_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Create Change Request" "FAIL" "Admin cannot create new change request"
        return
    fi
    
    # Test 6.2: Get change requests list
    local response=$(api_call "GET" "/change-requests" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Change Requests List" "PASS" "Admin can retrieve change requests list"
    else
        test_result "Get Change Requests List" "FAIL" "Admin cannot retrieve change requests list"
    fi
    
    # Test 6.3: Update change request
    if [ -n "$cr_id" ]; then
        local update_data="{\"title\":\"Updated Test Change Request\",\"status\":\"submitted\"}"
        local response=$(api_call "PUT" "/change-requests/$cr_id" "$update_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Update Change Request" "PASS" "Admin can update change request information"
        else
            test_result "Update Change Request" "FAIL" "Admin cannot update change request information"
        fi
    fi
    
    # Test 6.4: Delete change request
    if [ -n "$cr_id" ]; then
        local response=$(api_call "DELETE" "/change-requests/$cr_id" "" "Authorization: Bearer $admin_token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Delete Change Request" "PASS" "Admin can delete change request"
        else
            test_result "Delete Change Request" "FAIL" "Admin cannot delete change request"
        fi
    fi
    
    # Clean up project
    api_call "DELETE" "/projects/$project_id" "" "Authorization: Bearer $admin_token"
}

# Test 7: RBAC Tests
test_rbac() {
    log_info "Testing RBAC functionality..."
    
    # Get admin token
    local admin_token=$(get_auth_token "$TEST_USER_EMAIL" "$TEST_USER_PASSWORD")
    if [ -z "$admin_token" ]; then
        log_error "Cannot get admin token for RBAC tests"
        return
    fi
    
    # Test 7.1: Get roles list
    local response=$(api_call "GET" "/roles" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Roles List" "PASS" "Admin can retrieve roles list"
    else
        test_result "Get Roles List" "FAIL" "Admin cannot retrieve roles list"
    fi
    
    # Test 7.2: Get permissions list
    local response=$(api_call "GET" "/permissions" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Permissions List" "PASS" "Admin can retrieve permissions list"
    else
        test_result "Get Permissions List" "FAIL" "Admin cannot retrieve permissions list"
    fi
    
    # Test 7.3: Check user permissions
    local response=$(api_call "POST" "/auth/check-permission" "{\"permission\":\"user.view\"}" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Check User Permission" "PASS" "System can check user permissions"
    else
        test_result "Check User Permission" "FAIL" "System cannot check user permissions"
    fi
}

# Test 8: Health Check Tests
test_health_check() {
    log_info "Testing health check functionality..."
    
    # Test 8.1: Basic health check
    local response=$(api_call "GET" "/health" "" "")
    if echo "$response" | grep -q '"status":"success"'; then
        test_result "Health Check" "PASS" "System health check is working"
    else
        test_result "Health Check" "FAIL" "System health check is not working"
    fi
    
    # Test 8.2: Detailed health check
    local response=$(api_call "GET" "/health/detailed" "" "")
    if echo "$response" | grep -q '"status":"success"'; then
        test_result "Detailed Health Check" "PASS" "Detailed health check is working"
    else
        test_result "Detailed Health Check" "FAIL" "Detailed health check is not working"
    fi
}

# Test 9: Performance Tests
test_performance() {
    log_info "Testing performance functionality..."
    
    # Test 9.1: API response time
    local start_time=$(date +%s%N)
    local response=$(api_call "GET" "/health" "" "")
    local end_time=$(date +%s%N)
    local response_time=$(( (end_time - start_time) / 1000000 )) # Convert to milliseconds
    
    if [ "$response_time" -lt 2000 ]; then
        test_result "API Response Time" "PASS" "API response time is acceptable: ${response_time}ms"
    else
        test_result "API Response Time" "FAIL" "API response time is too slow: ${response_time}ms"
    fi
    
    # Test 9.2: Database connection
    local response=$(api_call "GET" "/health/detailed" "" "")
    if echo "$response" | grep -q '"database":"connected"'; then
        test_result "Database Connection" "PASS" "Database connection is working"
    else
        test_result "Database Connection" "FAIL" "Database connection is not working"
    fi
}

# Test 10: Security Tests
test_security() {
    log_info "Testing security functionality..."
    
    # Test 10.1: CSRF protection
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"test@test.com\",\"password\":\"test\"}" "Content-Type: application/json")
    if echo "$response" | grep -q "CSRF"; then
        test_result "CSRF Protection" "PASS" "CSRF protection is working"
    else
        test_result "CSRF Protection" "FAIL" "CSRF protection is not working"
    fi
    
    # Test 10.2: Input sanitization
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"<script>alert('xss')</script>\",\"password\":\"test\"}" "Content-Type: application/json")
    if echo "$response" | grep -q "suspicious"; then
        test_result "Input Sanitization" "PASS" "Input sanitization is working"
    else
        test_result "Input Sanitization" "FAIL" "Input sanitization is not working"
    fi
}

# Generate test report
generate_report() {
    log_info "Generating business logic test report..."
    
    local report_file="$TEST_RESULTS_DIR/business-logic-test-report-$(date +%Y%m%d-%H%M%S).html"
    
    cat > "$report_file" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>Business Logic Test Report</title>
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
        <h1>Business Logic Test Report</h1>
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
        <p>This report was generated by the ZenaManage Business Logic Testing Script.</p>
        <p>For questions or issues, contact the Technical Team.</p>
    </div>
</body>
</html>
EOF
    
    log_success "Test report generated: $report_file"
}

# Main execution
main() {
    log_info "Starting business logic testing..."
    log_info "Log file: $LOG_FILE"
    
    setup
    
    # Run all tests
    test_health_check
    test_authentication
    test_user_management
    test_project_management
    test_task_management
    test_document_management
    test_change_request_management
    test_rbac
    test_performance
    test_security
    
    # Generate report
    generate_report
    
    # Final summary
    log_info "Business logic testing completed!"
    log_info "Total tests: $TESTS_TOTAL"
    log_info "Passed: $TESTS_PASSED"
    log_info "Failed: $TESTS_FAILED"
    
    if [ $TESTS_FAILED -eq 0 ]; then
        log_success "All business logic tests PASSED!"
        exit 0
    else
        log_error "$TESTS_FAILED tests FAILED. Please review the results and fix issues."
        exit 1
    fi
}

# Run main function
main "$@"
