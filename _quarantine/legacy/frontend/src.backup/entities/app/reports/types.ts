// Reports API types and interfaces
export interface Trend {
  value: number;
  direction: 'up' | 'down' | 'neutral';
}

export interface ReportsMetrics {
  total_reports: number;
  recent_reports: number;
  by_type: Record<string, number>;
  downloads: number;
  trends?: {
    total_reports?: Trend;
    recent_reports?: Trend;
    downloads?: Trend;
  };
  period?: string;
}

export interface ReportAlert {
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

export interface ReportActivity {
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

