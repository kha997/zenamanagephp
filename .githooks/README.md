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
