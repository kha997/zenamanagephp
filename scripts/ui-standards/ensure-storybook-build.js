#!/usr/bin/env node
/**
 * Runs the Storybook build if the frontend workspace defines it.
 * Skips (with a warning) when Storybook is not configured yet.
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '../..');
const frontendPackagePath = path.join(repoRoot, 'frontend', 'package.json');
const storybookDir = path.join(repoRoot, 'frontend', '.storybook');

function run(cmd, options = {}) {
  execSync(cmd, { stdio: 'inherit', ...options });
}

function main() {
  if (!fs.existsSync(frontendPackagePath)) {
    console.log('⚠️  frontend workspace not found. Skipping Storybook build.');
    return;
  }

  const pkg = JSON.parse(fs.readFileSync(frontendPackagePath, 'utf8'));
  const hasScript = pkg.scripts && (pkg.scripts['build-storybook'] || pkg.scripts['storybook:build']);
  const hasConfig = fs.existsSync(storybookDir);

  if (hasScript && hasConfig) {
    const scriptName = pkg.scripts['build-storybook'] ? 'build-storybook' : 'storybook:build';
    console.log(`ℹ️  Running frontend ${scriptName} to ensure Storybook build succeeds...`);
    run(`npm run ${scriptName}`, { cwd: path.join(repoRoot, 'frontend') });
    return;
  }

  console.log('⚠️  Storybook is not configured in frontend/. Add .storybook and a build script to enable the guard.');
}

main();
