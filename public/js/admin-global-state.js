// Admin Global Alpine.js State
document.addEventListener('alpine:init', () => {
    Alpine.data('adminGlobalState', () => ({
        // Sidebar State
        sidebarCollapsed: false,
        
        // Global Search State
        globalSearchQuery: '',
        showGlobalSearchResults: false,
        globalSearchResults: {
            tenants: [],
            users: [],
            errors: []
        },

        // Notification State
        unreadNotifications: 0,
        showNotifications: false,
        notifications: [],

        // User Menu State
        showUserMenu: false,
        showMobileMenu: false,

        // Global Modal State
        showModal: false,
        modalTitle: '',
        modalContent: '',
        modalAction: null,

        // Initialize
        init() {
            // Load sidebar state
            const saved = localStorage.getItem('sidebarCollapsed');
            if (saved !== null) {
                this.sidebarCollapsed = JSON.parse(saved);
            }
            console.log('Body initialized, sidebar collapsed:', this.sidebarCollapsed);

            // Load notifications (mock data)
            this.loadNotifications();

            console.log('Admin Global State initialized');
        },

        // Sidebar Toggle
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
        },

        // Global Search
        async search(query) {
            if (!query || query.length < 2) {
                this.showGlobalSearchResults = false;
                return;
            }

            this.globalSearchQuery = query;
            
            try {
                // Mock search - replace with real API
                const response = await fetch(`/api/admin/search?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.globalSearchResults = data.results || { tenants: [], users: [], errors: [] };
                } else {
                    // Fallback to mock data
                    this.globalSearchResults = {
                        tenants: [{ id: 1, name: 'Demo Tenant', domain: 'demo.example.com' }],
                        users: [{ id: 1, name: 'Demo User', email: 'demo@example.com' }],
                        errors: [{ id: 1, message: 'Demo Error', timestamp: new Date().toISOString() }]
                    };
                }

                this.showGlobalSearchResults = true;
            } catch (error) {
                console.warn('Global search failed:', error);
                this.showGlobalSearchResults = false;
            }
        },

        clearGlobalSearch() {
            this.globalSearchQuery = '';
            this.showGlobalSearchResults = false;
            this.globalSearchResults = { tenants: [], users: [], errors: [] };
        },

        // Notifications
        loadNotifications() {
            // Mock notifications
            this.notifications = [
                {
                    id: 1,
                    title: 'New tenant registered',
                    message: 'TechCorp has registered',
                    timestamp: new Date().toISOString(),
                    read: false
                },
                {
                    id: 2,
                    title: 'System maintenance',
                    message: 'Scheduled maintenance tonight',
                    timestamp: new Date().toISOString(),
                    read: false
                }
            ];
            this.unreadNotifications = this.notifications.filter(n => !n.read).length;
        },

        markAllNotificationsRead() {
            this.notifications.forEach(n => n.read = true);
            this.unreadNotifications = 0;
        },

        toggleNotifications() {
            this.showNotifications = !this.showNotifications;
        },

        closeNotifications() {
            this.showNotifications = false;
        },

        // User Menu
        toggleUserMenu() {
            this.showUserMenu = !this.showUserMenu;
        },

        closeUserMenu() {
            this.showUserMenu = false;
        },

        toggleMobileMenu() {
            this.showMobileMenu = !this.showMobileMenu;
        },

        closeMobileMenu() {
            this.showMobileMenu = false;
        },

        // Modal Management
        closeModal() {
            this.showModal = false;
            this.modalTitle = '';
            this.modalContent = '';
            this.modalAction = null;
        },

        openModal(title, content, action = null) {
            this.modalTitle = title;
            this.modalContent = content;
            this.modalAction = action;
            this.showModal = true;
        },

        executeModalAction() {
            if (this.modalAction && typeof this.modalAction === 'function') {
                this.modalAction();
            }
            this.closeModal();
        }
    }));
});
