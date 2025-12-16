/**
 * Task Status Mapper
 * 
 * Maps between frontend status values and backend standardized status values.
 * Handles backward compatibility during migration period.
 */

/**
 * Backend standardized status values
 */
export type BackendTaskStatus = 'backlog' | 'in_progress' | 'blocked' | 'done' | 'canceled';

/**
 * Frontend status values (legacy support)
 */
export type FrontendTaskStatus = 'pending' | 'in_progress' | 'on_hold' | 'completed' | 'cancelled' | BackendTaskStatus;

/**
 * Status mapping from frontend to backend
 */
const FRONTEND_TO_BACKEND_MAP: Record<string, BackendTaskStatus> = {
  'pending': 'backlog',
  'completed': 'done',
  'cancelled': 'canceled',
  'on_hold': 'blocked',
  // Already standardized values pass through
  'backlog': 'backlog',
  'in_progress': 'in_progress',
  'blocked': 'blocked',
  'done': 'done',
  'canceled': 'canceled',
};

/**
 * Status mapping from backend to frontend
 */
const BACKEND_TO_FRONTEND_MAP: Record<BackendTaskStatus, FrontendTaskStatus> = {
  'backlog': 'backlog',
  'in_progress': 'in_progress',
  'blocked': 'blocked',
  'done': 'done',
  'canceled': 'canceled',
};

/**
 * Map frontend status to backend standardized status
 * 
 * @param frontendStatus - Frontend status value (may be legacy or standardized)
 * @returns Backend standardized status
 */
export function mapToBackendStatus(frontendStatus: string): BackendTaskStatus {
  const normalized = frontendStatus.toLowerCase().trim();
  const mapped = FRONTEND_TO_BACKEND_MAP[normalized];
  
  if (!mapped) {
    // If not found in map, assume it's already standardized
    if (['backlog', 'in_progress', 'blocked', 'done', 'canceled'].includes(normalized)) {
      return normalized as BackendTaskStatus;
    }
    
    // Fallback to backlog for unknown statuses
    console.warn(`Unknown task status "${frontendStatus}", defaulting to "backlog"`);
    return 'backlog';
  }
  
  return mapped;
}

/**
 * Map backend status to frontend status
 * 
 * @param backendStatus - Backend standardized status
 * @returns Frontend status (standardized format)
 */
export function mapToFrontendStatus(backendStatus: string): FrontendTaskStatus {
  const normalized = backendStatus.toLowerCase().trim() as BackendTaskStatus;
  return BACKEND_TO_FRONTEND_MAP[normalized] || normalized;
}

/**
 * Check if a status is a valid backend status
 * 
 * @param status - Status to check
 * @returns True if valid backend status
 */
export function isValidBackendStatus(status: string): boolean {
  const validStatuses: BackendTaskStatus[] = ['backlog', 'in_progress', 'blocked', 'done', 'canceled'];
  return validStatuses.includes(status.toLowerCase().trim() as BackendTaskStatus);
}

/**
 * Get human-readable label for a status
 * 
 * @param status - Backend status value
 * @returns Human-readable label
 */
export function getStatusLabel(status: BackendTaskStatus): string {
  const labels: Record<BackendTaskStatus, string> = {
    'backlog': 'Backlog',
    'in_progress': 'In Progress',
    'blocked': 'Blocked',
    'done': 'Done',
    'canceled': 'Canceled',
  };
  
  return labels[status] || status;
}

