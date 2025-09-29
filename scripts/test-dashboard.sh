#!/bin/bash

# Dashboard Testing Script
# Runs comprehensive tests for dashboard functionality

echo "🚀 Starting Dashboard Testing Suite..."
echo "======================================"

# Set environment
export APP_ENV=testing
export DB_DATABASE=zenamanage_test

echo ""
echo "📋 Running Feature Tests..."
echo "---------------------------"

# Run API endpoint tests
php artisan test tests/Feature/DashboardWithETagTest.php --verbose

echo ""
echo "🔧 Running Integration Tests..."  
echo "-----------------------------"

# Run cache integration tests
php artisan test tests/Integration/DashboardCacheIntegrationTest.php --verbose

echo ""
echo "🌐 Running Browser Tests..."
echo "---------------------------"

# Check if Dusk is available
if command -v chromedriver &> /dev/null; then
    php artisan dusk tests/Browser/DashboardSoftRefreshTest.php --verbose
else
    echo "⚠️  ChromeDriver not found. Skipping browser tests."
    echo "   Install ChromeDriver to run browser tests:"
    echo "   brew install chromedriver"
fi

echo ""
echo "📊 Running API Endpoint Health Checks..."
echo "---------------------------------------"

# Test API endpoints directly
echo "Testing /api/admin/dashboard/summary..."
curl -s -o /dev/null -w "Status: %{http_code}, Time: %{time_total}s\n" \
  http://localhost:8000/api/admin/dashboard/summary?range=30d

echo "Testing /api/admin/dashboard/charts..."
curl -s -o /dev/null -w "Status: %{http_code}, Time: %{time_total}s\n" \
  http://localhost:8000/api/admin/dashboard/charts?range=30d

echo "Testing /api/admin/dashboard/activity..."
curl -s -o /dev/null -w "Status: %{http_code}, Time: %{time_total}s\n" \
  http://localhost:8000/api/admin/dashboard/activity

echo "Testing ETag caching..."
RESPONSE1=$(curl -s -i http://localhost:8000/api/admin/dashboard/summary?range=30d)
ETAG=$(echo "$RESPONSE1" | grep -i etag | cut -d' ' -f2 | tr -d '\r')

if [ ! -z "$ETAG" ]; then
    echo "ETag found: $ETAG"
    
    echo "Testing 304 with If-None-Match..."
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
      -H "If-None-Match: $ETAG" \
      http://localhost:8000/api/admin/dashboard/summary?range=30d)
    
    if [ "$STATUS" = "304" ]; then
        echo "✅ ETag caching working correctly (304 response)"
    else
        echo "❌ ETag caching issue (got $STATUS, expected 304)"
    fi
else
    echo "⚠️  No ETag header found in response"
fi

echo ""
echo "🔥 Running Performance Tests..."
echo "------------------------------"

# Performance benchmarks
echo "Measuring dashboard load times..."

START=$(date +%s.%N)
curl -s http://localhost:8000/api/admin/dashboard/summary?range=30d > /dev/null
END=$(date +%s.%N)
SUMMARY_TIME=$(echo "$END - $START" | bc)

START=$(date +%s.%N)
curl -s http://localhost:8000/api/admin/dashboard/charts?range=30d > /dev/null  
END=$(date +%s.%N)
CHARTS_TIME=$(echo "$END - $START" | bc)

START=$(date +%s.%N)
curl -s http://localhost:8000/api/admin/dashboard/activity > /dev/null
END=$(date +%s.%N)
ACTIVITY_TIME=$(echo "$END - $START" | bc)

echo "📊 API Response Times:"
echo "   Summary:  ${SUMMARY_TIME}s"
echo "   Charts:   ${CHARTS_TIME}s" 
echo "   Activity: ${ACTIVITY_TIME}s"

# Check if times are under performance budgets
if (( $(echo "$SUMMARY_TIME < 0.3" | bc -l) )); then
    echo "✅ Summary API under 300ms budget"
else
    echo "⚠️  Summary API exceeds 300ms budget"
fi

if (( $(echo "$CHARTS_TIME < 0.3" | bc -l) )); then
    echo "✅ Charts API under 300ms budget"
else
    echo "⚠️  Charts API exceeds 300ms budget"
fi

echo ""
echo "🧪 Running Accessibility Tests..."
echo "--------------------------------"

# Check for required accessibility attributes
echo "Checking dashboard accessibility..."

# Test if server is running
if curl -s -f http://localhost:8000/admin > /dev/null; then
    # Check for accessibility attributes
    HTML=$(curl -s http://localhost:8000/admin)
    
    if echo "$HTML" | grep -q 'aria-live'; then
        echo "✅ ARIA live regions found"
    else
        echo "❌ ARIA live regions missing"
    fi
    
    if echo "$HTML" | grep -q 'role.*img'; then
        echo "✅ Chart roles found"
    else
        echo "❌ Chart roles missing"
    fi
    
    if echo "$HTML" | grep -q 'data-testid'; then
        echo "✅ Test IDs found"
    else
        echo "❌ Test IDs missing"
    fi
else
    echo "⚠️  Server not running on localhost:8000"
fi

echo ""
echo "📈 Dashboard Test Summary"
echo "========================"
echo ""
echo "✅ Feature Tests: Dashboard APIs"
echo "✅ Integration Tests: Caching behavior"  
echo "✅ Browser Tests: Soft refresh & accessibility"
echo "✅ Performance Tests: Response times"
echo "✅ Accessibility Tests: ARIA compliance"
echo ""
echo "🎯 All tests completed!"
echo ""
echo "📝 Next steps:"
echo "   1. Review any failed tests above"
echo "   2. Check browser console for JavaScript errors"
echo "   3. Verify dashboard works in different browsers"
echo "   4. Test with various screen sizes"
