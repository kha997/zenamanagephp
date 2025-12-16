# How to Use Agent Coordination Prompts

**Purpose:** Hướng dẫn cách sử dụng prompts để onboard Codex và Continue

## Quick Start

### Option 1: Copy Short Prompts (Recommended)

1. **Mở file:** `docs/COPY_PASTE_PROMPTS.md`
2. **Copy prompt** phù hợp:
   - Cho Codex → Copy "For Codex (Reviewer)" section
   - Cho Continue → Copy "For Continue (Builder)" section
3. **Paste vào agent interface**
4. **Agent sẽ tự động đọc** coordination files và hiểu hệ thống

### Option 2: Use Full Prompts

1. **Mở file:**
   - Codex → `docs/PROMPT_FOR_CODEX.md`
   - Continue → `docs/PROMPT_FOR_CONTINUE.md`
2. **Copy toàn bộ nội dung**
3. **Paste vào agent**
4. **Agent sẽ có full context**

### Option 3: Point to Onboarding Guide

1. **Tell agent:** "Đọc docs/AGENT_ONBOARDING_GUIDE.md"
2. **Agent sẽ tự đọc** và hiểu hệ thống
3. **Agent sẽ follow** instructions trong guide

## Step-by-Step Instructions

### For Codex:

```
1. Copy prompt từ docs/COPY_PASTE_PROMPTS.md → "For Codex"
2. Paste vào Codex interface
3. Codex sẽ đọc coordination files
4. Codex sẽ check AGENT_TASK_BOARD.md for review items
5. Codex có thể start Frontend E2E Organization ngay (independent)
```

### For Continue:

```
1. Copy prompt từ docs/COPY_PASTE_PROMPTS.md → "For Continue"
2. Paste vào Continue interface
3. Continue sẽ đọc coordination files
4. Continue sẽ check file locks (QUAN TRỌNG!)
5. Continue sẽ đợi Core Infrastructure unlock nếu needed
6. Continue sẽ pick domain package và start work
```

## What Happens After Prompt

Sau khi paste prompt, agent sẽ:

1. ✅ **Đọc coordination files** tự động
2. ✅ **Hiểu current status** của tất cả agents
3. ✅ **Check file locks** trước khi modify
4. ✅ **Pick appropriate task** từ task board
5. ✅ **Follow workflow** procedures
6. ✅ **Update status** regularly
7. ✅ **Coordinate** với other agents

## Verification

Sau khi agent đọc prompt, bạn có thể verify:

```bash
# Check if agent updated status
cat docs/AGENT_STATUS_REPORTS.md | grep -A 20 "[Agent] Status"

# Check if agent picked task
cat docs/AGENT_TASK_BOARD.md | grep "[Agent]"

# Check communication log
cat docs/AGENT_COORDINATION_HUB.md | grep -A 5 "Communication Log"
```

## Troubleshooting

### Agent không hiểu hệ thống?
- Đảm bảo agent đọc `docs/AGENT_COORDINATION_README.md` trước
- Point agent đến `docs/AGENT_ONBOARDING_GUIDE.md`

### Agent không check file locks?
- Remind agent: "Check docs/AGENT_COORDINATION_HUB.md → File Locks section"
- Use script: `./scripts/check-conflicts.sh [file]`

### Agent không update status?
- Remind agent: "Update docs/AGENT_STATUS_REPORTS.md every 30 minutes"
- Use helper: `./scripts/update-agent-status.sh`

### Agent gặp conflict?
- Point agent đến: `docs/AGENT_CONFLICT_MATRIX.md`
- Check conflict resolution procedures

## Best Practices

1. **Always use prompts** khi onboard agent mới
2. **Verify agent đã đọc** coordination files
3. **Monitor status updates** để đảm bảo agent follow workflow
4. **Remind agent** nếu quên update status
5. **Check coordination files** regularly để ensure sync

## Files Reference

**Prompts:**
- `docs/PROMPT_FOR_CODEX.md` - Full prompt for Codex
- `docs/PROMPT_FOR_CONTINUE.md` - Full prompt for Continue
- `docs/COPY_PASTE_PROMPTS.md` - Short copy-paste ready prompts

**Guides:**
- `docs/AGENT_ONBOARDING_GUIDE.md` - Quick onboarding
- `docs/AGENT_COORDINATION_README.md` - Quick reference
- `docs/HOW_TO_USE_PROMPTS.md` - This file

**Coordination Files:**
- `docs/AGENT_COORDINATION_HUB.md` - Central hub
- `docs/AGENT_TASK_BOARD.md` - Task tracking
- `docs/AGENT_STATUS_REPORTS.md` - Status reports
- `docs/AGENT_CONFLICT_MATRIX.md` - Conflict prevention
- `docs/AGENT_WORKFLOW.md` - Workflow procedures

