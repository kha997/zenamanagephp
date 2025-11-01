#!/bin/bash

# Test Business Logic - Realistic Version
# Test các nghiệp vụ thực tế dựa trên API có sẵn

echo "=== Z.E.N.A PROJECT MANAGEMENT - REALISTIC BUSINESS LOGIC TEST ==="
echo "Testing realistic business operations based on available APIs..."
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

# Test 1: Core API Health
echo -e "${YELLOW}=== CORE API HEALTH TESTS ===${NC}"
run_test "API Health Check" \
    "curl -s -X GET http://localhost:8000/api/health | grep -q 'status.*success'" \
    "200"

run_test "API Status Check" \
    "curl -s -X GET http://localhost:8000/api/status | grep -q 'status.*running'" \
    "200"

run_test "API Info Check" \
    "curl -s -X GET http://localhost:8000/api/info | grep -q 'status.*success'" \
    "200"

run_test "API v1 Health Check" \
    "curl -s -X GET http://localhost:8000/api/v1/health | grep -q 'status.*success'" \
    "200"

# Test 2: Authentication
echo -e "${YELLOW}=== AUTHENTICATION TESTS ===${NC}"
run_test "Login with Super Admin" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"superadmin@zena.com\",\"password\":\"zena1234\"}' | grep -q 'success.*true'" \
    "200"

run_test "Login with Project Manager" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"pm@zena.com\",\"password\":\"zena1234\"}' | grep -q 'success.*true'" \
    "200"

run_test "Login with Designer" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"designer@zena.com\",\"password\":\"zena1234\"}' | grep -q 'success.*true'" \
    "200"

run_test "Login with Site Engineer" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"site@zena.com\",\"password\":\"zena1234\"}' | grep -q 'success.*true'" \
    "200"

run_test "Login with QC Engineer" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"qc@zena.com\",\"password\":\"zena1234\"}' | grep -q 'success.*true'" \
    "200"

run_test "Login with Procurement" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"procurement@zena.com\",\"password\":\"zena1234\"}' | grep -q 'success.*true'" \
    "200"

run_test "Login with Finance" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"finance@zena.com\",\"password\":\"zena1234\"}' | grep -q 'success.*true'" \
    "200"

run_test "Login with Client" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"client@zena.com\",\"password\":\"zena1234\"}' | grep -q 'success.*true'" \
    "200"

run_test "Login with invalid credentials" \
    "curl -s -X POST http://localhost:8000/api/login -H 'Content-Type: application/json' -d '{\"email\":\"invalid@test.com\",\"password\":\"wrong\"}' | grep -q 'success.*false'" \
    "200"

# Test 3: Project Management
echo -e "${YELLOW}=== PROJECT MANAGEMENT TESTS ===${NC}"
run_test "Get projects list (simple)" \
    "curl -s -X GET http://localhost:8000/api/v1/projects-simple | grep -q 'status.*success'" \
    "200"

run_test "Get projects list (main)" \
    "curl -s -X GET http://localhost:8000/api/v1/projects | grep -q 'status.*success'" \
    "200"

# Test 4: Document Management
echo -e "${YELLOW}=== DOCUMENT MANAGEMENT TESTS ===${NC}"
run_test "Get documents list (simple)" \
    "curl -s -X GET http://localhost:8000/api/v1/documents-simple | grep -q 'status.*success'" \
    "200"

run_test "Get documents list (main)" \
    "curl -s -X GET http://localhost:8000/api/v1/documents | grep -q 'status.*success'" \
    "200"

# Test 5: Analytics
echo -e "${YELLOW}=== ANALYTICS TESTS ===${NC}"
run_test "Get analytics dashboard" \
    "curl -s -X GET http://localhost:8000/api/analytics/dashboard | grep -q 'status.*success'" \
    "200"

run_test "Get analytics tasks" \
    "curl -s -X GET http://localhost:8000/api/analytics/tasks | grep -q 'status.*success'" \
    "200"

run_test "Get analytics projects" \
    "curl -s -X GET http://localhost:8000/api/analytics/projects | grep -q 'status.*success'" \
    "200"

