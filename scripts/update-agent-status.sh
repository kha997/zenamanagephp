#!/bin/bash
# Helper script to update agent status quickly

AGENT=$1
STATUS=$2
PROGRESS=$3
TASK=$4

if [ -z "$AGENT" ] || [ -z "$STATUS" ]; then
    echo "Usage: ./scripts/update-agent-status.sh <agent> <status> [progress] [task]"
    echo "Example: ./scripts/update-agent-status.sh Cursor 'In Progress' '2/6 (33%)' 'Core Infrastructure'"
    exit 1
fi

AGENT_LOWER=$(echo "$AGENT" | tr '[:upper:]' '[:lower:]')
STATUS_FILE="docs/AGENT_STATUS_REPORTS.md"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M')

echo "Updating $AGENT status..."
echo "Status: $STATUS"
[ -n "$PROGRESS" ] && echo "Progress: $PROGRESS"
[ -n "$TASK" ] && echo "Task: $TASK"
echo "Timestamp: $TIMESTAMP"

# Note: This is a helper script - actual editing should be done manually
# or with a more sophisticated tool. This script provides the template.

cat << EOF

To update status manually, edit: $STATUS_FILE

Find the "$AGENT Status Report" section and update:
- Status: $STATUS
- Progress: ${PROGRESS:-"Update manually"}
- Current Task: ${TASK:-"Update manually"}
- Last Update: $TIMESTAMP

Or use your editor to update the file directly.

EOF

echo "✅ Status update template generated above"
echo "📝 Edit $STATUS_FILE to apply changes"

