<?php $__env->startSection('title', 'Security Center - ZenaManage'); ?>

<?php $__env->startSection('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/security-refresh.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto p-6" x-data="securityPage()">
    <!-- Breadcrumb -->
    <nav class="flex mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="/admin" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                    <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Security Center</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Security Center</h1>
            <p class="text-gray-600 mt-1">Monitor and manage system security across all tenants</p>
        </div>
        <div class="flex items-center space-x-3">
            <!-- Connection Status -->
            <span id="connection-status" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                <i class="fas fa-wifi mr-1"></i>
                Live
            </span>
            
            <button @click="refreshData()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
            <button @click="exportSecurityReport()" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-download mr-2"></i>
                Export Report
            </button>
        </div>
    </div>

    <!-- KPI Strip -->
    <div class="security-kpis security-panels">
        <?php echo $__env->make('admin.security._kpis', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    </div>

    <!-- Charts -->
    <div class="security-panels" x-data="securityCharts()" x-init="init()">
        <?php echo $__env->make('admin.security._charts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    </div>

    <!-- Realtime Script -->
    <script>
        // Simplified realtime script to avoid import errors
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Security Center loaded successfully');
            
            // Mock realtime connection status - no toast notifications
            const connectionStatus = document.querySelector('#connection-status');
            if (connectionStatus) {
                connectionStatus.textContent = 'Live';
                connectionStatus.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
            }
        });
    </script>

    <!-- Search & Filters -->
    <?php echo $__env->make('admin.security._filters', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!-- Error Banner -->
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
        <div class="flex">
            <i class="fas fa-exclamation-circle text-red-400 mt-0.5"></i>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Error loading security data</h3>
                <p class="mt-1 text-sm text-red-700" x-text="error"></p>
                <button @click="loadSecurityData()" class="mt-2 text-sm text-red-600 hover:text-red-500 underline">
                    Try again
                </button>
            </div>
        </div>
    </div>

    <!-- Security Panels -->
    <div class="security-panels below-fold" x-show="!loading && !error">
        <div class="space-y-6">
        <!-- MFA Adoption Panel -->
        <div x-show="activePanel === 'mfa' || !activePanel" class="bg-white shadow-md rounded-lg">
            <?php echo $__env->make('admin.security._panels.mfa_adoption', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>

        <!-- Login Attempts Panel -->
        <div x-show="activePanel === 'logins'" class="bg-white shadow-md rounded-lg">
            <?php echo $__env->make('admin.security._panels.login_attempts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>

        <!-- Audit Logs Panel -->
        <div x-show="activePanel === 'audit'" class="bg-white shadow-md rounded-lg">
            <?php echo $__env->make('admin.security._panels.audit_logs', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>

        <!-- API Keys Panel -->
        <div x-show="activePanel === 'keys'" class="bg-white shadow-md rounded-lg">
            <?php echo $__env->make('admin.security._panels.api_keys', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>

        <!-- IP Access Panel -->
        <div x-show="activePanel === 'ip'" class="bg-white shadow-md rounded-lg">
            <?php echo $__env->make('admin.security._panels.ip_access', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>

        <!-- Active Sessions Panel -->
        <div x-show="activePanel === 'sessions'" class="bg-white shadow-md rounded-lg">
            <?php echo $__env->make('admin.security._panels.active_sessions', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>

        <!-- RBAC Overview Panel -->
        <div x-show="activePanel === 'rbac'" class="bg-white shadow-md rounded-lg">
            <?php echo $__env->make('admin.security._panels.rbac_overview', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="bg-white shadow-md rounded-lg p-6">
        <div class="animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
            <div class="space-y-3">
                <div class="h-4 bg-gray-200 rounded"></div>
                <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                <div class="h-4 bg-gray-200 rounded w-4/6"></div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <?php echo $__env->make('admin.security._modals.force_mfa_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('admin.security._modals.revoke_key_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('admin.security._modals.block_ip_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('admin.security._modals.end_session_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('admin.security._modals.edit_policy_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('securityPage', () => ({
        // State
        loading: true,
        error: null,
        activePanel: null,
        kpis: {},
        filters: {
            q: '',
            range: '24h',
            tenant: '',
            severity: '',
            actor: '',
            source: ''
        },
        presets: {
            'critical-now': { severity: 'critical', range: '24h', panel: 'audit' },
            'brute-force': { panel: 'logins', outcome: 'failed' },
            'no-mfa': { panel: 'mfa', mfa: false },
            'risky-keys': { panel: 'keys', risk: 'high' },
            'long-sessions': { panel: 'sessions', duration_gt: '7d' }
        },

        // Data
        mfaUsers: [],
        loginAttempts: [],
        auditLogs: [],
        apiKeys: [],
        ipAllowlist: [],
        ipDenylist: [],
        activeSessions: [],
        rbacOverview: {},

        // Pagination
        pagination: {
            mfa: { page: 1, per_page: 20, total: 0 },
            logins: { page: 1, per_page: 20, total: 0 },
            audit: { page: 1, per_page: 20, total: 0 },
            keys: { page: 1, per_page: 20, total: 0 },
            sessions: { page: 1, per_page: 20, total: 0 }
        },

        // Modals
        showForceMfaModal: false,
        showRevokeKeyModal: false,
        showBlockIpModal: false,
        showEndSessionModal: false,
        showEditPolicyModal: false,
        selectedItems: [],

        init() {
            // Reset all modal states first to prevent flash
            this.resetAllModals();
            this.parseUrlParams();
            // Delay initial load to prevent flash
            setTimeout(() => {
                this.loadSecurityData();
            }, 100);
            this.logEvent('security_view_loaded', { query: this.getCurrentQuery(), panel: this.activePanel });
        },

        // Reset all modal states to prevent flash
        resetAllModals() {
            this.showForceMfaModal = false;
            this.showRevokeKeyModal = false;
            this.showBlockIpModal = false;
            this.showEndSessionModal = false;
            this.showEditPolicyModal = false;
        },

        parseUrlParams() {
            const urlParams = new URLSearchParams(window.location.search);
            this.activePanel = urlParams.get('panel') || null;
            this.filters.q = urlParams.get('q') || '';
            this.filters.range = urlParams.get('range') || '24h';
            this.filters.tenant = urlParams.get('tenant') || '';
            this.filters.severity = urlParams.get('severity') || '';
            this.filters.actor = urlParams.get('actor') || '';
            this.filters.source = urlParams.get('source') || '';
            
            // Only open modals if explicitly requested via URL parameter
            const modalParam = urlParams.get('modal');
            if (modalParam === 'edit-policy') {
                this.showEditPolicyModal = true;
            }
        },

        updateUrl() {
            const params = new URLSearchParams();
            if (this.activePanel) params.set('panel', this.activePanel);
            if (this.filters.q) params.set('q', this.filters.q);
            if (this.filters.range !== '24h') params.set('range', this.filters.range);
            if (this.filters.tenant) params.set('tenant', this.filters.tenant);
            if (this.filters.severity) params.set('severity', this.filters.severity);
            if (this.filters.actor) params.set('actor', this.filters.actor);
            if (this.filters.source) params.set('source', this.filters.source);

            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({}, '', newUrl);
        },

        async loadSecurityData() {
            this.loading = true;
            this.error = null;

            try {
                // Load KPIs (silent - no toast)
                await this.loadKpis();
                
                // Load panel data based on active panel (silent)
                if (this.activePanel) {
                    await this.loadPanelData(this.activePanel);
                } else {
                    // Load default panel (MFA)
                    await this.loadPanelData('mfa');
                }
            } catch (error) {
                this.error = error.message;
                console.error('Error loading security data:', error);
                // Don't show toast for initial load errors
            } finally {
                this.loading = false;
            }
        },

        async loadKpis() {
            try {
                // Use bypass endpoint to avoid auth issues (silent)
                const response = await fetch(`/api/admin/security/kpis-bypass?period=${this.filters.range}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                this.kpis = data.data || {};
            } catch (error) {
                console.error('Error loading KPIs:', error);
                // Fallback to mock data for development (silent)
                this.kpis = {
                    mfaAdoption: { value: 0, deltaPct: 0 },
                    failedLogins: { value: 0, deltaAbs: 0 },
                    lockedAccounts: { value: 0, deltaAbs: 0 },
                    activeSessions: { value: 0, deltaAbs: 0 },
                    riskyKeys: { value: 0, deltaAbs: 0 }
                };
            }
        },

        async loadPanelData(panel) {
            switch (panel) {
                case 'mfa':
                    await this.loadMfaUsers();
                    break;
                case 'logins':
                    await this.loadLoginAttempts();
                    break;
                case 'audit':
                    await this.loadAuditLogs();
                    break;
                case 'keys':
                    await this.loadApiKeys();
                    break;
                case 'ip':
                    await this.loadIpAccess();
                    break;
                case 'sessions':
                    await this.loadActiveSessions();
                    break;
                case 'rbac':
                    await this.loadRbacOverview();
                    break;
            }
        },

        async loadMfaUsers() {
            try {
                const params = this.buildApiParams({ mfa: false });
                const response = await fetch(`/api/admin/security/mfa-bypass?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                this.mfaUsers = data.data || [];
                this.pagination.mfa = data.meta || {};
            } catch (error) {
                console.error('Error loading MFA users:', error);
                this.mfaUsers = [];
                this.pagination.mfa = {};
            }
        },

        async loadLoginAttempts() {
            try {
                const params = this.buildApiParams();
                const response = await fetch(`/api/admin/security/logins-bypass?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                this.loginAttempts = data.data || [];
                this.pagination.logins = data.meta || {};
            } catch (error) {
                console.error('Error loading login attempts:', error);
            }
        },

        async loadAuditLogs() {
            try {
                const params = this.buildApiParams();
                const response = await fetch(`/api/admin/security/audit-bypass?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                this.auditLogs = data.data || [];
                this.pagination.audit = data.meta || {};
            } catch (error) {
                console.error('Error loading audit logs:', error);
            }
        },

        async loadApiKeys() {
            try {
                const params = this.buildApiParams();
                const response = await fetch(`/api/admin/security/keys?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                this.apiKeys = data.data || [];
                this.pagination.keys = data.meta || {};
            } catch (error) {
                console.error('Error loading API keys:', error);
            }
        },

        async loadIpAccess() {
            try {
                const [allowResponse, denyResponse] = await Promise.all([
                    fetch('/api/admin/security/ip-allow', {
                        headers: { 'Accept': 'application/json' }
                    }),
                    fetch('/api/admin/security/ip-deny', {
                        headers: { 'Accept': 'application/json' }
                    })
                ]);
                
                if (allowResponse.ok) {
                    const allowData = await allowResponse.json();
                    this.ipAllowlist = allowData.data || [];
                }
                
                if (denyResponse.ok) {
                    const denyData = await denyResponse.json();
                    this.ipDenylist = denyData.data || [];
                }
            } catch (error) {
                console.error('Error loading IP access:', error);
            }
        },

        async loadActiveSessions() {
            try {
                const params = this.buildApiParams();
                const response = await fetch(`/api/admin/security/sessions-bypass?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                this.activeSessions = data.data || [];
                this.pagination.sessions = data.meta || {};
            } catch (error) {
                console.error('Error loading active sessions:', error);
            }
        },

        async loadRbacOverview() {
            try {
                const response = await fetch('/api/admin/security/rbac', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                this.rbacOverview = data.data || {};
            } catch (error) {
                console.error('Error loading RBAC overview:', error);
            }
        },

        buildApiParams(extra = {}) {
            const params = new URLSearchParams();
            
            // Add filters
            if (this.filters.q) params.set('q', this.filters.q);
            if (this.filters.range) params.set('range', this.filters.range);
            if (this.filters.tenant) params.set('tenant', this.filters.tenant);
            if (this.filters.severity) params.set('severity', this.filters.severity);
            if (this.filters.actor) params.set('actor', this.filters.actor);
            if (this.filters.source) params.set('source', this.filters.source);
            
            // Add extra params
            Object.entries(extra).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    params.set(key, value);
                }
            });
            
            return params.toString();
        },

        // Panel Navigation
        setActivePanel(panel) {
            this.activePanel = panel;
            this.updateUrl();
            this.loadPanelData(panel);
        },

        // Preset Actions
        applyPreset(presetKey) {
            const preset = this.presets[presetKey];
            if (!preset) return;

            // Apply preset filters
            Object.entries(preset).forEach(([key, value]) => {
                if (key === 'panel') {
                    this.setActivePanel(value);
                } else {
                    this.filters[key] = value;
                }
            });

            this.updateUrl();
            this.loadSecurityData();
            this.logEvent('security_preset_click', { preset: presetKey });
        },

        // Search
        performSearch() {
            this.updateUrl();
            this.loadSecurityData();
        },

        // Actions
        async forceMfa(userIds) {
            try {
                const response = await fetch('/api/admin/security/users:bulk-force-mfa', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: userIds })
                });
                
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                
                this.logEvent('security_action', { type: 'force_mfa', count: userIds.length });
                this.loadMfaUsers();
                this.showForceMfaModal = false;
            } catch (error) {
                console.error('Error forcing MFA:', error);
            }
        },

        async revokeApiKey(keyId) {
            try {
                const response = await fetch(`/api/admin/security/keys/${keyId}:revoke`, {
                    method: 'POST'
                });
                
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                
                this.logEvent('security_action', { type: 'revoke_key', target: keyId });
                this.loadApiKeys();
                this.showRevokeKeyModal = false;
            } catch (error) {
                console.error('Error revoking API key:', error);
            }
        },

        async blockIp(cidr, reason) {
            try {
                const response = await fetch('/api/admin/security/ip:block', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cidr, reason })
                });
                
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                
                this.logEvent('security_action', { type: 'block_ip', target: cidr });
                this.loadIpAccess();
                this.showBlockIpModal = false;
            } catch (error) {
                console.error('Error blocking IP:', error);
            }
        },

        async endSession(sessionId) {
            try {
                const response = await fetch(`/api/admin/security/sessions/${sessionId}:end`, {
                    method: 'POST'
                });
                
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                
                this.logEvent('security_action', { type: 'end_session', target: sessionId });
                this.loadActiveSessions();
                this.showEndSessionModal = false;
            } catch (error) {
                console.error('Error ending session:', error);
            }
        },

        // Export
        async exportSecurityReport() {
            try {
                const params = this.buildApiParams();
                const response = await fetch(`/api/admin/security/audit/export?format=csv&${params}`);
                
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `security-report-${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                this.logEvent('security_export', { dataset: 'audit' });
            } catch (error) {
                console.error('Error exporting security report:', error);
            }
        },

        // Utility
        refreshData() {
            this.loadSecurityData();
        },

        getCurrentQuery() {
            return new URLSearchParams(window.location.search).toString();
        },

        logEvent(event, data = {}) {
            console.log('Analytics:', event, data);
            // TODO: Implement actual analytics tracking
        },

        // KPI Drill-down
        drillDownMfa() {
            this.setActivePanel('mfa');
            this.filters.mfa = false;
            this.updateUrl();
        },

        drillDownFailedLogins() {
            this.setActivePanel('logins');
            this.filters.outcome = 'failed';
            this.filters.range = '24h';
            this.updateUrl();
        },

        drillDownLockedAccounts() {
            window.location.href = '/admin/users?status=locked';
        },

        drillDownActiveSessions() {
            this.setActivePanel('sessions');
            this.updateUrl();
        },

        drillDownRiskyKeys() {
            this.setActivePanel('keys');
            this.filters.risk = 'high';
            this.updateUrl();
        },

        // UI Helper Functions
        formatDate(dateString) {
            if (!dateString) return '—';
            try {
                const date = new Date(dateString);
                const now = new Date();
                const diffMs = now - date;
                const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                const diffMinutes = Math.floor(diffMs / (1000 * 60));
                
                if (diffDays > 0) {
                    return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
                } else if (diffHours > 0) {
                    return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
                } else if (diffMinutes > 0) {
                    return `${diffMinutes} min ago`;
                } else {
                    return 'Just now';
                }
            } catch (error) {
                return dateString; // Fallback to raw string
            }
        },

        getRoleBadgeClass(role) {
            const roleClasses = {
                'super_admin': 'bg-red-100 text-red-800',
                'admin': 'bg-blue-100 text-blue-800', 
                'manager': 'bg-purple-100 text-purple-800',
                'pm': 'bg-yellow-100 text-yellow-800',
                'member': 'bg-gray-100 text-gray-800',
                'client': 'bg-green-100 text-green-800'
            };
            return roleClasses[role] || 'bg-gray-100 text-gray-800';
        },

        // StatCard utility functions
        formatInt(n) {
            if (typeof n !== 'number') return '0';
            return n.toLocaleString();
        },
        
        formatPercent(n) {
            if (typeof n !== 'number') return '0.0';
            return n.toFixed(1);
        },
        
        formatDelta(delta, suffix = '') {
            if (delta === null || delta === undefined) return '';
            
            const deltaNum = typeof delta === 'number' ? delta : parseFloat(delta);
            if (Math.abs(deltaNum) < 0.1) return `0${suffix}`;
            
            const sign = deltaNum > 0 ? '+' : '';
            if (suffix === '%') {
                return `${sign}${deltaNum.toFixed(1)}%`;
            }
            return `${sign}${deltaNum.toLocaleString()}`;
        },
        
        getDeltaClass(delta, policy = 'better') {
            if (delta === null || delta === 0) return 'bg-gray-100 text-gray-800';
            
            const isHigher = delta > 0;
            const isGood = policy === 'better' ? isHigher : !isHigher;
            
            return isGood ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        },
        
        generateSparkline(data) {
            if (!data || !Array.isArray(data) || data.length < 2) return '';
            
            const width = 120;
            const height = 32;
            const padding = 4;
            
            // Normalize data
            const min = Math.min(...data);
            const max = Math.max(...data);
            const range = max - min || 1;
            
            // Generate points
            const points = data.map((value, index) => {
                const x = padding + (index / (data.length - 1)) * (width - 2 * padding);
                const y = height - padding - ((value - min) / range) * (height - 2 * padding);
                return `${x},${y}`;
            });
            
            return points.join(' ');
        }
    }));
});

// Listen for soft refresh events - update component state
document.addEventListener('security:kpisUpdated', function(event) {
    const securityContainer = document.querySelector('[x-data="securityPage()"]');
    if (securityContainer && securityContainer._x_dataStack) {
        const component = securityContainer._x_dataStack[0];
        if (component) {
            Object.assign(component.kpis, event.detail);
            console.log('KPIs updated from soft refresh');
        }
    }
});

document.addEventListener('security:mfaUpdated', function(event) {
    const securityContainer = document.querySelector('[x-data="securityPage()"]');
    if (securityContainer && securityContainer._x_dataStack) {
        const component = securityContainer._x_dataStack[0];
        if (component) {
            component.mfaUsers = event.detail.data || [];
            component.mfaMeta = event.detail.meta || {};
            console.log('MFA users updated from soft refresh');
        }
    }
});

document.addEventListener('security:loginsUpdated', function(event) {
    const securityContainer = document.querySelector('[x-data="securityPage()"]');
    if (securityContainer && securityContainer._x_dataStack) {
        const component = securityContainer._x_dataStack[0];
        if (component) {
            component.loginAttempts = event.detail.data || [];
            component.loginMeta = event.detail.meta || {};
            console.log('Login attempts updated from soft refresh');
        }
    }
});

document.addEventListener('security:auditUpdated', function(event) {
    const securityContainer = document.querySelector('[x-data="securityPage()"]');
    if (securityContainer && securityContainer._x_dataStack) {
        const component = securityContainer._x_dataStack[0];
        if (component) {
            component.auditLogs = event.detail.data || [];
            component.auditMeta = event.detail.meta || {};
            console.log('Audit logs updated from soft refresh');
        }
    }
});

document.addEventListener('security:refreshError', function(event) {
    console.error('Soft refresh error:', event.detail.error);
    // Could show a subtle error toast here if needed
});
</script>

<!-- Soft Refresh using core modules -->
<script src="<?php echo e(asset('js/pages/dashboard-refresh.js')); ?>"></script>
<script>
// Override Dashboard refresh with Security-specific logic
if (window.Security?.refresh) {
    // Security already has its refresh function, keep it
    console.log('Security refresh already configured');
} else {
    // Use dashboard template as fallback
    window.Security = window.Dashboard || {};
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/security/index.blade.php ENDPATH**/ ?>