import { useState, useEffect, useCallback } from 'react';
import { useApi } from './useApi';

interface DashboardStats {
  totalUsers?: number;
  activeUsers?: number;
  totalProjects?: number;
  activeProjects?: number;
  totalTasks?: number;
  completedTasks?: number;
  teamMembers?: number;
  systemHealth?: string;
  storageUsed?: string;
  storageTotal?: string;
  uptime?: string;
  responseTime?: string;
  errorRate?: string;
}

interface DashboardData {
  stats: DashboardStats;
  loading: boolean;
  error: string | null;
  refetch: () => void;
}

export const useAdminDashboard = (): DashboardData => {
  const { getAdminDashboard } = useApi();
  const [stats, setStats] = useState<DashboardStats>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await getAdminDashboard();
      
      if (response.data && response.data.status === 'success') {
        setStats(response.data.data);
      } else {
        setStats(response.data || {});
      }
    } catch (err: any) {
      console.error('Error fetching admin dashboard data:', err);
      setError(err.message || 'Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  }, [getAdminDashboard]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  return {
    stats,
    loading,
    error,
    refetch: fetchData,
  };
};

export const useAppDashboard = (): DashboardData => {
  const { getAppDashboard } = useApi();
  const [stats, setStats] = useState<DashboardStats>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await getAppDashboard();
      
      if (response.data && response.data.success) {
        setStats(response.data.stats);
      } else {
        setStats(response.data || {});
      }
    } catch (err: any) {
      console.error('Error fetching app dashboard data:', err);
      setError(err.message || 'Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  }, [getAppDashboard]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  return {
    stats,
    loading,
    error,
    refetch: fetchData,
  };
};
