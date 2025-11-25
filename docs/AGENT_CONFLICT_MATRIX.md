# Agent Conflict Matrix

**Last Updated:** 2025-11-08 12:00  
**Purpose:** Prevent file conflicts and coordinate parallel work

## File Ownership Rules

### phpunit.xml
- **Primary Owner:** Cursor (Core Infrastructure)
- **Secondary Owners:** Continue (Domain test suites), Codex (Review only)
- **Lock Duration:** Until Core Infrastructure complete
- **Conflict Resolution:**
  1. Cursor adds groups structure first
  2. Continue adds domain test suites after unlock
  3. Codex reviews but doesn't modify
- **Others Can:** Read only, wait for unlock
- **Modification Order:** Cursor → Continue → Codex (review)

### tests/Helpers/TestDataSeeder.php
- **Primary Owner:** Cursor (Core Infrastructure - DomainTestIsolation trait)
- **Secondary Owners:** Continue (Domain seeding methods)
- **Lock Duration:** Per method/domain
- **Conflict Resolution:**
  1. Cursor adds DomainTestIsolation trait first
  2. Continue adds domain-specific methods (seedAuthDomain, seedProjectsDomain, etc.)
  3. Methods are independent - can be added in parallel after trait exists
- **Modification Strategy:** Sequential for trait, parallel for methods

### playwright.config.ts
- **Primary Owner:** Continue (Backend E2E projects)
- **Secondary Owner:** Codex (Frontend review, frontend projects)
- **Conflict Risk:** Low (different sections)
- **Conflict Resolution:**
  - Continue adds backend projects (auth-e2e, projects-e2e, etc.)
  - Codex reviews and may add frontend-specific projects
  - Different sections, minimal overlap
- **Modification Strategy:** Coordinate sections, review before merge

### package.json
- **Primary Owner:** Continue (Backend test scripts)
- **Secondary Owner:** Codex (Review, frontend scripts if needed)
- **Conflict Risk:** Medium (same file, different scripts)
- **Conflict Resolution:**
  - Continue adds backend scripts (test:auth, test:projects, etc.)
  - Codex reviews
  - Scripts are independent entries, low conflict risk
- **Modification Strategy:** Add scripts in order, review before merge

### frontend/playwright.config.ts
- **Primary Owner:** Codex (Frontend E2E organization)
- **Conflict Risk:** None (different directory)
- **Others Can:** Review only
- **Modification Strategy:** Codex owns this file independently

### frontend/package.json
- **Primary Owners:** 
  - Cursor (dependencies, devDependencies sections)
  - Codex (scripts section - test-related only)
- **Conflict Risk:** Medium (same file, different sections)
- **Conflict Resolution:**
  - Cursor adds UI/UX dependencies
  - Codex adds test scripts (e2e:*, test:*)
  - Lock file when modifying
  - Coordinate via Communication Log
  - Codex reviews Cursor's dependency changes
- **Modification Strategy:** 
  - Lock file before modification
  - Modify only assigned sections
  - Notify via Communication Log
  - Review before merge

## Workflow Dependencies

```
┌─────────────────────────────────────┐
│ Core Infrastructure (Cursor)          │
│ - DomainTestIsolation trait          │
│ - phpunit.xml groups structure      │
│ - CI/CD updates                      │
└──────────────┬──────────────────────┘
               │
               │ Blocks
               ▼
┌─────────────────────────────────────┐
│ Domain Packages (Continue)           │
│ - Auth Domain                        │
│ - Projects Domain                    │
│ - Tasks Domain                       │
│ - Documents Domain                   │
│ - Users Domain                       │
│ - Dashboard Domain                   │
│                                      │
│ Can work in parallel after unlock    │
└──────────────┬──────────────────────┘
               │
               │ Blocks
               ▼
┌─────────────────────────────────────┐
│ Review (Codex)                       │
│ - Review Core Infrastructure        │
│ - Review Domain Packages            │
│                                      │
│ Can review in parallel               │
└──────────────┬──────────────────────┘
               │
               │ Blocks
               ▼
┌─────────────────────────────────────┐
│ Final Integration (Cursor)           │
│ - Apply Codex reviews                │
│ - Run tests                          │
│ - Fix issues                         │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ Frontend E2E Organization (Codex)   │
│ - Independent work                   │
│ - No dependencies                    │
│ - Can start anytime                  │
└─────────────────────────────────────┘
```

## Conflict Prevention Checklist

### Before Starting Work

Each agent must:

1. ✅ **Check AGENT_COORDINATION_HUB.md**
   - Review "File Locks" section
   - Check "Conflict Warnings"
   - Verify no active conflicts

2. ✅ **Check AGENT_TASK_BOARD.md**
   - Verify dependencies are met
   - Check task status
   - Confirm assignment

3. ✅ **Check This File (AGENT_CONFLICT_MATRIX.md)**
   - Review file ownership rules
   - Understand modification order
   - Check conflict resolution strategies

4. ✅ **Lock Files in AGENT_COORDINATION_HUB.md**
   - Add files you'll modify to "File Locks"
   - Set lock duration (ETA)
   - Update your status

5. ✅ **Update AGENT_STATUS_REPORTS.md**
   - Report what you're working on
   - Set progress and ETA
   - Note any blockers

### During Work

1. ✅ **Respect File Locks**
   - Don't modify locked files
   - Wait for unlock if needed
   - Coordinate if urgent

