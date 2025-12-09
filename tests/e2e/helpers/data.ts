/**
 * Test Data for E2E Smoke Tests
 * 
 * Contains all test data needed for smoke testing scenarios
 */

export interface TestUser {
  email: string;
  password: string;
  role: string;
  tenant: string;
  permissions: string[];
}

export interface TestProject {
  id: string;
  name: string;
  code: string;
  description: string;
  status: string;
  tenant: string;
}

export interface TestTask {
  id: string;
  name: string;
  description: string;
  status: string;
  projectId: string;
  assigneeId: string;
}

export interface TestAlert {
  id: string;
  title: string;
  message: string;
  type: 'info' | 'warning' | 'error' | 'success';
  userId: string;
  isRead: boolean;
}

export interface TestPreference {
  userId: string;
  theme: 'light' | 'dark' | 'auto';
  language: 'en' | 'vi';
  notifications: {
    email: boolean;
    push: boolean;
    inApp: boolean;
  };
  density: 'compact' | 'comfortable' | 'spacious';
}

/**
 * Test users for E2E smoke tests
 */
export const testUsers: TestUser[] = [
  // ZENA Company Users
  {
    email: 'owner@zena.local',
    password: 'password',
    role: 'Owner',
    tenant: 'ZENA Company',
    permissions: ['*'] // All permissions
  },
  {
    email: 'admin@zena.local',
    password: 'password',
    role: 'Admin',
    tenant: 'ZENA Company',
    permissions: [
      'projects.create', 'projects.read', 'projects.update',
      'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
      'documents.create', 'documents.read', 'documents.update', 'documents.delete',
      'users.create', 'users.read', 'users.update',
      'teams.create', 'teams.read', 'teams.update',
      'settings.read', 'settings.update',
      'reports.read', 'reports.create'
    ]
  },
  {
    email: 'pm@zena.local',
    password: 'password',
    role: 'Project Manager',
    tenant: 'ZENA Company',
    permissions: [
      'projects.read', 'projects.update',
      'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
      'documents.create', 'documents.read', 'documents.update', 'documents.delete',
      'teams.read', 'teams.update',
      'reports.read'
    ]
  },
  {
    email: 'dev@zena.local',
    password: 'password',
    role: 'Developer',
    tenant: 'ZENA Company',
    permissions: [
      'projects.read',
      'tasks.read', 'tasks.update',
      'documents.read', 'documents.create',
      'teams.read'
    ]
  },
  {
    email: 'guest@zena.local',
    password: 'password',
    role: 'Guest',
    tenant: 'ZENA Company',
    permissions: [
      'projects.read',
      'documents.read'
    ]
  },
  
  // TTF Company Users
  {
    email: 'owner@ttf.local',
    password: 'password',
    role: 'Owner',
    tenant: 'TTF Company',
    permissions: ['*'] // All permissions
  },
  {
    email: 'admin@ttf.local',
    password: 'password',
    role: 'Admin',
    tenant: 'TTF Company',
    permissions: [
      'projects.create', 'projects.read', 'projects.update',
      'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
      'documents.create', 'documents.read', 'documents.update', 'documents.delete',
      'users.create', 'users.read', 'users.update',
      'teams.create', 'teams.read', 'teams.update',
      'settings.read', 'settings.update',
      'reports.read', 'reports.create'
    ]
  },
  {
    email: 'pm@ttf.local',
    password: 'password',
    role: 'Project Manager',
    tenant: 'TTF Company',
    permissions: [
      'projects.read', 'projects.update',
      'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
      'documents.create', 'documents.read', 'documents.update', 'documents.delete',
      'teams.read', 'teams.update',
      'reports.read'
    ]
  },
  {
    email: 'dev@ttf.local',
    password: 'password',
    role: 'Developer',
    tenant: 'TTF Company',
    permissions: [
      'projects.read',
      'tasks.read', 'tasks.update',
      'documents.read', 'documents.create',
      'teams.read'
    ]
  },
  {
    email: 'guest@ttf.local',
    password: 'password',
    role: 'Guest',
    tenant: 'TTF Company',
    permissions: [
      'projects.read',
      'documents.read'
    ]
  }
];

/**
 * Test projects for E2E smoke tests
 */
