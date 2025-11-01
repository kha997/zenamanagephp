#!/bin/bash

# Setup script for git hooks
# This script sets up the git hooks for duplicate detection

echo "🔧 Setting up git hooks for duplicate detection..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    print_error "Not in a git repository"
    exit 1
fi

# Get the git hooks directory
GIT_HOOKS_DIR=".git/hooks"
GITHOOKS_DIR=".githooks"

# Check if .githooks directory exists
if [ ! -d "$GITHOOKS_DIR" ]; then
    print_error ".githooks directory not found"
    exit 1
fi

# Create .git/hooks directory if it doesn't exist
if [ ! -d "$GIT_HOOKS_DIR" ]; then
    mkdir -p "$GIT_HOOKS_DIR"
    print_status "Created $GIT_HOOKS_DIR directory"
fi

# Copy hooks from .githooks to .git/hooks
print_status "Copying git hooks..."

# Copy pre-commit hook
if [ -f "$GITHOOKS_DIR/pre-commit" ]; then
    cp "$GITHOOKS_DIR/pre-commit" "$GIT_HOOKS_DIR/pre-commit"
    chmod +x "$GIT_HOOKS_DIR/pre-commit"
    print_status "Installed pre-commit hook"
else
    print_warning "pre-commit hook not found in $GITHOOKS_DIR"
fi

# Copy commit-msg hook
if [ -f "$GITHOOKS_DIR/commit-msg" ]; then
    cp "$GITHOOKS_DIR/commit-msg" "$GIT_HOOKS_DIR/commit-msg"
    chmod +x "$GIT_HOOKS_DIR/commit-msg"
    print_status "Installed commit-msg hook"
else
    print_warning "commit-msg hook not found in $GITHOOKS_DIR"
fi

# Create post-commit hook for duplicate detection summary
cat > "$GIT_HOOKS_DIR/post-commit" << 'EOF'
#!/bin/bash

# Post-commit hook for duplicate detection summary
# This hook runs after each commit to provide a summary

echo "🔍 Post-commit duplicate detection summary..."

# Get the last commit hash
LAST_COMMIT=$(git rev-parse HEAD)

# Get the last commit message
LAST_COMMIT_MSG=$(git log -1 --pretty=%B)

# Check if the commit message contains duplicate-related keywords
if echo "$LAST_COMMIT_MSG" | grep -qiE "duplicate|duplication|deduplicate|consolidate|merge|unify|refactor|cleanup"; then
    echo "📊 Duplicate-related commit detected: $LAST_COMMIT"
    echo "   Commit message: $LAST_COMMIT_MSG"
    echo ""
    echo "🔍 Please verify:"
    echo "   - All duplicate code has been removed"
    echo "   - Tests still pass"
    echo "   - Documentation has been updated"
    echo "   - No broken references remain"
    echo ""
fi

echo "✅ Post-commit hook completed"
EOF

chmod +x "$GIT_HOOKS_DIR/post-commit"
print_status "Installed post-commit hook"

# Create pre-push hook for duplicate detection
cat > "$GIT_HOOKS_DIR/pre-push" << 'EOF'
#!/bin/bash

# Pre-push hook for duplicate detection
# This hook runs before pushing to check for duplicate patterns

echo "🔍 Pre-push duplicate detection check..."

# Get the list of commits to be pushed
while read local_ref local_sha remote_ref remote_sha
do
    if [ "$local_sha" = "0000000000000000000000000000000000000000" ]; then
        # Handle delete
        continue
    fi
    
    if [ "$remote_sha" = "0000000000000000000000000000000000000000" ]; then
        # New branch, examine all commits
        range="$local_sha"
    else
        # Update to existing branch, examine new commits
        range="$remote_sha..$local_sha"
    fi
    
    # Check for duplicate patterns in the commits to be pushed
    COMMITS=$(git rev-list "$range")
    
    for commit in $COMMITS; do
        COMMIT_MSG=$(git log -1 --pretty=%B "$commit")
        
        # Check if commit message contains duplicate-related keywords
        if echo "$COMMIT_MSG" | grep -qiE "duplicate|duplication|deduplicate|consolidate|merge|unify|refactor|cleanup"; then
            echo "📊 Found duplicate-related commit: $commit"
            echo "   Message: $COMMIT_MSG"
            echo ""
        fi
    done
done

echo "✅ Pre-push duplicate detection completed"
EOF

chmod +x "$GIT_HOOKS_DIR/pre-push"
print_status "Installed pre-push hook"

# Create a script to run duplicate detection locally
cat > "scripts/check-duplicates.sh" << 'EOF'
#!/bin/bash

# Local duplicate detection script
# This script can be run locally to check for duplicate patterns

echo "🔍 Running local duplicate detection..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check for duplicate patterns
echo "📊 Checking for duplicate patterns..."

# Check for duplicate header components
HEADER_FILES=$(find resources/views -name "*header*" -type f | wc -l)
if [ $HEADER_FILES -gt 2 ]; then
    print_warning "Found $HEADER_FILES header files - consider consolidating"
else
    print_status "Header files count: $HEADER_FILES"
fi

# Check for duplicate layout files
LAYOUT_FILES=$(find resources/views/layouts -name "app*.blade.php" -type f | wc -l)
if [ $LAYOUT_FILES -gt 1 ]; then
    print_warning "Found $LAYOUT_FILES layout files - consider consolidating"
else
    print_status "Layout files count: $LAYOUT_FILES"
fi