2. ✅ **Follow Modification Order**
   - Core Infrastructure first
   - Then domain packages
   - Then reviews

3. ✅ **Update Progress Regularly**
   - Every 30 minutes
   - At major milestones
   - When blockers change

### After Completing Work

1. ✅ **Unlock Files**
   - Remove from "File Locks"
   - Update "Communication Log"
   - Notify waiting agents

2. ✅ **Update Task Status**
   - Move to "Ready for Review"
   - Update AGENT_TASK_BOARD.md
   - Notify next agent

## Conflict Resolution Procedures

### Scenario 1: Two Agents Want Same File

**Example:** Continue wants phpunit.xml but Cursor has lock

**Resolution:**
1. Check lock status in AGENT_COORDINATION_HUB.md
2. Wait for unlock (check "Until" time)
3. If urgent, coordinate via Communication Log
4. Follow modification order (Cursor first, then Continue)

### Scenario 2: Sequential Dependencies

**Example:** TestDataSeeder.php needs trait before methods

**Resolution:**
1. Cursor adds trait first (locks file)
2. Cursor unlocks when complete
3. Continue adds methods (can work in parallel after trait exists)
4. Each method is independent

### Scenario 3: Parallel Work on Related Files

**Example:** Multiple domain packages modifying different test files

**Resolution:**
1. Each domain works on different files (no conflict)
2. phpunit.xml modifications: sequential (Cursor → Continue)
3. TestDataSeeder methods: parallel after trait exists
4. Coordinate via AGENT_COORDINATION_HUB.md

### Scenario 4: Review Conflicts

**Example:** Codex wants to review while Continue is still working

**Resolution:**
1. Codex waits for "Ready for Review" status
2. Continue marks complete in AGENT_TASK_BOARD.md
3. Codex reviews on separate branch
4. Codex provides feedback, Continue fixes if needed

## File Modification Patterns

### Safe Parallel Modifications

These can be done in parallel (different files):
- ✅ Different domain test files (tests/Feature/Auth/*.php vs tests/Feature/Projects/*.php)
- ✅ Different fixture files (tests/fixtures/domains/auth/ vs tests/fixtures/domains/projects/)
- ✅ Frontend vs Backend files (frontend/ vs root/)
- ✅ Different documentation files

### Sequential Modifications Required

These must be done in order:
- ⚠️ phpunit.xml (Cursor structure → Continue suites)
- ⚠️ TestDataSeeder.php (Cursor trait → Continue methods)
- ⚠️ CI workflow (Cursor matrix → Continue scripts)

### Review-Only Access

These are review-only (Codex):
- ✅ Completed work from other agents
- ✅ Documentation
- ✅ Test improvements

## Emergency Conflict Resolution

If conflict occurs:

1. **Stop work immediately**
2. **Check AGENT_COORDINATION_HUB.md** for current locks
3. **Coordinate via Communication Log**
4. **Resolve using conflict resolution procedures**
5. **Update status in all coordination files**
6. **Resume work after resolution**

### frontend/src/shared/tokens/**
- **Primary Owner:** Cursor (UI/UX Implementation)
- **Secondary Owner:** Codex (Review only)
- **Conflict Risk:** Low (Codex chỉ review)
- **Modification Strategy:** Cursor implements, Codex reviews

### frontend/src/components/**
- **Primary Owner:** Cursor (UI/UX Implementation)
- **Secondary Owner:** Codex (Review only)
- **Conflict Risk:** Low (Codex chỉ review)
- **Modification Strategy:** Cursor implements, Codex reviews

### frontend/src/components/layout/HeaderShell.tsx
- **Primary Owner:** Cursor (UI/UX Implementation)
- **Secondary Owner:** Codex (Review only, may update E2E tests)
- **Conflict Risk:** Medium (Codex may need to update E2E tests after implementation)
- **Conflict Resolution:**
  - Cursor implements first (lock file)
  - Codex updates E2E tests after implementation
  - Coordination: Cursor notifies Codex when header is ready for testing
- **Modification Strategy:** Sequential - Cursor implements, then Codex updates tests

### frontend/e2e/**
- **Primary Owner:** Codex (E2E Organization)
- **Secondary Owner:** Cursor (Review only)
- **Conflict Risk:** Low (Cursor chỉ review)
- **Modification Strategy:** Codex organizes, Cursor reviews

### frontend/tailwind.config.ts
- **Primary Owner:** Cursor (UI/UX Implementation - tokens, theme config)
- **Secondary Owner:** Codex (Review only, may suggest E2E-related config)
- **Conflict Risk:** Low (Codex chỉ review và suggest)
- **Modification Strategy:** Cursor owns primary implementation, Codex reviews

### frontend/vite.config.ts
- **Primary Owner:** Cursor (Build config for UI/UX)
- **Secondary Owner:** Codex (Review only, may need test config)
- **Conflict Risk:** Low (Minimal overlap, separate test config if needed)
- **Modification Strategy:** Cursor owns build config, Codex owns test config (separate file if needed)

## Notes

- Always check before modifying shared files
- When in doubt, coordinate first
- Better to wait than create conflicts
- Use locks proactively, not reactively
- Update coordination files immediately when conflicts arise
- Frontend files: Cursor implements UI/UX, Codex organizes E2E tests and reviews