# Test 6: Test Endpoints
echo -e "${YELLOW}=== TEST ENDPOINTS ===${NC}"
run_test "Test endpoint" \
    "curl -s -X GET http://localhost:8000/api/v1/test | grep -q 'status.*success'" \
    "200"

run_test "ZENA test endpoint" \
    "curl -s -X GET http://localhost:8000/api/zena-test | grep -q 'message.*Z.E.N.A routes are working'" \
    "200"

# Test 7: File Upload Test
echo -e "${YELLOW}=== FILE UPLOAD TESTS ===${NC}"
run_test "Test file upload endpoint" \
    "curl -s -X POST http://localhost:8000/api/v1/upload-document -F 'title=Test Document' -F 'description=Test Description' -F 'project_id=1' -F 'document_type=other' -F 'version=1.0' -F 'file=@/dev/null' | grep -q 'status.*success'" \
    "200"

# Test 8: Dashboard Tests
echo -e "${YELLOW}=== DASHBOARD TESTS ===${NC}"
run_test "Get dashboard data" \
    "curl -s -X GET http://localhost:8000/api/v1/dashboard/data | grep -q 'status.*success'" \
    "200"

run_test "Get dashboard analytics" \
    "curl -s -X GET http://localhost:8000/api/v1/dashboard/analytics | grep -q 'status.*success'" \
    "200"

run_test "Get dashboard statistics" \
    "curl -s -X GET http://localhost:8000/api/v1/dashboard/statistics | grep -q 'status.*success'" \
    "200"

# Test 9: User Management Tests
echo -e "${YELLOW}=== USER MANAGEMENT TESTS ===${NC}"
run_test "Get users list" \
    "curl -s -X GET http://localhost:8000/api/v1/users | grep -q 'status.*success'" \
    "200"

run_test "Get simple users list" \
    "curl -s -X GET http://localhost:8000/api/v1/simple/users | grep -q 'status.*success'" \
    "200"

run_test "Get users v2 list" \
    "curl -s -X GET http://localhost:8000/api/v1/users-v2 | grep -q 'status.*success'" \
    "200"

# Test 10: Task Management Tests
echo -e "${YELLOW}=== TASK MANAGEMENT TESTS ===${NC}"
run_test "Get tasks list" \
    "curl -s -X GET http://localhost:8000/api/v1/tasks | grep -q 'status.*success'" \
    "200"

run_test "Get task assignments" \
    "curl -s -X GET http://localhost:8000/api/v1/task-assignments | grep -q 'status.*success'" \
    "200"

# Test 11: Team Management Tests
echo -e "${YELLOW}=== TEAM MANAGEMENT TESTS ===${NC}"
run_test "Get teams list" \
    "curl -s -X GET http://localhost:8000/api/v1/teams | grep -q 'status.*success'" \
    "200"

# Test 12: Component Management Tests
echo -e "${YELLOW}=== COMPONENT MANAGEMENT TESTS ===${NC}"
run_test "Get components list" \
    "curl -s -X GET http://localhost:8000/api/v1/components | grep -q 'status.*success'" \
    "200"

# Test 13: Template Tests
echo -e "${YELLOW}=== TEMPLATE TESTS ===${NC}"
run_test "Get templates list" \
    "curl -s -X GET http://localhost:8000/api/v1/templates | grep -q 'status.*success'" \
    "200"

run_test "Get work templates list" \
    "curl -s -X GET http://localhost:8000/api/v1/work-templates | grep -q 'status.*success'" \
    "200"

run_test "Get project templates list" \
    "curl -s -X GET http://localhost:8000/api/v1/project-templates | grep -q 'status.*success'" \
    "200"

# Test 14: Export Tests
echo -e "${YELLOW}=== EXPORT TESTS ===${NC}"
run_test "Export tasks" \
    "curl -s -X POST http://localhost:8000/api/tasks/bulk/export | grep -q 'status.*success'" \
    "200"

