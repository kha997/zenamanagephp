<?php

return [
    // Brand
    'brand_name' => 'ZenaManage',
    
    // Greeting
    'greeting' => 'Hello',
    
    // Navigation
    'nav' => [
        'dashboard' => 'Dashboard',
        'projects' => 'Projects',
        'tasks' => 'Tasks',
        'calendar' => 'Calendar',
        'team' => 'Team',
        'documents' => 'Documents',
        'templates' => 'Templates',
        'settings' => 'Settings',
    ],
    
    // Notifications
    'notifications' => [
        'title' => 'Notifications',
        'empty' => 'No notifications',
        'view_all' => 'View all notifications',
        'new_project' => 'New Project',
        'project_created' => 'A new project has been created',
        'task_assigned' => 'Task Assigned',
        'task_assigned_message' => 'You have been assigned a new task',
    ],
    
    // User Menu
    'user_menu' => [
        'profile' => 'Profile',
        'settings' => 'Settings',
        'logout' => 'Logout',
    ],
    
    // Toolbar
    'search_placeholder' => 'Search...',
    'filters' => 'Filters',
    'clear_filters' => 'Clear Filters',
    'apply_filters' => 'Apply Filters',
    'export' => 'Export',
    'export_csv' => 'Export CSV',
    'export_excel' => 'Export Excel',
    'export_pdf' => 'Export PDF',
    'all' => 'All',
    
    // Focus Mode
    'focus_mode' => 'Focus Mode',
    'focus' => 'Focus',
    'focus_mode_active' => 'Focus Mode Active',
    'focus_mode_description' => 'Minimal interface for better concentration',
    'exit_focus_mode' => 'Exit Focus Mode',
    'enter_focus_mode' => 'Enter Focus Mode',
    'minimal_ui' => 'Minimal UI',
    
    // Rewards
    'rewards' => 'Rewards',
    'rewards_active' => 'Rewards Active',
    'rewards_description' => 'Celebration animations for completed tasks',
    'disable_rewards' => 'Disable Rewards',
    'enable_rewards' => 'Enable Rewards',
    'celebrations' => 'Celebrations',
    'congratulations' => 'Congratulations!',
    'great_job_task_completed' => 'Great job! Task completed 🎉',
    'keep_up_great_work' => 'Keep up the great work!',
    'task_completed_successfully' => 'Task completed successfully',
    
    // Feature Flags
    'feature_disabled' => 'Feature Disabled',
    'feature_enabled' => 'Feature Enabled',
    'on' => 'On',
    'off' => 'Off',
    
    // Projects
    'projects' => [
        'title' => 'Projects',
        'search_placeholder' => 'Search projects...',
        'all_status' => 'All Status',
        'all_priorities' => 'All Priorities',
        'view_mode' => 'View Mode',
        'table_view' => 'Table',
        'card_view' => 'Cards',
        'filters_applied' => 'Filters (:count)',
        'clear_filters' => 'Clear Filters',
        
        'status' => [
            'planning' => 'Planning',
            'active' => 'Active',
            'on_hold' => 'On Hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
        
        'priority' => [
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ],
        
        'sort' => [
            'name' => 'Sort by Name',
            'date' => 'Sort by Date',
            'priority' => 'Sort by Priority',
            'progress' => 'Sort by Progress',
        ],
        
        'table' => [
            'name' => 'Name',
            'status' => 'Status',
            'priority' => 'Priority',
            'progress' => 'Progress',
            'team' => 'Team',
            'created' => 'Created',
            'actions' => 'Actions',
        ],
        
        'empty' => [
            'title' => 'No projects found',
            'description' => 'Get started by creating a new project.',
            'action' => 'New Project',
        ],
        
        'no_description' => 'No description available',
        'members' => 'members',
        'unknown' => 'Unknown',
        'progress' => 'Progress',
        'view_details' => 'View Details',
        'edit_project' => 'Edit Project',
        'subtitle' => 'Manage and track your project portfolio',
        'create_new' => 'New Project',
        'recent_activity' => 'Recent Project Activity',
        'activity' => [
            'created' => 'Project ":name" was created.',
            'empty' => 'No recent project activity.',
        ],
        'displayed' => 'displayed',
        'avg_progress' => 'avg progress',
        'success' => 'Success!',
        'none_yet' => 'None yet',
        'in_archive' => 'In archive',
        'none_archived' => 'None archived',
        'view_all' => 'View All',
        'manage_active' => 'Manage Active',
        'view_completed' => 'View Completed',
        'view_archived' => 'View Archived',
    ],
    
    // Tasks
    'tasks' => [
        'title' => 'Tasks',
        'search_placeholder' => 'Search tasks...',
        'all_status' => 'All Status',
        'all_projects' => 'All Projects',
        'all_assignees' => 'All Assignees',
        
        'status' => [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'cancelled' => 'Cancelled',
        ],
        
        'empty' => [
            'title' => 'No tasks found',
            'description' => 'Get started by creating a new task.',
            'action' => 'New Task',
        ],
    ],
    
    // Common
    'common' => [
        'dismiss' => 'Dismiss',
        'loading' => 'Loading...',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Update',
        'search' => 'Search',
        'filter' => 'Filter',
        'sort' => 'Sort',
        'actions' => 'Actions',
    ],
    
    // Pagination
    'pagination' => [
        'previous' => 'Previous',
        'next' => 'Next',
        'showing' => 'Showing',
        'to' => 'to',
        'of' => 'of',
        'results' => 'results',
    ],
    
    // KPI Labels
    'kpi' => [
        'total_projects' => 'Total Projects',
        'active_projects' => 'Active Projects',
        'completed_projects' => 'Completed',
        'archived_projects' => 'Archived',
        'total_tasks' => 'Total Tasks',
        'pending_tasks' => 'Pending Tasks',
        'completed_tasks' => 'Completed Tasks',
        'overdue_tasks' => 'Overdue Tasks',
    ],
];
