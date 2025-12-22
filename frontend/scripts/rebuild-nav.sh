#!/bin/bash

# Navigation Rebuild Script
# Rebuilds frontend with navigation feature flag awareness
# Usage: ./rebuild-nav.sh [clean]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRONTEND_DIR="$(dirname "$SCRIPT_DIR")"
ENV_LOCAL_FILE="$FRONTEND_DIR/.env.local"
ENV_FILE="$FRONTEND_DIR/.env"
ENV_DEV_FILE="$FRONTEND_DIR/env.development"
ENV_PROD_FILE="$FRONTEND_DIR/env.production"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to check if flag is enabled
check_status() {
    local value=""
    # Check .env.local first (highest priority)
    if [ -f "$ENV_LOCAL_FILE" ]; then
        value=$(grep -E "^VITE_USE_NEW_NAV=" "$ENV_LOCAL_FILE" | cut -d '=' -f2 | tr -d '"' | tr -d "'" || echo "")
    fi
    
    # Check .env if .env.local doesn't have it
    if [ -z "$value" ] && [ -f "$ENV_FILE" ]; then
        value=$(grep -E "^VITE_USE_NEW_NAV=" "$ENV_FILE" | cut -d '=' -f2 | tr -d '"' | tr -d "'" || echo "")
    fi
    
    # Check env.development as fallback
    if [ -z "$value" ] && [ -f "$ENV_DEV_FILE" ]; then
        value=$(grep -E "^VITE_USE_NEW_NAV=" "$ENV_DEV_FILE" | cut -d '=' -f2 | tr -d '"' | tr -d "'" || echo "")
    fi
    
    if [ "$value" = "true" ] || [ "$value" = "1" ]; then
        echo "true"
    else
        echo "false"
    fi
}

# Show current status
echo -e "${BLUE}Navigation Feature Flag Status:${NC}"
status=$(check_status)
if [ "$status" = "true" ]; then
    echo -e "  ${GREEN}✓ ENABLED${NC} - Using AppNavigator (new)"
else
    echo -e "  ${YELLOW}○ DISABLED${NC} - Using PrimaryNavigator (legacy)"
fi
echo ""

# Clean if requested
if [ "$1" = "clean" ]; then
    echo -e "${BLUE}Cleaning build artifacts...${NC}"
    cd "$FRONTEND_DIR"
    rm -rf node_modules/.vite
    rm -rf dist
    rm -rf build
    echo -e "${GREEN}✓ Clean complete${NC}"
    echo ""
fi

# Change to frontend directory
cd "$FRONTEND_DIR"

# Build
echo -e "${BLUE}Building frontend...${NC}"
npm run build

echo ""
echo -e "${GREEN}✓ Build complete!${NC}"
echo ""
echo "Next steps:"
echo "  1. Clear browser cache"
echo "  2. Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)"
echo "  3. Check DevTools to verify navigation component"
if [ "$status" = "true" ]; then
    echo ""
    echo -e "${YELLOW}Note:${NC} New navigation is enabled. Look for:"
    echo "  - data-source='react-new' in <nav> element"
    echo "  - No icons in navigation items"
    echo "  - Dark mode working correctly"
fi

