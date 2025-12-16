/**
 * Idempotency Key Helper
 * 
 * Generates and manages idempotency keys for API requests.
 * Idempotency keys ensure that duplicate requests are handled safely.
 */

/**
 * Generate a unique idempotency key for a request
 * 
 * Format: {resource}_{action}_{timestamp}_{nonce}
 * 
 * @param resource - Resource name (e.g., 'project', 'task', 'user')
 * @param action - Action name (e.g., 'create', 'update', 'delete')
 * @param additionalData - Optional additional data to include in key
 * @returns Unique idempotency key
 */
export function generateIdempotencyKey(
  resource: string,
  action: string,
  additionalData?: string | number
): string {
  const timestamp = Date.now();
  const nonce = Math.random().toString(36).substring(2, 15);
  const additional = additionalData ? `_${additionalData}` : '';
  
  return `${resource}_${action}_${timestamp}_${nonce}${additional}`;
}

/**
 * Generate idempotency key for project creation
 */
export function generateProjectCreateKey(projectCode?: string): string {
  return generateIdempotencyKey('project', 'create', projectCode);
}

/**
 * Generate idempotency key for task creation
 */
export function generateTaskCreateKey(taskName?: string): string {
  return generateIdempotencyKey('task', 'create', taskName);
}

/**
 * Generate idempotency key for user creation
 */
export function generateUserCreateKey(userEmail?: string): string {
  return generateIdempotencyKey('user', 'create', userEmail);
}

/**
 * Generate idempotency key for update operations
 */
export function generateUpdateKey(resource: string, resourceId: string): string {
  return generateIdempotencyKey(resource, 'update', resourceId);
}

/**
 * Generate idempotency key for delete operations
 */
export function generateDeleteKey(resource: string, resourceId: string): string {
  return generateIdempotencyKey(resource, 'delete', resourceId);
}

/**
 * Store idempotency key in session storage for retry scenarios
 */
export function storeIdempotencyKey(key: string, ttl: number = 86400000): void {
  if (typeof window !== 'undefined' && window.sessionStorage) {
    const expiry = Date.now() + ttl;
    sessionStorage.setItem(`idempotency:${key}`, expiry.toString());
  }
}

/**
 * Check if idempotency key is still valid
 */
export function isIdempotencyKeyValid(key: string): boolean {
  if (typeof window === 'undefined' || !window.sessionStorage) {
    return true; // Assume valid if we can't check
  }
  
  const stored = sessionStorage.getItem(`idempotency:${key}`);
  if (!stored) {
    return true; // Not stored, assume valid
  }
  
  const expiry = parseInt(stored, 10);
  return Date.now() < expiry;
}

/**
 * Clear expired idempotency keys from session storage
 */
export function clearExpiredIdempotencyKeys(): void {
  if (typeof window === 'undefined' || !window.sessionStorage) {
    return;
  }
  
  const keys = Object.keys(sessionStorage);
  const now = Date.now();
  
  keys.forEach((key) => {
    if (key.startsWith('idempotency:')) {
      const expiry = parseInt(sessionStorage.getItem(key) || '0', 10);
      if (now >= expiry) {
        sessionStorage.removeItem(key);
      }
    }
  });
}

