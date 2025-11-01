/**
 * Security Realtime - WebSocket updates for security events
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Echo
window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true,
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('admin_token')}`
        }
    }
});

class SecurityRealtime {
    constructor() {
        this.channel = null;
        this.isConnected = false;
        this.eventQueue = [];
        this.debounceTimer = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        
        this.init();
    }

    init() {
        this.connect();
        this.setupEventHandlers();
    }

    connect() {
        try {
            this.channel = window.Echo.private('admin-security');
            this.setupListeners();
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.updateConnectionStatus('connected');
        } catch (error) {
            console.error('Failed to connect to security channel:', error);
            this.handleConnectionError();
        }
    }

    setupListeners() {
        // Login Failed Events
        this.channel.listen('.security.login_failed', (event) => {
            this.handleLoginFailed(event);
        });

        // Key Revoked Events
        this.channel.listen('.security.key_revoked', (event) => {
            this.handleKeyRevoked(event);
        });

        // Session Ended Events
        this.channel.listen('.security.session_ended', (event) => {
            this.handleSessionEnded(event);
        });

        // Connection status
        this.channel.subscribed(() => {
            this.isConnected = true;
            this.updateConnectionStatus('connected');
        });

        this.channel.error((error) => {
            console.error('Channel error:', error);
            this.handleConnectionError();
        });
    }

    setupEventHandlers() {
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseUpdates();
            } else {
                this.resumeUpdates();
            }
        });

        // Handle window focus/blur
        window.addEventListener('focus', () => this.resumeUpdates());
        window.addEventListener('blur', () => this.pauseUpdates());
    }

    handleLoginFailed(event) {
        this.queueEvent('login_failed', event);
        this.updateKPICounter('failedLogins', 1);
        this.prependToAuditStream({
            type: 'login_failed',
            timestamp: event.ts,
            email: event.email,
            ip: event.ip,
            country: event.country,
            tenant: event.tenant
        });
    }

    handleKeyRevoked(event) {
        this.queueEvent('key_revoked', event);
        this.updateKPICounter('riskyKeys', -1);
        this.showToast('Key Revoked', `API key revoked for ${event.ownerEmail}`, 'warning');
    }

    handleSessionEnded(event) {
        this.queueEvent('session_ended', event);
        this.updateKPICounter('activeSessions', -1);
        this.showToast('Session Ended', `Session ended for ${event.userEmail}`, 'info');
    }

    queueEvent(type, event) {
        this.eventQueue.push({ type, event, timestamp: Date.now() });
        this.debounceEvents();
    }

    debounceEvents() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.processEventQueue();
        }, 1000); // 1 second debounce
    }

    processEventQueue() {
        if (this.eventQueue.length === 0) return;

        const events = [...this.eventQueue];
        this.eventQueue = [];

        // Update charts with batched events
        this.updateCharts(events);
        
        // Log analytics
        this.logAnalytics(events);
    }

    updateKPICounter(kpiName, delta) {
        const kpiElement = document.querySelector(`[data-kpi="${kpiName}"]`);
        if (kpiElement) {
            const currentValue = parseInt(kpiElement.textContent) || 0;
            const newValue = Math.max(0, currentValue + delta);
            kpiElement.textContent = newValue;
            
            // Add visual feedback
            kpiElement.classList.add('updated');
            setTimeout(() => kpiElement.classList.remove('updated'), 1000);
        }
    }

    prependToAuditStream(item) {
        const auditTable = document.querySelector('#audit-table tbody');
        if (auditTable) {
            const row = this.createAuditRow(item);
            auditTable.insertBefore(row, auditTable.firstChild);
            
            // Limit to 100 rows to prevent DOM bloat
            const rows = auditTable.querySelectorAll('tr');
            if (rows.length > 100) {
                auditTable.removeChild(rows[rows.length - 1]);
            }
        }
    }

    createAuditRow(item) {
        const row = document.createElement('tr');
        row.className = 'bg-red-50 animate-pulse';
        
        const statusClass = item.type === 'login_failed' ? 'text-red-600' : 'text-green-600';
        const statusText = item.type === 'login_failed' ? 'Failed' : 'Success';
        
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.timestamp}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.email}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm ${statusClass}">${statusText}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.ip}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.tenant || 'N/A'}</td>
        `;
        
        // Remove animation class after a delay
        setTimeout(() => {
            row.classList.remove('animate-pulse', 'bg-red-50');
        }, 2000);
        
        return row;
    }

    updateCharts(events) {
        // Update chart data points
        events.forEach(({ type, event }) => {
            switch (type) {
                case 'login_failed':
                    this.addChartDataPoint('failed-logins-chart', event.ts, 1);
                    break;
                case 'session_ended':
                    this.addChartDataPoint('active-sessions-chart', event.ts, -1);
                    break;
            }
        });
    }

    addChartDataPoint(chartId, timestamp, value) {
        const canvas = document.getElementById(chartId);
        if (canvas && canvas._chart) {
            const chart = canvas._chart;
            const dataset = chart.data.datasets[0];
            
            // Add new data point
            dataset.data.push(value);
            chart.data.labels.push(timestamp);
            
            // Keep only last 30 points
            if (dataset.data.length > 30) {
                dataset.data.shift();
                chart.data.labels.shift();
            }
            
            // Update chart
            chart.update('none');
        }
    }

    showToast(title, message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'warning' ? 'bg-yellow-100 border-yellow-400 text-yellow-800' :
            type === 'error' ? 'bg-red-100 border-red-400 text-red-800' :
            'bg-blue-100 border-blue-400 text-blue-800'
        }`;
        
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i>
                <div>
                    <div class="font-semibold">${title}</div>
                    <div class="text-sm">${message}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }

    updateConnectionStatus(status) {
        const statusElement = document.querySelector('#connection-status');
        if (statusElement) {
            statusElement.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                status === 'connected' ? 'bg-green-100 text-green-800' :
                status === 'connecting' ? 'bg-yellow-100 text-yellow-800' :
                'bg-red-100 text-red-800'
            }`;
            
            statusElement.innerHTML = `
                <i class="fas fa-${status === 'connected' ? 'wifi' : 'wifi-slash'} mr-1"></i>
                ${status === 'connected' ? 'Live' : status === 'connecting' ? 'Reconnecting...' : 'Offline'}
            `;
        }
    }

    handleConnectionError() {
        this.isConnected = false;
        this.updateConnectionStatus('disconnected');
        
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            setTimeout(() => {
                this.updateConnectionStatus('connecting');
                this.connect();
            }, Math.pow(2, this.reconnectAttempts) * 1000); // Exponential backoff
        }
    }

    pauseUpdates() {
        // Pause real-time updates when page is not visible
        if (this.channel) {
            this.channel.stopListening('.security.login_failed');
            this.channel.stopListening('.security.key_revoked');
            this.channel.stopListening('.security.session_ended');
        }
    }

    resumeUpdates() {
        // Resume real-time updates when page becomes visible
        if (this.channel && this.isConnected) {
            this.setupListeners();
        }
    }

    logAnalytics(events) {
        // Log analytics events
        events.forEach(({ type }) => {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'security_realtime_event', {
                    event_category: 'Security',
                    event_label: type
                });
            }
        });
    }

    disconnect() {
        if (this.channel) {
            this.channel.stopListening();
            this.channel = null;
        }
        this.isConnected = false;
        this.updateConnectionStatus('disconnected');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.securityRealtime = new SecurityRealtime();
});

export default SecurityRealtime;
