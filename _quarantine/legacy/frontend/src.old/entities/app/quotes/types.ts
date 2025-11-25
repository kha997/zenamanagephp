// Quotes API types and interfaces

export interface Quote {
  id: string;
  code: string;
  title: string;
  description: string;
  client_id: string;
  client_name: string;
  project_id?: string;
  project_name?: string;
  status: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired';
  amount: number;
  currency: string;
  valid_until: string;
  created_at: string;
  updated_at: string;
}

export interface QuotesResponse {
  data: Quote[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// Quotes KPI Types
export interface QuotesMetrics {
  total_quotes: number;
  pending_quotes: number;
  accepted_quotes: number;
  rejected_quotes: number;
  total_value: number;
  trends?: {
    total_quotes?: Trend;
    pending_quotes?: Trend;
    accepted_quotes?: Trend;
    rejected_quotes?: Trend;
    total_value?: Trend;
  };
  period?: string;
}

export interface Trend {
  value: number; // Percentage change
  direction: 'up' | 'down' | 'neutral';
}

// Quotes Alert Types
export interface QuoteAlert {
  id: string;
  title: string;
  message: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  status: 'unread' | 'read' | 'archived';
  type: string;
  source: string;
  createdAt: string;
  readAt?: string;
  metadata?: Record<string, any>;
}

// Quotes Activity Types
export interface QuoteActivity {
  id: string;
  type: string;
  action: string;
  description: string;
  timestamp: string;
  user?: {
    id: string;
    name: string;
    avatar?: string;
  };
}

