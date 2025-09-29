// Page Auto-Initialization Manager - Controls page-specific refresh managers
document.addEventListener('DOMContentLoaded', () => {
    function initializePageManager() {
        console.log('🚀 Auto-initializing page managers...');
        
        const currentPath = window.location.pathname;
        
        // Skip Security page - it has its own SecurityChartsManager
        if (currentPath.startsWith('/admin/security')) {
            console.log('Security page detected - skipping PageRefreshManager auto-init');
            
            // Wait for SecurityChartsManager to be available
            setTimeout(() => {
                if (typeof SecurityChartsManager !== 'undefined') {
                    const container = document.querySelector('[data-charts-container]');
                    if (container) {
                        console.log('Initializing SecurityChartsManager');
                        new window.SecurityChartsManager(container, '/api/admin/security/kpis-bypass');
                    }
                } else {
                    console.log('SecurityChartsManager not yet available, retrying...');
                    setTimeout(initializePageManager, 500);
                }
            }, 100);
            return;
        }
        
        // PageRefreshManager auto-initialization for other pages
        const pageManagers = [
            { 
                page: 'users', 
                route: '/admin/users',
                config: {
                    apiEndpoint: '/api/admin/users',
                    refreshInterval: 30000,
                    kpiKey: 'kpis',
                    tableKey: 'users'
                }
            },
            { 
                page: 'tenants', 
                route: '/admin/tenants',
                config: {
                    apiEndpoint: '/api/admin/tenants',
                    refreshInterval: 30000,
                    kpiKey: 'kpis',
                    tableKey: 'tenants'
                }
            },
            { 
                page: 'alerts', 
                route: '/admin/alerts',
                config: {
                    apiEndpoint: '/api/admin/alerts',
                    refreshInterval: 15000,
                    kpiKey: 'kpis',
                    tableKey: 'alerts'
                }
            }
        ];

        const activePage = pageManagers.find(manager => currentPath.startsWith(manager.route));
        
        if (activePage && typeof window.PageRefreshManager !== 'function') {
            console.log('PageRefreshManager not available yet, retrying...');
            setTimeout(initializePageManager, 500);
            return;
        }
        
        if (activePage) {
            console.log(`Auto-initializing ${activePage.page} page manager`);
            new window.PageRefreshManager(activePage.page, activePage.config);
        } else {
            console.log(`No page manager configured for ${currentPath}`);
        }
    }

    // Delay initialization to ensure all modules are loaded
    setTimeout(initializePageManager, 1000);
});

// Also handle soft refresh events properly
document.addEventListener('security:refresh', () => {
    if (window.SecurityChartsManager) {
        console.log('Security refresh triggered');
        const container = document.querySelector('[data-charts-container]');
        if (container && container.chartsManager) {
            container.chartsManager.refreshPage();
        }
    }
});
