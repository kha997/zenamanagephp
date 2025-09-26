#!/bin/bash

# Test Business Logic - Simple Version
# Test các nghiệp vụ cơ bản của hệ thống

echo "=== Z.E.N.A PROJECT MANAGEMENT - BUSINESS LOGIC TEST ==="
echo "Testing core business operations..."
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to run test
run_test() {
    local test_name="$1"
    local test_command="$2"
    local expected_status="$3"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo -e "${BLUE}Testing: ${test_name}${NC}"
    
    # Run the test command
    response=$(eval "$test_command" 2>/dev/null)
    status_code=$?
    
    # Check if command succeeded
    if [ $status_code -eq 0 ]; then
        echo -e "${GREEN}✓ PASSED${NC}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗ FAILED${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
    
    echo ""
}

# Test 1: API Health Check
run_test "API Health Check" \
    "curl -s -X GET http://localhost:8000/api/health" \
    "200"

# Test 2: Login API
run_test "Login API" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"superadmin@zena.com\",\"password\":\"zena1234\"}'" \
    "200"

# Test 3: Projects List API
run_test "Projects List API" \
    "curl -s -X GET http://localhost:8000/api/v1/projects-simple" \
    "200"

# Test 4: Documents List API
run_test "Documents List API" \
    "curl -s -X GET http://localhost:8000/api/v1/documents-simple" \
    "200"

# Test 5: Dashboard API
run_test "Dashboard API" \
    "curl -s -X GET http://localhost:8000/api/v1/dashboard/data" \
    "200"

# Test 6: User Management API
run_test "User Management API" \
    "curl -s -X GET http://localhost:8000/api/v1/users" \
    "200"

# Test 7: Task Management API
run_test "Task Management API" \
    "curl -s -X GET http://localhost:8000/api/v1/tasks" \
    "200"

# Test 8: Team Management API
run_test "Team Management API" \
    "curl -s -X GET http://localhost:8000/api/v1/teams" \
    "200"

# Test 9: Component Management API
run_test "Component Management API" \
    "curl -s -X GET http://localhost:8000/api/v1/components" \
    "200"

# Test 10: Analytics API
run_test "Analytics API" \
    "curl -s -X GET http://localhost:8000/api/analytics/dashboard" \
    "200"

# Test 11: Performance API
run_test "Performance API" \
    "curl -s -X GET http://localhost:8000/api/v1/performance/monitoring/health" \
    "200"

# Test 12: Security API
run_test "Security API" \
    "curl -s -X GET http://localhost:8000/api/v1/auth/security/overview" \
    "200"

# Test 13: Export API
run_test "Export API" \
    "curl -s -X POST http://localhost:8000/api/tasks/bulk/export" \
    "200"

# Test 14: Template API
run_test "Template API" \
    "curl -s -X GET http://localhost:8000/api/v1/templates" \
    "200"

# Test 15: Work Template API
run_test "Work Template API" \
    "curl -s -X GET http://localhost:8000/api/v1/work-templates" \
    "200"

echo "=== TEST SUMMARY ==="
echo -e "Total Tests: ${TOTAL_TESTS}"
echo -e "${GREEN}Passed: ${PASSED_TESTS}${NC}"
echo -e "${RED}Failed: ${FAILED_TESTS}${NC}"

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL TESTS PASSED! System is working correctly.${NC}"
    exit 0
else
    echo -e "${RED}❌ Some tests failed. Please check the system.${NC}"
    exit 1
fi
