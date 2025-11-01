#!/bin/bash

# ZenaManage Conflict Detection Script
# Checks for duplicates, overrides, and conflicts in the codebase

set -e

echo "🔍 ZenaManage Conflict Detection Starting..."
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_ISSUES=0
CRITICAL_ISSUES=0
WARNING_ISSUES=0

# Function to count issues
count_issue() {
    local count=$1
    local type=$2
    TOTAL_ISSUES=$((TOTAL_ISSUES + count))
    if [ "$type" = "CRITICAL" ]; then
        CRITICAL_ISSUES=$((CRITICAL_ISSUES + count))
    else
        WARNING_ISSUES=$((WARNING_ISSUES + count))
    fi
}

echo -e "${BLUE}1. Checking for duplicate function definitions...${NC}"
DUPLICATE_FUNCTIONS=$(find resources/views public/js -name "*.blade.php" -o -name "*.js" | xargs grep -h "function " | sort | uniq -d | wc -l)
if [ $DUPLICATE_FUNCTIONS -gt 0 ]; then
    echo -e "${RED}❌ Found $DUPLICATE_FUNCTIONS duplicate function definitions${NC}"
    find resources/views public/js -name "*.blade.php" -o -name "*.js" | xargs grep -h "function " | sort | uniq -d
    count_issue $DUPLICATE_FUNCTIONS "CRITICAL"
else
    echo -e "${GREEN}✅ No duplicate function definitions found${NC}"
fi

echo -e "${BLUE}2. Checking for duplicate Alpine.js x-data...${NC}"
DUPLICATE_XDATA=$(find resources/views -name "*.blade.php" | xargs grep -h "x-data=" | sort | uniq -d | wc -l)
if [ $DUPLICATE_XDATA -gt 0 ]; then
    echo -e "${RED}❌ Found $DUPLICATE_XDATA duplicate x-data definitions${NC}"
    find resources/views -name "*.blade.php" | xargs grep -h "x-data=" | sort | uniq -d
    count_issue $DUPLICATE_XDATA "CRITICAL"
else
    echo -e "${GREEN}✅ No duplicate x-data definitions found${NC}"
fi

