#!/bin/bash

# Duplicate Code Analysis Script
# Analyzes the codebase for duplicate code patterns and generates reports

set -e

echo "🔍 Starting Duplicate Code Analysis..."
echo "======================================"

# Create reports directory
mkdir -p reports

# Function to check file counts
check_file_counts() {
    echo "📊 File Count Analysis:"
    echo "----------------------"
    
    # Header files
    HEADER_FILES=$(find resources/views -name "*header*" -type f | wc -l)
    echo "Header files: $HEADER_FILES"
    
    # Layout files
    LAYOUT_FILES=$(find resources/views/layouts -name "app*.blade.php" -type f | wc -l)
    echo "Layout files: $LAYOUT_FILES"
    
    # Dashboard files
    DASHBOARD_FILES=$(find resources/views/app -name "*dashboard*" -type f | wc -l)
    echo "Dashboard files: $DASHBOARD_FILES"
    
    # Project files
    PROJECT_FILES=$(find resources/views/app -name "*project*" -type f | wc -l)
    echo "Project files: $PROJECT_FILES"
    
    # Controller files
    CONTROLLER_FILES=$(find app/Http/Controllers -name "*Controller.php" -type f | wc -l)
    echo "Controller files: $CONTROLLER_FILES"
    
    # Request files
    REQUEST_FILES=$(find app/Http/Requests -name "*Request.php" -type f | wc -l)
    echo "Request files: $REQUEST_FILES"
    
    echo ""
}

# Function to analyze specific duplicate clusters
analyze_clusters() {
    echo "🎯 Duplicate Cluster Analysis:"
    echo "-----------------------------"
    
    # CL-UI-001: Header components
    echo "CL-UI-001: Header Components"
    find resources/views -name "*header*" -type f -exec echo "  - {}" \;
    find src -name "*header*" -type f -exec echo "  - {}" \;
    echo ""
    
    # CL-UI-002: Layout components
    echo "CL-UI-002: Layout Components"
    find resources/views/layouts -name "app*.blade.php" -type f -exec echo "  - {}" \;
    echo ""
    
    # CL-UI-003: Dashboard components
    echo "CL-UI-003: Dashboard Components"
    find resources/views/app -name "*dashboard*" -type f -exec echo "  - {}" \;
    echo ""
    
    # CL-UI-004: Project components
    echo "CL-UI-004: Project Components"
    find resources/views/app -name "*project*" -type f -exec echo "  - {}" \;
    echo ""
    
    # CL-BE-005: User controllers
    echo "CL-BE-005: User Controllers"
    find app/Http/Controllers -name "*User*Controller.php" -type f -exec echo "  - {}" \;
    echo ""
    
    # CL-BE-006: Project controllers
    echo "CL-BE-006: Project Controllers"
    find app/Http/Controllers -name "*Project*Controller.php" -type f -exec echo "  - {}" \;
    echo ""
    
    # CL-BE-007: Rate limit middleware
    echo "CL-BE-007: Rate Limit Middleware"
    find app/Http/Middleware -name "*Rate*" -type f -exec echo "  - {}" \;
    echo ""
    
    # CL-DATA-008: Project requests
    echo "CL-DATA-008: Project Requests"
    find app/Http/Requests -name "*Project*" -type f -exec echo "  - {}" \;
    echo ""
}

# Function to check for common patterns
check_patterns() {
    echo "🔍 Pattern Analysis:"
    echo "-------------------"
    
    # Check for duplicate navigation menus
    echo "Navigation menu patterns:"
    grep -r "Dashboard.*Projects.*Tasks" resources/views/ --include="*.blade.php" | wc -l | xargs echo "  Found in files:"
    
    # Check for duplicate KPI cards
    echo "KPI card patterns:"
    grep -r "Total Projects" resources/views/ --include="*.blade.php" | wc -l | xargs echo "  Found in files:"
    
    # Check for duplicate form patterns
    echo "Form patterns:"
    grep -r "form.*method.*POST" resources/views/ --include="*.blade.php" | wc -l | xargs echo "  Found in files:"
    
    # Check for duplicate API patterns
    echo "API patterns:"
    grep -r "fetch.*api" resources/js/ --include="*.js" | wc -l | xargs echo "  Found in files:"
    
    echo ""
}

