/**
 * E2E Test Selectors
 * 
 * Centralized selectors for E2E smoke tests
 * Provides consistent element selection across all test files
 */

export const selectors = {
  // Authentication selectors
  auth: {
    // Login form
    emailInput: 'input[name="email"], input[type="email"], input[placeholder*="email"]',
    passwordInput: 'input[name="password"], input[type="password"], input[placeholder*="password"]',
    loginButton: 'button[type="submit"], button:has-text("Sign In"), button:has-text("Login"), button:has-text("Đăng nhập")',
    logoutButton: 'button:has-text("Logout"), button:has-text("Sign Out"), button:has-text("Đăng xuất")',
    
    // Links
    forgotPasswordLink: 'a:has-text("Forgot password"), a:has-text("Forgot Password"), a:has-text("Quên mật khẩu")',
    registerLink: 'a:has-text("Sign up"), a:has-text("Register"), a:has-text("Đăng ký")',
    
    // Password visibility
    passwordToggle: 'button[aria-label*="password"], button:has-text("Show"), button:has-text("Hide"), button:has-text("Hiện"), button:has-text("Ẩn")',
    
    // Validation
    errorMessage: '.error, .invalid, [data-testid="error"], .alert-error',
    successMessage: '.success, .alert-success, [data-testid="success"]'
  },
  
  // Navigation selectors
  navigation: {
    // Main navigation
    sidebar: '[data-testid="sidebar"], .sidebar, nav, .navigation',
    menuToggle: 'button[aria-label*="menu"], button:has-text("Menu"), .hamburger',
    userMenu: '[data-testid="user-menu"], .user-menu, button[aria-label*="user"]',
    
    // Theme and settings
    themeToggle: 'button[aria-label*="theme"], button:has-text("Theme"), select[name*="theme"]',
    languageToggle: 'button[aria-label*="language"], select[name*="language"]',
    
    // Navigation links
    dashboardLink: 'a:has-text("Dashboard"), a[href*="dashboard"]',
    projectsLink: 'a:has-text("Projects"), a[href*="projects"]',
    tasksLink: 'a:has-text("Tasks"), a[href*="tasks"]',
    alertsLink: 'a:has-text("Alerts"), a[href*="alerts"]',
    preferencesLink: 'a:has-text("Preferences"), a:has-text("Settings"), a[href*="preferences"], a[href*="settings"]'
  },
  
  // Dashboard selectors
  dashboard: {
    // Page elements
    title: 'h1:has-text("Dashboard"), h2:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")',
    page: '[data-testid="dashboard-page"], .dashboard-page',
    
    // KPI and stats
    kpiCards: '[data-testid="kpi-card"], .kpi-card, .stat-card, .metric-card',
    kpiTitle: '.kpi-title, .stat-title, .metric-title',
    kpiValue: '.kpi-value, .stat-value, .metric-value, .number',
    
    // Quick actions
    quickActions: '[data-testid="quick-actions"], .quick-actions, .action-buttons',
    quickActionButton: '.quick-action, .action-button, button[data-testid*="action"]',
    
    // Content sections
    recentProjects: '[data-testid="recent-projects"], .recent-projects, .project-list',
    activities: '[data-testid="activities"], .activities, .activity-list',
    alerts: '[data-testid="alerts"], .alerts, .alert-list',
    
    // Loading states
    loading: '.loading, .spinner, [data-testid="loading"], .skeleton'
  },
  
  // Project selectors
  projects: {
    // Page elements
    page: '[data-testid="projects-page"], .projects-page',
    title: 'h1:has-text("Projects"), h2:has-text("Projects"), [role="heading"]:has-text("Projects")',
    
    // List and grid
    list: '[data-testid="projects-list"], .projects-list, .project-grid',
    grid: '[data-testid="project-grid"], .project-grid',
    
    // Project cards
    projectCard: '[data-testid="project-card"], .project-card, .project-item',
    projectTitle: '.project-title, h3, h4, [data-testid="project-title"]',
    projectDescription: '.project-description, .project-desc',
    projectStatus: '.project-status, [data-testid="project-status"], .status',
    projectCode: '.project-code, [data-testid="project-code"]',
    
    // Actions
    createButton: 'button:has-text("Create Project"), button:has-text("New Project"), button:has-text("Tạo dự án")',
    editButton: 'button:has-text("Edit"), button:has-text("Chỉnh sửa")',
    deleteButton: 'button:has-text("Delete"), button:has-text("Xóa")',
    viewButton: 'button:has-text("View"), button:has-text("Xem")',
    
    // Filters and search
    searchInput: 'input[type="search"], input[placeholder*="search"], input[name*="search"]',
    filterSelect: 'select[name*="filter"], select[name*="status"]',
    filterButton: 'button:has-text("Filter"), button:has-text("Lọc")'
  },
  
  // Task selectors
  tasks: {
    // Page elements
    page: '[data-testid="tasks-page"], .tasks-page',
    title: 'h1:has-text("Tasks"), h2:has-text("Tasks"), [role="heading"]:has-text("Tasks")',
    
    // Task list
    list: '[data-testid="tasks-list"], .tasks-list, .task-list',
    taskItem: '[data-testid="task-item"], .task-item, .task',
    
    // Task elements
    taskTitle: '.task-title, h3, h4, [data-testid="task-title"]',
    taskDescription: '.task-description, .task-desc',
    taskStatus: '.task-status, [data-testid="task-status"], .status',
    taskAssignee: '.task-assignee, [data-testid="task-assignee"]',
    taskDueDate: '.task-due-date, [data-testid="task-due-date"]',
    
    // Actions
    createButton: 'button:has-text("Create Task"), button:has-text("New Task"), button:has-text("Tạo nhiệm vụ")',
    editButton: 'button:has-text("Edit"), button:has-text("Chỉnh sửa")',
    deleteButton: 'button:has-text("Delete"), button:has-text("Xóa")',
    completeButton: 'button:has-text("Complete"), button:has-text("Hoàn thành")'
  },
  
  // Alert selectors
  alerts: {
    // Page elements
    page: '[data-testid="alerts-page"], .alerts-page',
    title: 'h1:has-text("Alerts"), h2:has-text("Alerts"), [role="heading"]:has-text("Alerts")',
    
    // Alert list
    list: '[data-testid="alerts-list"], .alerts-list, .alert-list',
    alertItem: '[data-testid="alert-item"], .alert-item, .alert',
    
    // Alert elements
    alertTitle: '.alert-title, h3, h4, [data-testid="alert-title"]',
    alertMessage: '.alert-message, .alert-content, [data-testid="alert-message"]',
    alertType: '.alert-type, [data-testid="alert-type"]',
    alertDate: '.alert-date, [data-testid="alert-date"]',
    alertRead: '.alert-read, [data-testid="alert-read"]',
    
    // Actions
    markAsReadButton: 'button:has-text("Mark as Read"), button:has-text("Đánh dấu đã đọc")',
    dismissButton: 'button:has-text("Dismiss"), button:has-text("Bỏ qua")',
    deleteButton: 'button:has-text("Delete"), button:has-text("Xóa")',
    
    // Filters
    filterAll: 'button:has-text("All"), button:has-text("Tất cả")',
    filterUnread: 'button:has-text("Unread"), button:has-text("Chưa đọc")',
    filterRead: 'button:has-text("Read"), button:has-text("Đã đọc")'
  },
  
  // Preferences/Settings selectors
  preferences: {
    // Page elements
    page: '[data-testid="preferences-page"], .preferences-page, [data-testid="settings-page"], .settings-page',
    title: 'h1:has-text("Preferences"), h2:has-text("Settings"), h1:has-text("Settings"), h2:has-text("Preferences")',
    
    // Theme settings
    themeSection: '[data-testid="theme-section"], .theme-section',
    themeToggle: 'button[aria-label*="theme"], button:has-text("Theme"), select[name*="theme"]',
    themeLight: 'button:has-text("Light"), option:has-text("Light")',
    themeDark: 'button:has-text("Dark"), option:has-text("Dark")',
    themeAuto: 'button:has-text("Auto"), option:has-text("Auto")',
    
    // Language settings
    languageSection: '[data-testid="language-section"], .language-section',
    languageSelect: 'select[name*="language"], button[aria-label*="language"]',
    languageEnglish: 'option:has-text("English"), button:has-text("English")',
    languageVietnamese: 'option:has-text("Vietnamese"), button:has-text("Tiếng Việt")',
    
    // Notification settings
    notificationSection: '[data-testid="notification-section"], .notification-section',
    emailNotification: 'input[name*="email"], input[type="checkbox"][name*="email"]',
    pushNotification: 'input[name*="push"], input[type="checkbox"][name*="push"]',
    inAppNotification: 'input[name*="inApp"], input[type="checkbox"][name*="in-app"]',
    
    // Density settings
    densitySection: '[data-testid="density-section"], .density-section',
    densitySelect: 'select[name*="density"], button[aria-label*="density"]',
    densityCompact: 'option:has-text("Compact"), button:has-text("Compact")',
    densityComfortable: 'option:has-text("Comfortable"), button:has-text("Comfortable")',
    densitySpacious: 'option:has-text("Spacious"), button:has-text("Spacious")',
    
    // Actions
    saveButton: 'button:has-text("Save"), button:has-text("Update"), button:has-text("Lưu")',
    cancelButton: 'button:has-text("Cancel"), button:has-text("Hủy")',
    resetButton: 'button:has-text("Reset"), button:has-text("Đặt lại")'
  },
  
  // Form selectors
  forms: {
    // General form elements
    form: 'form, [data-testid="form"]',
    field: '.field, .form-field, [data-testid="field"]',
    label: 'label, .form-label, [data-testid="label"]',
    input: 'input, textarea, select',
    
    // Actions
    submitButton: 'button[type="submit"], button:has-text("Submit"), button:has-text("Save"), button:has-text("Create")',
    cancelButton: 'button:has-text("Cancel"), button:has-text("Hủy")',
    resetButton: 'button[type="reset"], button:has-text("Reset"), button:has-text("Đặt lại")',
    
    // Validation
    errorMessage: '.error, .invalid, [data-testid="error"], .form-error',
    successMessage: '.success, .valid, [data-testid="success"], .form-success',
    requiredIndicator: '.required, [data-testid="required"], *',
    
    // Specific form fields
    nameInput: 'input[name="name"], input[placeholder*="name"]',
    emailInput: 'input[name="email"], input[type="email"], input[placeholder*="email"]',
    passwordInput: 'input[name="password"], input[type="password"], input[placeholder*="password"]',
    descriptionInput: 'textarea[name="description"], textarea[placeholder*="description"]',
    codeInput: 'input[name="code"], input[placeholder*="code"]',
    statusSelect: 'select[name="status"], select[name*="status"]'
  },
  
  // Modal selectors
  modals: {
    // General modal elements
    modal: '.modal, [data-testid="modal"], [role="dialog"]',
    modalTitle: '.modal-title, h2, h3, [data-testid="modal-title"]',
    modalContent: '.modal-content, [data-testid="modal-content"]',
    modalFooter: '.modal-footer, [data-testid="modal-footer"]',
    
    // Actions
    closeButton: 'button:has-text("Close"), button:has-text("×"), button[aria-label="close"]',
    confirmButton: 'button:has-text("Confirm"), button:has-text("OK"), button:has-text("Xác nhận")',
    cancelButton: 'button:has-text("Cancel"), button:has-text("Hủy")',
    
    // Backdrop
    backdrop: '.modal-backdrop, .backdrop, [data-testid="backdrop"]'
  },
  
  // Toast/Notification selectors
  toasts: {
    // Toast container
    container: '.toast-container, [data-testid="toast-container"], .notification-container',
    
    // Toast items
    toast: '.toast, [data-testid="toast"], .notification',
    toastSuccess: '.toast-success, .toast.success, [data-testid="toast-success"]',
    toastError: '.toast-error, .toast.error, [data-testid="toast-error"]',
    toastWarning: '.toast-warning, .toast.warning, [data-testid="toast-warning"]',
    toastInfo: '.toast-info, .toast.info, [data-testid="toast-info"]',
    
    // Toast content
    toastTitle: '.toast-title, [data-testid="toast-title"]',
    toastMessage: '.toast-message, [data-testid="toast-message"]',
    
    // Toast actions
    toastClose: '.toast-close, button[aria-label="close"], button:has-text("×")',
    toastAction: '.toast-action, button[data-testid="toast-action"]'
  },
  
  // Loading selectors
  loading: {
    // Loading indicators
    spinner: '.spinner, .loading-spinner, [data-testid="spinner"]',
    skeleton: '.skeleton, .loading-skeleton, [data-testid="skeleton"]',
    progress: '.progress, .progress-bar, [data-testid="progress"]',
    
    // Loading states
    loading: '.loading, [data-testid="loading"], [aria-busy="true"]',
    loaded: '.loaded, [data-testid="loaded"]'
  },
  
  // Error selectors
  errors: {
    // Error messages
    errorMessage: '.error, .error-message, [data-testid="error"]',
    errorAlert: '.alert-error, .alert.error, [data-testid="alert-error"]',
    
    // Error states
    errorState: '.error-state, [data-testid="error-state"]',
    errorPage: '.error-page, [data-testid="error-page"]',
    
    // Error actions
    retryButton: 'button:has-text("Retry"), button:has-text("Thử lại")',
    refreshButton: 'button:has-text("Refresh"), button:has-text("Làm mới")'
  },
  
  // Success selectors
  success: {
    // Success messages
    successMessage: '.success, .success-message, [data-testid="success"]',
    successAlert: '.alert-success, .alert.success, [data-testid="alert-success"]',
    
    // Success states
    successState: '.success-state, [data-testid="success-state"]'
  },
  
  // Responsive selectors
  responsive: {
    // Mobile elements
    mobileMenu: '.mobile-menu, [data-testid="mobile-menu"]',
    mobileNav: '.mobile-nav, [data-testid="mobile-nav"]',
    
    // Desktop elements
    desktopMenu: '.desktop-menu, [data-testid="desktop-menu"]',
    desktopNav: '.desktop-nav, [data-testid="desktop-nav"]',
    
    // Breakpoint indicators
    mobileView: '[data-testid="mobile-view"]',
    tabletView: '[data-testid="tablet-view"]',
    desktopView: '[data-testid="desktop-view"]'
  }
};

