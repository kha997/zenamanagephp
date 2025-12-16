# Agent Coordination Instructions

**For:** Cursor, Codex, Continue  
**Purpose:** Standardized instructions for agent coordination

## Before Starting Work

### Step 1: Read Coordination Hub
```bash
cat docs/AGENT_COORDINATION_HUB.md
```

**Check:**
- ✅ File Locks section - don't modify locked files
- ✅ Conflict Warnings - avoid conflicts
- ✅ Task Queue - pick appropriate task
- ✅ Agent Reports - understand current state
- ✅ Communication Log - recent updates

### Step 2: Check Task Board
```bash
cat docs/AGENT_TASK_BOARD.md
```

**Check:**
- ✅ Available tasks in "Ready" section
- ✅ Dependencies are met
- ✅ No blockers
- ✅ Task assignment

### Step 3: Check Conflict Matrix
```bash
cat docs/AGENT_CONFLICT_MATRIX.md
```

**Check:**
- ✅ File ownership rules
- ✅ Modification order
- ✅ Conflict resolution strategies

### Step 4: Update Your Status
Edit `docs/AGENT_STATUS_REPORTS.md`:
- Add your current task
- Set status (In Progress, Waiting, etc.)
- Update progress percentage
- Set ETA
- Note any blockers

### Step 5: Lock Files
Edit `docs/AGENT_COORDINATION_HUB.md`:
- Add files you'll modify to "File Locks" table
- Set lock duration (ETA)
- Add reason for lock

**Example:**
```markdown
| File | Locked By | Reason | Locked At | Until | Status |
|------|-----------|--------|-----------|-------|--------|
| phpunit.xml | Cursor | Adding groups structure | 2025-11-08 09:00 | 2025-11-08 11:00 | 🔒 Locked |
```

## During Work

### Update Progress Regularly

**Every 30 minutes or at major milestones:**

1. **Update AGENT_STATUS_REPORTS.md:**
   - Update progress percentage
   - Update current task status
   - Note any issues

2. **Update AGENT_COORDINATION_HUB.md:**
   - Update "Communication Log" with milestones
   - Update "Last Updated" timestamp

3. **Update AGENT_TASK_BOARD.md:**
   - Update task progress if significant change
   - Move task status if needed

### Report Blockers Immediately

**When blocker found:**

1. **Update AGENT_STATUS_REPORTS.md:**
   - Add blocker to your status report
   - Note expected resolution

2. **Update AGENT_COORDINATION_HUB.md:**
   - Add to "Conflict Warnings" or create blocker entry
   - Add to "Communication Log"
   - Update "Last Updated"

3. **Update AGENT_TASK_BOARD.md:**
   - Move task to "Blocked" section
   - Note blocker details

4. **Notify Blocking Agent (if applicable):**
   - Add entry to Communication Log
   - Mention blocking agent

### Report New Tasks

**When discovering new task:**

1. **Add to AGENT_COORDINATION_HUB.md:**
   - Add to "New Tasks" table
   - Include: Task description, discovered by, date, priority

2. **Add to AGENT_TASK_BOARD.md:**
   - Add to "Backlog" section
   - Include dependencies and estimates

3. **Update AGENT_STATUS_REPORTS.md:**
   - Note task discovery in your report

4. **Notify Other Agents:**
   - Add to Communication Log
   - All agents will see in next check

## After Completing Work

### Step 1: Unlock Files

Edit `docs/AGENT_COORDINATION_HUB.md`:
- Remove files from "File Locks" table
- Add entry to "Communication Log" noting unlock

### Step 2: Update Task Status

Edit `docs/AGENT_TASK_BOARD.md`:
- Move task from "In Progress" to "Ready for Review"
- Update progress to 100%
- Note completion date

### Step 3: Update Handoff

Edit `AGENT_HANDOFF.md`:
- Add "Next for [Agent]" section
- Describe what needs to be done next
- Include any important notes

**Example:**
```markdown
## Next for Codex:
- Review Core Infrastructure implementation
- Check DomainTestIsolation trait
- Verify phpunit.xml groups structure
- Review documentation updates
```

### Step 4: Final Status Update

Edit `docs/AGENT_STATUS_REPORTS.md`:
- Mark task as complete
- Update progress to 100%
- Note completion time
- Update "Next Actions" if applicable

