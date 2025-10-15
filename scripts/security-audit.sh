#!/bin/bash

# Security Audit Script
# Checks for critical security vulnerabilities

set -e

echo "🔍 ZenaManage Security Audit"
echo "================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counter for issues found
ISSUES=0

# Function to check and report issues
check_issue() {
    local description="$1"
    local command="$2"
    local critical="$3"
    
    echo -n "Checking: $description... "
    
    if eval "$command" > /dev/null 2>&1; then
        echo -e "${RED}❌ FAIL${NC}"
        if [ "$critical" = "true" ]; then
            echo -e "${RED}CRITICAL: $description${NC}"
            ISSUES=$((ISSUES + 1))
        else
            echo -e "${YELLOW}WARNING: $description${NC}"
        fi
        return 1
    else
        echo -e "${GREEN}✅ PASS${NC}"
        return 0
    fi
}

echo ""
echo "1. Authentication Security"
echo "---------------------------"

# Check for dangerous test routes
check_issue "Dangerous test routes" "grep -r 'test-login\|auto-login\|debug-auth' routes/" "true"

# Check for unprotected login endpoints
check_issue "Unprotected Auth::attempt" "grep -r 'Auth::attempt' routes/ --exclude-dir=vendor" "true"

# Check for direct login bypass
check_issue "Direct login bypass" "grep -r 'Auth::login' routes/ --exclude-dir=vendor" "true"

echo ""
echo "2. RBAC Security"
echo "-----------------"

# Check for RBAC bypass
check_issue "RBAC bypass (hasPermission returning true)" "grep -r 'return true.*permission\|hasPermission.*true' app/Http/Controllers/" "true"

# Check for hardcoded permissions (excluding valid permission logic)
check_issue "Hardcoded permissions" "grep -r 'can.*true\|cannot.*false' app/Http/Controllers/ | grep -v 'can_view.*true'" "false"

echo ""
echo "3. Tenancy Security"
echo "-------------------"

# Check for session-based tenant checks
check_issue "Session-based tenant checks" "grep -r 'session.*user' app/Http/Requests/" "true"

# Check for missing tenant_id in queries
check_issue "Missing tenant_id in queries" "grep -r 'where.*tenant_id' app/Http/Controllers/ | grep -v 'tenant_id'" "false"

echo ""
echo "4. Mock Data Security"
echo "---------------------"

# Check for hardcoded notifications
check_issue "Hardcoded notifications" "grep -r 'New Project Created\|Task Completed' resources/views/" "true"

# Check for fake user data
check_issue "Fake user data" "grep -r 'Project Owner\|Sample User' app/Http/Controllers/" "true"

# Check for placeholder content (excluding email templates)
check_issue "Placeholder content" "grep -r 'Welcome to ZenaManage' resources/views/ --exclude-dir=emails" "true"

echo ""
echo "5. Rate Limiting Consistency"
echo "----------------------------"

# Check for duplicate rate limiting middleware
RATE_MIDDLEWARE_COUNT=$(find . -name "*RateLimit*Middleware.php" | wc -l)
if [ $RATE_MIDDLEWARE_COUNT -gt 1 ]; then
    echo -e "${RED}❌ CRITICAL: Multiple rate limiting middleware found!${NC}"
    find . -name "*RateLimit*Middleware.php"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}✅ PASS: Single rate limiting middleware${NC}"
fi

echo ""
echo "6. Module Duplication"
echo "---------------------"

# Check for duplicate CoreProject module
if [ -d "src/CoreProject" ]; then
    echo -e "${RED}❌ CRITICAL: Duplicate CoreProject module found!${NC}"
    echo "This module conflicts with app/ directory"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}✅ PASS: No duplicate modules${NC}"
fi

# Check for duplicate controllers
DUPLICATE_CONTROLLERS=$(find . -name "*Controller.php" | grep -E "(Project|User)" | sort | uniq -d)
if [ -n "$DUPLICATE_CONTROLLERS" ]; then
    echo -e "${YELLOW}⚠️  WARNING: Duplicate controllers found${NC}"
    echo "$DUPLICATE_CONTROLLERS"
else
    echo -e "${GREEN}✅ PASS: No duplicate controllers${NC}"
fi

echo ""
echo "7. API Response Consistency"
echo "---------------------------"

# Check for mixed API response formats (excluding class definitions)
jsend_usage=$(grep -r "JSendResponse::" app/Http/Controllers/ src/ 2>/dev/null | wc -l)
api_usage=$(grep -r "ApiResponse::" app/Http/Controllers/ src/ 2>/dev/null | wc -l)

if [ $jsend_usage -gt 0 ] && [ $api_usage -gt 0 ]; then
    echo -e "${RED}❌ CRITICAL: Mixed API response formats found!${NC}"
    echo "Use either JSendResponse or ApiResponse consistently"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}✅ PASS: Consistent API response format${NC}"
fi

echo ""
echo "8. FormRequest Security"
echo "-----------------------"

# Check for abort(403) in prepareForValidation (fixed pattern)
check_issue "abort(403) in prepareForValidation" "grep -r 'abort(403' app/Http/Requests/" "true"

# Check for session usage in FormRequests
check_issue "Session usage in FormRequests" "grep -r 'session(' app/Http/Requests/" "true"

echo ""
echo "9. Route Security"
echo "-----------------"

# Check for duplicate route names
DUPLICATE_ROUTES=$(php artisan route:list --compact 2>/dev/null | awk '{print $2}' | sort | uniq -d || true)
if [ -n "$DUPLICATE_ROUTES" ]; then
    echo -e "${RED}❌ CRITICAL: Duplicate route names found!${NC}"
    echo "$DUPLICATE_ROUTES"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}✅ PASS: No duplicate route names${NC}"
fi

echo ""
echo "10. Environment Security"
echo "------------------------"

# Check for debug routes in production
if [ "$APP_ENV" = "production" ]; then
    check_issue "Debug routes in production" "grep -r 'APP_DEBUG.*true' .env" "true"
else
    echo -e "${GREEN}✅ PASS: Not in production environment${NC}"
fi

echo ""
echo "================================"
echo "Security Audit Summary"
echo "================================"

if [ $ISSUES -eq 0 ]; then
    echo -e "${GREEN}🎉 All security checks passed!${NC}"
    echo -e "${GREEN}✅ No critical security issues found${NC}"
    exit 0
else
    echo -e "${RED}❌ $ISSUES critical security issue(s) found!${NC}"
    echo -e "${RED}🚨 Immediate action required${NC}"
    echo ""
    echo "Please fix the issues above before deploying to production."
    echo "Refer to SECURITY_CHECKLIST.md for detailed remediation steps."
    exit 1
fi
