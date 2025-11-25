# Agent Workflow Coordination

**Last Updated:** 2025-11-08 12:00  
**Purpose:** Standardized workflow for agent coordination

## Standard Workflow Phases

### Phase 1: Foundation (Cursor)

**Purpose:** Create infrastructure that other work depends on

**Steps:**
1. Cursor picks foundation task (e.g., Core Infrastructure)
2. Cursor checks AGENT_COORDINATION_HUB.md for conflicts
3. Cursor locks files in AGENT_COORDINATION_HUB.md
4. Cursor updates AGENT_STATUS_REPORTS.md with status
5. Cursor implements foundation work
6. Cursor updates progress regularly (every 30 min)
7. Cursor marks complete in AGENT_TASK_BOARD.md
8. Cursor unlocks files in AGENT_COORDINATION_HUB.md
9. Cursor updates AGENT_HANDOFF.md with "Next for Continue/Codex"
10. Cursor moves task to "Ready for Review" in AGENT_TASK_BOARD.md

**Output:**
- Foundation code complete
- Files unlocked
- Next agents notified

---

### Phase 2: Implementation (Continue)

**Purpose:** Implement domain-specific features/tests

**Steps:**
1. Continue checks AGENT_COORDINATION_HUB.md for file locks
2. Continue checks AGENT_TASK_BOARD.md for available tasks
3. Continue picks domain package (e.g., Auth Domain)
4. Continue verifies dependencies are met
5. Continue locks files in AGENT_COORDINATION_HUB.md (if needed)
6. Continue updates AGENT_STATUS_REPORTS.md with status
7. Continue creates branch: `test-org/[domain]-domain`
8. Continue implements tasks from work package
9. Continue updates progress regularly
10. Continue marks complete in AGENT_TASK_BOARD.md
11. Continue unlocks files in AGENT_COORDINATION_HUB.md
12. Continue updates AGENT_HANDOFF.md with "Next for Codex"
13. Continue moves task to "Ready for Review"

**Output:**
- Domain implementation complete
- Files unlocked
- Ready for review

**Parallel Work:**
- Multiple domain packages can work in parallel (after foundation)
- Each domain uses different files (minimal conflicts)

---

### Phase 3: Review (Codex)

**Purpose:** Review code quality, add tests, improve implementation

**Steps:**
1. Codex checks AGENT_TASK_BOARD.md for "Ready for Review"
2. Codex picks review item
3. Codex updates AGENT_STATUS_REPORTS.md with review status
4. Codex reviews code on branch
5. Codex adds review notes to AGENT_HANDOFF.md
6. Codex suggests improvements (if any)
7. Codex marks reviewed in AGENT_TASK_BOARD.md
8. Codex updates AGENT_HANDOFF.md with "Next for Cursor" (if fixes needed)

**Output:**
- Review complete
- Feedback provided
- Ready for integration or fixes

**Parallel Work:**
- Codex can review multiple items in parallel
- Codex can work on independent tasks (Frontend E2E) simultaneously

---

### Phase 4: Integration (Cursor)

**Purpose:** Apply reviews, run tests, fix issues, finalize

**Steps:**
1. Cursor checks AGENT_HANDOFF.md for "Next for Cursor"
2. Cursor applies Codex reviews
3. Cursor runs tests
4. Cursor fixes any issues
5. Cursor verifies all tests pass
6. Cursor updates AGENT_STATUS_REPORTS.md
7. Cursor marks complete in AGENT_TASK_BOARD.md
8. Cursor merges branch (if approved)

**Output:**
- All tests passing
- Code integrated
- Task complete

---

## New Task Workflow

When a new task is discovered during work:

### Step 1: Discovery
1. Agent identifies new task
2. Agent adds to AGENT_COORDINATION_HUB.md → "New Tasks" section
3. Agent updates AGENT_TASK_BOARD.md → "Backlog" section
4. Agent updates AGENT_STATUS_REPORTS.md with discovery note

### Step 2: Notification
1. Agent adds entry to AGENT_COORDINATION_HUB.md → "Communication Log"
2. All agents see new task in next status check
3. Task appears in AGENT_TASK_BOARD.md → "Backlog"

### Step 3: Assignment
1. Agent or coordinator assigns task based on:
   - Task type (matches agent role)
   - Agent availability
   - Dependencies
   - Priority
