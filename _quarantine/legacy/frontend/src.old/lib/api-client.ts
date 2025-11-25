/**
 * @deprecated Use '@/shared/api/client' instead
 * This file is deprecated and will be removed in a future version.
 * All API calls should use the unified API client from '@/shared/api/client'.
 * 
 * Migration guide:
 * - Replace: import { apiClient } from '@/lib/api-client'
 * - With: import { apiClient, http } from '@/shared/api/client'
 * 
 * See FRONTEND_GUIDELINES.md for API usage patterns.
 */

export * from '../shared/api/client';
