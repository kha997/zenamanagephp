#!/bin/bash

# ZenaManage View Testing Script
# Kiểm tra tất cả views và hiển thị kết quả

echo "🚀 ZenaManage View Testing Script"
echo "================================="
echo ""

# Danh sách các views cần kiểm tra
views=(
    "test-tailwind:Tailwind CSS Test"
    "admin-dashboard-enhanced:Admin Dashboard Enhanced"
    "projects-enhanced:Projects Management Enhanced"
    "test-mobile-simple:Mobile Simple Test"
    "test-mobile-optimization:Mobile Optimization Test"
    "test-accessibility:Accessibility Test"
    "admin-dashboard-test:Admin Dashboard Test"
    "tenant-dashboard-test:Tenant Dashboard Test"
    "testing-suite:Testing Suite"
    "performance-optimization:Performance Optimization"
    "final-integration:Final Integration & Launch"
    "admin-dashboard-complete:Admin Dashboard Complete"
    "projects-complete:Projects Management Complete"
    "tasks-complete:Tasks Management Complete"
    "calendar-complete:Calendar Management Complete"
)

base_url="http://localhost:8002"
total_views=${#views[@]}
passed=0
failed=0

echo "📊 Testing $total_views views..."
echo ""

for view in "${views[@]}"; do
    IFS=':' read -r route name <<< "$view"
    url="$base_url/$route"
    
    # Kiểm tra HTTP status code
    status_code=$(curl -s -o /dev/null -w "%{http_code}" "$url")
    
    if [ "$status_code" = "200" ]; then
        echo "✅ $name: OK ($status_code)"
        ((passed++))
    else
        echo "❌ $name: FAILED ($status_code)"
        ((failed++))
    fi
done

echo ""
echo "📈 Test Results:"
echo "   Total: $total_views"
echo "   Passed: $passed"
echo "   Failed: $failed"
echo "   Success Rate: $(( passed * 100 / total_views ))%"

if [ $failed -eq 0 ]; then
    echo ""
    echo "🎉 All views are working correctly!"
else
    echo ""
    echo "⚠️  Some views need attention."
fi

echo ""
echo "🌐 Access URLs:"
for view in "${views[@]}"; do
    IFS=':' read -r route name <<< "$view"
    echo "   $name: $base_url/$route"
done