/**
 * Helper functions for selectors
 */
export class SelectorHelper {
  /**
   * Get selector with fallbacks
   */
  static getSelector(primary: string, fallbacks: string[] = []): string {
    const allSelectors = [primary, ...fallbacks];
    return allSelectors.join(', ');
  }

  /**
   * Get role-based selector
   */
  static getRoleSelector(role: string, name?: string): string {
    if (name) {
      return `[role="${role}"][aria-label*="${name}"], [role="${role}"]:has-text("${name}")`;
    }
    return `[role="${role}"]`;
  }

  /**
   * Get data-testid selector
   */
  static getTestIdSelector(testId: string): string {
    return `[data-testid="${testId}"]`;
  }

  /**
   * Get aria-label selector
   */
  static getAriaLabelSelector(label: string): string {
    return `[aria-label="${label}"]`;
  }

  /**
   * Get text-based selector
   */
  static getTextSelector(text: string): string {
    return `:has-text("${text}")`;
  }

  /**
   * Get input selector by name
   */
  static getInputSelector(name: string): string {
    return `input[name="${name}"], input[placeholder*="${name}"]`;
  }

  /**
   * Get button selector by text
   */
  static getButtonSelector(text: string): string {
    return `button:has-text("${text}")`;
  }

  /**
   * Get link selector by text
   */
  static getLinkSelector(text: string): string {
    return `a:has-text("${text}")`;
  }
}

