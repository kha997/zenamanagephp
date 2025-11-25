# Rollback Procedures for Domain Test Organization

**Date:** 2025-11-08  
**Purpose:** Clear rollback procedures in case of issues during or after integration  
**Status:** Ready for use

---

## Overview

This document provides step-by-step rollback procedures for the domain test organization changes. Use these procedures if critical issues are discovered during or after integration.

---

## When to Rollback

Rollback should be considered if:

- ✅ Critical bugs are introduced that break existing functionality
- ✅ Test suite execution fails completely
- ✅ CI pipeline fails consistently
- ✅ Data integrity issues are discovered
- ✅ Performance regressions are significant
- ✅ Security vulnerabilities are introduced

**Do NOT rollback for:**
- ❌ Minor test failures (fix instead)
- ❌ Documentation issues (fix instead)
- ❌ Non-critical warnings (fix instead)
- ❌ Expected test failures during migration (fix instead)

---

## Rollback Decision Matrix

| Issue Type | Severity | Action |
|------------|----------|--------|
| Critical bug in production | High | Immediate rollback |
| Test suite completely broken | High | Immediate rollback |
| CI pipeline consistently failing | High | Immediate rollback |
| Data integrity issues | High | Immediate rollback |
| Security vulnerability | Critical | Immediate rollback |
| Minor test failures | Low | Fix instead |
| Documentation issues | Low | Fix instead |
| Performance regression < 10% | Medium | Fix instead |
| Performance regression > 10% | High | Consider rollback |

---

## Rollback Procedures

### Procedure 1: Rollback Integration Branch

**Use when:** Integration branch has issues before merging to main.

**Steps:**

1. **Stop Integration Process**
   ```bash
   # Document the issue
   git log --oneline -5 > /tmp/integration-issues.log
   ```

2. **Create Rollback Branch**
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b test-org/rollback-$(date +%Y%m%d)
   ```

3. **Revert Integration Commits**
   ```bash
   # Find integration commit
   git log --oneline | grep -i "integration\|test-org"
   
   # Revert specific commit
   git revert <commit-hash>
   
   # Or revert merge commit
   git revert -m 1 <merge-commit-hash>
   ```

4. **Verify Rollback**
   ```bash
   # Run tests to verify
   php artisan test
   
   # Check CI workflow
   git diff develop .github/workflows/ci.yml
   ```

5. **Push Rollback Branch**
   ```bash
   git push origin test-org/rollback-$(date +%Y%m%d)
   ```

6. **Create PR for Rollback**
   - Create PR from rollback branch to develop
   - Document reason for rollback
   - Link to issue/incident report

---

### Procedure 2: Rollback After Merge to Develop

**Use when:** Issues discovered after merging to develop branch.

**Steps:**

1. **Identify Problematic Commits**
   ```bash
   git log --oneline --grep="test-org\|domain\|DomainTestIsolation" -20
   ```

2. **Create Hotfix Branch**
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b hotfix/rollback-test-org-$(date +%Y%m%d)
   ```

3. **Revert Problematic Commits**
   ```bash
   # Revert in reverse order (newest first)
   git revert <commit-hash-3>
   git revert <commit-hash-2>
   git revert <commit-hash-1>
   ```

4. **Test Rollback**
   ```bash
   # Run full test suite
   php artisan test
   
   # Verify CI workflow
   git diff develop .github/workflows/ci.yml
   ```

5. **Push and Create PR**
   ```bash
   git push origin hotfix/rollback-test-org-$(date +%Y%m%d)
   # Create PR to develop
   ```

---

### Procedure 3: Partial Rollback (Selective Revert)

**Use when:** Only specific components need to be rolled back.

**Steps:**

1. **Identify Components to Rollback**
   - Core Infrastructure only?
   - Specific domain support materials?
   - CI workflow changes only?
   - Documentation only?

