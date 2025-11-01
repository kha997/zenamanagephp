# Template Management System Documentation

## Overview

The Template Management System is a comprehensive solution for creating, managing, and applying project templates in ZenaManage. It provides a unified approach to template management with advanced features including versioning, sharing, analytics, and import/export capabilities.

## Table of Contents

1. [Architecture](#architecture)
2. [Models](#models)
3. [Services](#services)
4. [API Endpoints](#api-endpoints)
5. [Frontend Components](#frontend-components)
6. [Database Schema](#database-schema)
7. [Usage Examples](#usage-examples)
8. [Testing](#testing)
9. [Troubleshooting](#troubleshooting)

## Architecture

### Multi-tenant Design
The Template Management System follows a strict multi-tenant architecture where all templates are isolated by `tenant_id`. This ensures complete data separation between different organizations.

### Key Components
- **Models**: `Template`, `TemplateVersion`
- **Services**: `TemplateService`, `TemplateImportExportService`, `TemplateSharingService`
- **Controllers**: `TemplateController`, `TemplateImportExportController`
- **Frontend**: Blade templates with Alpine.js for interactivity

## Models

### Template Model

The `Template` model represents a project template with the following key features:

#### Properties
```php
// Core properties
'id' => 'string (ULID)',
'tenant_id' => 'string',
'name' => 'string',
'description' => 'text',
'category' => 'enum (project|task|workflow|document|report)',
'template_data' => 'json',
'settings' => 'json',
'status' => 'enum (draft|published|archived)',
'version' => 'integer',
'is_public' => 'boolean',
'is_active' => 'boolean',
'created_by' => 'string',
'updated_by' => 'string',
'usage_count' => 'integer',
'tags' => 'json',
'metadata' => 'json'
```

#### Relationships
```php
// Belongs to
tenant() // Tenant model
creator() // User model (created_by)
updater() // User model (updated_by)

// Has many
versions() // TemplateVersion model
projects() // Project model (projects using this template)
```

#### Key Methods
```php
// Template operations
incrementUsage() // Increment usage counter
publish() // Publish template
archive() // Archive template
duplicate($newName, $userId) // Duplicate template

// Data access
getPhases() // Get template phases
getTasks() // Get template tasks
getMilestones() // Get template milestones
getEstimatedDuration() // Calculate total duration
getEstimatedCost() // Calculate total cost

// Validation
isValid() // Validate template data
canBeUsed() // Check if template can be applied
```

#### Scopes
```php
byTenant($tenantId) // Filter by tenant
byCategory($category) // Filter by category
active() // Active templates only
public() // Public templates only
published() // Published templates only
byUser($userId) // Templates by user
popular($limit) // Most popular templates
```

### TemplateVersion Model

The `TemplateVersion` model tracks template version history:

#### Properties
```php
'id' => 'string (ULID)',
'template_id' => 'string',
'version' => 'integer',
'name' => 'string',
'description' => 'text',
'template_data' => 'json',
'changes' => 'json',
'created_by' => 'string',
'is_active' => 'boolean'
```

#### Key Methods
```php
activate() // Activate this version
getVersionName() // Get formatted version name
```

## Services

### TemplateService

Core business logic for template operations:

#### Methods
```php
createTemplate($data, $userId, $tenantId) // Create new template
updateTemplate($template, $data, $userId) // Update existing template
applyTemplateToProject($template, $project, $userId) // Apply template to project
duplicateTemplate($template, $newName, $userId) // Duplicate template
createVersion($template, $userId, $description) // Create new version
getTemplateAnalytics($tenantId) // Get analytics data
searchTemplates($tenantId, $filters) // Search templates
```

### TemplateImportExportService

Handles template import/export operations:

#### Methods
```php
exportTemplate($template) // Export single template
importTemplate($data, $userId, $tenantId) // Import template
exportTemplates($templateIds, $tenantId) // Export multiple templates
importTemplates($data, $userId, $tenantId) // Import multiple templates
exportTemplateToFile($template) // Export to file
importTemplateFromFile($filePath, $userId, $tenantId) // Import from file
getImportExportStats($tenantId) // Get import/export statistics
```

### TemplateSharingService

Manages template sharing features:

#### Methods
```php
makePublic($template, $userId) // Make template public
makePrivate($template, $userId) // Make template private
shareWithUsers($template, $userIds, $sharerId) // Share with specific users
getSharedTemplates($userId, $tenantId) // Get shared templates
getTemplatesSharedByUser($userId, $tenantId) // Get templates shared by user
getTemplateSharingAnalytics($template) // Get sharing analytics
getUserSharingAnalytics($userId, $tenantId) // Get user sharing analytics
revokeSharing($template, $userId) // Revoke sharing
getPublicTemplatesFromOtherTenants($currentTenantId, $filters) // Cross-tenant discovery
```

## API Endpoints

### Template CRUD Operations

#### GET /api/v1/app/templates
Get templates list with filtering and pagination.

**Query Parameters:**
- `category` - Filter by category
- `status` - Filter by status
- `is_public` - Filter by public/private
- `search` - Search by name/description
- `tags` - Filter by tags

**Response:**
```json
{
  "success": true,
  "data": {
    "templates": [...],
    "filters": {...},
    "total": 10
  }
}
```

#### GET /api/v1/app/templates/{id}
Get specific template details.

**Response:**
```json
{
  "success": true,
  "data": {
    "template": {
      "id": "template-id",
      "name": "Template Name",
      "description": "Description",
      "category": "project",
      "status": "published",
      "version": 1,
      "usage_count": 5,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

#### POST /api/v1/app/templates
Create new template.

**Request Body:**
```json
{
  "name": "Template Name",
  "description": "Template description",
  "category": "project",
  "template_data": {
    "phases": [
      {
        "name": "Phase 1",
        "duration_days": 5,
        "tasks": [
          {
            "name": "Task 1",
            "description": "Task description",
            "duration_days": 3,
            "priority": "high",
            "estimated_hours": 24
          }
        ]
      }
    ]
  },
  "status": "draft",
  "is_public": false,
  "tags": ["tag1", "tag2"],
  "metadata": {"source": "manual"}
}
```

#### PUT /api/v1/app/templates/{id}
Update existing template.

#### DELETE /api/v1/app/templates/{id}
Delete template (soft delete).

### Template Operations

#### POST /api/v1/app/templates/{id}/apply-to-project
Apply template to a project.

**Request Body:**
```json
{
  "project_id": "project-id"
}
```

#### POST /api/v1/app/templates/{id}/duplicate
Duplicate template.

**Request Body:**
```json
{
  "name": "New Template Name"
}
```

### Analytics

#### GET /api/v1/app/templates/analytics
Get template analytics.

**Query Parameters:**
- `period` - Time period (7, 30, 90, 365 days)

**Response:**
```json
{
  "success": true,
  "data": {
    "total_templates": 10,
    "published_templates": 8,
    "draft_templates": 2,
    "archived_templates": 0,
    "public_templates": 3,
    "total_usage": 150,
    "most_used_template": {...},
    "categories": {
      "project": 5,
      "task": 3,
      "workflow": 2
    },
    "recent_templates": [...],
    "popular_templates": [...]
  }
}
```

#### GET /api/v1/app/templates/analytics/export
Export analytics as CSV.

### Import/Export

#### GET /api/v1/app/templates/{id}/export
Export single template.

#### POST /api/v1/app/templates/import
Import template.

#### POST /api/v1/app/templates/export-multiple
Export multiple templates.

#### GET /api/v1/app/templates/import-export-stats
Get import/export statistics.

## Frontend Components

### Template Index (`/app/templates`)
- KPI dashboard with template statistics
- Advanced filtering and search
- Template grid with actions
- Responsive design with mobile support

### Template Builder (`/app/templates/builder`)
- Visual template creation interface
- Drag-and-drop phase and task management
- Real-time preview
- Template configuration panel

### Template Library (`/app/templates/library`)
- Public template discovery
- User template management
- Template sharing features
- Category-based browsing

### Template Analytics (`/app/templates/analytics`)
- Usage statistics and charts
- Performance metrics
- Export functionality
- Period-based filtering

### Template Detail (`/app/templates/{id}`)
- Template information display
- Version history
- Usage statistics
- Apply to project functionality

## Database Schema

### templates Table
```sql
CREATE TABLE templates (
    id VARCHAR(26) PRIMARY KEY, -- ULID
    tenant_id VARCHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('project','task','workflow','document','report') NOT NULL,
    template_data JSON NOT NULL,
    settings JSON,
    status ENUM('draft','published','archived') DEFAULT 'draft',
    version INT DEFAULT 1,
    is_public BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(26),
    updated_by VARCHAR(26),
    usage_count INT DEFAULT 0,
    tags JSON,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_tenant_category (tenant_id, category),
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_tenant_public (tenant_id, is_public),
    INDEX idx_tenant_active (tenant_id, is_active),
    INDEX idx_created_by (created_by, created_at),
    INDEX idx_usage_count (usage_count),
    INDEX idx_name (name)
);
```

### template_versions Table
```sql
CREATE TABLE template_versions (
    id VARCHAR(26) PRIMARY KEY, -- ULID
    template_id VARCHAR(26) NOT NULL,
    version INT NOT NULL,
    name VARCHAR(255),
    description TEXT,
    template_data JSON NOT NULL,
    changes JSON,
    created_by VARCHAR(26),
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_template_version (template_id, version),
    INDEX idx_template_active (template_id, is_active),
    UNIQUE KEY unique_template_version (template_id, version)
);
```

## Usage Examples

### Creating a Template
```php
use App\Services\TemplateService;

$templateService = new TemplateService();

$templateData = [
    'name' => 'Software Development Template',
    'description' => 'Complete software development project template',
    'category' => Template::CATEGORY_PROJECT,
    'template_data' => [
        'phases' => [
            [
                'name' => 'Planning',
                'duration_days' => 5,
                'tasks' => [
                    [
                        'name' => 'Requirements Gathering',
                        'description' => 'Collect and document requirements',
                        'duration_days' => 3,
                        'priority' => 'high',
                        'estimated_hours' => 24
                    ]
                ]
            ],
            [
                'name' => 'Development',
                'duration_days' => 20,
                'tasks' => [
                    [
                        'name' => 'Core Development',
                        'description' => 'Main development work',
                        'duration_days' => 15,
                        'priority' => 'high',
                        'estimated_hours' => 120
                    ]
                ]
            ]
        ],
        'milestones' => [
            [
                'name' => 'Project Kickoff',
                'date_offset' => 0,
                'description' => 'Project initiation milestone'
            ],
            [
                'name' => 'Development Complete',
                'date_offset' => 25,
                'description' => 'All development work finished'
            ]
        ]
    ],
    'status' => Template::STATUS_PUBLISHED,
    'is_public' => true,
    'tags' => ['software', 'development', 'agile'],
    'metadata' => [
        'complexity' => 'high',
        'estimated_duration' => 30,
        'team_size' => 5
    ]
];

$template = $templateService->createTemplate($templateData, $userId, $tenantId);
```

### Applying Template to Project
```php
$project = Project::find($projectId);
$result = $templateService->applyTemplateToProject($template, $project, $userId);

// Access created tasks
foreach ($result['tasks'] as $task) {
    echo "Created task: " . $task->name . "\n";
}

// Access created milestones
foreach ($result['milestones'] as $milestone) {
    echo "Created milestone: " . $milestone['name'] . "\n";
}
```

### Template Analytics
```php
$analytics = $templateService->getTemplateAnalytics($tenantId);

echo "Total templates: " . $analytics['total_templates'] . "\n";
echo "Published templates: " . $analytics['published_templates'] . "\n";
echo "Total usage: " . $analytics['total_usage'] . "\n";

// Most popular template
$mostPopular = $analytics['most_used_template'];
echo "Most popular: " . $mostPopular->name . " (" . $mostPopular->usage_count . " uses)\n";
```

### Import/Export
```php
use App\Services\TemplateImportExportService;

$importExportService = new TemplateImportExportService();

// Export template
$exportData = $importExportService->exportTemplate($template);

// Save to file
file_put_contents('template.json', json_encode($exportData, JSON_PRETTY_PRINT));

// Import template
$importedTemplate = $importExportService->importTemplate($exportData, $userId, $tenantId);
```

## Testing

### Unit Tests
- `TemplateTest` - Model unit tests
- `TemplateServiceTest` - Service unit tests

### Integration Tests
- `TemplateIntegrationTest` - End-to-end integration tests

### Feature Tests
- `TemplateApiTest` - API endpoint tests

### Running Tests
```bash
# Run all template tests
php artisan test --filter=Template

# Run specific test file
php artisan test tests/Unit/TemplateTest.php

# Run with coverage
php artisan test --coverage --filter=Template
```

## Troubleshooting

### Common Issues

#### Template Not Found (404)
- Ensure template belongs to the authenticated user's tenant
- Check if template is soft deleted
- Verify template ID format (should be ULID)

#### Permission Denied (403)
- Check user has proper role/permissions
- Verify template ownership for update/delete operations
- Ensure user belongs to correct tenant

#### Validation Errors (422)
- Check required fields are provided
- Verify category is valid (project|task|workflow|document|report)
- Ensure template_data is valid JSON structure
- Check status is valid (draft|published|archived)

#### Import/Export Issues
- Verify JSON format is correct
- Check template_data structure matches expected format
- Ensure all required fields are present in import data
- Validate template category and status values

### Debug Mode
Enable debug mode in `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Logging
Template operations are logged with structured data:
```php
Log::info('Template created', [
    'template_id' => $template->id,
    'user_id' => $userId,
    'tenant_id' => $tenantId,
    'name' => $template->name
]);
```

### Performance Optimization
- Use database indexes for filtering
- Implement caching for analytics data
- Use eager loading for relationships
- Consider pagination for large datasets

### Security Considerations
- All operations respect tenant isolation
- User authentication required for all operations
- Proper authorization checks for template access
- Input validation and sanitization
- SQL injection prevention through Eloquent ORM

---

For more information, please refer to the source code or contact the development team.