2. Update AGENT_TASK_BOARD.md with assignment
3. Update AGENT_COORDINATION_HUB.md with assignment
4. Update AGENT_STATUS_REPORTS.md for assigned agent

### Step 4: Execution
1. Follow standard workflow phases
2. Update all coordination files
3. Complete task

---

## Blocker Resolution Workflow

When a blocker is encountered:

### Step 1: Identify Blocker
1. Agent identifies blocker
2. Agent updates AGENT_STATUS_REPORTS.md with blocker details
3. Agent adds to AGENT_COORDINATION_HUB.md → "Conflict Warnings" or "Blockers"
4. Agent moves task to "Blocked" in AGENT_TASK_BOARD.md

### Step 2: Communicate
1. Agent adds entry to AGENT_COORDINATION_HUB.md → "Communication Log"
2. Agent notifies blocking agent (if applicable)
3. Agent updates ETA if blocker affects timeline

### Step 3: Resolve
1. Blocking agent works to resolve blocker
2. Blocking agent updates status when blocker resolved
3. Blocked agent checks status
4. Blocked agent resumes work when unblocked

### Step 4: Update
1. Remove blocker from AGENT_COORDINATION_HUB.md
2. Move task back to appropriate status in AGENT_TASK_BOARD.md
3. Update AGENT_STATUS_REPORTS.md

---

## Daily Coordination Routine

### Morning Check (Each Agent)

1. **Read Coordination Hub:**
   ```bash
   cat docs/AGENT_COORDINATION_HUB.md
   ```
   - Check file locks
   - Check conflict warnings
   - Check new tasks
   - Check communication log

2. **Check Task Board:**
   ```bash
   cat docs/AGENT_TASK_BOARD.md
   ```
   - See what's in progress
   - Check for available tasks
   - Verify dependencies

3. **Check Status Reports:**
   ```bash
   cat docs/AGENT_STATUS_REPORTS.md
   ```
   - See other agents' status
   - Check for blockers
   - Understand current state

4. **Update Your Status:**
   - Update AGENT_STATUS_REPORTS.md
   - Update AGENT_COORDINATION_HUB.md if starting work
   - Lock files if modifying

### During Work (Each Agent)

1. **Update Progress:**
   - Every 30 minutes or at milestones
   - Update AGENT_STATUS_REPORTS.md
   - Update AGENT_TASK_BOARD.md if status changes

2. **Report Issues:**
   - Immediately if blocker found
   - Add to AGENT_COORDINATION_HUB.md
   - Update AGENT_STATUS_REPORTS.md

3. **Coordinate:**
   - Use Communication Log for important updates
   - Check for conflicts before modifying shared files

### End of Work Session (Each Agent)

1. **Final Status Update:**
   - Update AGENT_STATUS_REPORTS.md with final progress
   - Update AGENT_TASK_BOARD.md if completing task
   - Unlock files if done

2. **Handoff:**
   - Update AGENT_HANDOFF.md with "Next for [Agent]"
   - Move task to appropriate status
   - Notify next agent if needed

---

## Workflow Best Practices

### Do's ✅

- ✅ Always check coordination files before starting work
- ✅ Lock files you're modifying
- ✅ Update progress regularly
- ✅ Report blockers immediately
- ✅ Follow modification order
- ✅ Coordinate via Communication Log
- ✅ Unlock files when done
- ✅ Update handoff files

### Don'ts ❌

- ❌ Don't modify locked files
- ❌ Don't skip coordination file checks
- ❌ Don't work on conflicting files simultaneously
- ❌ Don't forget to unlock files
- ❌ Don't skip status updates
- ❌ Don't ignore blockers
- ❌ Don't work without checking dependencies

---

## Workflow Examples

### Example 1: Continue Starting Auth Domain

```
1. Continue reads AGENT_COORDINATION_HUB.md
   → Sees Core Infrastructure in progress
   → Sees phpunit.xml locked until 10:00
   → Waits

2. At 10:00, Cursor unlocks files
   → Continue checks AGENT_COORDINATION_HUB.md
   → Sees files unlocked
   → Locks files for Auth Domain work
   → Updates AGENT_STATUS_REPORTS.md
   → Starts work

3. Continue implements Auth Domain
   → Updates progress every 30 min
   → Completes all 6 phases
   → Unlocks files
   → Moves to "Ready for Review"
   → Updates AGENT_HANDOFF.md
```