2. **Create Selective Rollback Branch**
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b hotfix/selective-rollback-$(date +%Y%m%d)
   ```

3. **Revert Specific Files**
   ```bash
   # Example: Rollback only CI workflow
   git checkout develop -- .github/workflows/ci.yml
   git commit -m "Rollback: Revert CI workflow changes"
   
   # Example: Rollback only DomainTestIsolation trait
   git rm tests/Traits/DomainTestIsolation.php
   git checkout develop -- tests/Traits/DomainTestIsolation.php
   git commit -m "Rollback: Revert DomainTestIsolation trait"
   ```

4. **Update phpunit.xml**
   ```bash
   # Remove domain test suites if rolling back Core Infrastructure
   # Edit phpunit.xml to remove domain-specific test suites
   git add phpunit.xml
   git commit -m "Rollback: Remove domain test suites"
   ```

5. **Test Partial Rollback**
   ```bash
   php artisan test
   ```

6. **Push and Create PR**
   ```bash
   git push origin hotfix/selective-rollback-$(date +%Y%m%d)
   ```

---

### Procedure 4: Database Rollback

**Use when:** Database schema changes need to be rolled back.

**Steps:**

1. **Identify Migration Files**
   ```bash
   ls -la database/migrations/*test*org*
   ls -la database/migrations/*domain*
   ```

2. **Rollback Migrations**
   ```bash
   # Rollback specific migration
   php artisan migrate:rollback --step=1
   
   # Or rollback to specific migration
   php artisan migrate:rollback --path=database/migrations/YYYY_MM_DD_HHMMSS_create_domain_test_org_table.php
   ```

3. **Verify Database State**
   ```bash
   php artisan migrate:status
   ```

4. **Update Code**
   - Remove code that depends on rolled-back migrations
   - Update tests that use rolled-back schema

---

## Component-Specific Rollback

### Rollback Core Infrastructure

**Files to revert:**
- `tests/Traits/DomainTestIsolation.php`
- `phpunit.xml` (domain test suites section)
- `.github/workflows/ci.yml` (matrix strategy section)
- `scripts/aggregate-test-results.sh`

**Steps:**
```bash
git checkout develop -- tests/Traits/DomainTestIsolation.php
git checkout develop -- phpunit.xml
git checkout develop -- .github/workflows/ci.yml
git checkout develop -- scripts/aggregate-test-results.sh
git commit -m "Rollback: Revert Core Infrastructure changes"
```

---

### Rollback Domain Support Materials

**Files to revert:**
- `docs/work-packages/*-domain-*.md` (all domain work packages)
- `tests/Helpers/TestDataSeeder.php` (domain seed methods)

**Steps:**
```bash
# Remove domain work packages
git rm docs/work-packages/*-domain-*.md
git commit -m "Rollback: Remove domain work packages"

# Revert TestDataSeeder changes
git checkout develop -- tests/Helpers/TestDataSeeder.php
git commit -m "Rollback: Revert domain seed methods"
```

---

### Rollback Documentation

**Files to revert:**
- `docs/TEST_GROUPS.md`
- `docs/TEST_ORGANIZATION_MIGRATION_GUIDE.md`
- `docs/TEST_ORGANIZATION_BEST_PRACTICES.md`
- `docs/INFRASTRUCTURE_VALIDATION_REPORT.md`
- `DOCUMENTATION_INDEX.md` (Domain Test Organization section)
- `TEST_SUITE_SUMMARY.md` (Domain Test Organization section)

**Steps:**
```bash
git rm docs/TEST_GROUPS.md
git rm docs/TEST_ORGANIZATION_MIGRATION_GUIDE.md
git rm docs/TEST_ORGANIZATION_BEST_PRACTICES.md
git checkout develop -- DOCUMENTATION_INDEX.md
git checkout develop -- TEST_SUITE_SUMMARY.md
git commit -m "Rollback: Revert test organization documentation"
```

---

## Verification After Rollback

### Step 1: Verify Code State

```bash
# Check git status
git status

# Verify no test-org files remain
git ls-files | grep -i "test-org\|domain-test"
```

### Step 2: Run Tests

```bash
# Run full test suite
php artisan test

# Verify no test-org related tests fail
php artisan test --filter DomainTestIsolation
```

### Step 3: Verify CI Workflow

```bash
# Check CI workflow syntax
yamllint .github/workflows/ci.yml

# Verify no domain-test jobs remain
grep -i "domain-test" .github/workflows/ci.yml
```

### Step 4: Verify Database

```bash
# Check migration status
php artisan migrate:status

# Verify no test-org tables exist
php artisan tinker
>>> Schema::hasTable('domain_test_org');
=> false
```

---

## Communication During Rollback

### Step 1: Notify Team

- [ ] Create issue/incident report
- [ ] Notify team via Slack/email
- [ ] Update status in project management tool

### Step 2: Update Documentation

- [ ] Document rollback reason
- [ ] Document rollback steps taken
- [ ] Update `docs/AGENT_COORDINATION_HUB.md`
- [ ] Update `docs/AGENT_STATUS_REPORTS.md`

### Step 3: Update Agents

- [ ] Notify Continue Agent (if working on domain tests)
- [ ] Notify Codex Agent (if reviewing)
- [ ] Clear file locks
- [ ] Update task board

---

## Post-Rollback Actions

### Step 1: Root Cause Analysis

- [ ] Identify root cause of issue
- [ ] Document findings
- [ ] Create fix plan

### Step 2: Fix Issues

- [ ] Fix root cause
- [ ] Re-test fixes
- [ ] Verify no regressions

### Step 3: Re-attempt Integration

- [ ] Create new integration branch
- [ ] Apply fixes
- [ ] Re-run integration checklist
- [ ] Monitor closely

---

## Prevention Measures

To prevent future rollbacks:

1. **Thorough Testing**
   - Run full test suite before integration
   - Test CI workflow locally if possible
   - Verify reproducibility

2. **Code Review**
   - Review all changes before integration
   - Check for syntax errors
   - Verify best practices

3. **Staged Integration**
   - Integrate components incrementally
   - Test after each integration step
   - Monitor for issues

4. **Documentation**
   - Keep documentation up to date
   - Document all changes
   - Document known issues

---

## Emergency Contacts

If critical issues are discovered:

1. **Immediate Actions**
   - Stop integration process
   - Document the issue
   - Notify team lead

2. **Escalation**
   - Escalate to project manager if needed
   - Escalate to technical lead if needed
   - Create incident report

---

## Additional Resources

- **Integration Checklist:** `docs/DOMAIN_INTEGRATION_CHECKLIST.md`
- **Infrastructure Validation:** `docs/INFRASTRUCTURE_VALIDATION_REPORT.md`
- **Test Groups Documentation:** `docs/TEST_GROUPS.md`
- **Agent Coordination:** `docs/AGENT_COORDINATION_HUB.md`

---

**Last Updated:** 2025-11-08  
**Created By:** Cursor Agent

