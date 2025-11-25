// Clients API types and interfaces

export interface Client {
  id: string;
  name: string;
  email: string;
  phone?: string;
  company?: string;
  lifecycle_stage: 'lead' | 'prospect' | 'customer' | 'inactive';
  created_at: string;
  updated_at: string;
}

export interface ClientsResponse {
  data: Client[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// Clients KPI Types
export interface ClientsMetrics {
  total_clients: number;
  active_clients: number;
  new_clients: number;
  revenue: number;
  trends?: {
    total_clients?: Trend;
    active_clients?: Trend;
    new_clients?: Trend;
    revenue?: Trend;
  };
  period?: string;
}

export interface Trend {
  value: number; // Percentage change
  direction: 'up' | 'down' | 'neutral';
}

// Clients Alert Types
export interface ClientAlert {
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

// Clients Activity Types
export interface ClientActivity {
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