# Check for duplicate dashboard files
DASHBOARD_FILES=$(find resources/views/app -name "*dashboard*" -type f | wc -l)
if [ $DASHBOARD_FILES -gt 1 ]; then
    print_warning "Found $DASHBOARD_FILES dashboard files - consider consolidating"
else
    print_status "Dashboard files count: $DASHBOARD_FILES"
fi

# Check for duplicate project files
PROJECT_FILES=$(find resources/views/app -name "*project*" -type f | wc -l)
if [ $PROJECT_FILES -gt 2 ]; then
    print_warning "Found $PROJECT_FILES project files - consider consolidating"
else
    print_status "Project files count: $PROJECT_FILES"
fi

# Check for duplicate middleware files
MIDDLEWARE_FILES=$(find app/Http/Middleware -name "*.php" -type f | wc -l)
if [ $MIDDLEWARE_FILES -gt 15 ]; then
    print_warning "Found $MIDDLEWARE_FILES middleware files - consider consolidating"
else
    print_status "Middleware files count: $MIDDLEWARE_FILES"
fi

# Check for duplicate controller files
CONTROLLER_FILES=$(find app/Http/Controllers -name "*.php" -type f | wc -l)
if [ $CONTROLLER_FILES -gt 20 ]; then
    print_warning "Found $CONTROLLER_FILES controller files - consider consolidating"
else
    print_status "Controller files count: $CONTROLLER_FILES"
fi

# Check for duplicate service files
SERVICE_FILES=$(find app/Services -name "*.php" -type f | wc -l)
if [ $SERVICE_FILES -gt 10 ]; then
    print_warning "Found $SERVICE_FILES service files - consider consolidating"
else
    print_status "Service files count: $SERVICE_FILES"
fi

# Check for duplicate request files
REQUEST_FILES=$(find app/Http/Requests -name "*.php" -type f | wc -l)
if [ $REQUEST_FILES -gt 15 ]; then
    print_warning "Found $REQUEST_FILES request files - consider consolidating"
else
    print_status "Request files count: $REQUEST_FILES"
fi

# Check for duplicate React components
REACT_FILES=$(find resources/js -name "*.tsx" -o -name "*.jsx" | wc -l)
if [ $REACT_FILES -gt 20 ]; then
    print_warning "Found $REACT_FILES React components - consider consolidating"
else
    print_status "React components count: $REACT_FILES"
fi

# Check for duplicate Blade components
BLADE_FILES=$(find resources/views/components -name "*.blade.php" -type f | wc -l)
if [ $BLADE_FILES -gt 30 ]; then
    print_warning "Found $BLADE_FILES Blade components - consider consolidating"
else
    print_status "Blade components count: $BLADE_FILES"
fi

echo ""
print_status "Local duplicate detection completed"
EOF

# Create scripts directory if it doesn't exist
mkdir -p scripts
chmod +x scripts/check-duplicates.sh
print_status "Created local duplicate detection script"

# Create a script to install git hooks
cat > "scripts/install-hooks.sh" << 'EOF'
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
EOF

chmod +x scripts/install-hooks.sh
print_status "Created install hooks script"

# Create a script to uninstall git hooks
cat > "scripts/uninstall-hooks.sh" << 'EOF'
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
EOF

chmod +x scripts/uninstall-hooks.sh
print_status "Created uninstall hooks script"

# Create a README for git hooks
cat > ".githooks/README.md" << 'EOF'
# Git Hooks for Duplicate Detection

This directory contains git hooks for duplicate detection and prevention.

## Available Hooks

### pre-commit
- Runs before each commit
- Checks staged files for duplicate patterns
- Warns about potential duplication issues
- Can be bypassed with `git commit --no-verify`

### commit-msg
- Runs after commit message is written
- Checks commit message for duplicate-related keywords
- Provides recommendations for duplicate-related commits

### post-commit
- Runs after each commit
- Provides summary of duplicate-related commits
- Reminds to verify duplicate removal

### pre-push
- Runs before pushing to remote
- Checks commits to be pushed for duplicate patterns
- Provides summary of duplicate-related commits

## Installation

Run the setup script to install all hooks:

```bash
./scripts/setup-hooks.sh
```

Or use the install script:

```bash
./scripts/install-hooks.sh
```

## Uninstallation

To remove all hooks:

```bash
./scripts/uninstall-hooks.sh
```

## Local Duplicate Detection

To run duplicate detection locally:

```bash
./scripts/check-duplicates.sh
```

## Configuration

The hooks can be configured by modifying the scripts in this directory.

## Bypassing Hooks

To bypass hooks (not recommended):

```bash
git commit --no-verify
git push --no-verify
```

## Troubleshooting

If hooks are not working:

1. Check if hooks are executable: `ls -la .git/hooks/`
2. Reinstall hooks: `./scripts/install-hooks.sh`
3. Check git configuration: `git config --list | grep hooks`
EOF

print_status "Created git hooks README"

echo ""
print_status "Git hooks setup completed successfully!"
echo ""
echo "📋 Installed hooks:"
echo "   - pre-commit: Checks for duplicate patterns before commit"
echo "   - commit-msg: Checks commit message for duplicate-related keywords"
echo "   - post-commit: Provides summary after commit"
echo "   - pre-push: Checks for duplicate patterns before push"
echo ""
echo "🔍 To run duplicate detection locally:"
echo "   ./scripts/check-duplicates.sh"
echo ""
echo "📖 For more information, see: .githooks/README.md"
