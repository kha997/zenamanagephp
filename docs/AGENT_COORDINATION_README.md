# Agent Coordination System - Quick Start Guide

**Purpose:** Help 3 agents (Cursor, Codex, Continue) work together efficiently

## Files Overview

### Core Coordination Files

1. **AGENT_COORDINATION_HUB.md** - Central hub with current status, locks, conflicts
2. **AGENT_TASK_BOARD.md** - Visual task tracking (Backlog → Ready → In Progress → Review → Complete)
3. **AGENT_STATUS_REPORTS.md** - Individual agent status and progress
4. **AGENT_CONFLICT_MATRIX.md** - File ownership rules and conflict prevention
5. **AGENT_WORKFLOW.md** - Standardized workflow procedures

### Instructions

6. **.continue/agent-instructions/coordination.md** - Step-by-step instructions for agents

### Helper Scripts

7. **scripts/update-agent-status.sh** - Quick status update helper
8. **scripts/check-conflicts.sh** - Conflict checker before starting work

## Quick Start

### For Any Agent - Before Starting Work:

```bash
# 1. Check coordination hub
cat docs/AGENT_COORDINATION_HUB.md

# 2. Check task board
cat docs/AGENT_TASK_BOARD.md

# 3. Check for conflicts
./scripts/check-conflicts.sh phpunit.xml TestDataSeeder.php

# 4. Update your status
# Edit docs/AGENT_STATUS_REPORTS.md
```

### Workflow Summary

```
1. Cursor: Core Infrastructure (foundation)
   ↓
2. Continue: Domain Packages (implementation)
   ↓
3. Codex: Review + Frontend E2E (review & organize)
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

## Common Tasks

### Starting a New Task
1. Check AGENT_COORDINATION_HUB.md for available tasks
2. Check AGENT_TASK_BOARD.md for dependencies
3. Lock files in AGENT_COORDINATION_HUB.md
4. Update AGENT_STATUS_REPORTS.md
5. Start work

### Reporting Progress
1. Update AGENT_STATUS_REPORTS.md
2. Update AGENT_TASK_BOARD.md if status changes
3. Update AGENT_COORDINATION_HUB.md Communication Log

### Completing Work
1. Unlock files in AGENT_COORDINATION_HUB.md
2. Move task to "Ready for Review" in AGENT_TASK_BOARD.md
3. Update AGENT_HANDOFF.md with "Next for [Agent]"
4. Final status update in AGENT_STATUS_REPORTS.md

## Need Help?

- **What task should I do?** → Check AGENT_TASK_BOARD.md
- **Can I modify this file?** → Check AGENT_COORDINATION_HUB.md → File Locks
- **How do I resolve conflict?** → Check AGENT_CONFLICT_MATRIX.md
- **What's the workflow?** → Check AGENT_WORKFLOW.md
- **How do I update status?** → Check .continue/agent-instructions/coordination.md

## File Locations

All coordination files are in `docs/`:
- `docs/AGENT_COORDINATION_HUB.md`
- `docs/AGENT_TASK_BOARD.md`
- `docs/AGENT_STATUS_REPORTS.md`
- `docs/AGENT_CONFLICT_MATRIX.md`
- `docs/AGENT_WORKFLOW.md`

Instructions:
- `.continue/agent-instructions/coordination.md`

Scripts:
- `scripts/update-agent-status.sh`
- `scripts/check-conflicts.sh`

