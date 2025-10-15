# CLIENTS & QUOTES MODULE - API DOCUMENTATION

## Overview

The Clients & Quotes module provides comprehensive CRM and quoting functionality for ZenaManage. This module includes client lifecycle management, quote creation and management, and integration with the project management system.

## Features

- **Client Management**: Complete CRM functionality with lifecycle stages
- **Quote Management**: Professional quoting system with status tracking
- **Lifecycle Automation**: Automatic client stage updates based on quote activity
- **Project Integration**: Seamless conversion from quotes to projects
- **Document Integration**: PDF generation and document management
- **Multi-tenant Support**: Complete tenant isolation and security

---

## CLIENT API ENDPOINTS

### Base URL
```
/api/v1/app/clients
```

### Authentication
All endpoints require authentication with Sanctum token and tenant ability.

### Endpoints

#### GET /clients
Retrieve a paginated list of clients with optional filtering.

**Query Parameters:**
- `search` (string, optional): Search by name, email, company, or phone
- `lifecycle_stage` (string, optional): Filter by lifecycle stage (lead, prospect, customer, inactive)
- `status` (string, optional): Filter by status (active, customers, prospects, leads, inactive)
- `per_page` (integer, optional): Number of items per page (default: 20)

**Response:**
```json
{
  "success": true,
  "data": {
    "clients": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+1234567890",
        "company": "Acme Corp",
        "lifecycle_stage": "prospect",
        "notes": "Interested in design services",
        "address": {
          "street": "123 Main St",
          "city": "New York",
          "state": "NY",
          "postal_code": "10001",
          "country": "USA"
        },
        "created_at": "2025-01-15T10:00:00Z",
        "updated_at": "2025-01-15T10:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 100
    },
    "statistics": {
      "total": 100,
      "leads": 25,
      "prospects": 30,
      "customers": 40,
      "inactive": 5
    }
  }
}
```

#### POST /clients
Create a new client.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "company": "Acme Corp",
  "lifecycle_stage": "lead",
  "notes": "Interested in design services",
  "address": {
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postal_code": "10001",
    "country": "USA"
  },
  "custom_fields": {
    "source": "website",
    "industry": "technology"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "client": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "company": "Acme Corp",
      "lifecycle_stage": "lead",
      "notes": "Interested in design services",
      "address": {
        "street": "123 Main St",
        "city": "New York",
        "state": "NY",
        "postal_code": "10001",
        "country": "USA"
      },
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T10:00:00Z"
    },
    "message": "Client created successfully"
  }
}
```

#### GET /clients/{id}
Retrieve a specific client with related data.

**Response:**
```json
{
  "success": true,
  "data": {
    "client": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "company": "Acme Corp",
      "lifecycle_stage": "prospect",
      "notes": "Interested in design services",
      "address": {
        "street": "123 Main St",
        "city": "New York",
        "state": "NY",
        "postal_code": "10001",
        "country": "USA"
      },
      "quotes": [
        {
          "id": 1,
          "title": "Website Design Quote",
          "status": "sent",
          "final_amount": 5000.00,
          "created_at": "2025-01-15T10:00:00Z"
        }
      ],
      "projects": [
        {
          "id": 1,
          "name": "Website Development",
          "status": "active",
          "budget": 10000.00,
          "created_at": "2025-01-15T10:00:00Z"
        }
      ],
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T10:00:00Z"
    },
    "quote_statistics": {
      "total": 3,
      "draft": 1,
      "sent": 1,
      "viewed": 0,
      "accepted": 1,
      "rejected": 0,
      "expired": 0
    },
    "recent_activity": [
      {
        "id": 1,
        "type": "quote",
        "title": "Website Design Quote",
        "status": "accepted",
        "created_at": "2025-01-15T10:00:00Z"
      }
    ]
  }
}
```

#### PUT /clients/{id}
Update an existing client.

**Request Body:** Same as POST /clients

**Response:**
```json
{
  "success": true,
  "data": {
    "client": {
      "id": 1,
      "name": "John Doe Updated",
      "email": "john@example.com",
      "phone": "+1234567890",
      "company": "Acme Corp",
      "lifecycle_stage": "customer",
      "notes": "Interested in design services",
      "address": {
        "street": "123 Main St",
        "city": "New York",
        "state": "NY",
        "postal_code": "10001",
        "country": "USA"
      },
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T11:00:00Z"
    },
    "message": "Client updated successfully"
  }
}
```

#### DELETE /clients/{id}
Delete a client (soft delete).

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Client deleted successfully"
  }
}
```

#### PATCH /clients/{id}/lifecycle-stage
Update client lifecycle stage.

**Request Body:**
```json
{
  "lifecycle_stage": "customer"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "client": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "lifecycle_stage": "customer",
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T11:00:00Z"
    },
    "message": "Client lifecycle stage updated successfully"
  }
}
```

