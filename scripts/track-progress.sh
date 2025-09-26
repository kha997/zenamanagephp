#!/bin/bash

# 📊 ZENA SYSTEM IMPROVEMENT PROGRESS TRACKER
# Version: 1.0
# Date: 20/09/2025
# Author: Senior Software Architect

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Progress tracking file
PROGRESS_FILE="improvement_progress.json"

# Initialize progress file if it doesn't exist
init_progress() {
    if [ ! -f "$PROGRESS_FILE" ]; then
        cat > "$PROGRESS_FILE" << 'EOF'
{
    "phases": {
        "phase1": {
            "name": "Security & Performance",
            "weeks": "1-2",
            "total_tasks": 20,
            "completed_tasks": 0,
            "progress_percentage": 0,
            "status": "not_started",
            "start_date": null,
            "end_date": null,
            "tasks": []
        },
        "phase2": {
            "name": "Testing & Monitoring",
            "weeks": "3-4",
            "total_tasks": 20,
            "completed_tasks": 0,
            "progress_percentage": 0,
            "status": "not_started",
            "start_date": null,
            "end_date": null,
            "tasks": []
        },
        "phase3": {
            "name": "Code Quality & Documentation",
            "weeks": "5-8",
            "total_tasks": 20,
            "completed_tasks": 0,
            "progress_percentage": 0,
            "status": "not_started",
            "start_date": null,
            "end_date": null,
            "tasks": []
        },
        "phase4": {
            "name": "Optimization & Maintenance",
            "weeks": "9-12",
            "total_tasks": 20,
            "completed_tasks": 0,
            "progress_percentage": 0,
            "status": "not_started",
            "start_date": null,
            "end_date": null,
            "tasks": []
        }
    },
    "overall": {
        "total_tasks": 80,
        "completed_tasks": 0,
        "progress_percentage": 0,
        "current_phase": "phase1",
        "start_date": "2025-09-20",
        "estimated_end_date": "2025-12-13"
    }
}
EOF
        echo -e "${GREEN}✅ Progress tracking initialized${NC}"
    fi
}

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
}

info() {
    echo -e "${CYAN}ℹ️  $1${NC}"
}

# Update task status
update_task() {
    local phase=$1
    local task_id=$2
    local status=$3
    local notes=$4
    
    log "Updating task $task_id in $phase to $status"
    
    # Update progress file using jq
    if command -v jq >/dev/null 2>&1; then
        # Find and update the task
        jq --arg phase "$phase" --arg task_id "$task_id" --arg status "$status" --arg notes "$notes" \
           '.phases[$phase].tasks |= map(if .id == $task_id then .status = $status | .completed_date = (if $status == "completed" then now | strftime("%Y-%m-%d") else null end) | .notes = $notes else . end)' \
           "$PROGRESS_FILE" > "${PROGRESS_FILE}.tmp" && mv "${PROGRESS_FILE}.tmp" "$PROGRESS_FILE"
        
        # Update phase progress
        update_phase_progress "$phase"
        success "Task $task_id updated to $status"
    else
        warning "jq not installed. Please install jq for JSON manipulation."
    fi
}

# Update phase progress
update_phase_progress() {
    local phase=$1
    
    if command -v jq >/dev/null 2>&1; then
        # Count completed tasks in phase
        local completed=$(jq ".phases.$phase.tasks | map(select(.status == \"completed\")) | length" "$PROGRESS_FILE")
        local total=$(jq ".phases.$phase.total_tasks" "$PROGRESS_FILE")
        local percentage=$((completed * 100 / total))
        
        # Update phase progress
        jq --arg phase "$phase" --argjson completed "$completed" --argjson percentage "$percentage" \
           '.phases[$phase].completed_tasks = $completed | .phases[$phase].progress_percentage = $percentage' \
           "$PROGRESS_FILE" > "${PROGRESS_FILE}.tmp" && mv "${PROGRESS_FILE}.tmp" "$PROGRESS_FILE"
        
        # Update overall progress
        update_overall_progress
    fi
}

# Update overall progress
update_overall_progress() {
    if command -v jq >/dev/null 2>&1; then
        local total_completed=$(jq '[.phases[].completed_tasks] | add' "$PROGRESS_FILE")
        local total_tasks=$(jq '.overall.total_tasks' "$PROGRESS_FILE")
        local percentage=$((total_completed * 100 / total_tasks))
        
        # Update overall progress
        jq --argjson completed "$total_completed" --argjson percentage "$percentage" \
           '.overall.completed_tasks = $completed | .overall.progress_percentage = $percentage' \
           "$PROGRESS_FILE" > "${PROGRESS_FILE}.tmp" && mv "${PROGRESS_FILE}.tmp" "$PROGRESS_FILE"
    fi
}

