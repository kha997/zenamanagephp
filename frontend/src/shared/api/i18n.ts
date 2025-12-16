import { apiClient } from './client';
import { mapAxiosError } from './client';

export interface GetTranslationsOptions {
  locale?: string;
  namespaces?: string[];
  flat?: boolean;
}

export interface TranslationsResponse {
  success: boolean;
  locale: string;
  data: Record<string, any>;
}

/**
 * Get translations from backend API
 * 
 * @param options - Translation options
 * @param options.locale - Locale code (optional, defaults to current locale)
 * @param options.namespaces - Array of namespace names (optional, defaults to config default)
 * @param options.flat - Whether to return flat structure (optional, defaults to false)
 * @param cachedEtag - ETag from previous request for conditional request (optional)
 * @returns Promise with translations data
 */
export async function getTranslations(
  options: GetTranslationsOptions = {},
  cachedEtag?: string | null
): Promise<TranslationsResponse> {
  const params: Record<string, string> = {};
  
  if (options.locale) {
    params.locale = options.locale;
  }
  
  if (options.namespaces && options.namespaces.length > 0) {
    params.namespaces = options.namespaces.join(',');
  }
  
  if (options.flat) {
    params.flat = 'true';
  }
  
  const headers: Record<string, string> = {};
  if (cachedEtag) {
    headers['If-None-Match'] = cachedEtag;
  }
  
  try {
    const response = await apiClient.get<TranslationsResponse>(
      '/i18n/translations',
      {
        params,
        headers,
        validateStatus: (status) => status === 200 || status === 304,
      }
    );
    
    // Handle 304 Not Modified
    if (response.status === 304) {
      // Return special marker for NOT_MODIFIED
      const notModifiedError: any = new Error('NOT_MODIFIED');
      notModifiedError.isNotModified = true;
      throw notModifiedError;
    }
    
    return response.data;
  } catch (error: any) {
    // Re-throw NOT_MODIFIED errors
    if (error.isNotModified || error.message === 'NOT_MODIFIED') {
      const notModifiedError: any = new Error('NOT_MODIFIED');
      notModifiedError.isNotModified = true;
      throw notModifiedError;
    }
    // Map other errors
    throw mapAxiosError(error);
  }
}

