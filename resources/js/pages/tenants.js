// Tenants Page Module - No-Flash Implementation
// eslint-disable-next-line sonarjs/cognitive-complexity
(function() {
    let abortController = null;
    const pageKey = 'tenants';

    // Helper functions to reduce complexity
    function setLoadingState(loading) {
        const tablePanel = document.getElementById('tenants-table');
        if (tablePanel && window.setPanelLoading) {
            window.setPanelLoading(tablePanel, loading);
        }
    }

    function updateTenantsState(data) {
        const tenantsPage = Alpine.$data(document.querySelector('[x-data="tenantsPage()"]'));
        if (!tenantsPage) return;

        if (data.data) {
            tenantsPage.filteredTenants = data.data.tenants || [];
            tenantsPage.total = data.meta?.pagination?.total || 0;
            tenantsPage.lastPage = data.meta?.pagination?.last_page || 1;
            tenantsPage.kpis = data.data.kpis || {};
        }
        tenantsPage.tenantsLoading = false;
        tenantsPage.error = null;
    }

    function updateErrorState(error) {
        const tenantsPage = Alpine.$data(document.querySelector('[x-data="tenantsPage()"]'));
        if (!tenantsPage) return;

        tenantsPage.tenantsLoading = false;
        tenantsPage.error = error.message;
    }

    function dispatchEvent(eventName, detail) {
        document.dispatchEvent(new CustomEvent(eventName, { detail }));
    }

    // Page refresh handler
    async function loadTenantsData() {
        try {
            // Cancel previous request
            if (abortController) {
                abortController.abort();
            }
            
            abortController = new AbortController();
            setLoadingState(true);

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
            updateTenantsState(data);

            // Dispatch success event
            dispatchEvent('tenants:dataLoaded', { data, url: apiUrl });

        } catch (error) {
            console.error('[Tenants] Error loading data:', error);
            
            if (error.name !== 'AbortError') {
                updateErrorState(error);
                dispatchEvent('tenants:error', { error });
            }
        } finally {
            setLoadingState(false);
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
    } else {
        initializePage();
    }

    // Export for global access
    window.tenantsPageModule = {
        refresh: loadTenantsData,
        reload: () => loadTenantsData()
    };

})();
