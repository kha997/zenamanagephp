import type { Task } from '../types';

export interface ErrorExplanation {
  title: string;
  description: string;
  solutions?: string[];
  actionButton?: {
    label: string;
    action: string;
    data?: any;
  };
  relatedTasks?: string[];
}

export function getErrorExplanation(
  error: { code: string; message: string; details?: any },
  task: Task,
  targetStatus: string
): ErrorExplanation {
  switch (error.code) {
    case 'dependencies_incomplete':
      return {
        title: 'Cannot Start Task',
        description: 'This task has dependencies that must be completed first.',
        solutions: [
          'Complete all dependent tasks first',
          'Or remove dependencies if they\'re no longer needed'
        ],
        relatedTasks: error.details?.dependencies || [],
        actionButton: {
          label: 'View Dependencies',
          action: 'view_dependencies',
          data: { taskId: task.id, dependencies: error.details?.dependencies }
        }
      };
      
    case 'project_status_restricted':
      return {
        title: 'Project Status Restriction',
        description: `The project "${error.details?.project_name || 'Unknown'}" is in "${error.details?.project_status || 'unknown'}" status, which doesn't allow task modifications.`,
        solutions: [
          'Change project status to "active" or "planning"',
          'Or contact project manager to update project status'
        ],
        actionButton: {
          label: 'View Project',
          action: 'view_project',
          data: { projectId: error.details?.project_id }
        }
      };
      
    case 'invalid_transition':
      return {
        title: 'Invalid Status Transition',
        description: error.message,
        solutions: [
          `From "${error.details?.from_status}", you can only move to: ${error.details?.allowed_transitions?.join(', ') || 'none'}`,
          'If you need to change status differently, consider the allowed transitions first'
        ]
      };
      
    case 'optimistic_lock_conflict':
      return {
        title: 'Task Was Modified',
        description: 'Another user has modified this task. Please refresh and try again.',
        solutions: [
          'Refresh the page to get the latest task data',
          'Then retry your move operation'
        ],
        actionButton: {
          label: 'Refresh',
          action: 'refresh'
        }
      };
      
    case 'dependents_active':
      return {
        title: 'Active Dependents Warning',
        description: error.message,
        solutions: [
          'Consider completing or canceling dependent tasks first',
          'Or proceed if you understand the impact'
        ],
        relatedTasks: error.details?.dependents || []
      };
      
    default:
      return {
        title: 'Cannot Move Task',
        description: error.message || 'An error occurred while moving the task.',
        solutions: ['Please try again or contact support if the issue persists']
      };
  }
}