#### GET /clients/{id}/stats
Get client statistics.

**Response:**
```json
{
  "success": true,
  "data": {
    "statistics": {
      "total_clients": 100,
      "leads": 25,
      "prospects": 30,
      "customers": 40,
      "inactive": 5,
      "new_this_month": 10,
      "conversion_rate": 75.5,
      "total_value": 250000.00
    }
  }
}
```

---

## QUOTE API ENDPOINTS

### Base URL
```
/api/v1/app/quotes
```

### Authentication
All endpoints require authentication with Sanctum token and tenant ability.

### Endpoints

#### GET /quotes
Retrieve a paginated list of quotes with optional filtering.

**Query Parameters:**
- `search` (string, optional): Search by title, description, or client name
- `status` (string, optional): Filter by status (draft, sent, viewed, accepted, rejected, expired)
- `type` (string, optional): Filter by type (design, construction)
- `client_id` (integer, optional): Filter by client ID
- `project_id` (integer, optional): Filter by project ID
- `expiring_soon` (boolean, optional): Show quotes expiring within 7 days
- `per_page` (integer, optional): Number of items per page (default: 20)

**Response:**
```json
{
  "success": true,
  "data": {
    "quotes": [
      {
        "id": 1,
        "client_id": 1,
        "project_id": null,
        "type": "design",
        "status": "sent",
        "title": "Website Design Quote",
        "description": "Complete website design and development",
        "total_amount": 5000.00,
        "tax_rate": 10.0,
        "tax_amount": 500.00,
        "discount_amount": 0.00,
        "final_amount": 5500.00,
        "valid_until": "2025-02-15",
        "sent_at": "2025-01-15T10:00:00Z",
        "viewed_at": null,
        "accepted_at": null,
        "rejected_at": null,
        "rejection_reason": null,
        "created_at": "2025-01-15T10:00:00Z",
        "updated_at": "2025-01-15T10:00:00Z",
        "client": {
          "id": 1,
          "name": "John Doe",
          "email": "john@example.com",
          "company": "Acme Corp"
        },
        "project": null
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 20,
      "total": 50
    },
    "statistics": {
      "total": 50,
      "draft": 10,
      "sent": 15,
      "viewed": 8,
      "accepted": 12,
      "rejected": 3,
      "expired": 2,
      "expiring_soon": 5
    }
  }
}
```

#### POST /quotes
Create a new quote.

**Request Body:**
```json
{
  "client_id": 1,
  "project_id": null,
  "type": "design",
  "title": "Website Design Quote",
  "description": "Complete website design and development",
  "total_amount": 5000.00,
  "tax_rate": 10.0,
  "discount_amount": 0.00,
  "valid_until": "2025-02-15",
  "line_items": [
    {
      "description": "Website Design",
      "quantity": 1,
      "unit_price": 3000.00
    },
    {
      "description": "Development",
      "quantity": 1,
      "unit_price": 2000.00
    }
  ],
  "terms_conditions": {
    "payment_terms": "50% upfront, 50% on completion",
    "delivery_time": "4-6 weeks",
    "revisions": "3 rounds of revisions included"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "quote": {
      "id": 1,
      "client_id": 1,
      "project_id": null,
      "type": "design",
      "status": "draft",
      "title": "Website Design Quote",
      "description": "Complete website design and development",
      "total_amount": 5000.00,
      "tax_rate": 10.0,
      "tax_amount": 500.00,
      "discount_amount": 0.00,
      "final_amount": 5500.00,
      "valid_until": "2025-02-15",
      "sent_at": null,
      "viewed_at": null,
      "accepted_at": null,
      "rejected_at": null,
      "rejection_reason": null,
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T10:00:00Z",
      "client": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "company": "Acme Corp"
      },
      "project": null
    },
    "message": "Quote created successfully"
  }
}
```

#### GET /quotes/{id}
Retrieve a specific quote with related data.

**Response:**
```json
{
  "success": true,
  "data": {
    "quote": {
      "id": 1,
      "client_id": 1,
      "project_id": null,
      "type": "design",
      "status": "sent",
      "title": "Website Design Quote",
      "description": "Complete website design and development",
      "total_amount": 5000.00,
      "tax_rate": 10.0,
      "tax_amount": 500.00,
      "discount_amount": 0.00,
      "final_amount": 5500.00,
      "valid_until": "2025-02-15",
      "sent_at": "2025-01-15T10:00:00Z",
      "viewed_at": null,
      "accepted_at": null,
      "rejected_at": null,
      "rejection_reason": null,
      "line_items": [
        {
          "description": "Website Design",
          "quantity": 1,
          "unit_price": 3000.00,
          "total": 3000.00
        },
        {
          "description": "Development",
          "quantity": 1,
          "unit_price": 2000.00,
          "total": 2000.00
        }
      ],
      "terms_conditions": {
        "payment_terms": "50% upfront, 50% on completion",
        "delivery_time": "4-6 weeks",
        "revisions": "3 rounds of revisions included"
      },
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T10:00:00Z",
      "client": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "company": "Acme Corp"
      },
      "project": null,
      "creator": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      },
      "documents": []
    },
    "related_quotes": [
      {
        "id": 2,
        "title": "Mobile App Design Quote",
        "status": "draft",
        "final_amount": 3000.00,
        "created_at": "2025-01-14T10:00:00Z"
      }
    ]
  }
}
```

