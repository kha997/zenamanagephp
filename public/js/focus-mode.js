/**
 * Focus Mode JavaScript Logic
 * Handles UI changes for Focus Mode feature
 */
class FocusModeManager {
    constructor() {
        this.isActive = false;
        this.originalClasses = new Map();
        this.init();
    }
    /**
     * Initialize Focus Mode
     */
    init() {
        // Check if focus mode is enabled on page load
        this.checkFocusModeStatus();
        // Listen for focus mode toggle events
        document.addEventListener('focus-mode-toggled', (event) => {
            this.toggle(event.detail.enabled);
        });
    }
    /**
     * Check current focus mode status from API
     */
    async checkFocusModeStatus() {
        try {
            const response = await fetch('/api/v1/app/focus-mode/status', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin'
            });
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data.focus_mode_active) {
                    this.activate();
                }
            }
        }
        catch (error) {
            console.error('Error checking focus mode status:', error);
        }
    }
    /**
     * Toggle focus mode
     */
    async toggle(enabled = null) {
        try {
            const response = await fetch('/api/v1/app/focus-mode/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin'
            });
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.toggleUI(data.data.focus_mode_enabled);
                }
            }
        }
        catch (error) {
            console.error('Error toggling focus mode:', error);
        }
    }
    /**
     * Activate focus mode
     */
    activate() {
        this.toggleUI(true);
    }
    /**
     * Deactivate focus mode
     */
    deactivate() {
        this.toggleUI(false);
    }
    /**
     * Toggle UI elements for focus mode
     */
    toggleUI(enabled) {
        this.isActive = enabled;
        if (enabled) {
            this.enableFocusMode();
        }
        else {
            this.disableFocusMode();
        }
        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('focus-mode-changed', {
            detail: { enabled: this.isActive }
        }));
    }
    /**
     * Enable focus mode UI
     */
    enableFocusMode() {
        // Add focus mode class to body
        document.body.classList.add('focus-mode');
        // Collapse sidebar
        this.collapseSidebar();
        // Hide secondary KPIs
        this.hideSecondaryKPIs();
        // Show only main content
        this.showMainContentOnly();
        // Update focus mode toggle button
        this.updateToggleButton(true);
        // Add minimal theme
        this.applyMinimalTheme();
    }
    /**
     * Disable focus mode UI
     */
    disableFocusMode() {
        // Remove focus mode class from body
        document.body.classList.remove('focus-mode');
        // Expand sidebar
        this.expandSidebar();
        // Show secondary KPIs
        this.showSecondaryKPIs();
        // Show all content
        this.showAllContent();
        // Update focus mode toggle button
        this.updateToggleButton(false);
        // Remove minimal theme
        this.removeMinimalTheme();
    }
    /**
     * Collapse sidebar
     */
    collapseSidebar() {
        const sidebar = document.querySelector('.sidebar, .main-sidebar, #sidebar');
        if (sidebar) {
            sidebar.classList.add('collapsed', 'focus-mode-collapsed');
            sidebar.setAttribute('data-focus-mode', 'collapsed');
        }
        // Also handle mobile sidebar
        const mobileSidebar = document.querySelector('.mobile-sidebar, .sidebar-mobile');
        if (mobileSidebar) {
            mobileSidebar.classList.add('hidden');
        }
    }
    /**
     * Expand sidebar
     */
    expandSidebar() {
        const sidebar = document.querySelector('.sidebar, .main-sidebar, #sidebar');
        if (sidebar) {
            sidebar.classList.remove('collapsed', 'focus-mode-collapsed');
            sidebar.removeAttribute('data-focus-mode');
        }
        // Also handle mobile sidebar
        const mobileSidebar = document.querySelector('.mobile-sidebar, .sidebar-mobile');
        if (mobileSidebar) {
            mobileSidebar.classList.remove('hidden');
        }
    }
    /**
     * Hide secondary KPIs
     */
    hideSecondaryKPIs() {
        const secondaryKPIs = document.querySelectorAll('.kpi-strip .kpi-item:not(.primary), ' +
            '.secondary-kpis, ' +
            '.kpi-secondary, ' +
            '.dashboard-widgets .widget:not(.main-content)');
        secondaryKPIs.forEach(element => {
            element.classList.add('focus-mode-hidden');
            element.style.display = 'none';
        });
    }
    /**
     * Show secondary KPIs
     */
    showSecondaryKPIs() {
        const secondaryKPIs = document.querySelectorAll('.focus-mode-hidden');
        secondaryKPIs.forEach(element => {
            element.classList.remove('focus-mode-hidden');
            element.style.display = '';
        });
    }
    /**
     * Show only main content
     */
    showMainContentOnly() {
        const mainContent = document.querySelector('.main-content, .content-main, #main-content');
        if (mainContent) {
            mainContent.classList.add('focus-mode-main');
        }
        // Hide non-essential elements
        const nonEssentialElements = document.querySelectorAll('.breadcrumbs, ' +
            '.page-header-secondary, ' +
            '.secondary-actions, ' +
            '.toolbar-secondary');
        nonEssentialElements.forEach(element => {
            element.classList.add('focus-mode-hidden');
            element.style.display = 'none';
        });
    }
    /**
     * Show all content
     */
    showAllContent() {
        const mainContent = document.querySelector('.main-content, .content-main, #main-content');
        if (mainContent) {
            mainContent.classList.remove('focus-mode-main');
        }
        // Show all elements
        const hiddenElements = document.querySelectorAll('.focus-mode-hidden');
        hiddenElements.forEach(element => {
            element.classList.remove('focus-mode-hidden');
            element.style.display = '';
        });
    }
    /**
     * Update focus mode toggle button
     */
    updateToggleButton(enabled) {
        const toggleButton = document.querySelector('[data-focus-mode-toggle]');
        if (toggleButton) {
            if (enabled) {
                toggleButton.classList.add('active', 'focus-mode-active');
                toggleButton.setAttribute('aria-pressed', 'true');
                toggleButton.title = 'Exit Focus Mode';
            }
            else {
                toggleButton.classList.remove('active', 'focus-mode-active');
                toggleButton.setAttribute('aria-pressed', 'false');
                toggleButton.title = 'Enter Focus Mode';
            }
        }
    }
    /**
     * Apply minimal theme
     */
    applyMinimalTheme() {
        // Add minimal theme classes
        document.body.classList.add('minimal-theme');
        // Increase font size for better readability
        const style = document.createElement('style');
        style.id = 'focus-mode-styles';
        style.textContent = `
            .focus-mode {
                --focus-mode-spacing: 2rem;
                --focus-mode-font-size: 1.1rem;
                --focus-mode-line-height: 1.6;
            }
            
            .focus-mode .main-content {
                padding: var(--focus-mode-spacing);
                max-width: 100%;
            }
            
            .focus-mode .content-area {
                font-size: var(--focus-mode-font-size);
                line-height: var(--focus-mode-line-height);
            }
            
            .focus-mode .card, .focus-mode .panel {
                margin-bottom: var(--focus-mode-spacing);
                padding: var(--focus-mode-spacing);
            }
            
            .focus-mode .sidebar.collapsed {
                width: 60px;
                min-width: 60px;
            }
            
            .focus-mode .sidebar.focus-mode-collapsed {
                transform: translateX(-100%);
                width: 0;
                min-width: 0;
            }
        `;
        document.head.appendChild(style);
    }
    /**
     * Remove minimal theme
     */
    removeMinimalTheme() {
        document.body.classList.remove('minimal-theme');
        const style = document.getElementById('focus-mode-styles');
        if (style) {
            style.remove();
        }
    }
    /**
     * Get current focus mode state
     */
    getState() {
        return {
            isActive: this.isActive,
            sidebarCollapsed: document.querySelector('.sidebar')?.classList.contains('collapsed') || false,
            secondaryKPIsHidden: document.querySelectorAll('.focus-mode-hidden').length > 0
        };
    }
}
// Initialize Focus Mode Manager
const focusModeManager = new FocusModeManager();
// Export for use in other scripts
window.FocusModeManager = focusModeManager;
// Alpine.js integration
document.addEventListener('alpine:init', () => {
    Alpine.data('focusMode', () => ({
        isActive: false,
        init() {
            // Check initial state
            this.isActive = focusModeManager.isActive;
            // Listen for changes
            document.addEventListener('focus-mode-changed', (event) => {
                this.isActive = event.detail.enabled;
            });
        },
        toggle() {
            focusModeManager.toggle();
        },
        get toggleText() {
            return this.isActive ? 'Exit Focus Mode' : 'Enter Focus Mode';
        },
        get toggleIcon() {
            return this.isActive ? 'fas fa-expand' : 'fas fa-compress';
        }
    }));
});
