#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p storage/app/architecture

{
    echo "# Project-related class references (generated $(date -u +%Y-%m-%dT%H:%M:%SZ))"
    echo ""
    echo "## App\\Models\\Project"
    grep -rl 'App\\Models\\Project\b' app/ src/ --include="*.php" | sort
    echo ""
    echo "## App\\Models\\ZenaProject"
    grep -rl '\bZenaProject\b' app/ src/ database/ tests/ --include="*.php" | sort
    echo ""
    echo "## Src\\CoreProject\\Models\\Project"
    grep -rl 'Src\\CoreProject\\Models\\Project\b' app/ src/ --include="*.php" | sort
    echo ""
    echo "## Src\\CoreProject\\Models\\LegacyProjectAdapter"
    grep -rl 'LegacyProjectAdapter' app/ src/ --include="*.php" | sort
} > storage/app/architecture/project-model-references.md

echo "Project model reference dump written to storage/app/architecture/project-model-references.md"
