#!/bin/bash

# System Audit Script for ZenaManage
# This script performs comprehensive system audit and generates reports

set -e

echo "🔍 ZenaManage System Audit Script"
echo "=================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Please run this script from the Laravel project root directory"
    exit 1
fi

# Create audit directory
mkdir -p audit-reports
AUDIT_DIR="audit-reports/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$AUDIT_DIR"

print_status "Starting comprehensive system audit..."

# 1. Check Controllers
print_status "1. Auditing Controllers..."
CONTROLLER_COUNT=$(find app/Http/Controllers -name "*.php" | wc -l)
print_success "Found $CONTROLLER_COUNT controllers"

# Check for missing policies
POLICY_COUNT=$(find app/Policies -name "*.php" | wc -l)
print_warning "Found only $POLICY_COUNT policies (should have 15+)"

# 2. Check Services
print_status "2. Auditing Services..."
SERVICE_COUNT=$(find app/Services -name "*.php" | wc -l)
print_success "Found $SERVICE_COUNT services"

# 3. Check Models
print_status "3. Auditing Models..."
MODEL_COUNT=$(find app/Models -name "*.php" | wc -l)
print_success "Found $MODEL_COUNT models"

# 4. Check Migrations
print_status "4. Auditing Migrations..."
MIGRATION_COUNT=$(find database/migrations -name "*.php" | wc -l)
print_success "Found $MIGRATION_COUNT migrations"

# 5. Check Tests
print_status "5. Auditing Tests..."
TEST_COUNT=$(find tests -name "*.php" | wc -l)
print_success "Found $TEST_COUNT test files"

# 6. Check Views
print_status "6. Auditing Views..."
VIEW_COUNT=$(find resources/views -name "*.blade.php" | wc -l)
print_success "Found $VIEW_COUNT view files"

# 7. Check Routes
print_status "7. Auditing Routes..."
WEB_ROUTES=$(grep -c "Route::" routes/web.php || echo "0")
API_ROUTES=$(grep -c "Route::" routes/api.php || echo "0")
print_success "Found $WEB_ROUTES web routes and $API_ROUTES API routes"

# 8. Check for Critical Issues
print_status "8. Checking for Critical Issues..."

# Check for disabled files
DISABLED_FILES=$(find . -name "*.disabled" | wc -l)
if [ $DISABLED_FILES -gt 0 ]; then
    print_warning "Found $DISABLED_FILES disabled files"
    find . -name "*.disabled" >> "$AUDIT_DIR/disabled-files.txt"
fi

# Check for duplicate files
DUPLICATE_FILES=$(find . -name "*.php" -exec basename {} \; | sort | uniq -d | wc -l)
if [ $DUPLICATE_FILES -gt 0 ]; then
    print_warning "Found $DUPLICATE_FILES potential duplicate files"
    find . -name "*.php" -exec basename {} \; | sort | uniq -d >> "$AUDIT_DIR/duplicate-files.txt"
fi

# Check for missing middleware
MISSING_MIDDLEWARE=$(grep -c "withoutMiddleware" routes/web.php || echo "0")
if [ $MISSING_MIDDLEWARE -gt 0 ]; then
    print_warning "Found $MISSING_MIDDLEWARE routes without middleware"
    grep -n "withoutMiddleware" routes/web.php >> "$AUDIT_DIR/missing-middleware.txt"
fi

# 9. Check for Missing Components
print_status "9. Checking for Missing Components..."

# Check for missing policies
MISSING_POLICIES=0
if [ ! -f "app/Policies/DocumentPolicy.php" ]; then
    echo "DocumentPolicy.php" >> "$AUDIT_DIR/missing-policies.txt"
    ((MISSING_POLICIES++))
fi
if [ ! -f "app/Policies/ComponentPolicy.php" ]; then
    echo "ComponentPolicy.php" >> "$AUDIT_DIR/missing-policies.txt"
    ((MISSING_POLICIES++))
fi
if [ ! -f "app/Policies/TeamPolicy.php" ]; then
    echo "TeamPolicy.php" >> "$AUDIT_DIR/missing-policies.txt"
    ((MISSING_POLICIES++))
fi
if [ ! -f "app/Policies/NotificationPolicy.php" ]; then
    echo "NotificationPolicy.php" >> "$AUDIT_DIR/missing-policies.txt"
    ((MISSING_POLICIES++))
fi

if [ $MISSING_POLICIES -gt 0 ]; then
    print_warning "Found $MISSING_POLICIES missing policies"
