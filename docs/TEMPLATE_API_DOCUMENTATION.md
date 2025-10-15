# Template Management API Documentation

## Base URL
```
/api/v1/app/templates
```

## Authentication
All endpoints require authentication via Laravel Sanctum. Include the bearer token in the Authorization header:

```
Authorization: Bearer {your-token}
```

## Response Format
All API responses follow a consistent format:

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "error": {
    "id": "error-id",
    "code": "error-code",
    "details": {
      // Additional error details
    }
  }
}
```

## Endpoints

### 1. Get Templates List

**GET** `/api/v1/app/templates`

Retrieve a paginated list of templates with optional filtering.

#### Query Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `category` | string | Filter by template category | `project`, `task`, `workflow`, `document`, `report` |
| `status` | string | Filter by template status | `draft`, `published`, `archived` |
| `is_public` | boolean | Filter by public/private | `true`, `false` |
| `search` | string | Search by name or description | `"project management"` |
| `tags` | string | Filter by tags (comma-separated) | `"agile,development"` |
| `page` | integer | Page number for pagination | `1` |
| `per_page` | integer | Items per page | `10` |

#### Example Request
```bash
curl -X GET "https://api.example.com/api/v1/app/templates?category=project&status=published&page=1&per_page=10" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "templates": [
      {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "Software Development Template",
        "description": "Complete software development project template",
        "category": "project",
        "status": "published",
        "version": 1,
        "is_public": true,
        "is_active": true,
        "usage_count": 15,
        "tags": ["software", "development", "agile"],
        "metadata": {
          "complexity": "high",
          "estimated_duration": 30
        },
        "created_by": {
          "id": "01HXYZ123456789ABCDEFGHIJK",
          "name": "John Doe",
          "email": "john@example.com"
        },
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      }
    ],
    "filters": {
      "categories": ["project", "task", "workflow", "document", "report"],
      "statuses": ["draft", "published", "archived"],
      "tags": ["software", "development", "agile", "management"]
    },
    "total": 1,
    "per_page": 10,
    "current_page": 1,
    "last_page": 1
  }
}
```

### 2. Get Template Details

**GET** `/api/v1/app/templates/{id}`

Retrieve detailed information about a specific template.

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Template ID (ULID) |

#### Example Request
```bash
curl -X GET "https://api.example.com/api/v1/app/templates/01HXYZ123456789ABCDEFGHIJK" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "template": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "Software Development Template",
      "description": "Complete software development project template",
      "category": "project",
      "template_data": {
        "phases": [
          {
            "name": "Planning",
            "duration_days": 5,
            "tasks": [
              {
                "name": "Requirements Gathering",
                "description": "Collect and document requirements",
                "duration_days": 3,
                "priority": "high",
                "estimated_hours": 24
              }
            ]
          }
        ],
        "milestones": [
          {
            "name": "Project Kickoff",
            "date_offset": 0,
            "description": "Project initiation milestone"
          }
        ]
      },
      "settings": {
        "auto_assign_tasks": true,
        "notify_on_completion": false
      },
      "status": "published",
      "version": 1,
      "is_public": true,
      "is_active": true,
      "usage_count": 15,
      "tags": ["software", "development", "agile"],
      "metadata": {
        "complexity": "high",
        "estimated_duration": 30,
        "team_size": 5
      },
      "creator": {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "John Doe",
        "email": "john@example.com"
      },
      "updater": {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "John Doe",
        "email": "john@example.com"
      },
      "versions": [
        {
          "id": "01HXYZ123456789ABCDEFGHIJK",
          "version": 1,
          "name": "Initial Version",
          "description": "First version of the template",
          "is_active": true,
          "created_at": "2024-01-01T00:00:00Z"
        }
      ],
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

### 3. Create Template

**POST** `/api/v1/app/templates`

Create a new template.

