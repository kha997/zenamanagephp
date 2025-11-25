/**
 * Error Code Mapping
 * 
 * Maps backend error codes to i18n translation keys and user-friendly messages.
 * This ensures consistent error handling across the frontend.
 */

export interface ErrorDetails {
  validation?: Record<string, string[]>;
  data?: unknown;
  debug?: unknown;
}

export interface ApiError {
  ok: false;
  code: string;
  message: string;
  traceId: string;
  details?: ErrorDetails;
}

/**
 * Error code to i18n key mapping
 */
export const ERROR_I18N_KEYS: Record<string, string> = {
  // Authentication & Authorization
  UNAUTHORIZED: 'errors.unauthorized',
  FORBIDDEN: 'errors.forbidden',
  AUTH_REQUIRED: 'errors.auth_required',
  
  // Validation
  VALIDATION_FAILED: 'errors.validation_failed',
  BAD_REQUEST: 'errors.bad_request',
  
  // Not Found
  NOT_FOUND: 'errors.not_found',
  TASK_NOT_FOUND: 'errors.task_not_found',
  PROJECT_NOT_FOUND: 'errors.project_not_found',
  DOCUMENT_NOT_FOUND: 'errors.document_not_found',
  USER_NOT_FOUND: 'errors.user_not_found',
  
  // Conflict
  CONFLICT: 'errors.conflict',
  IDEMPOTENCY_KEY_CONFLICT: 'errors.idempotency_key_conflict',
  IDEMPOTENCY_KEY_REQUIRED: 'errors.idempotency_key_required',
  
  // Rate Limiting
  RATE_LIMIT_EXCEEDED: 'errors.rate_limit_exceeded',
  
  // Server Errors
  SERVER_ERROR: 'errors.server_error',
  SERVICE_UNAVAILABLE: 'errors.service_unavailable',
  
  // Domain-specific errors
  PROJECT_ALREADY_EXISTS: 'errors.project_already_exists',
  TASK_CANNOT_BE_MOVED: 'errors.task_cannot_be_moved',
  INSUFFICIENT_PERMISSIONS: 'errors.insufficient_permissions',
  TENANT_ISOLATION_VIOLATION: 'errors.tenant_isolation_violation',
};

/**
 * Default error messages (fallback if i18n key not found)
 */
export const DEFAULT_ERROR_MESSAGES: Record<string, string> = {
  UNAUTHORIZED: 'You are not authenticated. Please log in.',
  FORBIDDEN: 'You do not have permission to perform this action.',
  AUTH_REQUIRED: 'Authentication is required.',
  VALIDATION_FAILED: 'The provided data is invalid.',
  BAD_REQUEST: 'The request is invalid.',
  NOT_FOUND: 'The requested resource was not found.',
  TASK_NOT_FOUND: 'The task was not found.',
  PROJECT_NOT_FOUND: 'The project was not found.',
  DOCUMENT_NOT_FOUND: 'The document was not found.',
  USER_NOT_FOUND: 'The user was not found.',
  CONFLICT: 'A conflict occurred with the current state of the resource.',
  IDEMPOTENCY_KEY_CONFLICT: 'This request was already processed with different data.',
  IDEMPOTENCY_KEY_REQUIRED: 'Idempotency-Key header is required for this operation.',
  RATE_LIMIT_EXCEEDED: 'Too many requests. Please try again later.',
  SERVER_ERROR: 'An internal server error occurred. Please try again later.',
  SERVICE_UNAVAILABLE: 'The service is temporarily unavailable. Please try again later.',
  PROJECT_ALREADY_EXISTS: 'A project with this code already exists.',
  TASK_CANNOT_BE_MOVED: 'This task cannot be moved to the requested status.',
  INSUFFICIENT_PERMISSIONS: 'You do not have sufficient permissions.',
  TENANT_ISOLATION_VIOLATION: 'Access denied: tenant isolation violation.',
};

/**
 * Get i18n key for an error code
 */
export function getErrorI18nKey(code: string): string {
  return ERROR_I18N_KEYS[code] || 'errors.generic';
}

/**
 * Get default message for an error code
 */
export function getDefaultErrorMessage(code: string): string {
  return DEFAULT_ERROR_MESSAGES[code] || 'An error occurred. Please try again.';
}

/**
 * Check if error is a validation error
 */
export function isValidationError(error: ApiError): boolean {
  return error.code === 'VALIDATION_FAILED' || 
         error.details?.validation !== undefined;
}

/**
 * Check if error is a client error (4xx)
 */
export function isClientError(statusCode: number): boolean {
  return statusCode >= 400 && statusCode < 500;
}

/**
 * Check if error is a server error (5xx)
 */
export function isServerError(statusCode: number): boolean {
  return statusCode >= 500;
}

/**
 * Check if error is retryable
 */
export function isRetryableError(statusCode: number, code: string): boolean {
  // Retryable: 5xx errors (except 501, 505)
  if (statusCode >= 500 && statusCode !== 501 && statusCode !== 505) {
    return true;
  }
  
  // Retryable: 429 (rate limit) and 503 (service unavailable)
  if (statusCode === 429 || statusCode === 503) {
    return true;
  }
  
  // Not retryable: client errors (4xx)
  return false;
}