fi

# Check for missing request validation
MISSING_REQUESTS=0
if [ ! -f "app/Http/Requests/BulkOperationRequest.php" ]; then
    echo "BulkOperationRequest.php" >> "$AUDIT_DIR/missing-requests.txt"
    ((MISSING_REQUESTS++))
fi
if [ ! -f "app/Http/Requests/DashboardRequest.php" ]; then
    echo "DashboardRequest.php" >> "$AUDIT_DIR/missing-requests.txt"
    ((MISSING_REQUESTS++))
fi
if [ ! -f "app/Http/Requests/NotificationRequest.php" ]; then
    echo "NotificationRequest.php" >> "$AUDIT_DIR/missing-requests.txt"
    ((MISSING_REQUESTS++))
fi

if [ $MISSING_REQUESTS -gt 0 ]; then
    print_warning "Found $MISSING_REQUESTS missing request validation classes"
fi

# Check for missing API resources
MISSING_RESOURCES=0
if [ ! -f "app/Http/Resources/DashboardResource.php" ]; then
    echo "DashboardResource.php" >> "$AUDIT_DIR/missing-resources.txt"
    ((MISSING_RESOURCES++))
fi
if [ ! -f "app/Http/Resources/NotificationResource.php" ]; then
    echo "NotificationResource.php" >> "$AUDIT_DIR/missing-resources.txt"
    ((MISSING_RESOURCES++))
fi
if [ ! -f "app/Http/Resources/TeamResource.php" ]; then
    echo "TeamResource.php" >> "$AUDIT_DIR/missing-resources.txt"
    ((MISSING_RESOURCES++))
fi

if [ $MISSING_RESOURCES -gt 0 ]; then
    print_warning "Found $MISSING_RESOURCES missing API resources"
fi

# 10. Check for Missing Tests
print_status "10. Checking for Missing Tests..."

MISSING_TESTS=0
if [ ! -f "tests/Unit/Policies/DocumentPolicyTest.php" ]; then
    echo "DocumentPolicyTest.php" >> "$AUDIT_DIR/missing-tests.txt"
    ((MISSING_TESTS++))
fi
if [ ! -f "tests/Unit/Policies/ComponentPolicyTest.php" ]; then
    echo "ComponentPolicyTest.php" >> "$AUDIT_DIR/missing-tests.txt"
    ((MISSING_TESTS++))
fi
if [ ! -f "tests/Unit/Policies/TeamPolicyTest.php" ]; then
    echo "TeamPolicyTest.php" >> "$AUDIT_DIR/missing-tests.txt"
    ((MISSING_TESTS++))
fi
if [ ! -f "tests/Unit/Services/DocumentServiceTest.php" ]; then
    echo "DocumentServiceTest.php" >> "$AUDIT_DIR/missing-tests.txt"
    ((MISSING_TESTS++))
fi
if [ ! -f "tests/Unit/Services/TeamServiceTest.php" ]; then
    echo "TeamServiceTest.php" >> "$AUDIT_DIR/missing-tests.txt"
    ((MISSING_TESTS++))
fi

if [ $MISSING_TESTS -gt 0 ]; then
    print_warning "Found $MISSING_TESTS missing test files"
fi

# 11. Check for Performance Issues
print_status "11. Checking for Performance Issues..."

# Check for N+1 query patterns
N1_QUERIES=$(grep -r "->get()" app/Services/ | wc -l || echo "0")
if [ $N1_QUERIES -gt 10 ]; then
    print_warning "Found $N1_QUERIES potential N+1 query patterns"
    grep -r "->get()" app/Services/ >> "$AUDIT_DIR/n1-queries.txt"
fi

# Check for missing eager loading
MISSING_EAGER_LOADING=$(grep -r "with(" app/Services/ | wc -l || echo "0")
if [ $MISSING_EAGER_LOADING -lt 5 ]; then
    print_warning "Found only $MISSING_EAGER_LOADING eager loading patterns"
fi

# 12. Check for Security Issues
print_status "12. Checking for Security Issues..."

# Check for missing CSRF protection
MISSING_CSRF=$(grep -r "csrf" app/Http/Controllers/ | wc -l || echo "0")
if [ $MISSING_CSRF -lt 10 ]; then
    print_warning "Found only $MISSING_CSRF CSRF protection patterns"
fi