#### Request Body
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
    ],
    "milestones": [
      {
        "name": "Milestone 1",
        "date_offset": 0,
        "description": "Milestone description"
      }
    ]
  },
  "settings": {
    "auto_assign_tasks": true,
    "notify_on_completion": false
  },
  "status": "draft",
  "is_public": false,
  "tags": ["tag1", "tag2"],
  "metadata": {
    "source": "manual",
    "complexity": "medium"
  }
}
```

#### Field Validation
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | Yes | Max 255 characters |
| `description` | string | No | Max 1000 characters |
| `category` | string | Yes | Must be one of: `project`, `task`, `workflow`, `document`, `report` |
| `template_data` | object | Yes | Valid JSON structure |
| `settings` | object | No | Valid JSON structure |
| `status` | string | No | Must be one of: `draft`, `published`, `archived` |
| `is_public` | boolean | No | Default: `false` |
| `tags` | array | No | Array of strings |
| `metadata` | object | No | Valid JSON structure |

#### Example Request
```bash
curl -X POST "https://api.example.com/api/v1/app/templates" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "New Project Template",
    "description": "A new project template",
    "category": "project",
    "template_data": {
      "phases": [
        {
          "name": "Planning",
          "duration_days": 5,
          "tasks": []
        }
      ]
    },
    "status": "draft",
    "is_public": false,
    "tags": ["planning", "project"]
  }'
```

#### Example Response
```json
{
  "success": true,
  "message": "Template created successfully",
  "data": {
    "template": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "New Project Template",
      "description": "A new project template",
      "category": "project",
      "status": "draft",
      "version": 1,
      "is_public": false,
      "is_active": true,
      "usage_count": 0,
      "created_by": {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "John Doe",
        "email": "john@example.com"
      },
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

### 4. Update Template

**PUT** `/api/v1/app/templates/{id}`

Update an existing template.

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Template ID (ULID) |

#### Request Body
Same as create template, but all fields are optional.

#### Example Request
```bash
curl -X PUT "https://api.example.com/api/v1/app/templates/01HXYZ123456789ABCDEFGHIJK" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Updated Template Name",
    "description": "Updated description",
    "status": "published"
  }'
```

#### Example Response
```json
{
  "success": true,
  "message": "Template updated successfully",
  "data": {
    "template": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "Updated Template Name",
      "description": "Updated description",
      "status": "published",
      "version": 2,
      "updated_at": "2024-01-01T12:00:00Z"
    }
  }
}
```

### 5. Delete Template

**DELETE** `/api/v1/app/templates/{id}`

Delete a template (soft delete).

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Template ID (ULID) |

#### Example Request
```bash
curl -X DELETE "https://api.example.com/api/v1/app/templates/01HXYZ123456789ABCDEFGHIJK" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

#### Example Response
```json
{
  "success": true,
  "message": "Template deleted successfully"
}
```

### 6. Apply Template to Project

**POST** `/api/v1/app/templates/{id}/apply-to-project`

Apply a template to an existing project.

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Template ID (ULID) |

#### Request Body
```json
{
  "project_id": "01HXYZ123456789ABCDEFGHIJK"
}
```

#### Example Request
```bash
curl -X POST "https://api.example.com/api/v1/app/templates/01HXYZ123456789ABCDEFGHIJK/apply-to-project" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "project_id": "01HXYZ123456789ABCDEFGHIJK"
  }'
```

#### Example Response
```json
{
  "success": true,
  "message": "Template applied to project successfully",
  "data": {
    "template": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "Software Development Template",
      "usage_count": 16
    },
    "project": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "My Project"
    },
    "tasks": [
      {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "Requirements Gathering",
        "description": "Collect and document requirements",
        "project_id": "01HXYZ123456789ABCDEFGHIJK",
        "priority": "high",
        "estimated_hours": 24,
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "milestones": [
      {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "Project Kickoff",
        "description": "Project initiation milestone",
        "project_id": "01HXYZ123456789ABCDEFGHIJK",
        "created_at": "2024-01-01T00:00:00Z"
      }
    ]
  }
}
```

### 7. Duplicate Template

**POST** `/api/v1/app/templates/{id}/duplicate`

Create a copy of an existing template.

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Template ID (ULID) |

#### Request Body
```json
{
  "name": "Duplicated Template Name"
}
```

#### Example Request
```bash
curl -X POST "https://api.example.com/api/v1/app/templates/01HXYZ123456789ABCDEFGHIJK/duplicate" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "My Duplicated Template"
  }'