# Show progress dashboard
show_dashboard() {
    echo -e "${PURPLE}📊 ZENA SYSTEM IMPROVEMENT PROGRESS DASHBOARD${NC}"
    echo -e "${PURPLE}================================================${NC}"
    echo ""
    
    if command -v jq >/dev/null 2>&1; then
        # Overall progress
        local overall_progress=$(jq '.overall.progress_percentage' "$PROGRESS_FILE")
        local overall_completed=$(jq '.overall.completed_tasks' "$PROGRESS_FILE")
        local overall_total=$(jq '.overall.total_tasks' "$PROGRESS_FILE")
        
        echo -e "${CYAN}🎯 OVERALL PROGRESS${NC}"
        echo -e "   Completed: ${GREEN}$overall_completed${NC}/${BLUE}$overall_total${NC} tasks"
        echo -e "   Progress: ${GREEN}$overall_progress%${NC}"
        echo ""
        
        # Phase progress
        echo -e "${CYAN}📋 PHASE PROGRESS${NC}"
        for phase in phase1 phase2 phase3 phase4; do
            local phase_name=$(jq -r ".phases.$phase.name" "$PROGRESS_FILE")
            local phase_progress=$(jq ".phases.$phase.progress_percentage" "$PROGRESS_FILE")
            local phase_completed=$(jq ".phases.$phase.completed_tasks" "$PROGRESS_FILE")
            local phase_total=$(jq ".phases.$phase.total_tasks" "$PROGRESS_FILE")
            local phase_status=$(jq -r ".phases.$phase.status" "$PROGRESS_FILE")
            
            # Color based on progress
            local progress_color=$RED
            if [ "$phase_progress" -ge 80 ]; then
                progress_color=$GREEN
            elif [ "$phase_progress" -ge 50 ]; then
                progress_color=$YELLOW
            fi
            
            echo -e "   ${BLUE}$phase_name${NC} (${BLUE}$phase${NC})"
            echo -e "      Status: ${progress_color}$phase_status${NC}"
            echo -e "      Progress: ${progress_color}$phase_progress%${NC} (${GREEN}$phase_completed${NC}/${BLUE}$phase_total${NC})"
            echo ""
        done
        
        # Recent tasks
        echo -e "${CYAN}📝 RECENT TASKS${NC}"
        jq -r '.phases[].tasks[] | select(.status == "completed") | "   ✅ " + .id + ": " + .name' "$PROGRESS_FILE" | tail -5
        echo ""
        
        # Upcoming tasks
        echo -e "${CYAN}⏭️  UPCOMING TASKS${NC}"
        jq -r '.phases[].tasks[] | select(.status == "not_started" or .status == "in_progress") | "   📋 " + .id + ": " + .name' "$PROGRESS_FILE" | head -5
        echo ""
        
    else
        warning "jq not installed. Please install jq to view the dashboard."
    fi
}

# Generate progress report
generate_report() {
    local report_file="improvement_progress_report_$(date +%Y%m%d).md"
    
    log "Generating progress report: $report_file"
    
    cat > "$report_file" << 'EOF'
# 📊 ZENA SYSTEM IMPROVEMENT PROGRESS REPORT

**Generated:** $(date)
**Project:** Zena Project Management System
**Phase:** System Improvement Plan

## 🎯 Overall Progress

EOF

    if command -v jq >/dev/null 2>&1; then
        local overall_progress=$(jq '.overall.progress_percentage' "$PROGRESS_FILE")
        local overall_completed=$(jq '.overall.completed_tasks' "$PROGRESS_FILE")
        local overall_total=$(jq '.overall.total_tasks' "$PROGRESS_FILE")
        
        cat >> "$report_file" << EOF
- **Total Tasks:** $overall_total
- **Completed Tasks:** $overall_completed
- **Progress:** $overall_progress%
- **Status:** $(if [ "$overall_progress" -ge 80 ]; then echo "🟢 On Track"; elif [ "$overall_progress" -ge 50 ]; then echo "🟡 In Progress"; else echo "🔴 Behind Schedule"; fi)

## 📋 Phase Progress

EOF

        for phase in phase1 phase2 phase3 phase4; do
            local phase_name=$(jq -r ".phases.$phase.name" "$PROGRESS_FILE")
            local phase_progress=$(jq ".phases.$phase.progress_percentage" "$PROGRESS_FILE")
            local phase_completed=$(jq ".phases.$phase.completed_tasks" "$PROGRESS_FILE")
            local phase_total=$(jq ".phases.$phase.total_tasks" "$PROGRESS_FILE")
            local phase_status=$(jq -r ".phases.$phase.status" "$PROGRESS_FILE")
            
            cat >> "$report_file" << EOF
### $phase_name
- **Status:** $phase_status
- **Progress:** $phase_progress% ($phase_completed/$phase_total tasks)
- **Status:** $(if [ "$phase_progress" -ge 80 ]; then echo "🟢 Completed"; elif [ "$phase_progress" -ge 50 ]; then echo "🟡 In Progress"; else echo "🔴 Not Started"; fi)

EOF
        done
        
        cat >> "$report_file" << 'EOF'

## 📝 Completed Tasks

EOF

        jq -r '.phases[].tasks[] | select(.status == "completed") | "- ✅ " + .id + ": " + .name' "$PROGRESS_FILE" >> "$report_file"
        
        cat >> "$report_file" << 'EOF'

## ⏭️ Upcoming Tasks

EOF

        jq -r '.phases[].tasks[] | select(.status == "not_started" or .status == "in_progress") | "- 📋 " + .id + ": " + .name' "$PROGRESS_FILE" >> "$report_file"
        
    fi
    
    cat >> "$report_file" << 'EOF'

## 🎯 Next Steps

1. Review completed tasks
2. Address any blockers
3. Plan next phase activities
4. Update team on progress

## 📞 Contact

For questions about this report, contact the improvement plan team.

---
*Report generated automatically by Zena Improvement Progress Tracker*
EOF

    success "Progress report generated: $report_file"
}

