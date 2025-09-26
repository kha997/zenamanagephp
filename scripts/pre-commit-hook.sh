#!/bin/bash

# Git Pre-commit Hook để kiểm tra duplicate imports
# 
# Cài đặt:
# 1. Copy file này vào .git/hooks/pre-commit
# 2. chmod +x .git/hooks/pre-commit

echo "🔍 Kiểm tra duplicate imports..."

# Chạy script kiểm tra duplicate imports
php scripts/pre-commit-duplicate-check.php

# Lưu exit code
EXIT_CODE=$?

if [ $EXIT_CODE -ne 0 ]; then
    echo ""
    echo "❌ Pre-commit hook failed!"
    echo "💡 Hãy sửa các duplicate imports trước khi commit."
    exit 1
fi

echo "✅ Pre-commit checks passed!"
exit 0
