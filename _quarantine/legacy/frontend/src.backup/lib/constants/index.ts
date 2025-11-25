/**
 * Các hằng số cho ứng dụng Z.E.N.A
 */

// API Endpoints
export const API_ENDPOINTS = {
  AUTH: {
    LOGIN: '/auth/login',
    LOGOUT: '/auth/logout',
    REFRESH: '/auth/refresh',
    PROFILE: '/auth/profile',
  },
  PROJECTS: {
    LIST: '/projects',
    CREATE: '/projects',
    DETAIL: (id: string) => `/projects/${id}`,
    UPDATE: (id: string) => `/projects/${id}`,
    DELETE: (id: string) => `/projects/${id}`,
  },
  TASKS: {
    LIST: (projectId: string) => `/projects/${projectId}/tasks`,
    CREATE: (projectId: string) => `/projects/${projectId}/tasks`,
    DETAIL: (projectId: string, taskId: string) => `/projects/${projectId}/tasks/${taskId}`,
    UPDATE: (projectId: string, taskId: string) => `/projects/${projectId}/tasks/${taskId}`,
    DELETE: (projectId: string, taskId: string) => `/projects/${projectId}/tasks/${taskId}`,
  },
  COMPONENTS: {
    LIST: (projectId: string) => `/projects/${projectId}/components`,
    CREATE: (projectId: string) => `/projects/${projectId}/components`,
    DETAIL: (projectId: string, componentId: string) => `/projects/${projectId}/components/${componentId}`,
  },
  CHANGE_REQUESTS: {
    LIST: '/change-requests',
    CREATE: '/change-requests',
    DETAIL: (id: string) => `/change-requests/${id}`,
    UPDATE: (id: string) => `/change-requests/${id}`,
    DELETE: (id: string) => `/change-requests/${id}`,
    SUBMIT: (id: string) => `/change-requests/${id}/submit`,
    APPROVE: (id: string) => `/change-requests/${id}/approve`,
    REJECT: (id: string) => `/change-requests/${id}/reject`,
    BY_PROJECT: (projectId: string) => `/change-requests/project/${projectId}`,
    STATISTICS: (projectId?: string) => projectId ? `/change-requests/statistics/${projectId}` : '/change-requests/statistics',
    PENDING: '/change-requests/pending-approval',
  },
  INTERACTION_LOGS: {
    LIST: '/interaction-logs',
    CREATE: '/interaction-logs',
    DETAIL: (id: string) => `/interaction-logs/${id}`,
    UPDATE: (id: string) => `/interaction-logs/${id}`,
    DELETE: (id: string) => `/interaction-logs/${id}`,
    APPROVE: (id: string) => `/interaction-logs/${id}/approve-for-client`,
    BY_TAG_PATH: '/interaction-logs/by-tag-path',
    BULK_DELETE: '/interaction-logs/bulk-delete',
    BULK_APPROVE: '/interaction-logs/bulk-approve',
    BULK_CHANGE_VISIBILITY: '/interaction-logs/bulk-change-visibility',
    
    // Project-specific endpoints
    PROJECT_LIST: (projectId: string) => `/projects/${projectId}/interaction-logs`,
    PROJECT_STATS: (projectId: string) => `/projects/${projectId}/interaction-logs/stats`,
    PROJECT_AUTOCOMPLETE: (projectId: string) => `/projects/${projectId}/interaction-logs/autocomplete-tag-path`,
    PROJECT_EXPORT: (projectId: string) => `/projects/${projectId}/interaction-logs/export`,
    
    // Task-specific endpoints
    TASK_LIST: (projectId: string, taskId: string) => `/projects/${projectId}/tasks/${taskId}/interaction-logs`,
    
    // Client-specific endpoints
    CLIENT_LIST: '/interaction-logs/client-visible',
    CLIENT_PENDING: '/interaction-logs/pending-client-approval',
  },
  TEMPLATES: {
    LIST: '/work-templates',
    CREATE: '/work-templates',
    DETAIL: (id: string) => `/work-templates/${id}`,
    UPDATE: (id: string) => `/work-templates/${id}`,
    DELETE: (id: string) => `/work-templates/${id}`,
    PREVIEW: (id: string) => `/work-templates/${id}/preview`,
    APPLY: (id: string) => `/work-templates/${id}/apply`,
    DUPLICATE: (id: string) => `/work-templates/${id}/duplicate`,
    VERSIONS: (id: string) => `/work-templates/${id}/versions`,
    SEARCH: '/search/templates',
  },
  NOTIFICATIONS: {
    LIST: '/notifications',
    MARK_READ: (id: string) => `/notifications/${id}/read`,
    MARK_ALL_READ: '/notifications/mark-all-read',
  },
} as const

