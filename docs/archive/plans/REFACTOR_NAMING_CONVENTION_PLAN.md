# 🔄 REFACTOR NAMING CONVENTION PLAN

## 📋 **Mục tiêu**
- Loại bỏ prefix `zena_` khỏi tất cả database tables
- Đảm bảo tất cả references được cập nhật đồng bộ
- Không gây breaking changes cho existing functionality
- Follow Laravel naming conventions

## 🎯 **Scope**
- Database tables và migrations
- Model classes và relationships
- Controllers và Services
- Views và Blade templates
- Configuration files
- Tests và Factories

---

## 📊 **PHASE 1: ANALYSIS & MAPPING**

### 1.1 **Identify all zena_ prefixed tables**
```sql
-- Tables to refactor:
zena_users → users
zena_components → components  
zena_task_assignments → task_assignments
zena_documents → documents
zena_notifications → notifications
zena_roles → roles
zena_permissions → permissions
zena_role_permissions → role_permissions
zena_user_roles → user_roles
zena_audit_logs → audit_logs
zena_email_tracking → email_tracking
zena_system_settings → system_settings
zena_work_templates → work_templates
zena_template_tasks → template_tasks
zena_design_construction → design_construction
zena_change_requests → change_requests
zena_change_request_comments → change_request_comments
zena_change_request_approvals → change_request_approvals
```

### 1.2 **Create mapping file**
```json
{
  "table_mappings": {
    "zena_users": "users",
    "zena_components": "components",
    "zena_task_assignments": "task_assignments",
    "zena_documents": "documents",
    "zena_notifications": "notifications",
    "zena_roles": "roles",
    "zena_permissions": "permissions",
    "zena_role_permissions": "role_permissions",
    "zena_user_roles": "user_roles",
    "zena_audit_logs": "audit_logs",
    "zena_email_tracking": "email_tracking",
    "zena_system_settings": "system_settings",
    "zena_work_templates": "work_templates",
    "zena_template_tasks": "template_tasks",
    "zena_design_construction": "design_construction",
    "zena_change_requests": "change_requests",
    "zena_change_request_comments": "change_request_comments",
    "zena_change_request_approvals": "change_request_approvals"
  },
  "model_mappings": {
    "ZenaUser": "User",
    "ZenaComponent": "Component",
    "ZenaTaskAssignment": "TaskAssignment",
    "ZenaDocument": "Document",
    "ZenaNotification": "Notification",
    "ZenaRole": "Role",
    "ZenaPermission": "Permission",
    "ZenaAuditLog": "AuditLog",
    "ZenaEmailTracking": "EmailTracking",
    "ZenaSystemSetting": "SystemSetting",
    "ZenaWorkTemplate": "WorkTemplate",
    "ZenaTemplateTask": "TemplateTask",
    "ZenaDesignConstruction": "DesignConstruction",
    "ZenaChangeRequest": "ChangeRequest",
    "ZenaChangeRequestComment": "ChangeRequestComment",
    "ZenaChangeRequestApproval": "ChangeRequestApproval"
  }
}
```

---

## 🔧 **PHASE 2: DATABASE REFACTORING**

### 2.1 **Create rename migrations**
```php
// 2025_09_19_180000_rename_zena_tables.php
public function up()
{
    // Rename tables
    Schema::rename('zena_users', 'users');
    Schema::rename('zena_components', 'components');
    Schema::rename('zena_task_assignments', 'task_assignments');
    Schema::rename('zena_documents', 'documents');
    Schema::rename('zena_notifications', 'notifications');
    Schema::rename('zena_roles', 'roles');
    Schema::rename('zena_permissions', 'permissions');
    Schema::rename('zena_role_permissions', 'role_permissions');
    Schema::rename('zena_user_roles', 'user_roles');
    Schema::rename('zena_audit_logs', 'audit_logs');
    Schema::rename('zena_email_tracking', 'email_tracking');
    Schema::rename('zena_system_settings', 'system_settings');
    Schema::rename('zena_work_templates', 'work_templates');
    Schema::rename('zena_template_tasks', 'template_tasks');
    Schema::rename('zena_design_construction', 'design_construction');
    Schema::rename('zena_change_requests', 'change_requests');
    Schema::rename('zena_change_request_comments', 'change_request_comments');
    Schema::rename('zena_change_request_approvals', 'change_request_approvals');
}

public function down()
{
    // Rollback renames
    Schema::rename('users', 'zena_users');
    Schema::rename('components', 'zena_components');
    // ... etc
}
```

### 2.2 **Update foreign key constraints**
```php
// Update all foreign key references
Schema::table('tasks', function (Blueprint $table) {
    $table->dropForeign(['assignee_id']);
    $table->foreign('assignee_id')->references('id')->on('users');
});

Schema::table('task_assignments', function (Blueprint $table) {
    $table->dropForeign(['user_id']);
    $table->foreign('user_id')->references('id')->on('users');
    $table->dropForeign(['task_id']);
    $table->foreign('task_id')->references('id')->on('tasks');
});
```