run_test "Export projects" \
    "curl -s -X POST http://localhost:8000/api/projects/bulk/export | grep -q 'status.*success'" \
    "200"

# Test 15: Performance Tests
echo -e "${YELLOW}=== PERFORMANCE TESTS ===${NC}"
run_test "Get performance health" \
    "curl -s -X GET http://localhost:8000/api/v1/performance/monitoring/health | grep -q 'status.*success'" \
    "200"

run_test "Get performance metrics" \
    "curl -s -X GET http://localhost:8000/api/v1/performance/monitoring/metrics | grep -q 'status.*success'" \
    "200"

run_test "Get cache stats" \
    "curl -s -X GET http://localhost:8000/api/v1/performance/cache/stats | grep -q 'status.*success'" \
    "200"

run_test "Get database stats" \
    "curl -s -X GET http://localhost:8000/api/v1/performance/database/stats | grep -q 'status.*success'" \
    "200"

# Test 16: Security Tests
echo -e "${YELLOW}=== SECURITY TESTS ===${NC}"
run_test "Get security overview" \
    "curl -s -X GET http://localhost:8000/api/v1/auth/security/overview | grep -q 'status.*success'" \
    "200"

run_test "Get security events timeline" \
    "curl -s -X GET http://localhost:8000/api/v1/auth/security/events/timeline | grep -q 'status.*success'" \
    "200"

run_test "Get failed login attempts" \
    "curl -s -X GET http://localhost:8000/api/v1/auth/security/failed-logins | grep -q 'status.*success'" \
    "200"

run_test "Get security metrics" \
    "curl -s -X GET http://localhost:8000/api/v1/auth/security/metrics | grep -q 'status.*success'" \
    "200"

# Test 17: Real-time Tests
echo -e "${YELLOW}=== REAL-TIME TESTS ===${NC}"
run_test "Get real-time connection status" \
    "curl -s -X GET http://localhost:8000/api/v1/realtime/connection-status | grep -q 'status.*success'" \
    "200"

run_test "Get user activities" \
    "curl -s -X GET http://localhost:8000/api/v1/realtime/user/activities | grep -q 'status.*success'" \
    "200"

# Test 18: Admin Tests
echo -e "${YELLOW}=== ADMIN TESTS ===${NC}"
run_test "Get admin dashboard stats" \
    "curl -s -X GET http://localhost:8000/api/v1/admin/dashboard/stats | grep -q 'status.*success'" \
    "200"

run_test "Get admin dashboard activities" \
    "curl -s -X GET http://localhost:8000/api/v1/admin/dashboard/activities | grep -q 'status.*success'" \
    "200"

run_test "Get admin dashboard alerts" \
    "curl -s -X GET http://localhost:8000/api/v1/admin/dashboard/alerts | grep -q 'status.*success'" \
    "200"

# Test 19: Integration Tests
echo -e "${YELLOW}=== INTEGRATION TESTS ===${NC}"
run_test "Get SSO providers" \
    "curl -s -X GET http://localhost:8000/api/v1/auth/sso/providers | grep -q 'status.*success'" \
    "200"

run_test "Get SSO config" \
    "curl -s -X GET http://localhost:8000/api/v1/auth/sso/config | grep -q 'status.*success'" \
    "200"

run_test "Get OIDC providers" \
    "curl -s -X GET http://localhost:8000/api/v1/auth/oidc/providers | grep -q 'status.*success'" \
    "200"

run_test "Get SAML providers" \
    "curl -s -X GET http://localhost:8000/api/v1/auth/saml/providers | grep -q 'status.*success'" \
    "200"

echo "=== REALISTIC TEST SUMMARY ==="
echo -e "Total Tests: ${TOTAL_TESTS}"
echo -e "${GREEN}Passed: ${PASSED_TESTS}${NC}"
echo -e "${RED}Failed: ${FAILED_TESTS}${NC}"

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL REALISTIC TESTS PASSED! System is working correctly.${NC}"
    exit 0
else
    echo -e "${RED}❌ Some tests failed. Please check the system.${NC}"
    exit 1
fi