#### PUT /quotes/{id}
Update an existing quote.

**Request Body:** Same as POST /quotes

**Response:**
```json
{
  "success": true,
  "data": {
    "quote": {
      "id": 1,
      "client_id": 1,
      "project_id": null,
      "type": "design",
      "status": "draft",
      "title": "Website Design Quote Updated",
      "description": "Complete website design and development",
      "total_amount": 6000.00,
      "tax_rate": 10.0,
      "tax_amount": 600.00,
      "discount_amount": 0.00,
      "final_amount": 6600.00,
      "valid_until": "2025-02-15",
      "sent_at": null,
      "viewed_at": null,
      "accepted_at": null,
      "rejected_at": null,
      "rejection_reason": null,
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T11:00:00Z",
      "client": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "company": "Acme Corp"
      },
      "project": null
    },
    "message": "Quote updated successfully"
  }
}
```

#### DELETE /quotes/{id}
Delete a quote (soft delete).

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Quote deleted successfully"
  }
}
```

#### POST /quotes/{id}/send
Send quote to client.

**Response:**
```json
{
  "success": true,
  "data": {
    "quote": {
      "id": 1,
      "status": "sent",
      "sent_at": "2025-01-15T11:00:00Z"
    },
    "message": "Quote sent successfully to client"
  }
}
```

#### POST /quotes/{id}/accept
Accept quote and create project.

**Response:**
```json
{
  "success": true,
  "data": {
    "quote": {
      "id": 1,
      "status": "accepted",
      "accepted_at": "2025-01-15T11:00:00Z",
      "project_id": 1
    },
    "message": "Quote accepted successfully. Project created."
  }
}
```

#### POST /quotes/{id}/reject
Reject quote.

**Request Body:**
```json
{
  "rejection_reason": "Price too high"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "quote": {
      "id": 1,
      "status": "rejected",
      "rejected_at": "2025-01-15T11:00:00Z",
      "rejection_reason": "Price too high"
    },
    "message": "Quote rejected successfully"
  }
}
```

#### GET /quotes/stats
Get quote statistics.

**Response:**
```json
{
  "success": true,
  "data": {
    "statistics": {
      "total_quotes": 100,
      "draft": 20,
      "sent": 30,
      "viewed": 15,
      "accepted": 25,
      "rejected": 5,
      "expired": 5,
      "expiring_soon": 8,
      "total_value": 500000.00,
      "conversion_rate": 45.5,
      "average_quote_value": 5000.00,
      "quotes_this_month": 15
    }
  }
}
```

---

## ERROR RESPONSES

All endpoints return standardized error responses:

```json
{
  "success": false,
  "error": {
    "message": "Error description",
    "code": "error_code",
    "details": "Additional error details"
  }
}
```

### Common Error Codes

- `validation_error`: Validation failed
- `client_not_found`: Client not found
- `quote_not_found`: Quote not found
- `client_creation_error`: Failed to create client
- `quote_creation_error`: Failed to create quote
- `quote_send_error`: Failed to send quote
- `quote_accept_error`: Failed to accept quote
- `quote_reject_error`: Failed to reject quote

### HTTP Status Codes

- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `422`: Validation Error
- `500`: Internal Server Error

---

## CLIENT LIFECYCLE STAGES

1. **Lead**: New client, no quotes sent yet
2. **Prospect**: Client with quotes sent but not accepted
3. **Customer**: Client with accepted quotes or active projects
4. **Inactive**: Client with multiple rejected quotes or no activity

## QUOTE STATUSES

1. **Draft**: Quote being prepared
2. **Sent**: Quote sent to client
3. **Viewed**: Client has viewed the quote
4. **Accepted**: Client accepted the quote
5. **Rejected**: Client rejected the quote
6. **Expired**: Quote has passed its validity date

## QUOTE TYPES

1. **Design**: Design-related services
2. **Construction**: Construction-related services

---

## INTEGRATION NOTES

- Quotes automatically update client lifecycle stages
- Accepted quotes create new projects
- All data is tenant-isolated
- PDF generation is triggered when quotes are sent
- Documents are linked to quotes for file attachments
- Email notifications are sent for quote status changes
