#!/bin/bash

# PR: Performance Budgets Enforcement in CI
# This script validates performance metrics against budgets defined in performance-budgets.json

set -e

BUDGETS_FILE="performance-budgets.json"
METRICS_FILE="${1:-test-results/performance-metrics.json}"
REPORT_FILE="test-results/performance-budget-report.json"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "🔍 Checking performance budgets..."

# Check if budgets file exists
if [ ! -f "$BUDGETS_FILE" ]; then
  echo -e "${RED}❌ Performance budgets file not found: $BUDGETS_FILE${NC}"
  exit 1
fi

# Check if metrics file exists
if [ ! -f "$METRICS_FILE" ]; then
  echo -e "${YELLOW}⚠️  Metrics file not found: $METRICS_FILE${NC}"
  echo "   Creating empty metrics file for validation..."
  echo '{"api": {}, "pages": {}, "websocket": {}, "cache": {}, "database": {}, "memory": {}}' > "$METRICS_FILE"
fi

# Create report directory
mkdir -p "$(dirname "$REPORT_FILE")"

# Run Node.js script to check budgets
NODE_SCRIPT="scripts/check-performance-budgets.js"

if [ ! -f "$NODE_SCRIPT" ]; then
  echo -e "${RED}❌ Budget checker script not found: $NODE_SCRIPT${NC}"
  exit 1
fi

# Run budget checker
if node "$NODE_SCRIPT" "$BUDGETS_FILE" "$METRICS_FILE" "$REPORT_FILE"; then
  echo -e "${GREEN}✅ All performance budgets met${NC}"
  exit 0
else
  echo -e "${RED}❌ Performance budget violations detected${NC}"
  echo "   See report: $REPORT_FILE"
  
  # Check if we should fail on violation
  FAIL_ON_VIOLATION=$(node -e "const b=require('./$BUDGETS_FILE');console.log(b.enforcement.ci.fail_on_violation)")
  
  if [ "$FAIL_ON_VIOLATION" = "true" ]; then
    exit 1
  else
    exit 0
  fi
fi

