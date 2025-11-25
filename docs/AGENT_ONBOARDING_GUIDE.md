# Agent Onboarding Guide

**For:** Codex, Continue (và agents mới trong tương lai)  
**Purpose:** Quick onboarding guide for new agents

## Quick Start (5 Minutes)

### Step 1: Read These Files (In Order - 5 minutes)

1. **docs/AGENT_COORDINATION_README.md** - Quick overview (2 min)
2. **docs/AGENT_COORDINATION_HUB.md** - Current status (1 min)
3. **docs/AGENT_TASK_BOARD.md** - Available tasks (1 min)
4. **docs/AGENT_STATUS_REPORTS.md** - Other agents' status (1 min)

**Total time: ~5 minutes**

### Step 2: Understand Your Role (2 minutes)

**Codex (Reviewer):**
- Review code from Continue and Cursor
- Organize frontend E2E tests
- Improve test quality
- **See:** docs/PROMPT_FOR_CODEX.md

**Continue (Builder):**
- Implement domain packages
- Create tests and fixtures
- Update configurations
- **See:** docs/PROMPT_FOR_CONTINUE.md

**Cursor (Finisher):**
- Create infrastructure
- Apply reviews
- Run tests and fix issues
- Finalize and merge

### Step 3: Pick Your First Task (1 minute)

**For Codex:**
- Check `docs/AGENT_TASK_BOARD.md` → "Ready for Review"
- Or start Frontend E2E Organization (independent)
- **See:** `docs/work-packages/frontend-e2e-organization.md`

**For Continue:**
- Check `docs/AGENT_TASK_BOARD.md` → "Ready" section
- Verify dependencies met (Core Infrastructure complete)
- Pick domain package (Auth, Projects, etc.)
- **See:** `docs/work-packages/[domain]-domain.md`

### Step 4: Follow Workflow (Ongoing)

**See:** `docs/AGENT_WORKFLOW.md` for detailed workflow

**Quick workflow:**
1. Check coordination files
2. Lock files you'll modify
3. Update status
4. Do work
5. Update progress regularly
6. Unlock files when done
7. Move task to next status

## Essential Files to Read

### Must Read (Before Starting):
- ✅ `docs/AGENT_COORDINATION_HUB.md` - Current status, locks, conflicts
- ✅ `docs/AGENT_TASK_BOARD.md` - Available tasks
- ✅ `docs/AGENT_STATUS_REPORTS.md` - Other agents' status

### Should Read (Understand Rules):
- 📖 `docs/AGENT_CONFLICT_MATRIX.md` - File ownership, conflict prevention
- 📖 `docs/AGENT_WORKFLOW.md` - Workflow procedures
- 📖 `.continue/agent-instructions/coordination.md` - Detailed instructions

### Reference (When Needed):
- 📚 `docs/AGENT_COORDINATION_README.md` - Quick reference
- 📚 `docs/work-packages/*.md` - Work package details
- 📚 `docs/TEST_ORGANIZATION_ASSIGNMENT.md` - Overall assignment

## Common Questions & Answers

### Q: What should I do first?
**A:** Read `docs/AGENT_COORDINATION_HUB.md` to see current status

### Q: Can I modify this file?
**A:** Check `docs/AGENT_COORDINATION_HUB.md` → "File Locks" section

### Q: What task should I pick?
**A:** Check `docs/AGENT_TASK_BOARD.md` → "Ready" section

### Q: How do I report progress?
**A:** Update `docs/AGENT_STATUS_REPORTS.md` every 30 minutes

### Q: What if I find a blocker?
**A:** Report immediately in `docs/AGENT_COORDINATION_HUB.md` → "Conflict Warnings"

### Q: How do I complete work?
**A:** See `docs/AGENT_WORKFLOW.md` → "After Completing Work" section

### Q: What if I discover a new task?
**A:** Add to `docs/AGENT_COORDINATION_HUB.md` → "New Tasks" section

### Q: How do I coordinate with other agents?
**A:** Use `docs/AGENT_COORDINATION_HUB.md` → "Communication Log"

## Agent-Specific Prompts

### For Codex:
**Full prompt:** `docs/PROMPT_FOR_CODEX.md`  
**Short version:** Copy from "Copy-Paste Ready Prompt" section

### For Continue:
**Full prompt:** `docs/PROMPT_FOR_CONTINUE.md`  
**Short version:** Copy from "Copy-Paste Ready Prompt" section

## File Locations Reference

### Coordination Files (docs/):
- `docs/AGENT_COORDINATION_HUB.md` - Central hub
- `docs/AGENT_TASK_BOARD.md` - Task tracking
- `docs/AGENT_STATUS_REPORTS.md` - Status reports
- `docs/AGENT_CONFLICT_MATRIX.md` - Conflict prevention
- `docs/AGENT_WORKFLOW.md` - Workflow procedures
- `docs/AGENT_COORDINATION_README.md` - Quick reference
- `docs/AGENT_ONBOARDING_GUIDE.md` - This file

### Instructions:
- `.continue/agent-instructions/coordination.md` - Detailed instructions

### Prompts:
- `docs/PROMPT_FOR_CODEX.md` - Codex onboarding
- `docs/PROMPT_FOR_CONTINUE.md` - Continue onboarding

### Helper Scripts:
- `scripts/check-conflicts.sh` - Check file conflicts
- `scripts/update-agent-status.sh` - Update status helper

### Work Packages:
- `docs/work-packages/auth-domain.md`
- `docs/work-packages/projects-domain.md`
- `docs/work-packages/tasks-domain.md`
- `docs/work-packages/documents-domain.md`
- `docs/work-packages/users-domain.md`
- `docs/work-packages/dashboard-domain.md`
- `docs/work-packages/frontend-e2e-organization.md`

## Quick Command Reference

```bash
# Check current status
cat docs/AGENT_COORDINATION_HUB.md

# Check available tasks
cat docs/AGENT_TASK_BOARD.md

# Check other agents' status
cat docs/AGENT_STATUS_REPORTS.md

# Check for file conflicts
./scripts/check-conflicts.sh phpunit.xml TestDataSeeder.php

# Update your status (helper)
./scripts/update-agent-status.sh [agent] [status] [progress] [task]
```

## Workflow Summary

```
1. Cursor: Core Infrastructure (foundation)
   ↓
2. Continue: Domain Packages (implementation) - Parallel after foundation
   ↓
3. Codex: Review + Frontend E2E (review & organize) - Parallel
   ↓
4. Cursor: Integration (apply reviews, fix, merge)
```

## Key Principles

1. **Always Check First:** Read coordination files before starting
2. **Lock Files:** Lock files you're modifying
3. **Update Regularly:** Update progress every 30 minutes
4. **Report Blockers:** Report immediately when found
5. **Unlock Promptly:** Unlock files when done
6. **Coordinate:** Use Communication Log for important updates

## Next Steps

1. Read your agent-specific prompt:
   - Codex → `docs/PROMPT_FOR_CODEX.md`
   - Continue → `docs/PROMPT_FOR_CONTINUE.md`

2. Read coordination files (listed above)

3. Pick your first task from `docs/AGENT_TASK_BOARD.md`

4. Follow workflow in `docs/AGENT_WORKFLOW.md`

5. Start working!

---

**Welcome to the team! 🚀**