### Example 2: Codex Reviewing Work

```
1. Codex checks AGENT_TASK_BOARD.md
   → Sees "Ready for Review" items
   → Picks Core Infrastructure

2. Codex reviews code
   → Adds review notes to AGENT_HANDOFF.md
   → Suggests improvements
   → Marks reviewed

3. Codex updates AGENT_HANDOFF.md
   → "Next for Cursor: Apply reviews and fix issues"
   → Cursor picks up work
```

### Example 3: New Task Discovery

```
1. Continue discovers need for new fixture
   → Adds to AGENT_COORDINATION_HUB.md → "New Tasks"
   → Adds to AGENT_TASK_BOARD.md → "Backlog"
   → Updates AGENT_STATUS_REPORTS.md

2. All agents see new task
   → Codex picks it up (fits reviewer role)
   → Updates assignments
   → Starts work
```

### Example 4: Frontend File Coordination (Cursor + Codex)

```
1. Cursor wants to implement UI/UX (tokens, components)
   → Checks AGENT_COORDINATION_HUB.md for file locks
   → Locks frontend/src/shared/tokens/** and frontend/src/components/**
   → Updates AGENT_STATUS_REPORTS.md
   → Starts UI/UX implementation

2. Codex wants to organize E2E tests
   → Checks AGENT_COORDINATION_HUB.md for file locks
   → Locks frontend/playwright.config.ts and frontend/e2e/**
   → Updates AGENT_STATUS_REPORTS.md
   → Starts E2E organization

3. Both work in parallel (different files, no conflict)
   → Cursor implements UI/UX components
   → Codex organizes E2E test structure
   → Both update progress every 30 minutes

4. If both need frontend/package.json:
   → Cursor locks for dependencies section
   → Codex locks for scripts section (different sections)
   → Both coordinate via Communication Log
   → Codex reviews Cursor's dependency changes
```

---

## Frontend Coordination Procedures

### Shared Files (frontend/package.json)

**When Cursor needs to add dependencies:**
1. Check AGENT_COORDINATION_HUB.md for existing locks
2. Lock `frontend/package.json` (dependencies section) in coordination hub
3. Add entry to Communication Log: "Locking frontend/package.json for dependencies"
4. Modify `dependencies` and `devDependencies` sections only
5. Unlock file after modification
6. Update Communication Log: "Unlocked frontend/package.json, added UI dependencies"

**When Codex needs to add test scripts:**
1. Check AGENT_COORDINATION_HUB.md for existing locks
2. Lock `frontend/package.json` (scripts section) in coordination hub
3. Add entry to Communication Log: "Locking frontend/package.json for test scripts"
4. Modify `scripts` section only (test-related: e2e:*, test:*)
5. Unlock file after modification
6. Update Communication Log: "Unlocked frontend/package.json, added test scripts"

**Coordination for shared file:**
- Both agents can lock the same file if modifying different sections
- Coordinate via Communication Log to avoid merge conflicts
- Codex reviews Cursor's dependency changes before proceeding
- If merge conflict occurs, resolve using standard conflict resolution procedures

### Independent Files (Safe Parallel Work)

**Cursor (UI/UX Implementation):**
- `frontend/src/shared/tokens/**` - Work freely, Codex reviews only
- `frontend/src/components/**` - Work freely, Codex reviews only
- `frontend/src/app/layouts/**` - Work freely, Codex reviews only
- `frontend/src/features/**/components/**` - Work freely, Codex reviews only
- `frontend/src/features/**/pages/**` - Work freely, Codex reviews only
- `frontend/tailwind.config.ts` - Work freely, Codex reviews only

**Codex (Frontend E2E Organization):**
- `frontend/playwright.config.ts` - Work freely, Cursor reviews only
- `frontend/e2e/**` - Work freely, Cursor reviews only
- `frontend/tests/**` - Work freely, Cursor reviews only

**Coordination:**
- Lock files before starting work
- Update progress every 30 minutes
- Review each other's work when complete
- No conflicts expected (different files/directories)

## Notes

- Workflow is iterative - agents cycle through phases
- Multiple agents can work in parallel when no conflicts
- Coordination files are the single source of truth
- Always update files immediately when status changes
- Better to over-communicate than under-communicate
- Frontend work: Cursor implements UI/UX, Codex organizes E2E tests
- Shared files require coordination via locks and Communication Log

