#!/bin/bash

# Navigation Feature Flag Toggle Script
# Usage: ./toggle-nav-flag.sh [on|off|status]
#   on     - Enable new navigation (AppNavigator)
#   off    - Disable new navigation (use PrimaryNavigator)
#   status - Show current status

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_LOCAL_FILE="$SCRIPT_DIR/.env.local"
ENV_FILE="$SCRIPT_DIR/.env"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to check if flag is enabled
check_status() {
    local value=""
    if [ -f "$ENV_LOCAL_FILE" ]; then
        value=$(grep -E "^VITE_USE_NEW_NAV=" "$ENV_LOCAL_FILE" | cut -d '=' -f2 || echo "")
    fi
    
    if [ -z "$value" ] && [ -f "$ENV_FILE" ]; then
        value=$(grep -E "^VITE_USE_NEW_NAV=" "$ENV_FILE" | cut -d '=' -f2 || echo "")
    fi
    
    if [ "$value" = "true" ] || [ "$value" = "1" ]; then
        echo "true"
    else
        echo "false"
    fi
}

# Function to set flag value
set_flag() {
    local value=$1
    local file_to_use=""
    
    # Use .env.local if exists, otherwise use .env
    if [ -f "$ENV_LOCAL_FILE" ]; then
        file_to_use="$ENV_LOCAL_FILE"
    elif [ -f "$ENV_FILE" ]; then
        file_to_use="$ENV_FILE"
    else
        # Create .env.local if neither exists
        file_to_use="$ENV_LOCAL_FILE"
        touch "$file_to_use"
    fi
    
    # Remove existing VITE_USE_NEW_NAV line if exists
    if grep -q "^VITE_USE_NEW_NAV=" "$file_to_use" 2>/dev/null; then
        sed -i.bak "s/^VITE_USE_NEW_NAV=.*/VITE_USE_NEW_NAV=$value/" "$file_to_use"
        rm -f "${file_to_use}.bak"
    else
        # Add to end of file
        echo "VITE_USE_NEW_NAV=$value" >> "$file_to_use"
    fi
    
    echo "$value"
}

# Function to show status
show_status() {
    local status=$(check_status)
    if [ "$status" = "true" ]; then
        echo -e "${GREEN}✓ Navigation feature flag is ENABLED${NC}"
        echo "   Using: AppNavigator (new, text-only, full dark mode)"
    else
        echo -e "${YELLOW}○ Navigation feature flag is DISABLED${NC}"
        echo "   Using: PrimaryNavigator (legacy)"
    fi
    echo ""
    echo "To toggle: ./toggle-nav-flag.sh [on|off]"
}

# Main script logic
case "${1:-status}" in
    on|enable|true|1)
        set_flag "true"
        echo -e "${GREEN}✓ Feature flag enabled${NC}"
        echo "   New navigation (AppNavigator) will be used"
        echo ""
        echo "Next steps:"
        echo "  1. Run: npm run build"
        echo "  2. Clear browser cache"
        echo "  3. Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)"
        ;;
    off|disable|false|0)
        set_flag "false"
        echo -e "${YELLOW}○ Feature flag disabled${NC}"
        echo "   Legacy navigation (PrimaryNavigator) will be used"
        echo ""
        echo "Next steps:"
        echo "  1. Run: npm run build"
        echo "  2. Clear browser cache"
        echo "  3. Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)"
        ;;
    status|check)
        show_status
        ;;
    *)
        echo "Usage: $0 [on|off|status]"
        echo ""
        echo "Commands:"
        echo "  on     - Enable new navigation (AppNavigator)"
        echo "  off    - Disable new navigation (use PrimaryNavigator)"
        echo "  status - Show current status (default)"
        echo ""
        show_status
        exit 1
        ;;
esac

