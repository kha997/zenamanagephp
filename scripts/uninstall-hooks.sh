#!/bin/bash

# Uninstall git hooks script
# This script can be run to uninstall git hooks

echo "🔧 Uninstalling git hooks..."

# Remove hooks from .git/hooks
rm -f .git/hooks/pre-commit
rm -f .git/hooks/commit-msg
rm -f .git/hooks/post-commit
rm -f .git/hooks/pre-push

echo "✅ Git hooks uninstalled successfully"