---

## 🏗️ **PHASE 3: MODEL REFACTORING**

### 3.1 **Update Model classes**
```php
// app/Models/User.php
class User extends Model
{
    protected $table = 'users'; // Changed from 'zena_users'
    
    // Update relationships
    public function tasks()
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }
    
    public function taskAssignments()
    {
        return $this->hasMany(TaskAssignment::class);
    }
}

// app/Models/Component.php  
class Component extends Model
{
    protected $table = 'components'; // Changed from 'zena_components'
    
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
```

### 3.2 **Update Model relationships**
```php
// Update all relationship references
// Before: $this->belongsTo(ZenaUser::class)
// After:  $this->belongsTo(User::class)

// Before: $this->hasMany(ZenaTaskAssignment::class)
// After:  $this->hasMany(TaskAssignment::class)
```

---

## 🎮 **PHASE 4: CONTROLLER & SERVICE REFACTORING**

### 4.1 **Update Controller imports**
```php
// app/Http/Controllers/TaskController.php
use App\Models\User;           // Changed from ZenaUser
use App\Models\Component;      // Changed from ZenaComponent
use App\Models\TaskAssignment; // Changed from ZenaTaskAssignment
use App\Models\Document;       // Changed from ZenaDocument
```

### 4.2 **Update Service classes**
```php
// app/Services/TaskService.php
class TaskService
{
    public function createTask(array $data)
    {
        $assignee = User::find($data['assignee_id']); // Changed from ZenaUser
        $component = Component::find($data['component_id']); // Changed from ZenaComponent
        
        // Update all model references
    }
}
```

---

## 🎨 **PHASE 5: VIEW & TEMPLATE REFACTORING**

### 5.1 **Update Blade templates**
```php
// resources/views/tasks/create.blade.php
@php
    $users = \App\Models\User::select('id', 'name', 'email')->get(); // Changed from ZenaUser
    $components = \App\Models\Component::select('id', 'name')->get(); // Changed from ZenaComponent
@endphp
```

### 5.2 **Update form references**
```html
<!-- Update all form field names -->
<select name="assignee_id">
    @foreach($users as $user)
        <option value="{{ $user->id }}">{{ $user->name }}</option>
    @endforeach
</select>
```

---

## ⚙️ **PHASE 6: CONFIGURATION REFACTORING**

### 6.1 **Update config files**
```php
// config/database.php
'connections' => [
    'mysql' => [
        'prefix' => '', // Remove 'zena_' prefix
    ],
],

// config/auth.php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class, // Changed from ZenaUser
    ],
],
```

### 6.2 **Update service providers**
```php
// app/Providers/AuthServiceProvider.php
public function boot()
{
    Gate::define('view-task', function (User $user, Task $task) { // Changed from ZenaUser
        return $user->can('view', $task);
    });
}
```

---

## 🧪 **PHASE 7: TEST REFACTORING**

### 7.1 **Update Test classes**
```php
// tests/Feature/TaskTest.php
use App\Models\User;           // Changed from ZenaUser
use App\Models\Component;      // Changed from ZenaComponent
use App\Models\TaskAssignment; // Changed from ZenaTaskAssignment

class TaskTest extends TestCase
{
    public function test_can_create_task()
    {
        $user = User::factory()->create(); // Changed from ZenaUser
        $component = Component::factory()->create(); // Changed from ZenaComponent
        
        // Update all test references
    }
}
```

### 7.2 **Update Factory classes**
```php
// database/factories/UserFactory.php
class UserFactory extends Factory
{
    protected $model = User::class; // Changed from ZenaUser
    
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            // ... other fields
        ];
    }
}
```

---

## 🔍 **PHASE 8: COMPREHENSIVE SEARCH & REPLACE**

### 8.1 **Create automated script**
```php
// scripts/refactor_naming_convention.php
<?php

class NamingConventionRefactor
{
    private $mappings = [
        'zena_users' => 'users',
        'zena_components' => 'components',
        'zena_task_assignments' => 'task_assignments',
        // ... all mappings
    ];
    
    public function refactorAll()
    {
        $this->refactorModels();
        $this->refactorControllers();
        $this->refactorServices();
        $this->refactorViews();
        $this->refactorTests();
        $this->refactorConfigs();
    }
    
    private function refactorModels()
    {
        $files = glob('app/Models/*.php');
        foreach ($files as $file) {
            $this->refactorFile($file);
        }
    }
    
    private function refactorFile($filePath)
    {
        $content = file_get_contents($filePath);
        
        // Replace table names
        foreach ($this->mappings as $old => $new) {
            $content = str_replace("'$old'", "'$new'", $content);
            $content = str_replace('"$old"', '"$new"', $content);
        }
        
        // Replace class names
        $content = str_replace('ZenaUser', 'User', $content);
        $content = str_replace('ZenaComponent', 'Component', $content);
        // ... etc
        
        file_put_contents($filePath, $content);
    }
}
```