export const testProjects: TestProject[] = [
  {
    id: 'proj_e2e_001',
    name: 'E2E Test Project 1',
    code: 'E2E-001',
    description: 'Project for E2E testing - Basic functionality',
    status: 'active',
    tenant: 'ZENA Company'
  },
  {
    id: 'proj_e2e_002',
    name: 'E2E Test Project 2',
    code: 'E2E-002',
    description: 'Project for E2E testing - Advanced features',
    status: 'planning',
    tenant: 'ZENA Company'
  }
];

/**
 * Test tasks for E2E smoke tests
 */
export const testTasks: TestTask[] = [
  {
    id: 'task_e2e_001',
    name: 'Setup E2E Environment',
    description: 'Set up the E2E testing environment',
    status: 'completed',
    projectId: 'proj_e2e_001',
    assigneeId: 'admin@zena.local'
  },
  {
    id: 'task_e2e_002',
    name: 'Write Smoke Tests',
    description: 'Write comprehensive smoke tests',
    status: 'in_progress',
    projectId: 'proj_e2e_001',
    assigneeId: 'pm@zena.local'
  },
  {
    id: 'task_e2e_003',
    name: 'Test Project Creation',
    description: 'Test project creation functionality',
    status: 'pending',
    projectId: 'proj_e2e_002',
    assigneeId: 'dev@zena.local'
  }
];

/**
 * Test alerts for E2E smoke tests
 */
export const testAlerts: TestAlert[] = [
  {
    id: 'alert_e2e_001',
    title: 'Project Deadline Approaching',
    message: 'E2E Test Project 1 deadline is approaching',
    type: 'warning',
    userId: 'admin@zena.local',
    isRead: false
  },
  {
    id: 'alert_e2e_002',
    title: 'Task Completed',
    message: 'Setup E2E Environment task has been completed',
    type: 'success',
    userId: 'pm@zena.local',
    isRead: true
  },
  {
    id: 'alert_e2e_003',
    title: 'System Maintenance',
    message: 'Scheduled system maintenance will occur tonight',
    type: 'info',
    userId: 'owner@zena.local',
    isRead: false
  }
];

/**
 * Test preferences for E2E smoke tests
 */
export const testPreferences: TestPreference[] = [
  {
    userId: 'admin@zena.local',
    theme: 'light',
    language: 'en',
    notifications: {
      email: true,
      push: true,
      inApp: true
    },
    density: 'comfortable'
  },
  {
    userId: 'pm@zena.local',
    theme: 'dark',
    language: 'vi',
    notifications: {
      email: true,
      push: false,
      inApp: true
    },
    density: 'compact'
  },
  {
    userId: 'dev@zena.local',
    theme: 'auto',
    language: 'en',
    notifications: {
      email: false,
      push: false,
      inApp: true
    },
    density: 'spacious'
  }
];

/**
 * Helper functions for test data
 */
export class TestDataHelper {
  /**
   * Get users by tenant
   */
  static getUsersByTenant(tenant: string): TestUser[] {
    return testUsers.filter(user => user.tenant === tenant);
  }

  /**
   * Get users by role
   */
  static getUsersByRole(role: string): TestUser[] {
    return testUsers.filter(user => user.role === role);
  }

  /**
   * Get user by email
   */
  static getUserByEmail(email: string): TestUser | undefined {
    return testUsers.find(user => user.email === email);
  }

  /**
   * Get projects by tenant
   */
  static getProjectsByTenant(tenant: string): TestProject[] {
    return testProjects.filter(project => project.tenant === tenant);
  }

  /**
   * Get tasks by project
   */
  static getTasksByProject(projectId: string): TestTask[] {
    return testTasks.filter(task => task.projectId === projectId);
  }

  /**
   * Get alerts by user
   */
  static getAlertsByUser(userId: string): TestAlert[] {
    return testAlerts.filter(alert => alert.userId === userId);
  }

  /**
   * Get preferences by user
   */
  static getPreferencesByUser(userId: string): TestPreference | undefined {
    return testPreferences.find(pref => pref.userId === userId);
  }

  /**
   * Generate unique test data
   */
  static generateUniqueProject(): TestProject {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 1000);
    