/**
 * Common selector combinations
 */
export const commonSelectors = {
  // Form combinations
  loginForm: `${selectors.auth.emailInput}, ${selectors.auth.passwordInput}, ${selectors.auth.loginButton}`,
  projectForm: `${selectors.forms.nameInput}, ${selectors.forms.descriptionInput}, ${selectors.forms.submitButton}`,
  
  // Navigation combinations
  mainNav: `${selectors.navigation.sidebar}, ${selectors.navigation.menuToggle}`,
  userNav: `${selectors.navigation.userMenu}, ${selectors.auth.logoutButton}`,
  
  // Dashboard combinations
  dashboardContent: `${selectors.dashboard.kpiCards}, ${selectors.dashboard.quickActions}, ${selectors.dashboard.recentProjects}`,
  
  // Project combinations
  projectList: `${selectors.projects.list}, ${selectors.projects.projectCard}, ${selectors.projects.createButton}`,
  
  // Alert combinations
  alertList: `${selectors.alerts.list}, ${selectors.alerts.alertItem}, ${selectors.alerts.markAsReadButton}`,
  
  // Preferences combinations
  preferencesForm: `${selectors.preferences.themeToggle}, ${selectors.preferences.languageSelect}, ${selectors.preferences.saveButton}`
};

/**
 * Selector validation
 */
export class SelectorValidator {
  /**
   * Validate selector syntax
   */
  static validateSelector(selector: string): boolean {
    try {
      // Basic validation - check for common issues
      if (selector.includes('""') || selector.includes("''")) {
        return false;
      }
      
      if (selector.includes('undefined') || selector.includes('null')) {
        return false;
      }
      
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Get safe selector with validation
   */
  static getSafeSelector(selector: string, fallback: string = '*'): string {
    if (this.validateSelector(selector)) {
      return selector;
    }
    return fallback;
  }
}
