#!/bin/bash

# Detailed Business Logic Testing Script
# ZenaManage Project - Comprehensive Business Logic Testing

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$PROJECT_ROOT/logs/detailed-business-test-$(date +%Y%m%d-%H%M%S).log"
API_BASE_URL="http://localhost:8000/api/v1"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Test results
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
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
}

# API call helper
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

# Get auth token
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

# Test Authentication Business Logic
test_authentication_business_logic() {
    log_info "Testing Authentication Business Logic..."
    
    # Test 1: Valid login with correct credentials
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"admin@zenamanage.com\",\"password\":\"admin123\"}" "Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Valid Login" "PASS" "User can login with valid credentials"
        local token=$(echo "$response" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Valid Login" "FAIL" "User cannot login with valid credentials"
        return
    fi
    
    # Test 2: Invalid login with wrong password
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"admin@zenamanage.com\",\"password\":\"wrongpassword\"}" "Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Invalid Login" "PASS" "System correctly rejects invalid credentials"
    else
        test_result "Invalid Login" "FAIL" "System should reject invalid credentials"
    fi
    
    # Test 3: Login with non-existent user
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"nonexistent@test.com\",\"password\":\"password123\"}" "Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Non-existent User Login" "PASS" "System correctly rejects non-existent user"
    else
        test_result "Non-existent User Login" "FAIL" "System should reject non-existent user"
    fi
    
    # Test 4: Get user profile with valid token
    if [ -n "$token" ]; then
        local response=$(api_call "GET" "/auth/me" "" "Authorization: Bearer $token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Get User Profile" "PASS" "User can retrieve their profile with valid token"
        else
            test_result "Get User Profile" "FAIL" "User cannot retrieve their profile with valid token"
        fi
    fi
    
    # Test 5: Get user profile with invalid token
    local response=$(api_call "GET" "/auth/me" "" "Authorization: Bearer invalidtoken")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Get Profile with Invalid Token" "PASS" "System correctly rejects invalid token"
    else
        test_result "Get Profile with Invalid Token" "FAIL" "System should reject invalid token"
    fi
    
    # Test 6: Logout with valid token
    if [ -n "$token" ]; then
        local response=$(api_call "POST" "/auth/logout" "" "Authorization: Bearer $token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Logout" "PASS" "User can logout successfully"
        else
            test_result "Logout" "FAIL" "User cannot logout successfully"
        fi
    fi
}

# Test User Management Business Logic
test_user_management_business_logic() {
    log_info "Testing User Management Business Logic..."
    
    # Get admin token
    local admin_token=$(get_auth_token "admin@zenamanage.com" "admin123")
    if [ -z "$admin_token" ]; then
        log_error "Cannot get admin token for user management tests"
        return
    fi
    
    # Test 1: Create user with valid data
    local new_user_data="{\"name\":\"Test User\",\"email\":\"newuser@test.com\",\"password\":\"NewPassword123!\",\"password_confirmation\":\"NewPassword123!\"}"
    local response=$(api_call "POST" "/users" "$new_user_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Create User with Valid Data" "PASS" "Admin can create new user with valid data"
        local user_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Create User with Valid Data" "FAIL" "Admin cannot create new user with valid data"
        return
    fi
    
    # Test 2: Create user with duplicate email
    local duplicate_user_data="{\"name\":\"Duplicate User\",\"email\":\"newuser@test.com\",\"password\":\"Password123!\",\"password_confirmation\":\"Password123!\"}"
    local response=$(api_call "POST" "/users" "$duplicate_user_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Create User with Duplicate Email" "PASS" "System correctly rejects duplicate email"
    else
        test_result "Create User with Duplicate Email" "FAIL" "System should reject duplicate email"
    fi
    
    # Test 3: Create user with weak password
    local weak_password_data="{\"name\":\"Weak Password User\",\"email\":\"weak@test.com\",\"password\":\"123\",\"password_confirmation\":\"123\"}"
    local response=$(api_call "POST" "/users" "$weak_password_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Create User with Weak Password" "PASS" "System correctly rejects weak password"
    else
        test_result "Create User with Weak Password" "FAIL" "System should reject weak password"
    fi
    
    # Test 4: Create user with mismatched password confirmation
    local mismatched_password_data="{\"name\":\"Mismatched User\",\"email\":\"mismatched@test.com\",\"password\":\"Password123!\",\"password_confirmation\":\"DifferentPassword123!\"}"
    local response=$(api_call "POST" "/users" "$mismatched_password_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Create User with Mismatched Password" "PASS" "System correctly rejects mismatched password confirmation"
    else
        test_result "Create User with Mismatched Password" "FAIL" "System should reject mismatched password confirmation"
    fi
    
    # Test 5: Get users list
    local response=$(api_call "GET" "/users" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Users List" "PASS" "Admin can retrieve users list"
    else
        test_result "Get Users List" "FAIL" "Admin cannot retrieve users list"
    fi
    
    # Test 6: Update user with valid data
    if [ -n "$user_id" ]; then
        local update_data="{\"name\":\"Updated Test User\"}"
        local response=$(api_call "PUT" "/users/$user_id" "$update_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Update User with Valid Data" "PASS" "Admin can update user information"
        else
            test_result "Update User with Valid Data" "FAIL" "Admin cannot update user information"
        fi
    fi
    
    # Test 7: Update user with invalid email
    if [ -n "$user_id" ]; then
        local invalid_email_data="{\"email\":\"invalid-email\"}"
        local response=$(api_call "PUT" "/users/$user_id" "$invalid_email_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":false'; then
            test_result "Update User with Invalid Email" "PASS" "System correctly rejects invalid email format"
        else
            test_result "Update User with Invalid Email" "FAIL" "System should reject invalid email format"
        fi
    fi
    
    # Test 8: Delete user
    if [ -n "$user_id" ]; then
        local response=$(api_call "DELETE" "/users/$user_id" "" "Authorization: Bearer $admin_token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Delete User" "PASS" "Admin can delete user"
        else
            test_result "Delete User" "FAIL" "Admin cannot delete user"
        fi
    fi
    
    # Test 9: Delete non-existent user
    local response=$(api_call "DELETE" "/users/nonexistent-id" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Delete Non-existent User" "PASS" "System correctly handles non-existent user deletion"
    else
        test_result "Delete Non-existent User" "FAIL" "System should handle non-existent user deletion"
    fi
}

# Test Project Management Business Logic
test_project_management_business_logic() {
    log_info "Testing Project Management Business Logic..."
    
    # Get admin token
    local admin_token=$(get_auth_token "admin@zenamanage.com" "admin123")
    if [ -z "$admin_token" ]; then
        log_error "Cannot get admin token for project management tests"
        return
    fi
    
    # Test 1: Create project with valid data
    local project_data="{\"code\":\"TEST001\",\"name\":\"Test Project\",\"description\":\"Test project description\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-12-31\",\"status\":\"planning\"}"
    local response=$(api_call "POST" "/projects" "$project_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Create Project with Valid Data" "PASS" "Admin can create new project with valid data"
        local project_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Create Project with Valid Data" "FAIL" "Admin cannot create new project with valid data"
        return
    fi
    
    # Test 2: Create project with duplicate code
    local duplicate_project_data="{\"code\":\"TEST001\",\"name\":\"Duplicate Project\",\"description\":\"Duplicate project description\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-12-31\",\"status\":\"planning\"}"
    local response=$(api_call "POST" "/projects" "$duplicate_project_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Create Project with Duplicate Code" "PASS" "System correctly rejects duplicate project code"
    else
        test_result "Create Project with Duplicate Code" "FAIL" "System should reject duplicate project code"
    fi
    
    # Test 3: Create project with invalid date range
    local invalid_date_data="{\"code\":\"TEST002\",\"name\":\"Invalid Date Project\",\"description\":\"Project with invalid date range\",\"start_date\":\"2024-12-31\",\"end_date\":\"2024-01-01\",\"status\":\"planning\"}"
    local response=$(api_call "POST" "/projects" "$invalid_date_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Create Project with Invalid Date Range" "PASS" "System correctly rejects invalid date range"
    else
        test_result "Create Project with Invalid Date Range" "FAIL" "System should reject invalid date range"
    fi
    
    # Test 4: Create project with invalid status
    local invalid_status_data="{\"code\":\"TEST003\",\"name\":\"Invalid Status Project\",\"description\":\"Project with invalid status\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-12-31\",\"status\":\"invalid_status\"}"
    local response=$(api_call "POST" "/projects" "$invalid_status_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Create Project with Invalid Status" "PASS" "System correctly rejects invalid status"
    else
        test_result "Create Project with Invalid Status" "FAIL" "System should reject invalid status"
    fi
    
    # Test 5: Get projects list
    local response=$(api_call "GET" "/projects" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Projects List" "PASS" "Admin can retrieve projects list"
    else
        test_result "Get Projects List" "FAIL" "Admin cannot retrieve projects list"
    fi
    
    # Test 6: Update project with valid data
    if [ -n "$project_id" ]; then
        local update_data="{\"name\":\"Updated Test Project\",\"status\":\"active\"}"
        local response=$(api_call "PUT" "/projects/$project_id" "$update_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Update Project with Valid Data" "PASS" "Admin can update project information"
        else
            test_result "Update Project with Valid Data" "FAIL" "Admin cannot update project information"
        fi
    fi
    
    # Test 7: Update project with invalid status
    if [ -n "$project_id" ]; then
        local invalid_status_update="{\"status\":\"invalid_status\"}"
        local response=$(api_call "PUT" "/projects/$project_id" "$invalid_status_update" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":false'; then
            test_result "Update Project with Invalid Status" "PASS" "System correctly rejects invalid status update"
        else
            test_result "Update Project with Invalid Status" "FAIL" "System should reject invalid status update"
        fi
    fi
    
    # Test 8: Delete project
    if [ -n "$project_id" ]; then
        local response=$(api_call "DELETE" "/projects/$project_id" "" "Authorization: Bearer $admin_token")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Delete Project" "PASS" "Admin can delete project"
        else
            test_result "Delete Project" "FAIL" "Admin cannot delete project"
        fi
    fi
}

# Test Task Management Business Logic
test_task_management_business_logic() {
    log_info "Testing Task Management Business Logic..."
    
    # Get admin token
    local admin_token=$(get_auth_token "admin@zenamanage.com" "admin123")
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
    
    # Test 1: Create task with valid data
    local task_data="{\"project_id\":\"$project_id\",\"name\":\"Test Task\",\"description\":\"Test task description\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-01-31\",\"status\":\"pending\",\"priority\":\"medium\"}"
    local response=$(api_call "POST" "/tasks" "$task_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Create Task with Valid Data" "PASS" "Admin can create new task with valid data"
        local task_id=$(echo "$response" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    else
        test_result "Create Task with Valid Data" "FAIL" "Admin cannot create new task with valid data"
        return
    fi
    
    # Test 2: Create task with invalid date range
    local invalid_date_task="{\"project_id\":\"$project_id\",\"name\":\"Invalid Date Task\",\"description\":\"Task with invalid date range\",\"start_date\":\"2024-01-31\",\"end_date\":\"2024-01-01\",\"status\":\"pending\",\"priority\":\"medium\"}"
    local response=$(api_call "POST" "/tasks" "$invalid_date_task" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Create Task with Invalid Date Range" "PASS" "System correctly rejects invalid date range"
    else
        test_result "Create Task with Invalid Date Range" "FAIL" "System should reject invalid date range"
    fi
    
    # Test 3: Create task with invalid priority
    local invalid_priority_task="{\"project_id\":\"$project_id\",\"name\":\"Invalid Priority Task\",\"description\":\"Task with invalid priority\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-01-31\",\"status\":\"pending\",\"priority\":\"invalid_priority\"}"
    local response=$(api_call "POST" "/tasks" "$invalid_priority_task" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Create Task with Invalid Priority" "PASS" "System correctly rejects invalid priority"
    else
        test_result "Create Task with Invalid Priority" "FAIL" "System should reject invalid priority"
    fi
    
    # Test 4: Create task with invalid status
    local invalid_status_task="{\"project_id\":\"$project_id\",\"name\":\"Invalid Status Task\",\"description\":\"Task with invalid status\",\"start_date\":\"2024-01-01\",\"end_date\":\"2024-01-31\",\"status\":\"invalid_status\",\"priority\":\"medium\"}"
    local response=$(api_call "POST" "/tasks" "$invalid_status_task" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Create Task with Invalid Status" "PASS" "System correctly rejects invalid status"
    else
        test_result "Create Task with Invalid Status" "FAIL" "System should reject invalid status"
    fi
    
    # Test 5: Get tasks list
    local response=$(api_call "GET" "/tasks" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Tasks List" "PASS" "Admin can retrieve tasks list"
    else
        test_result "Get Tasks List" "FAIL" "Admin cannot retrieve tasks list"
    fi
    
    # Test 6: Update task with valid data
    if [ -n "$task_id" ]; then
        local update_data="{\"name\":\"Updated Test Task\",\"status\":\"in_progress\"}"
        local response=$(api_call "PUT" "/tasks/$task_id" "$update_data" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":true'; then
            test_result "Update Task with Valid Data" "PASS" "Admin can update task information"
        else
            test_result "Update Task with Valid Data" "FAIL" "Admin cannot update task information"
        fi
    fi
    
    # Test 7: Update task with invalid status
    if [ -n "$task_id" ]; then
        local invalid_status_update="{\"status\":\"invalid_status\"}"
        local response=$(api_call "PUT" "/tasks/$task_id" "$invalid_status_update" "Authorization: Bearer $admin_token, Content-Type: application/json")
        if echo "$response" | grep -q '"success":false'; then
            test_result "Update Task with Invalid Status" "PASS" "System correctly rejects invalid status update"
        else
            test_result "Update Task with Invalid Status" "FAIL" "System should reject invalid status update"
        fi
    fi
    
    # Test 8: Delete task
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

# Test RBAC Business Logic
test_rbac_business_logic() {
    log_info "Testing RBAC Business Logic..."
    
    # Get admin token
    local admin_token=$(get_auth_token "admin@zenamanage.com" "admin123")
    if [ -z "$admin_token" ]; then
        log_error "Cannot get admin token for RBAC tests"
        return
    fi
    
    # Test 1: Get roles list
    local response=$(api_call "GET" "/roles" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Roles List" "PASS" "Admin can retrieve roles list"
    else
        test_result "Get Roles List" "FAIL" "Admin cannot retrieve roles list"
    fi
    
    # Test 2: Get permissions list
    local response=$(api_call "GET" "/permissions" "" "Authorization: Bearer $admin_token")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Get Permissions List" "PASS" "Admin can retrieve permissions list"
    else
        test_result "Get Permissions List" "FAIL" "Admin cannot retrieve permissions list"
    fi
    
    # Test 3: Check user permission
    local response=$(api_call "POST" "/auth/check-permission" "{\"permission\":\"user.view\"}" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":true'; then
        test_result "Check User Permission" "PASS" "System can check user permissions"
    else
        test_result "Check User Permission" "FAIL" "System cannot check user permissions"
    fi
    
    # Test 4: Check non-existent permission
    local response=$(api_call "POST" "/auth/check-permission" "{\"permission\":\"nonexistent.permission\"}" "Authorization: Bearer $admin_token, Content-Type: application/json")
    if echo "$response" | grep -q '"success":false'; then
        test_result "Check Non-existent Permission" "PASS" "System correctly handles non-existent permission"
    else
        test_result "Check Non-existent Permission" "FAIL" "System should handle non-existent permission"
    fi
}

# Test Security Business Logic
test_security_business_logic() {
    log_info "Testing Security Business Logic..."
    
    # Test 1: CSRF protection
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"test@test.com\",\"password\":\"test\"}" "Content-Type: application/json")
    if echo "$response" | grep -q "CSRF\|419\|csrf"; then
        test_result "CSRF Protection" "PASS" "CSRF protection is working"
    else
        test_result "CSRF Protection" "FAIL" "CSRF protection is not working"
    fi
    
    # Test 2: Input sanitization
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"<script>alert('xss')</script>\",\"password\":\"test\"}" "Content-Type: application/json")
    if echo "$response" | grep -q "suspicious\|sanitized\|blocked"; then
        test_result "Input Sanitization" "PASS" "Input sanitization is working"
    else
        test_result "Input Sanitization" "FAIL" "Input sanitization is not working"
    fi
    
    # Test 3: SQL injection attempt
    local response=$(api_call "POST" "/auth/login" "{\"email\":\"admin@zenamanage.com'; DROP TABLE users; --\",\"password\":\"test\"}" "Content-Type: application/json")
    if echo "$response" | grep -q "suspicious\|sanitized\|blocked"; then
        test_result "SQL Injection Protection" "PASS" "SQL injection protection is working"
    else
        test_result "SQL Injection Protection" "FAIL" "SQL injection protection is not working"
    fi
}

# Test Performance Business Logic
test_performance_business_logic() {
    log_info "Testing Performance Business Logic..."
    
    # Test 1: API response time
    local start_time=$(date +%s%N)
    local response=$(api_call "GET" "/health" "" "")
    local end_time=$(date +%s%N)
    local response_time=$(( (end_time - start_time) / 1000000 )) # Convert to milliseconds
    
    if [ "$response_time" -lt 2000 ]; then
        test_result "API Response Time" "PASS" "API response time is acceptable: ${response_time}ms"
    else
        test_result "API Response Time" "FAIL" "API response time is too slow: ${response_time}ms"
    fi
    
    # Test 2: Database connection
    local response=$(api_call "GET" "/health/detailed" "" "")
    if echo "$response" | grep -q '"database":"connected"'; then
        test_result "Database Connection" "PASS" "Database connection is working"
    else
        test_result "Database Connection" "FAIL" "Database connection is not working"
    fi
    
    # Test 3: Cache performance
    local start_time=$(date +%s%N)
    local response=$(api_call "GET" "/health" "" "")
    local end_time=$(date +%s%N)
    local cache_response_time=$(( (end_time - start_time) / 1000000 ))
    
    if [ "$cache_response_time" -lt 1000 ]; then
        test_result "Cache Performance" "PASS" "Cache performance is good: ${cache_response_time}ms"
    else
        test_result "Cache Performance" "FAIL" "Cache performance is slow: ${cache_response_time}ms"
    fi
}

# Main execution
main() {
    log_info "Starting detailed business logic testing..."
    log_info "Log file: $LOG_FILE"
    
    # Create log directory
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Check if Laravel application is running
    if ! curl -s "$API_BASE_URL/health" >/dev/null 2>&1; then
        log_error "Laravel application is not running. Please start the application first."
        exit 1
    fi
    
    # Run all tests
    test_authentication_business_logic
    test_user_management_business_logic
    test_project_management_business_logic
    test_task_management_business_logic
    test_rbac_business_logic
    test_security_business_logic
    test_performance_business_logic
    
    # Final summary
    log_info "Detailed business logic testing completed!"
    log_info "Total tests: $TESTS_TOTAL"
    log_info "Passed: $TESTS_PASSED"
    log_info "Failed: $TESTS_FAILED"
    
    if [ $TESTS_FAILED -eq 0 ]; then
        log_success "All detailed business logic tests PASSED!"
        exit 0
    else
        log_error "$TESTS_FAILED tests FAILED. Please review the results and fix issues."
        exit 1
    fi
}

# Run main function
main "$@"
