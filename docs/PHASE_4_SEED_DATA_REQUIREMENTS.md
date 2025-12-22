# Phase 4 Seed Data & Hooks Requirements

**Date**: 2025-01-18  
**Phase**: Phase 4 - Advanced Features & Regression Testing  
**Status**: Planning  

---

## 📋 Seed Data Requirements

### 1. Extended User Data
**Purpose**: Comprehensive RBAC testing with all role combinations

#### User Roles to Seed:
- **Super Admin**: 2 users (cross-tenant access)
- **Admin**: 5 users per tenant (tenant management)
- **Project Manager**: 10 users per tenant (project management)
- **Developer**: 15 users per tenant (task assignment)
- **Client**: 20 users per tenant (read-only access)
- **Guest**: 5 users per tenant (limited access)

#### User Attributes:
```php
$extendedUsers = [
    [
        'email' => 'superadmin@zena.local',
        'name' => 'ZENA Super Admin',
        'role' => 'super_admin',
        'password' => 'password',
        'is_active' => true,
        'email_verified_at' => now(),
        'timezone' => 'Asia/Ho_Chi_Minh',
        'language' => 'vi',
        'last_login_at' => now()->subDays(1),
        'created_at' => now()->subMonths(6),
    ],
    // ... more users with different roles and attributes
];
```

#### User Preferences:
- **Theme**: Light, Dark, Auto
- **Language**: English, Vietnamese
- **Timezone**: Various timezones
- **Notifications**: Email, In-app, SMS preferences
- **Dashboard**: Widget preferences and layout

### 2. Large Dataset Requirements
**Purpose**: Performance testing with realistic data volumes

#### Projects Data:
- **Total Projects**: 100+ per tenant
- **Project Statuses**: Planning, Active, On Hold, Completed, Cancelled
- **Project Types**: Web Development, Mobile App, Consulting, Maintenance
- **Project Sizes**: Small (1-5 tasks), Medium (6-20 tasks), Large (21+ tasks)

#### Tasks Data:
- **Total Tasks**: 500+ per tenant
- **Task Priorities**: Low, Medium, High, Critical
- **Task Statuses**: Todo, In Progress, Review, Done, Cancelled
- **Task Types**: Development, Testing, Documentation, Review
- **Task Dependencies**: Complex dependency chains

#### Documents Data:
- **Total Documents**: 200+ per tenant
- **Document Types**: PDF, Word, Excel, Images, Code files
- **Document Sizes**: Small (<1MB), Medium (1-10MB), Large (10MB+)
- **Document Categories**: Requirements, Design, Code, Testing, Documentation

### 3. Multi-language Content
**Purpose**: Internationalization testing

#### English Content:
- Project names, descriptions, and documentation
- Task titles, descriptions, and comments
- Document names and content
- User profiles and preferences

#### Vietnamese Content:
- Translated project names and descriptions
- Localized task content and comments
- Vietnamese document names and content
- Localized user interface elements

### 4. Timezone Data
**Purpose**: Timezone handling and conversion testing

#### User Timezones:
- **Asia/Ho_Chi_Minh**: Primary timezone
- **UTC**: Universal timezone
- **America/New_York**: Eastern timezone
- **Europe/London**: GMT timezone
- **Asia/Tokyo**: JST timezone

#### Timezone-aware Data:
- Project deadlines and milestones
- Task due dates and schedules
- Document timestamps
- User activity logs
- System notifications

---

## 🔧 Database Hooks & Events

### 1. Model Events
**Purpose**: Data integrity and audit logging

#### User Model Events:
```php
// User model events
protected static function boot()
{
    parent::boot();
    
    static::creating(function ($user) {
        $user->created_by = auth()->id();
        $user->created_at = now();
    });
    
    static::updating(function ($user) {
        $user->updated_by = auth()->id();
        $user->updated_at = now();
    });
    
    static::deleting(function ($user) {
        // Soft delete with audit trail
        $user->deleted_by = auth()->id();
        $user->deleted_at = now();
    });
}
```

