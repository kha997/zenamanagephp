#!/bin/bash

# Validate OpenAPI Specification
# This script validates the OpenAPI spec and checks for breaking changes

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SPEC_FILE="$PROJECT_ROOT/docs/api/openapi.yaml"
SPEC_JSON="$PROJECT_ROOT/docs/api/openapi.json"

echo "🔍 Validating OpenAPI specification..."

# Check if spec file exists
if [ ! -f "$SPEC_FILE" ] && [ ! -f "$SPEC_JSON" ]; then
    echo "❌ Error: OpenAPI spec not found at $SPEC_FILE or $SPEC_JSON"
    echo "   Run: php artisan openapi:generate --copy-to-docs"
    exit 1
fi

# Use JSON file if YAML doesn't exist
if [ ! -f "$SPEC_FILE" ] && [ -f "$SPEC_JSON" ]; then
    echo "⚠️  YAML spec not found, using JSON spec for validation"
    SPEC_FILE="$SPEC_JSON"
fi

# Check if openapi-cli is installed (from @redocly/cli or swagger-cli)
if command -v openapi &> /dev/null; then
    echo "✅ Using openapi-cli for validation..."
    openapi lint "$SPEC_FILE"
elif command -v swagger-cli &> /dev/null; then
    echo "✅ Using swagger-cli for validation..."
    swagger-cli validate "$SPEC_FILE"
elif command -v npx &> /dev/null; then
    echo "✅ Using @redocly/cli via npx for validation..."
    npx @redocly/cli lint "$SPEC_FILE" || {
        echo "⚠️  @redocly/cli lint failed, trying basic validation..."
        npx @redocly/cli validate "$SPEC_FILE"
    }
else
    echo "⚠️  No OpenAPI validator found. Installing @redocly/cli..."
    npm install -g @redocly/cli || {
        echo "❌ Failed to install @redocly/cli"
        echo "   Please install manually: npm install -g @redocly/cli"
        exit 1
    }
    npx @redocly/cli validate "$SPEC_FILE"
fi

# Check for breaking changes (compare with previous version if available)
if [ -n "$CI" ] && [ -d "$PROJECT_ROOT/.git" ]; then
    echo ""
    echo "🔍 Checking for breaking changes..."
    
    # Get previous version from git
    if git rev-parse HEAD~1 &> /dev/null; then
        PREV_SPEC=$(git show HEAD~1:docs/api/openapi.yaml 2>/dev/null || echo "")
        
        if [ -n "$PREV_SPEC" ]; then
            # Create temp file for previous spec
            PREV_TEMP=$(mktemp)
            echo "$PREV_SPEC" > "$PREV_TEMP"
            
            # Check version bump
            CURRENT_VERSION=$(grep -E "^\s*version:" "$SPEC_FILE" | head -1 | sed 's/.*version:\s*\(.*\)/\1/' | tr -d '"' | tr -d "'")
            PREV_VERSION=$(grep -E "^\s*version:" "$PREV_TEMP" | head -1 | sed 's/.*version:\s*\(.*\)/\1/' | tr -d '"' | tr -d "'")
            
            if [ -n "$CURRENT_VERSION" ] && [ -n "$PREV_VERSION" ]; then
                echo "   Previous version: $PREV_VERSION"
                echo "   Current version: $CURRENT_VERSION"
                
                # Simple version comparison (assumes semantic versioning)
                if [ "$CURRENT_VERSION" = "$PREV_VERSION" ]; then
                    echo "⚠️  Warning: Version not bumped. Breaking changes require version bump."
                    echo "   If you made breaking changes, bump the version in openapi.yaml"
                fi
            fi
            
            rm -f "$PREV_TEMP"
        fi
    fi
fi

echo ""
echo "✅ OpenAPI specification is valid!"