# Check for missing authorization
MISSING_AUTH=$(grep -r "authorize" app/Http/Controllers/ | wc -l || echo "0")
if [ $MISSING_AUTH -lt 20 ]; then
    print_warning "Found only $MISSING_AUTH authorization patterns"
fi

# 13. Generate Summary Report
print_status "13. Generating Summary Report..."

cat > "$AUDIT_DIR/audit-summary.md" << EOF
# ZenaManage System Audit Summary

**Audit Date**: $(date)  
**Audit Directory**: $AUDIT_DIR  

## 📊 Component Counts

| Component | Count | Status |
|-----------|-------|--------|
| Controllers | $CONTROLLER_COUNT | ✅ |
| Services | $SERVICE_COUNT | ✅ |
| Models | $MODEL_COUNT | ✅ |
| Migrations | $MIGRATION_COUNT | ✅ |
| Tests | $TEST_COUNT | ✅ |
| Views | $VIEW_COUNT | ✅ |
| Web Routes | $WEB_ROUTES | ✅ |
| API Routes | $API_ROUTES | ✅ |
| Policies | $POLICY_COUNT | ⚠️ |

## ⚠️ Critical Issues Found

- **Missing Policies**: $MISSING_POLICIES
- **Missing Request Validation**: $MISSING_REQUESTS
- **Missing API Resources**: $MISSING_RESOURCES
- **Missing Tests**: $MISSING_TESTS
- **Disabled Files**: $DISABLED_FILES
- **Duplicate Files**: $DUPLICATE_FILES
- **Routes Without Middleware**: $MISSING_MIDDLEWARE

## 🔧 Performance Issues

- **N+1 Query Patterns**: $N1_QUERIES
- **Eager Loading Patterns**: $MISSING_EAGER_LOADING

## 🔒 Security Issues

- **CSRF Protection Patterns**: $MISSING_CSRF
- **Authorization Patterns**: $MISSING_AUTH

## 📋 Recommendations

1. **High Priority**: Add missing policies and request validation
2. **Medium Priority**: Add missing tests and API resources
3. **Low Priority**: Fix performance and security issues

## 🎯 Next Steps

1. Review detailed reports in audit directory
2. Prioritize fixes based on criticality
3. Implement fixes systematically
4. Re-run audit to verify improvements

EOF

# 14. Generate Detailed Report
print_status "14. Generating Detailed Report..."

cat > "$AUDIT_DIR/detailed-report.md" << EOF
# ZenaManage Detailed Audit Report

## 🔍 File Analysis

### Controllers Analysis
- **Total Controllers**: $CONTROLLER_COUNT
- **Admin Controllers**: $(find app/Http/Controllers/Admin -name "*.php" | wc -l)
- **API Controllers**: $(find app/Http/Controllers/Api -name "*.php" | wc -l)
- **Web Controllers**: $(find app/Http/Controllers/Web -name "*.php" | wc -l)

### Services Analysis
- **Total Services**: $SERVICE_COUNT
- **Core Services**: $(find app/Services -name "*Service.php" | wc -l)
- **Integration Services**: $(find app/Services -name "*Integration*" | wc -l)
- **Security Services**: $(find app/Services -name "*Security*" | wc -l)

### Models Analysis
- **Total Models**: $MODEL_COUNT
- **Core Models**: $(find app/Models -name "*.php" | grep -E "(User|Project|Task|Document)" | wc -l)
- **RBAC Models**: $(find app/Models -name "*.php" | grep -E "(Role|Permission)" | wc -l)
- **Dashboard Models**: $(find app/Models -name "*.php" | grep -E "(Dashboard|Widget)" | wc -l)

### Tests Analysis
- **Total Tests**: $TEST_COUNT
- **Unit Tests**: $(find tests/Unit -name "*.php" | wc -l)
- **Feature Tests**: $(find tests/Feature -name "*.php" | wc -l)
- **Browser Tests**: $(find tests/Browser -name "*.php" | wc -l)

## 🚨 Critical Issues

### Missing Policies
EOF

if [ -f "$AUDIT_DIR/missing-policies.txt" ]; then
    cat "$AUDIT_DIR/missing-policies.txt" >> "$AUDIT_DIR/detailed-report.md"
fi

cat >> "$AUDIT_DIR/detailed-report.md" << EOF

### Missing Request Validation
EOF

if [ -f "$AUDIT_DIR/missing-requests.txt" ]; then
    cat "$AUDIT_DIR/missing-requests.txt" >> "$AUDIT_DIR/detailed-report.md"
