# FIELD MAPPING STANDARDIZATION

## 🎯 MỤC TIÊU
Đồng bộ tên trường giữa API ↔ Model ↔ Frontend để đảm bảo consistency và tránh confusion.

## 📋 FIELD MAPPING TABLE

### 1. TASK FIELDS
| **Frontend Expected** | **API Response** | **Model Field** | **Database Column** | **Status** |
|----------------------|------------------|-----------------|---------------------|------------|
| `title` | `title` | `name` | `name` | ❌ **INCONSISTENT** |
| `description` | `description` | `description` | `description` | ✅ Consistent |
| `status` | `status` | `status` | `status` | ✅ Consistent |
| `priority` | `priority` | `priority` | `priority` | ✅ Consistent |
| `assignee_id` | `assignee_id` | `assignee_id` | `assignee_id` | ✅ Consistent |
| `due_date` | `due_date` | `end_date` | `end_date` | ❌ **INCONSISTENT** |
| `progress` | `progress_percent` | `progress_percent` | `progress_percent` | ✅ Consistent |
| `estimated_hours` | `estimated_hours` | `estimated_hours` | `estimated_hours` | ✅ Consistent |

### 2. PROJECT FIELDS
| **Frontend Expected** | **API Response** | **Model Field** | **Database Column** | **Status** |
|----------------------|------------------|-----------------|---------------------|------------|
| `name` | `name` | `name` | `name` | ✅ Consistent |
| `description` | `description` | `description` | `description` | ✅ Consistent |
| `status` | `status` | `status` | `status` | ✅ Consistent |
| `budget` | `budget_total` | `budget_total` | `budget_total` | ✅ Consistent |
| `start_date` | `start_date` | `start_date` | `start_date` | ✅ Consistent |
| `end_date` | `end_date` | `end_date` | `end_date` | ✅ Consistent |
| `progress` | `progress_percent` | `progress_pct` | `progress_pct` | ❌ **INCONSISTENT** |
| `priority` | `priority` | `priority` | `priority` | ✅ Consistent |

### 3. CLIENT FIELDS
| **Frontend Expected** | **API Response** | **Model Field** | **Database Column** | **Status** |
|----------------------|------------------|-----------------|---------------------|------------|
| `name` | `name` | `name` | `name` | ✅ Consistent |
| `email` | `email` | `email` | `email` | ✅ Consistent |
| `phone` | `phone` | `phone` | `phone` | ✅ Consistent |
| `company` | `company` | `company` | `company` | ✅ Consistent |
| `lifecycle_stage` | `lifecycle_stage` | `lifecycle_stage` | `lifecycle_stage` | ✅ Consistent |
| `notes` | `notes` | `notes` | `notes` | ✅ Consistent |

### 4. DOCUMENT FIELDS
| **Frontend Expected** | **API Response** | **Model Field** | **Database Column** | **Status** |
|----------------------|------------------|-----------------|---------------------|------------|
| `name` | `original_name` | `original_name` | `original_name` | ✅ Consistent |
| `file_path` | `file_path` | `file_path` | `file_path` | ✅ Consistent |
| `mime_type` | `mime_type` | `mime_type` | `mime_type` | ✅ Consistent |
| `file_size` | `file_size` | `file_size` | `file_size` | ✅ Consistent |
| `category` | `category` | `category` | `category` | ✅ Consistent |

---

## 🚨 CRITICAL INCONSISTENCIES IDENTIFIED

### 1. Task Title vs Name ❌
- **Problem**: API uses `title` but Model uses `name`
- **Impact**: HIGH - Frontend expects `title` but gets `name`
- **Solution**: Standardize on `title` for API responses

### 2. Task Due Date vs End Date ❌
- **Problem**: API uses `due_date` but Model uses `end_date`
- **Impact**: MEDIUM - Confusing for developers
- **Solution**: Use `due_date` consistently

### 3. Project Progress Field ❌
- **Problem**: API uses `progress_percent` but Model uses `progress_pct`
- **Impact**: MEDIUM - Inconsistent naming
- **Solution**: Standardize on `progress_percent`

---

## 🔧 STANDARDIZATION PLAN

### Phase 1: API Response Standardization
1. **Task API**: Map `name` → `title` in responses
2. **Task API**: Map `end_date` → `due_date` in responses  
3. **Project API**: Map `progress_pct` → `progress_percent` in responses

### Phase 2: Model Attribute Mapping
1. **Task Model**: Add `title` accessor that returns `name`
2. **Task Model**: Add `due_date` accessor that returns `end_date`
3. **Project Model**: Add `progress_percent` accessor that returns `progress_pct`

### Phase 3: Frontend Contract Updates
1. Update frontend to expect standardized field names
2. Update API documentation
3. Update tests to use standardized names

---

## 📝 IMPLEMENTATION DETAILS

### Task Model Updates
```php
// Add accessors for consistent field names
public function getTitleAttribute(): string
{
    return $this->name;
}

public function getDueDateAttribute(): ?string
{
    return $this->end_date?->toDateString();
}
```

### Project Model Updates
```php
// Add accessor for consistent field names
public function getProgressPercentAttribute(): int
{
    return $this->progress_pct;
}
```

### API Response Transformations
```php
// In API responses, transform field names
$task->title = $task->name;
$task->due_date = $task->end_date;
$project->progress_percent = $project->progress_pct;
```

---

## ✅ SUCCESS CRITERIA

### Technical Criteria:
- [ ] 100% field name consistency across API responses
- [ ] 0 field mapping errors in frontend
- [ ] All API documentation updated with correct field names
- [ ] All tests updated to use standardized field names

### Quality Criteria:
- [ ] No breaking changes for existing frontend code
- [ ] Clear field naming conventions documented
- [ ] Easy to maintain and extend

---

**This standardization will significantly improve developer experience and reduce confusion when working with the API.**
