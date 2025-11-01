<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class FilterService
{
    /**
     * Get role-aware filter presets
     */
    public function getFilterPresets(): array
    {
        $isAdmin = Auth::user()->hasRole('super_admin');
        
        if ($isAdmin) {
            return $this->getAdminFilterPresets();
        } else {
            return $this->getTenantFilterPresets();
        }
    }
    
    /**
     * Get admin-specific filter presets
     */
    private function getAdminFilterPresets(): array
    {
        return [
            [
                'id' => 'admin_critical_alerts',
                'name' => 'Critical Alerts',
                'description' => 'Show only critical system alerts',
                'icon' => 'fas fa-exclamation-triangle',
                'filters' => [
                    'level' => 'critical',
                    'status' => 'active'
                ],
                'url' => '/admin/alerts?level=critical'
            ],
            [
                'id' => 'admin_active_users',
                'name' => 'Active Users',
                'description' => 'Users who logged in within 7 days',
                'icon' => 'fas fa-users',
                'filters' => [
                    'status' => 'active',
                    'last_login' => '7_days'
                ],
                'url' => '/admin/users?status=active&last_login=7_days'
            ],
            [
                'id' => 'admin_high_usage_tenants',
                'name' => 'High Usage Tenants',
                'description' => 'Tenants with high resource usage',
                'icon' => 'fas fa-chart-line',
                'filters' => [
                    'usage_level' => 'high',
                    'plan' => 'enterprise'
                ],
                'url' => '/admin/tenants?usage_level=high'
            ],
            [
                'id' => 'admin_recent_projects',
                'name' => 'Recent Projects',
                'description' => 'Projects created in last 30 days',
                'icon' => 'fas fa-project-diagram',
                'filters' => [
                    'created_at' => '30_days',
                    'status' => 'active'
                ],
                'url' => '/admin/projects?created_at=30_days'
            ]
        ];
    }
    
    /**
     * Get tenant-specific filter presets
     */
    private function getTenantFilterPresets(): array
    {
        return [
            [
                'id' => 'my_overdue_tasks',
                'name' => 'My Overdue Tasks',
                'description' => 'Tasks assigned to me that are overdue',
                'icon' => 'fas fa-clock',
                'filters' => [
                    'assigned_to' => 'me',
                    'status' => 'overdue'
                ],
                'url' => '/app/tasks?assigned_to=me&status=overdue'
            ],
            [
                'id' => 'at_risk_projects',
                'name' => 'At-Risk Projects',
                'description' => 'Projects with health status at-risk or critical',
                'icon' => 'fas fa-exclamation-circle',
                'filters' => [
                    'health' => ['at_risk', 'critical'],
                    'status' => 'active'
                ],
                'url' => '/app/projects?health=at_risk,critical'
            ],
            [
                'id' => 'due_this_week',
                'name' => 'Due This Week',
                'description' => 'Tasks and projects due within 7 days',
                'icon' => 'fas fa-calendar-week',
                'filters' => [
                    'due_date' => '7_days',
                    'status' => 'active'
                ],
                'url' => '/app/tasks?due_date=7_days'
            ],
            [
                'id' => 'pending_approvals',
                'name' => 'Pending Approvals',
                'description' => 'Documents and tasks awaiting approval',
                'icon' => 'fas fa-hourglass-half',
                'filters' => [
                    'status' => 'pending_approval',
                    'assigned_to' => 'me'
                ],
                'url' => '/app/documents?status=pending_approval'
            ],
            [
                'id' => 'high_priority_items',
                'name' => 'High Priority Items',
                'description' => 'All high priority tasks and projects',
                'icon' => 'fas fa-arrow-up',
                'filters' => [
                    'priority' => 'high',
                    'status' => 'active'
                ],
                'url' => '/app/tasks?priority=high'
            ],
            [
                'id' => 'recent_activities',
                'name' => 'Recent Activities',
                'description' => 'Items updated in the last 24 hours',
                'icon' => 'fas fa-history',
                'filters' => [
                    'updated_at' => '24_hours'
                ],
                'url' => '/app/activities?updated_at=24_hours'
            ]
        ];
    }
    
    /**
     * Get deep filter options for a specific context
     */
    public function getDeepFilters(string $context): array
    {
        switch ($context) {
            case 'projects':
                return $this->getProjectDeepFilters();
            case 'tasks':
                return $this->getTaskDeepFilters();
            case 'documents':
                return $this->getDocumentDeepFilters();
            case 'users':
                return $this->getUserDeepFilters();
            default:
                return [];
        }
    }
    
    /**
     * Get project deep filters
     */
    private function getProjectDeepFilters(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    ['value' => 'planning', 'label' => 'Planning'],
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'on_hold', 'label' => 'On Hold'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'cancelled', 'label' => 'Cancelled']
                ]
            ],
            'health' => [
                'label' => 'Health',
                'type' => 'select',
                'options' => [
                    ['value' => 'good', 'label' => 'Good'],
                    ['value' => 'at_risk', 'label' => 'At Risk'],
                    ['value' => 'critical', 'label' => 'Critical']
                ]
            ],
            'priority' => [
                'label' => 'Priority',
                'type' => 'select',
                'options' => [
                    ['value' => 'low', 'label' => 'Low'],
                    ['value' => 'medium', 'label' => 'Medium'],
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'urgent', 'label' => 'Urgent']
                ]
            ],
            'progress' => [
                'label' => 'Progress',
                'type' => 'range',
                'min' => 0,
                'max' => 100,
                'step' => 5
            ],
            'budget_range' => [
                'label' => 'Budget Range',
                'type' => 'range',
                'min' => 0,
                'max' => 1000000,
                'step' => 1000
            ],
            'created_date' => [
                'label' => 'Created Date',
                'type' => 'date_range'
            ],
            'due_date' => [
                'label' => 'Due Date',
                'type' => 'date_range'
            ],
            'team_member' => [
                'label' => 'Team Member',
                'type' => 'select',
                'options' => $this->getTeamMembers()
            ]
        ];
    }
    
    /**
     * Get task deep filters
     */
    private function getTaskDeepFilters(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'in_progress', 'label' => 'In Progress'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'cancelled', 'label' => 'Cancelled']
                ]
            ],
            'priority' => [
                'label' => 'Priority',
                'type' => 'select',
                'options' => [
                    ['value' => 'low', 'label' => 'Low'],
                    ['value' => 'medium', 'label' => 'Medium'],
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'urgent', 'label' => 'Urgent']
                ]
            ],
            'assigned_to' => [
                'label' => 'Assigned To',
                'type' => 'select',
                'options' => $this->getTeamMembers()
            ],
            'project' => [
                'label' => 'Project',
                'type' => 'select',
                'options' => $this->getProjects()
            ],
            'due_date' => [
                'label' => 'Due Date',
                'type' => 'date_range'
            ],
            'created_date' => [
                'label' => 'Created Date',
                'type' => 'date_range'
            ],
            'estimated_hours' => [
                'label' => 'Estimated Hours',
                'type' => 'range',
                'min' => 0,
                'max' => 100,
                'step' => 0.5
            ]
        ];
    }
    
    /**
     * Get document deep filters
     */
    private function getDocumentDeepFilters(): array
    {
        return [
            'type' => [
                'label' => 'Document Type',
                'type' => 'select',
                'options' => [
                    ['value' => 'pdf', 'label' => 'PDF'],
                    ['value' => 'docx', 'label' => 'Word Document'],
                    ['value' => 'xlsx', 'label' => 'Excel Spreadsheet'],
                    ['value' => 'pptx', 'label' => 'PowerPoint'],
                    ['value' => 'image', 'label' => 'Image'],
                    ['value' => 'other', 'label' => 'Other']
                ]
            ],
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'pending_approval', 'label' => 'Pending Approval'],
                    ['value' => 'approved', 'label' => 'Approved'],
                    ['value' => 'archived', 'label' => 'Archived']
                ]
            ],
            'project' => [
                'label' => 'Project',
                'type' => 'select',
                'options' => $this->getProjects()
            ],
            'uploaded_by' => [
                'label' => 'Uploaded By',
                'type' => 'select',
                'options' => $this->getTeamMembers()
            ],
            'upload_date' => [
                'label' => 'Upload Date',
                'type' => 'date_range'
            ],
            'file_size' => [
                'label' => 'File Size',
                'type' => 'range',
                'min' => 0,
                'max' => 100,
                'step' => 1
            ]
        ];
    }
    
    /**
     * Get user deep filters (admin only)
     */
    private function getUserDeepFilters(): array
    {
        return [
            'role' => [
                'label' => 'Role',
                'type' => 'select',
                'options' => [
                    ['value' => 'super_admin', 'label' => 'Super Admin'],
                    ['value' => 'admin', 'label' => 'Admin'],
                    ['value' => 'project_manager', 'label' => 'Project Manager'],
                    ['value' => 'developer', 'label' => 'Developer'],
                    ['value' => 'designer', 'label' => 'Designer'],
                    ['value' => 'client', 'label' => 'Client']
                ]
            ],
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                    ['value' => 'suspended', 'label' => 'Suspended']
                ]
            ],
            'last_login' => [
                'label' => 'Last Login',
                'type' => 'date_range'
            ],
            'created_date' => [
                'label' => 'Created Date',
                'type' => 'date_range'
            ],
            'tenant' => [
                'label' => 'Tenant',
                'type' => 'select',
                'options' => $this->getTenants()
            ]
        ];
    }
    
    /**
     * Get saved filter views for the user
     */
    public function getSavedViews(): array
    {
        $userId = Auth::id();
        $cacheKey = "saved_views_{$userId}";
        
        return Cache::get($cacheKey, []);
    }
    
    /**
     * Save a filter view
     */
    public function saveView(array $viewData): bool
    {
        $userId = Auth::id();
        $cacheKey = "saved_views_{$userId}";
        
        $savedViews = Cache::get($cacheKey, []);
        
        $viewData['id'] = uniqid();
        $viewData['created_at'] = now()->toISOString();
        
        $savedViews[] = $viewData;
        
        // Keep only last 20 saved views
        $savedViews = array_slice($savedViews, -20);
        
        Cache::put($cacheKey, $savedViews, 3600); // 1 hour
        
        return true;
    }
    
    /**
     * Delete a saved view
     */
    public function deleteView(string $viewId): bool
    {
        $userId = Auth::id();
        $cacheKey = "saved_views_{$userId}";
        
        $savedViews = Cache::get($cacheKey, []);
        $savedViews = array_filter($savedViews, fn($view) => $view['id'] !== $viewId);
        
        Cache::put($cacheKey, $savedViews, 3600);
        
        return true;
    }
    
    /**
     * Apply filters to data
     */
    public function applyFilters(array $data, array $filters): array
    {
        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            
            $data = $this->applyFilter($data, $key, $value);
        }
        
        return $data;
    }
    
    /**
     * Apply a single filter
     */
    private function applyFilter(array $data, string $key, $value): array
    {
        return array_filter($data, function ($item) use ($key, $value) {
            if (!isset($item[$key])) return false;
            
            if (is_array($value)) {
                return in_array($item[$key], $value);
            }
            
            if (str_contains($key, '_date') || str_contains($key, '_at')) {
                return $this->applyDateFilter($item[$key], $value);
            }
            
            if (str_contains($key, '_range')) {
                return $this->applyRangeFilter($item[$key], $value);
            }
            
            return $item[$key] === $value;
        });
    }
    
    /**
     * Apply date filter
     */
    private function applyDateFilter($itemDate, $filterValue): bool
    {
        // This would implement date range filtering
        return true; // Simplified for now
    }
    
    /**
     * Apply range filter
     */
    private function applyRangeFilter($itemValue, $filterValue): bool
    {
        if (is_array($filterValue) && count($filterValue) === 2) {
            return $itemValue >= $filterValue[0] && $itemValue <= $filterValue[1];
        }
        
        return true; // Simplified for now
    }
    
    // Mock data methods
    
    private function getTeamMembers(): array
    {
        return [
            ['value' => 'john_doe', 'label' => 'John Doe'],
            ['value' => 'jane_smith', 'label' => 'Jane Smith'],
            ['value' => 'mike_johnson', 'label' => 'Mike Johnson']
        ];
    }
    
    private function getProjects(): array
    {
        return [
            ['value' => 'website_redesign', 'label' => 'Website Redesign'],
            ['value' => 'mobile_app', 'label' => 'Mobile App Development'],
            ['value' => 'database_migration', 'label' => 'Database Migration']
        ];
    }
    
    private function getTenants(): array
    {
        return [
            ['value' => 'acme_corp', 'label' => 'Acme Corporation'],
            ['value' => 'techstart', 'label' => 'TechStart Inc']
        ];
    }
}