# Add new task
add_task() {
    local phase=$1
    local task_id=$2
    local task_name=$3
    local priority=$4
    local estimate=$5
    local owner=$6
    
    log "Adding new task: $task_id to $phase"
    
    if command -v jq >/dev/null 2>&1; then
        local task_json=$(jq -n \
            --arg id "$task_id" \
            --arg name "$task_name" \
            --arg priority "$priority" \
            --arg estimate "$estimate" \
            --arg owner "$owner" \
            '{id: $id, name: $name, priority: $priority, estimate: $estimate, owner: $owner, status: "not_started", created_date: now | strftime("%Y-%m-%d"), completed_date: null, notes: ""}')
        
        jq --arg phase "$phase" --argjson task "$task_json" \
           '.phases[$phase].tasks += [$task]' \
           "$PROGRESS_FILE" > "${PROGRESS_FILE}.tmp" && mv "${PROGRESS_FILE}.tmp" "$PROGRESS_FILE"
        
        success "Task $task_id added to $phase"
    else
        warning "jq not installed. Please install jq for JSON manipulation."
    fi
}

# Start phase
start_phase() {
    local phase=$1
    
    log "Starting phase: $phase"
    
    if command -v jq >/dev/null 2>&1; then
        jq --arg phase "$phase" --arg date "$(date +%Y-%m-%d)" \
           '.phases[$phase].status = "in_progress" | .phases[$phase].start_date = $date' \
           "$PROGRESS_FILE" > "${PROGRESS_FILE}.tmp" && mv "${PROGRESS_FILE}.tmp" "$PROGRESS_FILE"
        
        success "Phase $phase started"
    else
        warning "jq not installed. Please install jq for JSON manipulation."
    fi
}

# Complete phase
complete_phase() {
    local phase=$1
    
    log "Completing phase: $phase"
    
    if command -v jq >/dev/null 2>&1; then
        jq --arg phase "$phase" --arg date "$(date +%Y-%m-%d)" \
           '.phases[$phase].status = "completed" | .phases[$phase].end_date = $date' \
           "$PROGRESS_FILE" > "${PROGRESS_FILE}.tmp" && mv "${PROGRESS_FILE}.tmp" "$PROGRESS_FILE"
        
        success "Phase $phase completed"
    else
        warning "jq not installed. Please install jq for JSON manipulation."
    fi
}

# Main execution function
main() {
    log "📊 Starting Zena Improvement Progress Tracker"
    
    init_progress
    
    case "${1:-dashboard}" in
        "dashboard")
            show_dashboard
            ;;
        "update")
            if [ $# -lt 4 ]; then
                echo "Usage: $0 update <phase> <task_id> <status> [notes]"
                exit 1
            fi
            update_task "$2" "$3" "$4" "${5:-}"
            ;;
        "add")
            if [ $# -lt 7 ]; then
                echo "Usage: $0 add <phase> <task_id> <task_name> <priority> <estimate> <owner>"
                exit 1
            fi
            add_task "$2" "$3" "$4" "$5" "$6" "$7"
            ;;
        "start-phase")
            if [ $# -lt 2 ]; then
                echo "Usage: $0 start-phase <phase>"
                exit 1
            fi
            start_phase "$2"
            ;;
        "complete-phase")
            if [ $# -lt 2 ]; then
                echo "Usage: $0 complete-phase <phase>"
                exit 1
            fi
            complete_phase "$2"
            ;;
        "report")
            generate_report
            ;;
        *)
            echo "Usage: $0 [dashboard|update|add|start-phase|complete-phase|report]"
            echo ""
            echo "Commands:"
            echo "  dashboard              - Show progress dashboard"
            echo "  update <phase> <task_id> <status> [notes] - Update task status"
            echo "  add <phase> <task_id> <name> <priority> <estimate> <owner> - Add new task"
            echo "  start-phase <phase>    - Start a phase"
            echo "  complete-phase <phase> - Complete a phase"
            echo "  report                 - Generate progress report"
            exit 1
            ;;
    esac
    
    success "🎉 Progress tracking completed!"
}

# Run main function
main "$@"
