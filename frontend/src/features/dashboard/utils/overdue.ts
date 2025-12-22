/**
 * Overdue Utility Functions
 * 
 * Single source of truth for overdue calculation logic in frontend.
 * Matches backend OverdueService logic for consistency.
 */

export interface Task {
  id: string;
  end_date?: string | null;
  status: string;
}

export interface Project {
  id: string;
  end_date?: string | null;
  status: string;
}

/**
 * Check if a task is overdue
 * 
 * Rule: Task is overdue if:
 * - end_date < today (start of day)
 * - AND status NOT IN [done, completed, canceled, cancelled]
 * 
 * Matches backend OverdueService::isTaskOverdue() logic
 * 
 * @param task Task object with end_date and status
 * @returns boolean
 */
export function isTaskOverdue(task: Task): boolean {
  // Task must have an end_date
  if (!task.end_date) {
    return false;
  }

  // Check if end_date is in the past (before today, start of day)
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  
  const endDate = new Date(task.end_date);
  endDate.setHours(0, 0, 0, 0);

  if (endDate >= today) {
    return false;
  }

  // Check if task status is NOT in terminal/completed states
  const status = (task.status || '').toLowerCase();
  const nonOverdueStatuses = ['done', 'completed', 'canceled', 'cancelled'];
  
  return !nonOverdueStatuses.includes(status);
}

/**
 * Check if a project is overdue
 * 
 * Rule: Project is overdue if:
 * - end_date < today (start of day)
 * - AND status IN [active, on_hold]
 * 
 * Note: Planning projects are not considered overdue as they haven't started yet.
 * Completed/cancelled/archived projects are not considered overdue.
 * 
 * Matches backend OverdueService::isProjectOverdue() logic
 * 
 * @param project Project object with end_date and status
 * @returns boolean
 */
export function isProjectOverdue(project: Project): boolean {
  // Project must have an end_date
  if (!project.end_date) {
    return false;
  }

  // Check if end_date is in the past (before today, start of day)
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  
  const endDate = new Date(project.end_date);
  endDate.setHours(0, 0, 0, 0);

  if (endDate >= today) {
    return false;
  }

  // Check if project status is in active states (not planning, completed, cancelled, archived)
  const status = project.status || '';
  const activeStatuses = ['active', 'on_hold'];
  
  return activeStatuses.includes(status);
}

