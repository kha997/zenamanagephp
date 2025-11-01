import { useCallback } from 'react';
export const useApi = () => {
    const baseUrl = '/api'; // Adjust this to match your API base URL
    // Get auth token from localStorage or use default test token
    const getAuthToken = () => {
        return localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
    };
    const getHeaders = (includeAuth = true) => {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        if (includeAuth) {
            headers['Authorization'] = `Bearer ${getAuthToken()}`;
        }
        return headers;
    };
    const handleResponse = async (response) => {
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw {
                message: errorData.message || `HTTP ${response.status}`,
                status: response.status,
                errors: errorData.errors
            };
        }
        const data = await response.json();
        return {
            data: data.data || data,
            message: data.message,
            status: response.status
        };
    };
    const get = useCallback(async (url, includeAuth = true) => {
        const response = await fetch(`${baseUrl}${url}`, {
            method: 'GET',
            headers: getHeaders(includeAuth),
        });
        return handleResponse(response);
    }, []);
    const post = useCallback(async (url, data, includeAuth = true) => {
        const response = await fetch(`${baseUrl}${url}`, {
            method: 'POST',
            headers: getHeaders(includeAuth),
            body: data ? JSON.stringify(data) : undefined,
        });
        return handleResponse(response);
    }, []);
    const put = useCallback(async (url, data, includeAuth = true) => {
        const response = await fetch(`${baseUrl}${url}`, {
            method: 'PUT',
            headers: getHeaders(includeAuth),
            body: data ? JSON.stringify(data) : undefined,
        });
        return handleResponse(response);
    }, []);
    const del = useCallback(async (url, includeAuth = true) => {
        const response = await fetch(`${baseUrl}${url}`, {
            method: 'DELETE',
            headers: getHeaders(includeAuth),
        });
        return handleResponse(response);
    }, []);
    // Dashboard API methods
    const getAdminDashboard = useCallback(async () => {
        const response = await fetch('/test-api-admin-dashboard', {
            method: 'GET',
            headers: getHeaders(true),
        });
        return handleResponse(response);
    }, []);
    const getAppDashboard = useCallback(async () => {
        const response = await fetch('/test-api-app-dashboard', {
            method: 'GET',
            headers: getHeaders(true),
        });
        return handleResponse(response);
    }, []);
    return {
        get,
        post,
        put,
        delete: del,
        getAdminDashboard,
        getAppDashboard,
        getAuthToken,
    };
};