#### Project Model Events:
```php
// Project model events
protected static function boot()
{
    parent::boot();
    
    static::created(function ($project) {
        // Create default project settings
        $project->settings()->create([
            'notifications_enabled' => true,
            'auto_assign_tasks' => false,
            'require_approval' => true,
        ]);
        
        // Log project creation
        activity()
            ->performedOn($project)
            ->log('Project created');
    });
    
    static::updated(function ($project) {
        // Log project updates
        activity()
            ->performedOn($project)
            ->log('Project updated');
    });
}
```

### 2. Database Observers
**Purpose**: Complex business logic and data validation

#### UserObserver:
```php
class UserObserver
{
    public function creating(User $user)
    {
        // Generate unique user code
        $user->user_code = $this->generateUserCode();
        
        // Set default preferences
        $user->preferences = [
            'theme' => 'light',
            'language' => 'en',
            'timezone' => 'UTC',
            'notifications' => [
                'email' => true,
                'in_app' => true,
                'sms' => false,
            ],
        ];
    }
    
    public function created(User $user)
    {
        // Create user dashboard
        $user->dashboard()->create([
            'name' => 'Default Dashboard',
            'is_default' => true,
            'widgets' => $this->getDefaultWidgets(),
        ]);
        
        // Send welcome email
        Mail::to($user->email)->send(new WelcomeEmail($user));
    }
    
    public function updated(User $user)
    {
        // Update user activity
        $user->touch('last_activity_at');
        
        // Log user changes
        activity()
            ->performedOn($user)
            ->log('User profile updated');
    }
}
```

#### ProjectObserver:
```php
class ProjectObserver
{
    public function creating(Project $project)
    {
        // Set project code
        $project->project_code = $this->generateProjectCode();
        
        // Set default status
        $project->status = 'planning';
        
        // Set default progress
        $project->progress = 0;
    }
    
    public function created(Project $project)
    {
        // Create project team
        $project->team()->create([
            'name' => $project->name . ' Team',
            'description' => 'Default project team',
        ]);
        
        // Create project milestones
        $this->createDefaultMilestones($project);
        
        // Notify project stakeholders
        $this->notifyProjectStakeholders($project);
    }
    
    public function updated(Project $project)
    {
        // Update project progress
        $this->updateProjectProgress($project);
        
        // Check for milestone completion
        $this->checkMilestoneCompletion($project);
        
        // Log project changes
        activity()
            ->performedOn($project)
            ->log('Project updated');
    }
}
```

### 3. Event Listeners
**Purpose**: Asynchronous processing and notifications

#### User Event Listeners:
```php
class UserEventSubscriber
{
    public function handleUserCreated($event)
    {
        // Create user preferences
        $event->user->preferences()->create([
            'theme' => 'light',
            'language' => 'en',
            'timezone' => 'UTC',
        ]);
        
        // Send welcome notification
        $event->user->notify(new WelcomeNotification());
        
        // Create user activity log
        activity()
            ->performedOn($event->user)
            ->log('User account created');
    }
    
    public function handleUserUpdated($event)
    {
        // Update user activity
        $event->user->touch('last_activity_at');
        
        // Log user changes
        activity()
            ->performedOn($event->user)
            ->log('User profile updated');
    }
    
    public function handleUserDeleted($event)
    {
        // Soft delete user data
        $event->user->update([
            'deleted_at' => now(),
            'deleted_by' => auth()->id(),
        ]);
        
        // Log user deletion
        activity()
            ->performedOn($event->user)
            ->log('User account deleted');
    }
}
```

#### Project Event Listeners:
```php
class ProjectEventSubscriber
{
    public function handleProjectCreated($event)
    {
        // Create project settings
        $event->project->settings()->create([
            'notifications_enabled' => true,
            'auto_assign_tasks' => false,
            'require_approval' => true,
        ]);
        
        // Create project team
        $event->project->team()->create([
            'name' => $event->project->name . ' Team',
            'description' => 'Default project team',
        ]);
        
        // Notify project stakeholders
        $this->notifyProjectStakeholders($event->project);
        
        // Log project creation
        activity()
            ->performedOn($event->project)
            ->log('Project created');
    }
    
    public function handleProjectUpdated($event)
    {
        // Update project progress
        $this->updateProjectProgress($event->project);
        
        // Check for milestone completion
        $this->checkMilestoneCompletion($event->project);
        
        // Log project changes
        activity()
            ->performedOn($event->project)
            ->log('Project updated');
    }
}
```