# Function to generate recommendations
generate_recommendations() {
    echo "💡 Recommendations:"
    echo "------------------"
    
    # Header consolidation
    HEADER_COUNT=$(find resources/views -name "*header*" -type f | wc -l)
    if [ $HEADER_COUNT -gt 2 ]; then
        echo "1. Consolidate $HEADER_COUNT header files into HeaderShell component"
    fi
    
    # Layout consolidation
    LAYOUT_COUNT=$(find resources/views/layouts -name "app*.blade.php" -type f | wc -l)
    if [ $LAYOUT_COUNT -gt 1 ]; then
        echo "2. Consolidate $LAYOUT_COUNT layout files into single app.blade.php"
    fi
    
    # Dashboard consolidation
    DASHBOARD_COUNT=$(find resources/views/app -name "*dashboard*" -type f | wc -l)
    if [ $DASHBOARD_COUNT -gt 1 ]; then
        echo "3. Consolidate $DASHBOARD_COUNT dashboard files into React component"
    fi
    
    # Project consolidation
    PROJECT_COUNT=$(find resources/views/app -name "*project*" -type f | wc -l)
    if [ $PROJECT_COUNT -gt 2 ]; then
        echo "4. Consolidate $PROJECT_COUNT project files into shared components"
    fi
    
    # Controller consolidation
    USER_CONTROLLER_COUNT=$(find app/Http/Controllers -name "*User*Controller.php" -type f | wc -l)
    if [ $USER_CONTROLLER_COUNT -gt 1 ]; then
        echo "5. Consolidate $USER_CONTROLLER_COUNT user controllers into single controller"
    fi
    
    echo ""
}

# Function to run JavaScript duplication check
run_js_duplication_check() {
    echo "🔍 JavaScript Duplication Check:"
    echo "--------------------------------"
    
    if command -v npx &> /dev/null; then
        npx jscpd --min-lines 10 --min-tokens 50 --threshold 5 --reporters console,html --output ./reports/jscpd resources/js src/ || true
    else
        echo "jscpd not available, skipping JavaScript duplication check"
    fi
    
    echo ""
}

# Function to run PHP syntax check
run_php_syntax_check() {
    echo "🔍 PHP Syntax Check:"
    echo "--------------------"
    
    # Check PHP syntax
    find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors" || true
    
    echo ""
}

# Function to run ESLint check
run_eslint_check() {
    echo "🔍 ESLint Quality Check:"
    echo "------------------------"
    
    if command -v npx &> /dev/null; then
        npx eslint resources/js src/ --ext .js,.ts,.jsx,.tsx --config .eslintrc.sonarjs.js --format json --output-file ./reports/eslint.json || true
    else
        echo "ESLint not available, skipping quality check"
    fi
    
    echo ""
}

# Function to generate summary report
generate_summary_report() {
    echo "📋 Summary Report:"
    echo "=================="
    
    # Count total files
    TOTAL_VIEWS=$(find resources/views -name "*.blade.php" -type f | wc -l)
    TOTAL_CONTROLLERS=$(find app/Http/Controllers -name "*.php" -type f | wc -l)
    TOTAL_REQUESTS=$(find app/Http/Requests -name "*.php" -type f | wc -l)
    TOTAL_JS=$(find resources/js src -name "*.js" -o -name "*.ts" -o -name "*.jsx" -o -name "*.tsx" | wc -l)
    
    echo "Total files analyzed:"
    echo "  - Blade views: $TOTAL_VIEWS"
    echo "  - Controllers: $TOTAL_CONTROLLERS"
    echo "  - Requests: $TOTAL_REQUESTS"
    echo "  - JavaScript/TypeScript: $TOTAL_JS"
    
    # Calculate potential savings
    HEADER_SAVINGS=$((HEADER_FILES - 1))
    LAYOUT_SAVINGS=$((LAYOUT_FILES - 1))
    DASHBOARD_SAVINGS=$((DASHBOARD_FILES - 1))
    
    echo ""
    echo "Potential consolidation savings:"
    echo "  - Header files: $HEADER_SAVINGS files can be consolidated"
    echo "  - Layout files: $LAYOUT_SAVINGS files can be consolidated"
    echo "  - Dashboard files: $DASHBOARD_SAVINGS files can be consolidated"
    
    echo ""
    echo "Estimated LOC reduction: 40-60% in dashboard/projects views"
    echo "Estimated maintenance reduction: 50-70% for duplicate components"
    
    echo ""
}

# Main execution
main() {
    check_file_counts
    analyze_clusters
    check_patterns
    run_js_duplication_check
    run_php_syntax_check
    run_eslint_check
    generate_recommendations
    generate_summary_report
    
    echo "✅ Analysis completed! Check reports/ directory for detailed results."
}

# Run main function
main "$@"
