#!/bin/bash
# Creates a work package file for a domain

DOMAIN=$1
SEED=$2

if [ -z "$DOMAIN" ] || [ -z "$SEED" ]; then
    echo "Usage: ./scripts/create-work-package.sh <domain> <seed>"
    echo "Example: ./scripts/create-work-package.sh auth 12345"
    exit 1
fi

DOMAIN_CAPITALIZED=$(echo "$DOMAIN" | sed 's/^./\U&/')

cat > "docs/work-packages/${DOMAIN}-domain.md" <<EOF
# ${DOMAIN_CAPITALIZED} Domain Test Organization

**Package ID:** [Auto-assigned]  
**Domain:** ${DOMAIN}  
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** ${SEED}  
**Branch:** \`test-org/${DOMAIN}-domain\`

## Overview

Organize all ${DOMAIN}-related tests into a cohesive test group with reproducible seed data.

## Tasks Checklist

### Phase 1: PHPUnit Groups
- [ ] Add \`@group ${DOMAIN}\` annotation to all ${DOMAIN} tests
- [ ] Verify annotations: \`grep -r "@group ${DOMAIN}" tests/\`

### Phase 2: Test Suites
- [ ] Add \`${DOMAIN}-unit\` test suite to \`phpunit.xml\`
- [ ] Add \`${DOMAIN}-feature\` test suite
- [ ] Add \`${DOMAIN}-integration\` test suite
- [ ] Verify: \`php artisan test --testsuite=${DOMAIN}-feature\`

### Phase 3: Test Data Seeding
- [ ] Add \`TestDataSeeder::seed${DOMAIN_CAPITALIZED}Domain(\$seed = ${SEED})\` method
- [ ] Use fixed seed for reproducibility
- [ ] Verify: \`php artisan test --group=${DOMAIN} --seed=${SEED}\`

### Phase 4: Fixtures
- [ ] Create \`tests/fixtures/domains/${DOMAIN}/fixtures.json\`
- [ ] Use standard test data structure

### Phase 5: Playwright Projects
- [ ] Add \`${DOMAIN}-e2e-chromium\` project to \`playwright.config.ts\`
- [ ] Verify: \`npm run test:${DOMAIN}:e2e\`

### Phase 6: NPM Scripts
- [ ] Add scripts to \`package.json\`:
  - \`test:${DOMAIN}\`
  - \`test:${DOMAIN}:unit\`
  - \`test:${DOMAIN}:feature\`
  - \`test:${DOMAIN}:integration\`
  - \`test:${DOMAIN}:e2e\`
- [ ] Verify all scripts work

## Files to Modify

### Add @group annotations:
- \`tests/Feature/**/${DOMAIN}*.php\`
- \`tests/Unit/**/${DOMAIN}*.php\`
- \`tests/Integration/**/${DOMAIN}*.php\`

### Modify configuration:
- \`phpunit.xml\` - Add test suites
- \`tests/Helpers/TestDataSeeder.php\` - Add seed method
- \`playwright.config.ts\` - Add project
- \`package.json\` - Add scripts

## Verification Commands

\`\`\`bash
# 1. Check annotations
grep -r "@group ${DOMAIN}" tests/

# 2. Run test suite
php artisan test --testsuite=${DOMAIN}-feature

# 3. Verify reproducibility
php artisan test --group=${DOMAIN} --seed=${SEED} > /tmp/${DOMAIN}-test1.log
php artisan test --group=${DOMAIN} --seed=${SEED} > /tmp/${DOMAIN}-test2.log
diff /tmp/${DOMAIN}-test1.log /tmp/${DOMAIN}-test2.log
# Should output nothing (identical results)

# 4. Test NPM scripts
npm run test:${DOMAIN}
npm run test:${DOMAIN}:unit
npm run test:${DOMAIN}:feature
npm run test:${DOMAIN}:e2e
\`\`\`

## Completion Criteria

✅ All ${DOMAIN} tests have \`@group ${DOMAIN}\` annotation  
✅ Test suites exist and work  
✅ \`TestDataSeeder::seed${DOMAIN_CAPITALIZED}Domain()\` method exists with fixed seed  
✅ Fixtures file created  
✅ Playwright project added  
✅ NPM scripts added and working  
✅ Reproducibility verified  
✅ Documentation updated
EOF

echo "Created work package: docs/work-packages/${DOMAIN}-domain.md"
echo "Seed: ${SEED}"

