# Core Infrastructure Integration Checklist

**Last Updated:** 2025-11-08  
**Purpose:** Step-by-step checklist for integrating Codex review feedback into Core Infrastructure work

## Pre-Integration Checklist

### Review Status
- [ ] Codex review complete
- [ ] Review notes added to `AGENT_HANDOFF.md`
- [ ] All review feedback documented
- [ ] No blocking issues identified

### Conflict Check
- [ ] Check `docs/AGENT_COORDINATION_HUB.md` for file locks
- [ ] Verify no other agents are modifying same files
- [ ] Check `docs/AGENT_CONFLICT_MATRIX.md` for ownership rules
- [ ] Resolve any conflicts before starting

### Branch Status
- [ ] Current branch: `test-org/core-infrastructure`
- [ ] Branch is up to date with base branch
- [ ] No uncommitted changes (or commit before integration)
- [ ] Backup current work if needed

## Integration Process

### Step 1: Review Feedback Analysis
- [ ] Read all review notes in `AGENT_HANDOFF.md`
- [ ] Categorize feedback:
  - [ ] Critical (must fix)
  - [ ] Important (should fix)
  - [ ] Nice to have (optional)
- [ ] Prioritize fixes
- [ ] Estimate time for each fix

### Step 2: Apply Code Fixes

#### File: `tests/Traits/DomainTestIsolation.php`
- [ ] Review feedback on trait structure
- [ ] Apply method implementation fixes
- [ ] Update PHPDoc if needed
- [ ] Verify trait still works after changes

#### File: `phpunit.xml`
- [ ] Review test suite structure feedback
- [ ] Fix any configuration issues
- [ ] Verify test suites are valid
- [ ] Test that groups work correctly

#### File: `.github/workflows/ci.yml`
- [ ] Review matrix strategy feedback
- [ ] Fix any workflow syntax issues
- [ ] Verify seed values are correct
- [ ] Check job dependencies

#### File: `scripts/aggregate-test-results.sh`
- [ ] Review script functionality feedback
- [ ] Fix error handling issues
- [ ] Improve output formats if needed
- [ ] Test script with sample data

### Step 3: Documentation Updates

#### File: `docs/TEST_GROUPS.md`
- [ ] Apply documentation feedback
- [ ] Fix any inaccuracies
- [ ] Add missing examples
- [ ] Verify all links work

#### File: `TEST_SUITE_SUMMARY.md`
- [ ] Apply documentation feedback
- [ ] Fix any inaccuracies
- [ ] Update examples if needed
- [ ] Verify formatting

### Step 4: Testing After Fixes

#### Run Validation Tests
- [ ] Run `DomainTestIsolationTest` to verify trait works
- [ ] Test one domain test suite (e.g., `auth-feature`)
- [ ] Verify aggregate-test-results.sh works
- [ ] Check CI workflow syntax (validate YAML)

#### Verify No Breaking Changes
- [ ] Run existing tests to ensure nothing broke
- [ ] Check that test suites still work
- [ ] Verify groups still function
- [ ] Test with fixed seeds

### Step 5: Update Coordination Files

#### Update Status
- [ ] Update `docs/AGENT_STATUS_REPORTS.md` with integration status
- [ ] Update `docs/AGENT_TASK_BOARD.md` if status changes
- [ ] Update `docs/AGENT_COORDINATION_HUB.md` Communication Log
- [ ] Update `AGENT_HANDOFF.md` with integration completion

#### Unlock Files (if needed)
- [ ] Unlock any files in `docs/AGENT_COORDINATION_HUB.md`
- [ ] Notify Continue that files are available
- [ ] Update file lock status

## Post-Integration Verification

### Code Quality
- [ ] All fixes applied correctly
- [ ] No syntax errors
- [ ] Code follows project conventions
- [ ] Documentation is accurate

### Functionality
- [ ] All tests pass
- [ ] Scripts work correctly
- [ ] CI workflow is valid
- [ ] Documentation is complete

### Integration
- [ ] Changes are committed
- [ ] Branch is ready for merge
- [ ] Coordination files updated
- [ ] Next agent notified (if applicable)

## Rollback Procedures

If issues are found after integration:

### Immediate Rollback
- [ ] Revert to previous commit
- [ ] Restore files from backup
- [ ] Update coordination files with rollback status
- [ ] Document issues found

### Investigation
- [ ] Identify root cause of issues
- [ ] Create fix plan
- [ ] Test fixes in isolation
- [ ] Re-apply integration after fixes

## File-Specific Verification

### DomainTestIsolation Trait
- [ ] Trait can be used in test classes
- [ ] Methods work as expected
- [ ] Seed reproducibility verified
- [ ] Test isolation verified

### phpunit.xml
- [ ] All test suites are valid
- [ ] Groups work correctly
- [ ] Can run tests by suite
- [ ] Can run tests by group

### CI Workflow
- [ ] YAML syntax is valid
- [ ] Matrix strategy works
- [ ] Jobs run in correct order
- [ ] Artifacts are uploaded

### Aggregate Script
- [ ] Script runs without errors
- [ ] JSON output is valid
- [ ] Statistics are accurate
- [ ] All formats work

## Communication

### Update Codex
- [ ] Notify Codex that fixes are applied
- [ ] Request re-review if needed
- [ ] Update review status

### Update Continue
- [ ] Notify Continue that files are ready
- [ ] Share any important changes
- [ ] Update task board

### Update Coordination Hub
- [ ] Add integration completion entry
- [ ] Update task status
- [ ] Document any issues encountered

## Success Criteria

Integration is complete when:
- [ ] All critical feedback addressed
- [ ] All tests pass
- [ ] Documentation is accurate
- [ ] Coordination files updated
- [ ] Ready for merge or next phase

## Notes

- Keep detailed notes of all changes made
- Document any deviations from review feedback
- Note any new issues discovered during integration
- Maintain communication with other agents

---

**Last Updated:** 2025-11-08  
**Maintainer:** Cursor (Finisher)

