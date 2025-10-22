// Users Page Module - No-Flash Implementation
// eslint-disable-next-line sonarjs/cognitive-complexity
(function() {
    let abortController = null;
    const pageKey = 'users';

    // Helper functions to reduce complexity
    function setLoadingState(loading) {
        const tablePanel = document.getElementById('users-table');
        if (tablePanel && window.setPanelLoading) {
            window.setPanelLoading(tablePanel, loading);
        }
    }

    function updateUsersState(data) {
        const usersPage = Alpine.$data(document.querySelector('[x-data="usersPage()"]'));
        if (!usersPage) return;

        if (data.data) {
            usersPage.filteredUsers = data.data.users || [];
            usersPage.total = data.meta?.pagination?.total || 0;
            usersPage.lastPage = data.meta?.pagination?.last_page || 1;
            usersPage.kpis = data.data.kpis || {};
        }
        usersPage.usersLoading = false;
        usersPage.error = null;
    }

    function updateErrorState(error) {
        const usersPage = Alpine.$data(document.querySelector('[x-data="usersPage()"]'));
        if (!usersPage) return;

        usersPage.usersLoading = false;
        usersPage.error = error.message;
    }

    function dispatchEvent(eventName, detail) {
        document.dispatchEvent(new CustomEvent(eventName, { detail }));
    }

    // Page refresh handler
    async function loadUsersData() {
        try {
            // Cancel previous request
            if (abortController) {
                abortController.abort();
            }
            
            abortController = new AbortController();
            setLoadingState(true);

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
            updateUsersState(data);

            // Dispatch success event
            dispatchEvent('users:dataLoaded', { data, url: apiUrl });

        } catch (error) {
            console.error('[Users] Error loading data:', error);
            
            if (error.name !== 'AbortError') {
                updateErrorState(error);
                dispatchEvent('users:error', { error });
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