fi

cat >> "$AUDIT_DIR/detailed-report.md" << EOF

### Missing API Resources
EOF

if [ -f "$AUDIT_DIR/missing-resources.txt" ]; then
    cat "$AUDIT_DIR/missing-resources.txt" >> "$AUDIT_DIR/detailed-report.md"
fi

cat >> "$AUDIT_DIR/detailed-report.md" << EOF

### Missing Tests
EOF

if [ -f "$AUDIT_DIR/missing-tests.txt" ]; then
    cat "$AUDIT_DIR/missing-tests.txt" >> "$AUDIT_DIR/detailed-report.md"
fi

cat >> "$AUDIT_DIR/detailed-report.md" << EOF

## 🔧 Performance Issues

### N+1 Query Patterns
EOF

if [ -f "$AUDIT_DIR/n1-queries.txt" ]; then
    cat "$AUDIT_DIR/n1-queries.txt" >> "$AUDIT_DIR/detailed-report.md"
fi

cat >> "$AUDIT_DIR/detailed-report.md" << EOF

## 🔒 Security Issues

### Routes Without Middleware
EOF

if [ -f "$AUDIT_DIR/missing-middleware.txt" ]; then
    cat "$AUDIT_DIR/missing-middleware.txt" >> "$AUDIT_DIR/detailed-report.md"
fi

cat >> "$AUDIT_DIR/detailed-report.md" << EOF

## 📋 Action Items

1. **Immediate Actions**:
   - Add missing policies
   - Fix routes without middleware
   - Add request validation classes

2. **Short-term Actions**:
   - Add missing tests
   - Add API resources
   - Fix performance issues

3. **Long-term Actions**:
   - Implement comprehensive security
   - Add monitoring and logging
   - Optimize database queries

EOF

# 15. Generate JSON Report
print_status "15. Generating JSON Report..."

cat > "$AUDIT_DIR/audit-report.json" << EOF
{
  "audit_date": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "audit_directory": "$AUDIT_DIR",
  "components": {
    "controllers": $CONTROLLER_COUNT,
    "services": $SERVICE_COUNT,
    "models": $MODEL_COUNT,
    "migrations": $MIGRATION_COUNT,
    "tests": $TEST_COUNT,
    "views": $VIEW_COUNT,
    "web_routes": $WEB_ROUTES,
    "api_routes": $API_ROUTES,
    "policies": $POLICY_COUNT
  },
  "issues": {
    "missing_policies": $MISSING_POLICIES,
    "missing_requests": $MISSING_REQUESTS,
    "missing_resources": $MISSING_RESOURCES,
    "missing_tests": $MISSING_TESTS,
    "disabled_files": $DISABLED_FILES,
    "duplicate_files": $DUPLICATE_FILES,
    "missing_middleware": $MISSING_MIDDLEWARE,
    "n1_queries": $N1_QUERIES,
    "missing_eager_loading": $MISSING_EAGER_LOADING,
    "missing_csrf": $MISSING_CSRF,
    "missing_auth": $MISSING_AUTH
  },
  "recommendations": [
    "Add missing policies for security",
    "Add request validation classes",
    "Add missing test files",
    "Fix routes without middleware",
    "Optimize database queries",
    "Implement comprehensive security"
  ]
}
EOF

# 16. Final Summary
print_status "16. Audit Complete!"

echo ""
echo "=============================================="
print_success "System Audit Completed Successfully!"
echo ""
print_status "Audit Results:"
echo "  - Controllers: $CONTROLLER_COUNT"
echo "  - Services: $SERVICE_COUNT"
echo "  - Models: $MODEL_COUNT"
echo "  - Tests: $TEST_COUNT"
echo "  - Policies: $POLICY_COUNT (⚠️ Low)"
echo ""
print_status "Critical Issues Found:"
echo "  - Missing Policies: $MISSING_POLICIES"
echo "  - Missing Tests: $MISSING_TESTS"
echo "  - Routes Without Middleware: $MISSING_MIDDLEWARE"
echo "  - Disabled Files: $DISABLED_FILES"
echo ""
print_status "Reports Generated:"
echo "  - Summary: $AUDIT_DIR/audit-summary.md"
echo "  - Detailed: $AUDIT_DIR/detailed-report.md"
echo "  - JSON: $AUDIT_DIR/audit-report.json"
echo ""
print_success "🎉 Audit complete! Review reports for next steps."
echo "=============================================="