## Agent-Specific Instructions

### For Cursor (Finisher)

**Role:** Infrastructure, Integration, Final Fixes

**Workflow:**
1. Usually works on foundation/infrastructure first
2. Applies reviews from Codex
3. Runs tests and fixes issues
4. Finalizes and merges work

**Special Considerations:**
- Often locks shared files (phpunit.xml, TestDataSeeder.php)
- Should unlock promptly when done
- Should coordinate with Continue for domain work
- Should apply Codex reviews carefully

### For Codex (Reviewer)

**Role:** Code Review, Frontend Organization, Test Improvements

**Workflow:**
1. Reviews completed work
2. Suggests improvements
3. Can work on independent tasks (Frontend E2E)
4. Adds tests if needed

**Special Considerations:**
- Usually doesn't lock files (review-only)
- Can work on frontend independently
- Should provide clear review feedback
- Can work in parallel on multiple reviews

### For Continue (Builder)

**Role:** Implementation, Domain Packages, Test Creation

**Workflow:**
1. Implements domain-specific work
2. Creates tests and fixtures
3. Updates configurations
4. Waits for foundation if needed

**Special Considerations:**
- Often waits for Cursor's foundation work
- Can work on multiple domains in parallel (after foundation)
- Should lock files when modifying
- Should coordinate with Cursor for shared files

## Conflict Resolution

### If You Encounter a Conflict:

1. **Stop work immediately**
2. **Check AGENT_COORDINATION_HUB.md:**
   - Review "File Locks"
   - Check "Conflict Warnings"
   - See who has the file locked

3. **Check AGENT_CONFLICT_MATRIX.md:**
   - Review conflict resolution procedures
   - Follow modification order
   - Coordinate if needed

4. **Coordinate:**
   - Add entry to Communication Log
   - Wait for unlock or coordinate timing
   - Follow resolution procedures

5. **Resume:**
   - After conflict resolved
   - Update status
   - Continue work

## Quick Reference Commands

### Check Current Status
```bash
# Coordination Hub
cat docs/AGENT_COORDINATION_HUB.md

# Task Board
cat docs/AGENT_TASK_BOARD.md

# Status Reports
cat docs/AGENT_STATUS_REPORTS.md

# Conflict Matrix
cat docs/AGENT_CONFLICT_MATRIX.md
```

### Update Your Status
```bash
# Edit status report
# File: docs/AGENT_STATUS_REPORTS.md
# Find your agent section and update
```

### Lock a File
```bash
# Edit coordination hub
# File: docs/AGENT_COORDINATION_HUB.md
# Add to "File Locks" table
```

### Report Blocker
```bash
# 1. Update your status report
# 2. Add to coordination hub → Conflict Warnings
# 3. Update task board → Blocked section
# 4. Add to communication log
```

## Checklist

### Before Starting Work
- [ ] Read AGENT_COORDINATION_HUB.md
- [ ] Check AGENT_TASK_BOARD.md
- [ ] Review AGENT_CONFLICT_MATRIX.md
- [ ] Update AGENT_STATUS_REPORTS.md
- [ ] Lock files in AGENT_COORDINATION_HUB.md

### During Work
- [ ] Update progress every 30 minutes
- [ ] Report blockers immediately
- [ ] Report new tasks when discovered
- [ ] Update communication log for milestones

### After Completing Work
- [ ] Unlock files
- [ ] Update task status
- [ ] Update handoff file
- [ ] Final status update

## Troubleshooting

### Can't find available task
- Check AGENT_TASK_BOARD.md → "Ready" section
- Check dependencies are met
- Check for blockers

### File is locked
- Check AGENT_COORDINATION_HUB.md → "File Locks"
- Wait for unlock time
- Coordinate if urgent

### Don't know what to do next
- Check AGENT_HANDOFF.md → "Next for [Your Agent]"
- Check AGENT_TASK_BOARD.md → "Ready" section
- Check AGENT_COORDINATION_HUB.md → "Task Queue"

### Conflict occurred
- Stop work immediately
- Check AGENT_CONFLICT_MATRIX.md for resolution
- Coordinate via Communication Log
- Follow conflict resolution procedures

## Notes

- Always check coordination files before starting
- Better to over-communicate than under-communicate
- Update files immediately when status changes
- Respect file locks and modification order
- Follow the workflow phases for best results

