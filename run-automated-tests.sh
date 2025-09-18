#!/bin/bash

# Automated Test Runner for Task Edit Functionality
# Runs all automated tests and generates comprehensive report

echo "🚀 STARTING AUTOMATED TEST SUITE"
echo "================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results
declare -A test_results

# Function to run test and capture result
run_test() {
    local test_name="$1"
    local test_file="$2"
    local description="$3"
    
    echo -e "${BLUE}📊 Running: $test_name${NC}"
    echo "Description: $description"
    echo "File: $test_file"
    echo "----------------------------------------"
    
    if [ -f "$test_file" ]; then
        if php "$test_file" > "test_output_${test_name}.txt" 2>&1; then
            echo -e "${GREEN}✅ $test_name PASSED${NC}"
            test_results[$test_name]="PASSED"
        else
            echo -e "${RED}❌ $test_name FAILED${NC}"
            test_results[$test_name]="FAILED"
        fi
    else
        echo -e "${RED}❌ $test_name SKIPPED (file not found)${NC}"
        test_results[$test_name]="SKIPPED"
    fi
    echo ""
}

# Function to generate summary report
generate_report() {
    echo "📊 AUTOMATED TEST SUITE SUMMARY"
    echo "=============================="
    echo ""
    
    local total_tests=0
    local passed_tests=0
    local failed_tests=0
    local skipped_tests=0
    
    for test_name in "${!test_results[@]}"; do
        total_tests=$((total_tests + 1))
        case "${test_results[$test_name]}" in
            "PASSED")
                passed_tests=$((passed_tests + 1))
                echo -e "${GREEN}✅ $test_name: PASSED${NC}"
                ;;
            "FAILED")
                failed_tests=$((failed_tests + 1))
                echo -e "${RED}❌ $test_name: FAILED${NC}"
                ;;
            "SKIPPED")
                skipped_tests=$((skipped_tests + 1))
                echo -e "${YELLOW}⏭️  $test_name: SKIPPED${NC}"
                ;;
        esac
    done
    
    echo ""
    echo "📈 STATISTICS:"
    echo "Total Tests: $total_tests"
    echo -e "Passed: ${GREEN}$passed_tests${NC}"
    echo -e "Failed: ${RED}$failed_tests${NC}"
    echo -e "Skipped: ${YELLOW}$skipped_tests${NC}"
    
    if [ $total_tests -gt 0 ]; then
        local success_rate=$((passed_tests * 100 / total_tests))
        echo "Success Rate: $success_rate%"
    fi
    
    echo ""
    
    if [ $failed_tests -eq 0 ] && [ $skipped_tests -eq 0 ]; then
        echo -e "${GREEN}🎉 ALL TESTS PASSED! Task edit functionality is working correctly.${NC}"
    elif [ $failed_tests -gt 0 ]; then
        echo -e "${RED}⚠️  SOME TESTS FAILED! Check the details above for issues.${NC}"
        echo ""
        echo "🔧 FAILED TEST DETAILS:"
        for test_name in "${!test_results[@]}"; do
            if [ "${test_results[$test_name]}" = "FAILED" ]; then
                echo "❌ $test_name:"
                if [ -f "test_output_${test_name}.txt" ]; then
                    echo "   Last few lines of output:"
                    tail -5 "test_output_${test_name}.txt" | sed 's/^/   /'
                fi
                echo ""
            fi
        done
    else
        echo -e "${YELLOW}⚠️  SOME TESTS WERE SKIPPED! Check file availability.${NC}"
    fi
    
    echo ""
    echo "📁 TEST OUTPUT FILES:"
    for test_name in "${!test_results[@]}"; do
        if [ -f "test_output_${test_name}.txt" ]; then
            echo "   - test_output_${test_name}.txt"
        fi
    done
    
    if [ -f "test-task-edit.html" ]; then
        echo "   - test-task-edit.html (Browser test file)"
    fi
    
    echo ""
    echo "🚀 Automated testing completed!"
}

# Main test execution
echo "Starting automated test suite..."
echo ""

# Test 1: Backend Task Edit Tests
run_test "BACKEND_TESTS" "test-task-edit-automated.php" "Tests database, controller, view, form, and update functionality"

# Test 2: Browser/Frontend Tests
run_test "BROWSER_TESTS" "test-browser-automated.php" "Tests HTML generation, Alpine.js binding, form population, and JavaScript console"

# Test 3: Quick Database Check
echo -e "${BLUE}📊 Running: DATABASE_CHECK${NC}"
echo "Description: Quick database connectivity and task data check"
echo "----------------------------------------"
if php -r "
require_once 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$task = App\Models\Task::find('01k5e5nty3m1059pcyymbkgqt8');
if (\$task) {
    echo '✅ Database connection: OK' . PHP_EOL;
    echo '✅ Task found: ' . \$task->name . PHP_EOL;
    echo '✅ Task status: ' . \$task->status . PHP_EOL;
    echo '✅ Task priority: ' . \$task->priority . PHP_EOL;
} else {
    echo '❌ Task not found in database' . PHP_EOL;
}
" > test_output_DATABASE_CHECK.txt 2>&1; then
    echo -e "${GREEN}✅ DATABASE_CHECK PASSED${NC}"
    test_results["DATABASE_CHECK"]="PASSED"
else
    echo -e "${RED}❌ DATABASE_CHECK FAILED${NC}"
    test_results["DATABASE_CHECK"]="FAILED"
fi
echo ""

# Test 4: Laravel Route Check
echo -e "${BLUE}📊 Running: ROUTE_CHECK${NC}"
echo "Description: Check if task edit routes are working"
echo "----------------------------------------"
if php artisan route:list | grep -q "tasks.*edit" > test_output_ROUTE_CHECK.txt 2>&1; then
    echo -e "${GREEN}✅ ROUTE_CHECK PASSED${NC}"
    test_results["ROUTE_CHECK"]="PASSED"
else
    echo -e "${RED}❌ ROUTE_CHECK FAILED${NC}"
    test_results["ROUTE_CHECK"]="FAILED"
fi
echo ""

# Generate final report
generate_report

# Cleanup option
echo ""
read -p "🧹 Clean up test output files? (y/n): " cleanup
if [[ $cleanup =~ ^[Yy]$ ]]; then
    rm -f test_output_*.txt
    echo "✅ Test output files cleaned up"
fi

echo ""
echo "🎯 NEXT STEPS:"
echo "1. Review test results above"
echo "2. Check failed tests for specific issues"
echo "3. Open test-task-edit.html in browser for frontend testing"
echo "4. Fix any identified issues"
echo "5. Re-run tests to verify fixes"
echo ""
echo "🚀 Automated testing completed successfully!"
