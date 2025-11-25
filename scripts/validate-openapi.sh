#!/bin/bash

# PR #4: Validate OpenAPI specification
# This script validates the OpenAPI spec and ensures types can be generated

set -e

OPENAPI_SPEC="docs/api/openapi.yaml"
FRONTEND_DIR="frontend"

echo "🔍 Validating OpenAPI specification..."

# Check if OpenAPI spec exists
if [ ! -f "$OPENAPI_SPEC" ]; then
  echo "❌ OpenAPI spec not found at: $OPENAPI_SPEC"
  exit 1
fi

# Check if openapi-typescript is available
if ! command -v npx &> /dev/null; then
  echo "❌ npx not found. Please install Node.js and npm."
  exit 1
fi

# Validate OpenAPI spec using openapi-typescript (it will fail if spec is invalid)
echo "📋 Validating OpenAPI spec structure..."
cd "$FRONTEND_DIR"

if npm run gen:api > /dev/null 2>&1; then
  echo "✅ OpenAPI spec is valid"
else
  echo "❌ OpenAPI spec validation failed"
  exit 1
fi

# Check if generated types file exists
if [ ! -f "src/shared/types/api.d.ts" ]; then
  echo "❌ Generated types file not found"
  exit 1
fi

echo "✅ OpenAPI validation passed"
echo "✅ Type generation successful"

