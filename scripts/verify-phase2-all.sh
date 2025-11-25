#!/bin/bash
# verify-phase2-all.sh - Script để chạy tất cả verification tests theo từng phase
# 
# Usage: ./scripts/verify-phase2-all.sh [phase]
# 
# Phases:
#   1 - Unit tests only (6 suites, ~30-60 minutes)
#   2 - Feature tests only (6 suites, ~60-120 minutes)
#   3 - Integration tests only (6 suites, ~90-180 minutes)
#   all - All tests (18 suites) - NOT RECOMMENDED, use individual phases instead
# 
# Examples:
#   ./scripts/verify-phase2-all.sh 1    # Run all unit tests
#   ./scripts/verify-phase2-all.sh 2    # Run all feature tests
#   ./scripts/verify-phase2-all.sh 3    # Run all integration tests

set -e

PHASE=$1

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

DOMAINS=("auth" "projects" "tasks" "documents" "users" "dashboard")

# Validate phase
if [ -z "$PHASE" ]; then
    echo -e "${YELLOW}Usage: ./scripts/verify-phase2-all.sh [phase]${NC}"
    echo ""
    echo "Phases:"
    echo "  1 - Unit tests only (6 suites, ~30-60 minutes)"
    echo "  2 - Feature tests only (6 suites, ~60-120 minutes)"
    echo "  3 - Integration tests only (6 suites, ~90-180 minutes)"
    echo "  all - All tests (18 suites) - NOT RECOMMENDED"
    echo ""
    echo "Examples:"
    echo "  ./scripts/verify-phase2-all.sh 1"
    echo "  ./scripts/verify-phase2-all.sh 2"
    exit 1
fi

# Determine test type based on phase
case $PHASE in
    1)
        TYPE="unit"
        PHASE_NAME="Unit Tests"
        ;;
    2)
        TYPE="feature"
        PHASE_NAME="Feature Tests"
        ;;
    3)
        TYPE="integration"
        PHASE_NAME="Integration Tests"
        ;;
    all)
        echo -e "${RED}Warning: Running all 18 test suites may take several hours!${NC}"
        echo -e "${YELLOW}Consider running phases 1, 2, 3 separately instead.${NC}"
        read -p "Continue? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
        ;;
    *)
        echo -e "${RED}Error: Invalid phase '${PHASE}'${NC}"
        echo "Valid phases: 1, 2, 3, all"
        exit 1
        ;;
esac

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Phase 2 Verification: ${PHASE_NAME}${NC}"
echo -e "${BLUE}Started: $(date +"%Y-%m-%d %H:%M:%S")${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

TOTAL_START_TIME=$(date +%s)
SUCCESS_COUNT=0
FAIL_COUNT=0

# Run tests for each domain
if [ "$PHASE" = "all" ]; then
    # Run all phases
    for phase_num in 1 2 3; do
        case $phase_num in
            1) TYPE="unit" ;;
            2) TYPE="feature" ;;
            3) TYPE="integration" ;;
        esac
        
        echo -e "${CYAN}=== Phase ${phase_num}: ${TYPE^} Tests ===${NC}"
        echo ""
        
        for domain in "${DOMAINS[@]}"; do
            echo -e "${BLUE}Running ${domain}-${TYPE}...${NC}"
            if ./scripts/verify-phase2.sh "${domain}" "${TYPE}"; then
                ((SUCCESS_COUNT++))
            else
                ((FAIL_COUNT++))
            fi
            echo ""
        done
    done
else
    # Run single phase
    for domain in "${DOMAINS[@]}"; do
        echo -e "${BLUE}Running ${domain}-${TYPE}...${NC}"
        if ./scripts/verify-phase2.sh "${domain}" "${TYPE}"; then
            ((SUCCESS_COUNT++))
        else
            ((FAIL_COUNT++))
        fi
        echo ""
    done
fi

TOTAL_END_TIME=$(date +%s)
TOTAL_DURATION=$((TOTAL_END_TIME - TOTAL_START_TIME))
TOTAL_MINUTES=$((TOTAL_DURATION / 60))
TOTAL_SECONDS=$((TOTAL_DURATION % 60))

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Summary${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "Total Duration: ${TOTAL_MINUTES}m ${TOTAL_SECONDS}s"
echo -e "${GREEN}Passed: ${SUCCESS_COUNT}${NC}"
echo -e "${RED}Failed: ${FAIL_COUNT}${NC}"
echo -e "${BLUE}========================================${NC}"

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}❌ Some tests failed${NC}"
    exit 1
fi

