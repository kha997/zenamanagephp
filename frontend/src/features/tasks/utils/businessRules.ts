import type { Task } from '../types';

export interface BusinessRule {
  title: string;
  description: string;
}

/**
 * Get business rules relevant to a task
 * Returns null if no rules apply
 */
export function getBusinessRulesForTask(task: Task): BusinessRule | null {
  // Check project status
  if (task.project?.status === 'archived') {
    return {
      title: 'Project Archived',
      description: 'Tasks in archived projects cannot be modified. Unarchive the project to make changes.'
    };
  }
  
  // Check if project is completed or cancelled
  if (task.project?.status === 'completed' || task.project?.status === 'cancelled') {
    return {
      title: 'Project Completed/Cancelled',
      description: 'This project is in a terminal state. Task modifications are restricted.'
    };
  }
  
  // Check dependencies
  if (task.dependencies && Array.isArray(task.dependencies) && task.dependencies.length > 0) {
    // Note: We can't check if dependencies are done without fetching them
    // This is a simplified check - full validation happens on server
    return {
      title: 'Dependencies Required',
      description: `This task has ${task.dependencies.length} dependency(ies). Complete them first to start this task.`
    };
  }
  
  // Check if task is in terminal state
  const status = task.status;
  if (status === 'done' || status === 'completed' || status === 'canceled' || status === 'cancelled') {
    return {
      title: 'Task Completed',
      description: 'This task is in a terminal state. You can reopen it if needed.'
    };
  }
  
  // Check if task is blocked
  if (status === 'blocked') {
    return {
      title: 'Task Blocked',
      description: 'This task is currently blocked. Resolve the blocking issue to continue.'
    };
  }
  
  return null;
}

