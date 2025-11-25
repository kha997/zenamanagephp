#!/bin/bash
# verify-phase2.sh - Script để chạy verification tests từng phần
# 
# Usage: ./scripts/verify-phase2.sh <domain> <type>
# 
# Domains: auth, projects, tasks, documents, users, dashboard
# Types: unit, feature, integration
# 
# Examples:
#   ./scripts/verify-phase2.sh auth unit
#   ./scripts/verify-phase2.sh projects feature
#   ./scripts/verify-phase2.sh dashboard integration

set -e

DOMAIN=$1
TYPE=$2

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Validate arguments
if [ -z "$DOMAIN" ] || [ -z "$TYPE" ]; then
    echo -e "${YELLOW}Usage: ./scripts/verify-phase2.sh <domain> <type>${NC}"
    echo ""
    echo "Domains: auth, projects, tasks, documents, users, dashboard"
    echo "Types: unit, feature, integration"
    echo ""
    echo "Examples:"
    echo "  ./scripts/verify-phase2.sh auth unit"
    echo "  ./scripts/verify-phase2.sh projects feature"
    echo "  ./scripts/verify-phase2.sh dashboard integration"
    exit 1
fi

# Validate domain
VALID_DOMAINS=("auth" "projects" "tasks" "documents" "users" "dashboard")
if [[ ! " ${VALID_DOMAINS[@]} " =~ " ${DOMAIN} " ]]; then
    echo -e "${RED}Error: Invalid domain '${DOMAIN}'${NC}"
    echo "Valid domains: ${VALID_DOMAINS[*]}"
    exit 1
fi

# Validate type
VALID_TYPES=("unit" "feature" "integration")
if [[ ! " ${VALID_TYPES[@]} " =~ " ${TYPE} " ]]; then
    echo -e "${RED}Error: Invalid type '${TYPE}'${NC}"
    echo "Valid types: ${VALID_TYPES[*]}"
    exit 1
fi

SUITE="${DOMAIN}-${TYPE}"
OUTPUT_DIR="storage/app/test-results"
OUTPUT_FILE="${OUTPUT_DIR}/${SUITE}.txt"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

# Create output directory if it doesn't exist
mkdir -p "${OUTPUT_DIR}"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Phase 2 Verification: ${SUITE}${NC}"
echo -e "${BLUE}Started: ${TIMESTAMP}${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Run tests and save output
echo -e "${CYAN}Running ${SUITE} tests...${NC}"
echo -e "${CYAN}Output will be saved to: ${OUTPUT_FILE}${NC}"
echo ""

START_TIME=$(date +%s)

php artisan test --testsuite=${SUITE} 2>&1 | tee ${OUTPUT_FILE}

EXIT_CODE=${PIPESTATUS[0]}

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
MINUTES=$((DURATION / 60))
SECONDS=$((DURATION % 60))

echo ""
echo -e "${BLUE}========================================${NC}"

if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✅ ${SUITE} tests PASSED${NC}"
    echo -e "${GREEN}Duration: ${MINUTES}m ${SECONDS}s${NC}"
else
    echo -e "${RED}❌ ${SUITE} tests FAILED${NC}"
    echo -e "${RED}Exit code: ${EXIT_CODE}${NC}"
    echo -e "${RED}Duration: ${MINUTES}m ${SECONDS}s${NC}"
    echo ""
    echo -e "${YELLOW}Check output file for details: ${OUTPUT_FILE}${NC}"
fi

echo -e "${BLUE}========================================${NC}"
echo ""

# Extract test statistics from output (macOS compatible)
if [ -f "${OUTPUT_FILE}" ]; then
    # Use sed instead of grep -oP for macOS compatibility
    PASSED=$(grep -E "passed" "${OUTPUT_FILE}" | sed -E 's/.* ([0-9]+) passed.*/\1/' | tail -1 || echo "0")
    FAILED=$(grep -E "failed" "${OUTPUT_FILE}" | sed -E 's/.* ([0-9]+) failed.*/\1/' | tail -1 || echo "0")
    SKIPPED=$(grep -E "skipped" "${OUTPUT_FILE}" | sed -E 's/.* ([0-9]+) skipped.*/\1/' | tail -1 || echo "0")
    
    # Clean up extracted numbers (remove non-numeric characters)
    PASSED=$(echo "$PASSED" | tr -cd '0-9' || echo "0")
    FAILED=$(echo "$FAILED" | tr -cd '0-9' || echo "0")
    SKIPPED=$(echo "$SKIPPED" | tr -cd '0-9' || echo "0")
    
    # Only show if we found valid numbers
    if [ ! -z "$PASSED" ] && [ "$PASSED" != "0" ] || [ ! -z "$FAILED" ] && [ "$FAILED" != "0" ] || [ ! -z "$SKIPPED" ] && [ "$SKIPPED" != "0" ]; then
        echo -e "${CYAN}Test Statistics:${NC}"
        echo -e "  Passed:  ${GREEN}${PASSED}${NC}"
        echo -e "  Failed:  ${RED}${FAILED}${NC}"
        echo -e "  Skipped: ${YELLOW}${SKIPPED}${NC}"
        echo ""
    fi
fi

exit $EXIT_CODE