// Local Storage Keys
export const STORAGE_KEYS = {
  AUTH_TOKEN: 'zena_auth_token',
  USER_PROFILE: 'zena_user_profile',
  THEME: 'zena_theme',
  LANGUAGE: 'zena_language',
  SIDEBAR_COLLAPSED: 'zena_sidebar_collapsed',
} as const

// Project Status
export const PROJECT_STATUS = {
  DRAFT: 'draft',
  ACTIVE: 'active',
  ON_HOLD: 'on_hold',
  COMPLETED: 'completed',
  CANCELLED: 'cancelled',
} as const

// Task Status
export const TASK_STATUS = {
  PENDING: 'pending',
  IN_PROGRESS: 'in_progress',
  REVIEW: 'review',
  COMPLETED: 'completed',
  CANCELLED: 'cancelled',
} as const

// User Roles
export const USER_ROLES = {
  SUPER_ADMIN: 'super_admin',
  ADMIN: 'admin',
  PROJECT_MANAGER: 'project_manager',
  TEAM_LEAD: 'team_lead',
  MEMBER: 'member',
  CLIENT: 'client',
} as const

// Interaction Log Types
export const INTERACTION_LOG_TYPES = {
  CALL: 'call',
  EMAIL: 'email',
  MEETING: 'meeting',
  NOTE: 'note',
  FEEDBACK: 'feedback',
} as const

// Interaction Log Visibility
export const INTERACTION_LOG_VISIBILITY = {
  INTERNAL: 'internal',
  CLIENT: 'client',
} as const

// Interaction Log Status
export const INTERACTION_LOG_STATUS = {
  DRAFT: 'draft',
  PENDING_APPROVAL: 'pending_approval',
  APPROVED: 'approved',
  REJECTED: 'rejected',
} as const

// Permissions
export const PERMISSIONS = {
  PROJECT: {
    CREATE: 'project.create',
    READ: 'project.read',
    UPDATE: 'project.update',
    DELETE: 'project.delete',
  },
  TASK: {
    CREATE: 'task.create',
    READ: 'task.read',
    UPDATE: 'task.update',
    DELETE: 'task.delete',
    ASSIGN: 'task.assign',
  },
  USER: {
    CREATE: 'user.create',
    READ: 'user.read',
    UPDATE: 'user.update',
    DELETE: 'user.delete',
  },
  INTERACTION_LOG: {
    CREATE: 'interaction_log.create',
    READ: 'interaction_log.read',
    UPDATE: 'interaction_log.update',
    DELETE: 'interaction_log.delete',
    APPROVE: 'interaction_log.approve',
    VIEW_CLIENT: 'interaction_log.view_client',
    BULK_OPERATIONS: 'interaction_log.bulk_operations',
  },
} as const

// Notification Types
export const NOTIFICATION_TYPES = {
  TASK_ASSIGNED: 'task_assigned',
  TASK_COMPLETED: 'task_completed',
  PROJECT_UPDATED: 'project_updated',
  DEADLINE_APPROACHING: 'deadline_approaching',
  SYSTEM_ALERT: 'system_alert',
  INTERACTION_LOG_CREATED: 'interaction_log_created',
  INTERACTION_LOG_APPROVED: 'interaction_log_approved',
  INTERACTION_LOG_CLIENT_FEEDBACK: 'interaction_log_client_feedback',
} as const

// Date Formats
export const DATE_FORMATS = {
  DISPLAY: 'DD/MM/YYYY',
  DISPLAY_WITH_TIME: 'DD/MM/YYYY HH:mm',
  API: 'YYYY-MM-DD',
  API_WITH_TIME: 'YYYY-MM-DD HH:mm:ss',
} as const

// Pagination
export const PAGINATION = {
  DEFAULT_PAGE_SIZE: 20,
  PAGE_SIZE_OPTIONS: [10, 20, 50, 100],
} as const

// Theme Colors
export const THEME_COLORS = {
  PRIMARY: '#3B82F6',
  SECONDARY: '#6B7280',
  SUCCESS: '#10B981',
  WARNING: '#F59E0B',
  ERROR: '#EF4444',
  INFO: '#06B6D4',
} as const