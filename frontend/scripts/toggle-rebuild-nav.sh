#!/bin/bash

# Complete Navigation Toggle & Rebuild Script
# Toggles feature flag and rebuilds frontend
# Usage: ./toggle-rebuild-nav.sh [on|off]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRONTEND_DIR="$(dirname "$SCRIPT_DIR")"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Run toggle script first
echo -e "${BLUE}Step 1: Toggling feature flag...${NC}"
"$SCRIPT_DIR/toggle-nav-flag.sh" "${1:-status}"

# Check if we actually toggled (not just status)
if [ "$1" = "on" ] || [ "$1" = "off" ] || [ "$1" = "enable" ] || [ "$1" = "disable" ]; then
    echo ""
    echo -e "${BLUE}Step 2: Rebuilding frontend...${NC}"
    "$SCRIPT_DIR/rebuild-nav.sh"
else
    echo ""
    echo "To rebuild after toggling, run:"
    echo "  ./scripts/rebuild-nav.sh"
    echo ""
    echo "Or use this script with on/off:"
    echo "  ./scripts/toggle-rebuild-nav.sh on"
    echo "  ./scripts/toggle-rebuild-nav.sh off"
fi

