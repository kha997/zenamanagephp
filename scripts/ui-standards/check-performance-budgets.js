#!/usr/bin/env node
/**
 * Reads performance-budgets.json and ensures compiled bundles stay within thresholds.
 */

const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '../..');
const configPath = path.join(repoRoot, 'performance-budgets.json');

if (!fs.existsSync(configPath)) {
  console.warn('⚠️  No performance-budgets.json found. Skipping bundle size checks.');
  process.exit(0);
}

const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));

function patternToRegex(pattern) {
  const escaped = pattern.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
  return new RegExp(`^${escaped}$`);
}

function checkTarget(target) {
  const dir = path.join(repoRoot, target.directory);
  if (!fs.existsSync(dir)) {
    return [`${target.label}: directory "${target.directory}" not found. Run the relevant build first.`];
  }

  const regex = patternToRegex(target.pattern);
  const entries = fs.readdirSync(dir).filter(file => regex.test(file));
  if (entries.length === 0) {
    return [`${target.label}: pattern "${target.pattern}" matched no files in ${target.directory}.`];
  }

  const violations = [];
  entries.forEach(entry => {
    const filePath = path.join(dir, entry);
    const stats = fs.statSync(filePath);
    const sizeKB = stats.size / 1024;
    if (sizeKB > target.maxKB) {
      violations.push(
        `${target.label}: ${entry} is ${sizeKB.toFixed(1)}KB (budget ${target.maxKB}KB).`,
      );
    }
  });
  return violations;
}

function main() {
  const failures = [];
  config.targets.forEach(target => {
    failures.push(...checkTarget(target));
  });

  if (failures.length) {
    console.error('❌ Performance budgets violated:\n');
    failures.forEach(line => console.error(` - ${line}`));
    console.error(
      '\nRebuild assets (npm run build && npm run --prefix frontend build) before re-running.',
    );
    process.exit(1);
  }

  console.log('✅ Performance budgets are within limits.');
}

main();