```

#### Example Response
```json
{
  "success": true,
  "message": "Template duplicated successfully",
  "data": {
    "template": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "My Duplicated Template",
      "description": "Complete software development project template",
      "category": "project",
      "status": "draft",
      "version": 1,
      "usage_count": 0,
      "created_by": {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "John Doe",
        "email": "john@example.com"
      },
      "created_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

### 8. Get Template Analytics

**GET** `/api/v1/app/templates/analytics`

Get analytics data for templates.

#### Query Parameters
| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `period` | integer | Time period in days | `30` |

#### Example Request
```bash
curl -X GET "https://api.example.com/api/v1/app/templates/analytics?period=30" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

#### Example Response
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
    "most_used_template": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "Software Development Template",
      "usage_count": 25
    },
    "categories": {
      "project": 5,
      "task": 3,
      "workflow": 2
    },
    "recent_templates": [
      {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "Recent Template",
        "category": "project",
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "popular_templates": [
      {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "Popular Template",
        "category": "project",
        "usage_count": 25
      }
    ]
  }
}
```

### 9. Export Analytics

**GET** `/api/v1/app/templates/analytics/export`

Export analytics data as CSV.

#### Query Parameters
| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `period` | integer | Time period in days | `30` |

#### Example Request
```bash
curl -X GET "https://api.example.com/api/v1/app/templates/analytics/export?period=30" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: text/csv"
```

#### Response
Returns a CSV file with analytics data.

### 10. Export Template

**GET** `/api/v1/app/templates/{id}/export`

Export a single template.

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Template ID (ULID) |

#### Example Request
```bash
curl -X GET "https://api.example.com/api/v1/app/templates/01HXYZ123456789ABCDEFGHIJK/export" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "template_export": {
      "version": "1.0",
      "exported_at": "2024-01-01T00:00:00Z",
      "exported_by": "John Doe"
    },
    "template": {
      "name": "Software Development Template",
      "description": "Complete software development project template",
      "category": "project",
      "template_data": {
        "phases": [...],
        "milestones": [...]
      },
      "settings": {...},
      "tags": ["software", "development", "agile"],
      "metadata": {...}
    },
    "versions": [
      {
        "version": 1,
        "name": "Initial Version",
        "description": "First version",
        "template_data": {...},
        "changes": {...},
        "created_at": "2024-01-01T00:00:00Z",
        "created_by": "John Doe"
      }
    ]
  }
}
```

### 11. Import Template

**POST** `/api/v1/app/templates/import`

Import a template from export data.

#### Request Body
```json
{
  "template_data": {
    "template_export": {
      "version": "1.0",
      "exported_at": "2024-01-01T00:00:00Z",
      "exported_by": "John Doe"
    },
    "template": {
      "name": "Imported Template",
      "description": "Template description",
      "category": "project",
      "template_data": {
        "phases": [...],
        "milestones": [...]
      },
      "settings": {...},
      "tags": ["imported"],
      "metadata": {...}
    },
    "versions": [...]
  }
}
```

#### Example Request
```bash
curl -X POST "https://api.example.com/api/v1/app/templates/import" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "template_data": {
      "template": {
        "name": "Imported Template",
        "description": "Template description",
        "category": "project",
        "template_data": {
          "phases": [
            {
              "name": "Phase 1",
              "duration_days": 5,
              "tasks": []
            }
          ]
        }
      }
    }
  }'
```

#### Example Response
```json
{
  "success": true,
  "message": "Template imported successfully",
  "data": {
    "template": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "Imported Template (Imported)",
      "description": "Template description",
      "category": "project",
      "status": "draft",
      "version": 1,
      "created_by": {
        "id": "01HXYZ123456789ABCDEFGHIJK",
        "name": "John Doe",
        "email": "john@example.com"
      },
      "created_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

### 12. Export Multiple Templates

**POST** `/api/v1/app/templates/export-multiple`

Export multiple templates at once.

#### Request Body
```json
{
  "template_ids": [
    "01HXYZ123456789ABCDEFGHIJK",
    "01HXYZ123456789ABCDEFGHIJL"
  ]
}
```

#### Example Request
```bash
curl -X POST "https://api.example.com/api/v1/app/templates/export-multiple" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "template_ids": [
      "01HXYZ123456789ABCDEFGHIJK",
      "01HXYZ123456789ABCDEFGHIJL"
    ]
  }'
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "templates_export": {
      "version": "1.0",
      "exported_at": "2024-01-01T00:00:00Z",
      "exported_by": "John Doe",
      "template_count": 2
    },
    "templates": [
      {
        "template_export": {...},
        "template": {...},
        "versions": [...]
      },
      {
        "template_export": {...},
        "template": {...},
        "versions": [...]
      }
    ]
  }
}
```

### 13. Get Import/Export Statistics

**GET** `/api/v1/app/templates/import-export-stats`

Get statistics about import/export activities.

#### Example Request
```bash
curl -X GET "https://api.example.com/api/v1/app/templates/import-export-stats" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "total_templates": 10,
    "imported_templates": 3,
    "created_templates": 7,
    "import_sources": {
      "John Doe": 2,
      "Jane Smith": 1
    }
  }
}
```

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `template_not_found` | 404 | Template not found or not accessible |
| `template_validation_failed` | 422 | Template validation failed |
| `template_creation_failed` | 500 | Failed to create template |
| `template_update_failed` | 500 | Failed to update template |
| `template_deletion_failed` | 500 | Failed to delete template |
| `template_application_failed` | 500 | Failed to apply template to project |
| `template_duplication_failed` | 500 | Failed to duplicate template |
| `template_export_failed` | 500 | Failed to export template |
| `template_import_failed` | 500 | Failed to import template |
| `analytics_fetch_failed` | 500 | Failed to fetch analytics |
| `analytics_export_failed` | 500 | Failed to export analytics |
| `unauthenticated` | 401 | User not authenticated |
| `unauthorized` | 403 | User not authorized to perform action |
| `validation_failed` | 422 | Request validation failed |
| `internal_error` | 500 | Internal server error |

## Rate Limiting

Template API endpoints are rate limited to prevent abuse:

- **General endpoints**: 100 requests per minute per user
- **Analytics endpoints**: 20 requests per minute per user
- **Import/Export endpoints**: 10 requests per minute per user

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640995200
```