    return {
      id: `proj_${timestamp}_${random}`,
      name: `Test Project ${timestamp}`,
      code: `TP${timestamp}`,
      description: 'Generated test project',
      status: 'planning',
      tenant: 'ZENA Company'
    };
  }

  static generateUniqueTask(projectId: string): TestTask {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 1000);
    
    return {
      id: `task_${timestamp}_${random}`,
      name: `Test Task ${timestamp}`,
      description: 'Generated test task',
      status: 'pending',
      projectId,
      assigneeId: 'admin@zena.local'
    };
  }

  static generateUniqueAlert(userId: string): TestAlert {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 1000);
    
    return {
      id: `alert_${timestamp}_${random}`,
      title: `Test Alert ${timestamp}`,
      message: 'Generated test alert',
      type: 'info',
      userId,
      isRead: false
    };
  }
}

/**
 * Test scenarios for smoke tests
 */
export const smokeTestScenarios = {
  // Authentication scenarios
  auth: {
    login: {
      validCredentials: {
        email: 'admin@zena.local',
        password: 'password'
      },
      invalidCredentials: {
        email: 'invalid@test.local',
        password: 'wrongpassword'
      },
      emptyCredentials: {
        email: '',
        password: ''
      }
    },
    logout: {
      fromDashboard: true,
      fromProjects: true,
      fromSettings: true
    }
  },
  
  // Dashboard scenarios
  dashboard: {
    load: {
      asAdmin: 'admin@zena.local',
      asPM: 'pm@zena.local',
      asDev: 'dev@zena.local',
      asGuest: 'guest@zena.local'
    },
    themeToggle: {
      lightToDark: true,
      darkToLight: true,
      persistence: true
    }
  },
  
  // Project scenarios
  projects: {
    create: {
      minimalData: {
        name: 'Minimal Project',
        description: ''
      },
      fullData: {
        name: 'Full Project',
        description: 'Complete project description',
        code: 'FP001',
        status: 'planning'
      },
      invalidData: {
        name: '', // Empty name should fail
        description: 'Invalid project'
      }
    },
    list: {
      viewAll: true,
      filterByStatus: true,
      searchByName: true
    }
  },
  
  // Alert scenarios
  alerts: {
    view: {
      allAlerts: true,
      unreadAlerts: true,
      readAlerts: true
    },
    actions: {
      markAsRead: true,
      dismiss: true,
      filter: true
    }
  },
  
  // Preferences scenarios
  preferences: {
    theme: {
      light: 'light',
      dark: 'dark',
      auto: 'auto'
    },
    language: {
      english: 'en',
      vietnamese: 'vi'
    },
    notifications: {
      email: true,
      push: false,
      inApp: true
    }
  }
};

/**
 * Test data object for easy access in tests
 */
export const testData = {
  adminUser: testUsers.find(user => user.email === 'admin@zena.local')!,
  projectManager: testUsers.find(user => user.email === 'pm@zena.local')!, // Added this line
  pmUser: testUsers.find(user => user.email === 'pm@zena.local')!,
  devUser: testUsers.find(user => user.email === 'dev@zena.local')!,
  guestUser: testUsers.find(user => user.email === 'guest@zena.local')!,
  ownerUser: testUsers.find(user => user.email === 'owner@zena.local')!,
  users: {
    zena: testUsers.filter(user => user.tenant === 'ZENA Company'),
    ttf: testUsers.filter(user => user.tenant === 'TTF Company')
  },
  projects: testProjects,
  tasks: testTasks,
  alerts: testAlerts,
  preferences: testPreferences
};

/**
 * Expected results for smoke tests
 */
export const expectedResults = {
  auth: {
    loginSuccess: {
      redirectUrl: /\/app\/dashboard|\/dashboard/,
      pageTitle: /Dashboard|ZENA Manage/
    },
    loginFailure: {
      stayOnLoginPage: true,
      showErrorMessage: true
    }
  },
  
  dashboard: {
    loadSuccess: {
      kpiCardsVisible: true,
      quickActionsVisible: true,
      recentProjectsVisible: true
    }
  },
  
  projects: {
    createSuccess: {
      redirectToProject: true,
      projectInList: true
    },
    createFailure: {
      stayOnForm: true,
      showValidationError: true
    }
  },
  
  alerts: {
    loadSuccess: {
      alertsVisible: true,
      filterControlsVisible: true
    }
  },
  
  preferences: {
    saveSuccess: {
      noErrors: true,
      settingsPersist: true
    }
  }
};