echo -e "${BLUE}3. Checking for duplicate CSS classes...${NC}"
DUPLICATE_CSS=$(find public/css -name "*.css" | xargs grep -h "\." | grep -v "/*" | sort | uniq -d | wc -l)
if [ $DUPLICATE_CSS -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Found $DUPLICATE_CSS duplicate CSS class definitions${NC}"
    find public/css -name "*.css" | xargs grep -h "\." | grep -v "/*" | sort | uniq -d | head -10
    count_issue $DUPLICATE_CSS "WARNING"
else
    echo -e "${GREEN}✅ No duplicate CSS class definitions found${NC}"
fi

echo -e "${BLUE}4. Checking for duplicate script includes...${NC}"
DUPLICATE_SCRIPTS=$(find resources/views -name "*.blade.php" | xargs grep -h "script.*src=" | sort | uniq -d | wc -l)
if [ $DUPLICATE_SCRIPTS -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Found $DUPLICATE_SCRIPTS duplicate script includes${NC}"
    find resources/views -name "*.blade.php" | xargs grep -h "script.*src=" | sort | uniq -d | head -5
    count_issue $DUPLICATE_SCRIPTS "WARNING"
else
    echo -e "${GREEN}✅ No duplicate script includes found${NC}"
fi

echo -e "${BLUE}5. Checking for ES6 import/export conflicts...${NC}"
ES6_CONFLICTS=$(find public/js -name "*.js" | xargs grep -l "import\|export" | wc -l)
if [ $ES6_CONFLICTS -gt 0 ]; then
    echo -e "${RED}❌ Found $ES6_CONFLICTS files with ES6 import/export (potential conflicts)${NC}"
    find public/js -name "*.js" | xargs grep -l "import\|export"
    count_issue $ES6_CONFLICTS "CRITICAL"
else
    echo -e "${GREEN}✅ No ES6 import/export conflicts found${NC}"
fi

echo -e "${BLUE}6. Checking for duplicate Chart.js initializations...${NC}"
CHART_CONFLICTS=$(find resources/views public/js -name "*.blade.php" -o -name "*.js" | xargs grep -l "Chart\|chart" | wc -l)
if [ $CHART_CONFLICTS -gt 3 ]; then
    echo -e "${YELLOW}⚠️  Found $CHART_CONFLICTS files with Chart.js code (potential conflicts)${NC}"
    find resources/views public/js -name "*.blade.php" -o -name "*.js" | xargs grep -l "Chart\|chart"
    count_issue $CHART_CONFLICTS "WARNING"
else
    echo -e "${GREEN}✅ Chart.js usage looks reasonable${NC}"
fi

echo -e "${BLUE}7. Checking for duplicate route definitions...${NC}"
DUPLICATE_ROUTES=$(find routes -name "*.php" | xargs grep -h "Route::" | sort | uniq -d | wc -l)
if [ $DUPLICATE_ROUTES -gt 0 ]; then
    echo -e "${RED}❌ Found $DUPLICATE_ROUTES duplicate route definitions${NC}"
    find routes -name "*.php" | xargs grep -h "Route::" | sort | uniq -d
    count_issue $DUPLICATE_ROUTES "CRITICAL"
else
    echo -e "${GREEN}✅ No duplicate route definitions found${NC}"
fi

echo -e "${BLUE}8. Checking for conflicting middleware...${NC}"
MIDDLEWARE_CONFLICTS=$(find app/Http -name "*.php" | xargs grep -h "middleware" | sort | uniq -d | wc -l)
if [ $MIDDLEWARE_CONFLICTS -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Found $MIDDLEWARE_CONFLICTS potential middleware conflicts${NC}"
    find app/Http -name "*.php" | xargs grep -h "middleware" | sort | uniq -d | head -5
    count_issue $MIDDLEWARE_CONFLICTS "WARNING"
else
    echo -e "${GREEN}✅ No middleware conflicts found${NC}"
fi

echo -e "${BLUE}9. Checking for duplicate database migrations...${NC}"
DUPLICATE_MIGRATIONS=$(find database/migrations -name "*.php" | xargs grep -h "Schema::" | sort | uniq -d | wc -l)
if [ $DUPLICATE_MIGRATIONS -gt 0 ]; then
    echo -e "${RED}❌ Found $DUPLICATE_MIGRATIONS duplicate migration definitions${NC}"
    find database/migrations -name "*.php" | xargs grep -h "Schema::" | sort | uniq -d
    count_issue $DUPLICATE_MIGRATIONS "CRITICAL"
else
    echo -e "${GREEN}✅ No duplicate migration definitions found${NC}"
fi

echo -e "${BLUE}10. Checking for archive files with conflicts...${NC}"
ARCHIVE_CONFLICTS=$(find resources/views -path "*/_archive*" -name "*.blade.php" | wc -l)
if [ $ARCHIVE_CONFLICTS -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Found $ARCHIVE_CONFLICTS archive files (potential conflicts)${NC}"
    find resources/views -path "*/_archive*" -name "*.blade.php" | head -5
    count_issue $ARCHIVE_CONFLICTS "WARNING"
else
    echo -e "${GREEN}✅ No archive files found${NC}"
fi

echo ""
echo "=============================================="
echo -e "${BLUE}📊 CONFLICT DETECTION SUMMARY${NC}"
echo "=============================================="
echo -e "Total Issues Found: ${YELLOW}$TOTAL_ISSUES${NC}"
echo -e "Critical Issues: ${RED}$CRITICAL_ISSUES${NC}"
echo -e "Warning Issues: ${YELLOW}$WARNING_ISSUES${NC}"

if [ $CRITICAL_ISSUES -gt 0 ]; then
    echo -e "${RED}❌ CRITICAL ISSUES DETECTED - COMMIT BLOCKED${NC}"
    exit 1
elif [ $WARNING_ISSUES -gt 0 ]; then
    echo -e "${YELLOW}⚠️  WARNINGS DETECTED - REVIEW RECOMMENDED${NC}"
    exit 0
else
    echo -e "${GREEN}✅ NO CONFLICTS DETECTED - SAFE TO COMMIT${NC}"
    exit 0
fi
