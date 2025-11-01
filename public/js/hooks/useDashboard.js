import { useState, useEffect, useCallback } from 'react';
import { useApi } from './useApi';
export const useAdminDashboard = () => {
    const { getAdminDashboard } = useApi();
    const [stats, setStats] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const fetchData = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await getAdminDashboard();
            if (response.data && response.data.status === 'success') {
                setStats(response.data.data);
            }
            else {
                setStats(response.data || {});
            }
        }
        catch (err) {
            console.error('Error fetching admin dashboard data:', err);
            setError(err.message || 'Failed to load dashboard data');
        }
        finally {
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
export const useAppDashboard = () => {
    const { getAppDashboard } = useApi();
    const [stats, setStats] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const fetchData = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await getAppDashboard();
            if (response.data && response.data.success) {
                setStats(response.data.stats);
            }
            else {
                setStats(response.data || {});
            }
        }
        catch (err) {
            console.error('Error fetching app dashboard data:', err);
            setError(err.message || 'Failed to load dashboard data');
        }
        finally {
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