### 8.2 **Search patterns**
```bash
# Find all references to zena_ tables
grep -r "zena_" app/ --include="*.php"
grep -r "zena_" resources/ --include="*.blade.php"
grep -r "zena_" database/ --include="*.php"
grep -r "zena_" tests/ --include="*.php"

# Find all Zena class references
grep -r "ZenaUser" app/
grep -r "ZenaComponent" app/
grep -r "ZenaTaskAssignment" app/
```

---

## ✅ **PHASE 9: VALIDATION & TESTING**

### 9.1 **Create validation script**
```php
// scripts/validate_refactoring.php
<?php

class RefactoringValidator
{
    public function validateAll()
    {
        $this->validateDatabase();
        $this->validateModels();
        $this->validateControllers();
        $this->validateViews();
        $this->validateTests();
    }
    
    private function validateDatabase()
    {
        // Check if all tables exist with new names
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map('current', $tables);
        
        foreach ($this->mappings as $old => $new) {
            if (in_array($old, $tableNames)) {
                throw new Exception("Table $old still exists!");
            }
            if (!in_array($new, $tableNames)) {
                throw new Exception("Table $new does not exist!");
            }
        }
    }
    
    private function validateModels()
    {
        // Check if all models can be instantiated
        $models = ['User', 'Component', 'TaskAssignment', 'Document'];
        
        foreach ($models as $model) {
            $instance = new $model();
            if (!$instance instanceof Model) {
                throw new Exception("Model $model is not valid!");
            }
        }
    }
}
```

### 9.2 **Run comprehensive tests**
```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Check for syntax errors
php -l app/Models/User.php
php -l app/Models/Component.php
```

---

## 🚀 **PHASE 10: DEPLOYMENT & ROLLBACK**

### 10.1 **Deployment steps**
```bash
# 1. Backup database
mysqldump -u root -p zenamanage > backup_before_refactor.sql

# 2. Run migrations
php artisan migrate

# 3. Clear all caches
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 4. Run tests
php artisan test

# 5. Verify functionality
# Test all major features manually
```

### 10.2 **Rollback plan**
```bash
# If issues occur, rollback:
php artisan migrate:rollback --step=1
mysql -u root -p zenamanage < backup_before_refactor.sql
```

---

## 📋 **IMPLEMENTATION CHECKLIST**

### ✅ **Pre-refactoring**
- [ ] Create comprehensive backup
- [ ] Document current state
- [ ] Create mapping file
- [ ] Test current functionality

### ✅ **Database**
- [ ] Create rename migration
- [ ] Update foreign key constraints
- [ ] Test database integrity
- [ ] Verify all relationships

### ✅ **Models**
- [ ] Update table names
- [ ] Update class names
- [ ] Update relationships
- [ ] Update fillable/guarded
- [ ] Update casts/attributes

### ✅ **Controllers**
- [ ] Update imports
- [ ] Update model references
- [ ] Update method calls
- [ ] Update validation rules

### ✅ **Services**
- [ ] Update imports
- [ ] Update model references
- [ ] Update method calls
- [ ] Update event dispatching

### ✅ **Views**
- [ ] Update Blade templates
- [ ] Update form references
- [ ] Update variable names
- [ ] Update JavaScript references

### ✅ **Tests**
- [ ] Update test classes
- [ ] Update factory classes
- [ ] Update test data
- [ ] Update assertions

### ✅ **Configuration**
- [ ] Update config files
- [ ] Update service providers
- [ ] Update middleware
- [ ] Update routes

### ✅ **Post-refactoring**
- [ ] Run all tests
- [ ] Verify functionality
- [ ] Check performance
- [ ] Update documentation

---

## ⚠️ **RISKS & MITIGATION**

### **High Risk**
- **Database corruption** → Comprehensive backup + rollback plan
- **Breaking changes** → Thorough testing + gradual rollout
- **Data loss** → Multiple backup strategies

### **Medium Risk**
- **Performance impact** → Monitor query performance
- **Cache issues** → Clear all caches
- **Session problems** → Test authentication flows

### **Low Risk**
- **Code style** → Use automated tools
- **Documentation** → Update as you go

---

## 📊 **SUCCESS METRICS**

- [ ] All tests pass
- [ ] No broken functionality
- [ ] Improved code readability
- [ ] Better Laravel compliance
- [ ] Reduced technical debt
- [ ] Faster development velocity

---

## 🎯 **CONCLUSION**

This refactoring plan ensures:
- **Zero data loss**
- **Minimal downtime**
- **Comprehensive coverage**
- **Easy rollback**
- **Better maintainability**

The plan follows Laravel best practices and ensures all references are updated consistently.
