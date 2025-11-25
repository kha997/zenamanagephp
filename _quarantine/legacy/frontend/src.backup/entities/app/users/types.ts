// Users API types and interfaces
export interface Trend {
  value: number;
  direction: 'up' | 'down' | 'neutral';
}

export interface UsersMetrics {
  total_users: number;
  active_users: number;
  new_users: number;
  by_role: Record<string, number>;
  trends?: {
    total_users?: Trend;
    active_users?: Trend;
    new_users?: Trend;
  };
  period?: string;
}

export interface UserAlert {
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

export interface UserActivity {
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

