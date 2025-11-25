import { apiClient } from '../../../shared/api/client';
import type {
  QuotesMetrics,
  QuoteAlert,
  QuoteActivity
} from './types';
import type { ApiResponse } from '../../dashboard/types';

export const quotesApi = {
  // Get Quotes KPIs
  getQuotesKpis: async (period?: string): Promise<ApiResponse<QuotesMetrics>> => {
    const params = new URLSearchParams();
    if (period) params.append('period', period);
    
    const response = await apiClient.get(`/app/quotes/kpis?${params.toString()}`);
    return response.data;
  },

  // Get Quotes Alerts
  getQuotesAlerts: async (): Promise<ApiResponse<QuoteAlert[]>> => {
    const response = await apiClient.get('/app/quotes/alerts');
    return response.data;
  },

  // Get Quotes Activity
  getQuotesActivity: async (limit: number = 10): Promise<ApiResponse<QuoteActivity[]>> => {
    const params = new URLSearchParams();
    params.append('limit', limit.toString());
    
    const response = await apiClient.get(`/app/quotes/activity?${params.toString()}`);
    return response.data;
  },
};

