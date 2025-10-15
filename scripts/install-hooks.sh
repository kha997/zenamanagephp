#!/bin/bash

# Install git hooks script
# This script can be run to install git hooks

echo "🔧 Installing git hooks..."

# Run the setup script
./scripts/setup-hooks.sh

echo "✅ Git hooks installed successfully"
echo ""
echo "📋 Available hooks:"
echo "   - pre-commit: Checks for duplicate patterns before commit"
echo "   - commit-msg: Checks commit message for duplicate-related keywords"
echo "   - post-commit: Provides summary after commit"
echo "   - pre-push: Checks for duplicate patterns before push"
echo ""
echo "🔍 To run duplicate detection locally:"
echo "   ./scripts/check-duplicates.sh"