## Pagination

List endpoints support pagination with the following parameters:

- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 10, max: 100)

Pagination metadata is included in responses:
```json
{
  "data": {
    "templates": [...],
    "total": 50,
    "per_page": 10,
    "current_page": 1,
    "last_page": 5,
    "from": 1,
    "to": 10
  }
}
```

## Filtering and Search

### Category Filter
Filter templates by category:
```
GET /api/v1/app/templates?category=project
```

### Status Filter
Filter templates by status:
```
GET /api/v1/app/templates?status=published
```

### Public/Private Filter
Filter templates by visibility:
```
GET /api/v1/app/templates?is_public=true
```

### Search
Search templates by name or description:
```
GET /api/v1/app/templates?search=software development
```

### Tags Filter
Filter templates by tags:
```
GET /api/v1/app/templates?tags=agile,development
```

### Combined Filters
Combine multiple filters:
```
GET /api/v1/app/templates?category=project&status=published&search=management&page=1&per_page=20
```

## Webhooks

Template events can trigger webhooks (if configured):

### Events
- `template.created` - Template created
- `template.updated` - Template updated
- `template.deleted` - Template deleted
- `template.published` - Template published
- `template.applied` - Template applied to project

### Webhook Payload
```json
{
  "event": "template.created",
  "data": {
    "template": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "Template Name",
      "category": "project"
    },
    "user": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "John Doe",
      "email": "john@example.com"
    },
    "tenant": {
      "id": "01HXYZ123456789ABCDEFGHIJK",
      "name": "Company Name"
    }
  },
  "timestamp": "2024-01-01T00:00:00Z"
}
```

## SDK Examples

### JavaScript/Node.js
```javascript
const axios = require('axios');

const api = axios.create({
  baseURL: 'https://api.example.com/api/v1/app',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  }
});

// Get templates
const templates = await api.get('/templates');

// Create template
const newTemplate = await api.post('/templates', {
  name: 'My Template',
  category: 'project',
  template_data: {
    phases: []
  }
});

// Apply template to project
const result = await api.post(`/templates/${templateId}/apply-to-project`, {
  project_id: projectId
});
```

### PHP
```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.example.com/api/v1/app/',
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json'
    ]
]);

// Get templates
$response = $client->get('templates');
$templates = json_decode($response->getBody(), true);

// Create template
$response = $client->post('templates', [
    'json' => [
        'name' => 'My Template',
        'category' => 'project',
        'template_data' => [
            'phases' => []
        ]
    ]
]);
```

### Python
```python
import requests

headers = {
    'Authorization': f'Bearer {token}',
    'Content-Type': 'application/json'
}

# Get templates
response = requests.get('https://api.example.com/api/v1/app/templates', headers=headers)
templates = response.json()

# Create template
data = {
    'name': 'My Template',
    'category': 'project',
    'template_data': {
        'phases': []
    }
}
response = requests.post('https://api.example.com/api/v1/app/templates', headers=headers, json=data)
```

## Support

For API support and questions:
- Email: api-support@example.com
- Documentation: https://docs.example.com
- Status Page: https://status.example.com
