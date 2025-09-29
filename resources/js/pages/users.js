// Users Page Module - No-Flash Implementation
(function() {
    let abortController = null;
    const pageKey = 'users';

    // Page refresh handler
    async function loadUsersData() {
        try {
            // Cancel previous request
            if (abortController) {
                abortController.abort();
            }
            
            abortController = new AbortController();

            // Set loading state on table
            const tablePanel = document.getElementById('users-table');
            if (tablePanel && window.setPanelLoading) {
                window.setPanelLoading(tablePanel, true);
            }

            // Build cache key with current params
            const urlParams = new URLSearchParams(window.location.search);
            const cacheKey = `users:list:${urlParams.toString()}`;
            const apiUrl = `/api/admin/users?${urlParams.toString()}`;

            // Fetch with SWR
            const data = await window.getWithETag(cacheKey, apiUrl, {
                signal: abortController.signal,
                forceRefresh: true // Always refresh on manual refresh
            });

            // Update Alpine.js state
            const usersPage = Alpine.$data(document.querySelector('[x-data="usersPage()"]'));
            if (usersPage) {
                // Extract data from response
                if (data.data) {
                    usersPage.filteredUsers = data.data.users || [];
                    usersPage.total = data.meta?.pagination?.total || 0;
                    usersPage.lastPage = data.meta?.pagination?.last_page || 1;
                    usersPage.kpis = data.data.kpis || {};
                }
                usersPage.usersLoading = false;
                usersPage.error = null;
            }

            // Dispatch success event
            document.dispatchEvent(new CustomEvent('users:dataLoaded', {
                detail: { data, url: apiUrl }
            }));

        } catch (error) {
            console.error('[Users] Error loading data:', error);
            
            if (error.name !== 'AbortError') {
                // Update error state
                const usersPage = Alpine.$data(document.querySelector('[x-data="usersPage()"]'));
                if (usersPage) {
                    usersPage.usersLoading = false;
                    usersPage.error = error.message;
                }
                
                // Dispatch error event
                document.dispatchEvent(new CustomEvent('users:error', {
                    detail: { error }
                }));
            }
        } finally {
            // Always clean up loading state
            const tablePanel = document.getElementById('users-table');
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
            window.registerRefreshHandler(pageKey, loadUsersData);
        }

        // Set up panel loading event listeners
        document.addEventListener('users:filterChange', loadUsersData);
        document.addEventListener('users:pageChange', loadUsersData);
        document.addEventListener('users:searchChange', loadUsersData);

        console.log('[Users] Page module initialized');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePage);
    } else {
        initializePage();
    }

    // Export for global access
    window.usersPageModule = {
        refresh: loadUsersData,
        reload: () => loadUsersData()
    };

})();