---

## 🗄️ Database Seeder Updates

### 1. Extended E2E Seeder
**Purpose**: Comprehensive test data for Phase 4 testing

#### Seeder Structure:
```php
class Phase4E2EDatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create extended users
        $this->createExtendedUsers();
        
        // Create large datasets
        $this->createLargeDatasets();
        
        // Create multi-language content
        $this->createMultiLanguageContent();
        
        // Create timezone data
        $this->createTimezoneData();
        
        // Create performance test data
        $this->createPerformanceTestData();
    }
    
    private function createExtendedUsers()
    {
        // Create users with all role combinations
        $roles = ['super_admin', 'admin', 'project_manager', 'developer', 'client', 'guest'];
        
        foreach ($roles as $role) {
            $this->createUsersForRole($role);
        }
    }
    
    private function createLargeDatasets()
    {
        // Create 100+ projects per tenant
        $this->createProjects(100);
        
        // Create 500+ tasks per tenant
        $this->createTasks(500);
        
        // Create 200+ documents per tenant
        $this->createDocuments(200);
    }
    
    private function createMultiLanguageContent()
    {
        // Create English content
        $this->createEnglishContent();
        
        // Create Vietnamese content
        $this->createVietnameseContent();
    }
    
    private function createTimezoneData()
    {
        // Create users across different timezones
        $timezones = [
            'Asia/Ho_Chi_Minh',
            'UTC',
            'America/New_York',
            'Europe/London',
            'Asia/Tokyo',
        ];
        
        foreach ($timezones as $timezone) {
            $this->createUsersInTimezone($timezone);
        }
    }
    
    private function createPerformanceTestData()
    {
        // Create large files for performance testing
        $this->createLargeFiles();
        
        // Create complex data relationships
        $this->createComplexRelationships();
        
        // Create data for load testing
        $this->createLoadTestData();
    }
}
```

### 2. Performance Test Seeder
**Purpose**: Data specifically for performance testing

#### Performance Data:
- **Large Files**: 10MB+ files for upload testing
- **Complex Queries**: Data that triggers complex database queries
- **Bulk Operations**: Large datasets for bulk operation testing
- **Concurrent Users**: Data for concurrent user testing

### 3. Security Test Seeder
**Purpose**: Data for security testing

#### Security Data:
- **Malicious Content**: SQL injection attempts, XSS payloads
- **Invalid Data**: Invalid email formats, malformed data
- **Edge Cases**: Boundary values, special characters
- **Permission Data**: Users with various permission combinations

---

## 🔄 Database Migration Updates

### 1. New Tables for Phase 4
**Purpose**: Support advanced features and testing

#### Tables to Add:
- `user_preferences`: User-specific settings and preferences
- `project_settings`: Project-specific configuration
- `activity_logs`: Audit trail and activity tracking
- `notification_preferences`: User notification settings
- `timezone_data`: Timezone information and conversions
- `performance_metrics`: Performance monitoring data

### 2. Index Optimization
**Purpose**: Improve query performance for large datasets

#### Indexes to Add:
- Composite indexes on frequently queried columns
- Full-text indexes for search functionality
- Partial indexes for filtered queries
- Covering indexes for common query patterns

### 3. Data Validation
**Purpose**: Ensure data integrity and consistency

#### Validation Rules:
- Foreign key constraints
- Check constraints for data validation
- Unique constraints for data uniqueness
- Default values for required fields

---

## 📊 Monitoring & Observability

### 1. Performance Monitoring
**Purpose**: Track system performance during testing

#### Metrics to Track:
- Database query performance
- API response times
- Memory usage and leaks
- CPU utilization
- Disk I/O performance

### 2. Error Tracking
**Purpose**: Monitor and log errors during testing

#### Error Types:
- Database errors and exceptions
- API errors and failures
- Frontend errors and crashes
- Performance degradation
- Security violations

### 3. Audit Logging
**Purpose**: Track all system activities and changes

#### Audit Events:
- User authentication and authorization
- Data creation, modification, and deletion
- System configuration changes
- Security events and violations
- Performance metrics and alerts

---

**Last Updated**: 2025-01-18  
**Next Review**: After Phase 4 Implementation Complete
