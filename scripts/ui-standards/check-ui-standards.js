#!/usr/bin/env node
/**
 * UI guardrail script:
 * 1. Fails when Blade templates render raw <a> tags inside <nav> blocks (must use PrimaryNav).
 * 2. Ensures changed React components carry either a spec or a story.
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '../..');
process.chdir(repoRoot);

const CORE_COMPONENT_DIRS = [
  'src/components',
  'frontend/src/components',
  'resources/views/components',
];

function run(cmd) {
  return execSync(cmd, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
}

function safeRun(cmd) {
  try {
    return run(cmd);
  } catch (error) {
    return '';
  }
}

function getChangedFiles() {
  const envTargets = [
    process.env.GITHUB_BASE_REF && `origin/${process.env.GITHUB_BASE_REF}`,
    process.env.GITHUB_BASE_SHA,
    process.env.UI_STANDARDS_BASE,
    'origin/main',
  ].filter(Boolean);

  for (const target of envTargets) {
    try {
      safeRun(`git fetch ${target.split('/')[0]} ${target.split('/')[1] || ''}`);
    } catch (_) {
      // ignore fetch errors
    }
    const diff = safeRun(`git diff --name-only ${target}...HEAD`);
    if (diff) {
      return diff.split('\n').filter(Boolean);
    }
  }

  const fallback = safeRun('git diff --name-only HEAD~1');
  return fallback ? fallback.split('\n').filter(Boolean) : [];
}

function isComponentFile(filePath) {
  return CORE_COMPONENT_DIRS.some(dir => filePath.startsWith(dir))
    && /\.(tsx|ts|jsx)$/.test(filePath)
    && !/(__tests__|\.spec\.|\.test\.|\.stories\.)/.test(filePath);
}

function hasCompanionArtifact(componentName) {
  const patterns = [
    `-g "*${componentName}.spec.tsx"`,
    `-g "*${componentName}.test.tsx"`,
    `-g "*${componentName}.spec.ts"`,
    `-g "*${componentName}.stories.tsx"`,
    `-g "*${componentName}.stories.ts"`,
  ];
  for (const pattern of patterns) {
    const result = safeRun(`rg --files ${pattern}`);
    if (result) {
      return true;
    }
  }
  return false;
}

function ensureComponentCoverage(changedFiles) {
  const errors = [];
  changedFiles.filter(isComponentFile).forEach(filePath => {
    const componentName = path.basename(filePath).replace(/\.(tsx|ts|jsx)$/, '');
    if (!hasCompanionArtifact(componentName)) {
      errors.push(
        `Missing spec/story for ${filePath}. Add ${componentName}.spec.tsx or ${componentName}.stories.tsx.`,
      );
    }
  });
  return errors;
}

function bladeFiles() {
  const files = safeRun('rg --files -g "*.blade.php" resources/views');
  return files ? files.split('\n').filter(Boolean) : [];
}

function scanBladeForRawAnchors() {
  const violations = [];
  bladeFiles().forEach(file => {
    const contents = fs.readFileSync(file, 'utf8');
    const navBlocks = contents.match(/<nav[\s\S]*?<\/nav>/gi) || [];
    navBlocks.forEach(block => {
      const hasAnchor = /<a\s(?![^>]*data-allow-nav-anchor)/i.test(block);
      if (hasAnchor) {
        violations.push(`${file}: <a> detected inside <nav>. Use PrimaryNavLink or add data-allow-nav-anchor="true".`);
      }
    });
  });
  return violations;
}

function main() {
  const errors = [];
  const changedFiles = getChangedFiles();
  errors.push(...ensureComponentCoverage(changedFiles));
  errors.push(...scanBladeForRawAnchors());

  if (errors.length) {
    console.error('❌ UI standards check failed:\n');
    errors.forEach(err => console.error(` - ${err}`));
    console.error('\nSet UI_STANDARDS_BASE=<ref> to compare against a custom base.');
    process.exit(1);
  }

  console.log('✅ UI standards guardrails passed.');
}

main();
