import { useCallback } from 'react';
import { mapToBackendStatus } from '../../../shared/utils/taskStatusMapper';
import type { Task } from '../../types';

// Client-side transition rules (mirror backend ALLOWED_TRANSITIONS)
const ALLOWED_TRANSITIONS: Record<string, string[]> = {
  'backlog': ['in_progress', 'canceled'],
  'in_progress': ['done', 'blocked', 'canceled', 'backlog'],
  'blocked': ['in_progress', 'canceled'],
  'done': ['in_progress'],
  'canceled': ['backlog'],
};

export function useTaskTransitionValidation() {
  const canMoveToStatus = useCallback((task: Task, targetStatus: string): {
    allowed: boolean;
    reason?: string;
  } => {
    const currentStatus = mapToBackendStatus(task.status || 'backlog');
    const backendTarget = mapToBackendStatus(targetStatus);
    
    // Same status is always allowed
    if (currentStatus === backendTarget) {
      return { allowed: true };
    }
    
    // Check transition rules
    const allowed = ALLOWED_TRANSITIONS[currentStatus]?.includes(backendTarget) ?? false;
    
    if (!allowed) {
      return {
        allowed: false,
        reason: `Cannot move from "${currentStatus}" to "${backendTarget}". Allowed: ${ALLOWED_TRANSITIONS[currentStatus]?.join(', ') || 'none'}`
      };
    }
    
    // Additional checks (can be expanded)
    // Note: Full validation (dependencies, project status) requires server call
    // This is just for quick client-side feedback
    
    return { allowed: true };
  }, []);
  
  return { canMoveToStatus };
}

