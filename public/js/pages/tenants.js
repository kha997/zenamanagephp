// Tenants Page Module - No-Flash Implementation
(function () {
    let abortController = null;
    const pageKey = 'tenants';
    // Page refresh handler
    async function loadTenantsData() {
        try {
            // Cancel previous request
            if (abortController) {
                abortController.abort();
            }
            abortController = new AbortController();
            // Set loading state on table
            const tablePanel = document.getElementById('tenants-table');
            if (tablePanel && window.setPanelLoading) {
                window.setPanelLoading(tablePanel, true);
            }
            // Build cache key with current params
            const urlParams = new URLSearchParams(window.location.search);
            const cacheKey = `tenants:list:${urlParams.toString()}`;
            const apiUrl = `/api/admin/tenants?${urlParams.toString()}`;
            // Fetch with SWR
            const data = await window.getWithETag(cacheKey, apiUrl, {
                signal: abortController.signal,
                forceRefresh: true // Always refresh on manual refresh
            });
            // Update Alpine.js state
            const tenantsPage = Alpine.$data(document.querySelector('[x-data="tenantsPage()"]'));
            if (tenantsPage) {
                // Extract data from response
                if (data.data) {
                    tenantsPage.filteredTenants = data.data.tenants || [];
                    tenantsPage.total = data.meta?.pagination?.total || 0;
                    tenantsPage.lastPage = data.meta?.pagination?.last_page || 1;
                    tenantsPage.kpis = data.data.kpis || {};
                }
                tenantsPage.tenantsLoading = false;
                tenantsPage.error = null;
            }
            // Dispatch success event
            document.dispatchEvent(new CustomEvent('tenants:dataLoaded', {
                detail: { data, url: apiUrl }
            }));
        }
        catch (error) {
            console.error('[Tenants] Error loading data:', error);
            if (error.name !== 'AbortError') {
                // Update error state
                const tenantsPage = Alpine.$data(document.querySelector('[x-data="tenantsPage()"]'));
                if (tenantsPage) {
                    tenantsPage.tenantsLoading = false;
                    tenantsPage.error = error.message;
                }
                // Dispatch error event
                document.dispatchEvent(new CustomEvent('tenants:error', {
                    detail: { error }
                }));
            }
        }
        finally {
            // Always clean up loading state
            const tablePanel = document.getElementById('tenants-table');
            if (tablePanel && window.setPanelLoading) {
                window.setPanelLoading(tablePanel, false);
            }
            abortController = null;
        }
    }
    // Initialize page
    function initializePage() {
        // Register soft refresh handler
        if (window.registerRefreshHandler) {
            window.registerRefreshHandler(pageKey, loadTenantsData);
        }
        // Set up panel loading event listeners
        document.addEventListener('tenants:filterChange', loadTenantsData);
        document.addEventListener('tenants:pageChange', loadTenantsData);
        document.addEventListener('tenants:searchChange', loadTenantsData);
        console.log('[Tenants] Page module initialized');
    }
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePage);
    }
    else {
        initializePage();
    }
    // Export for global access
    window.tenantsPageModule = {
        refresh: loadTenantsData,
        reload: () => loadTenantsData()
    };
})();
