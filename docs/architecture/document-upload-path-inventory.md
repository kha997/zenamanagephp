# Document Upload Path Inventory

Audit date: 2026-07-13

## Canonical Model

`App\Models\Document` — uses `HasUlids`, `TenantScope`, `SoftDeletes`.

```php
public const VALID_DOCUMENT_TYPES = [
    'drawing', 'specification', 'contract', 'report', 'photo', 'other',
];
```

## Adapter

`Src\DocumentManagement\Models\LegacyDocumentAdapter` — **empty subclass** of `App\Models\Document`.
No traits, no overrides, no added behaviour. Safe to replace with direct `App\Models\Document` usage.

## Upload Paths

| # | Controller | Model Used | `document_type` Validation | Upload Logic | Notes |
|---|-----------|-----------|---------------------------|-------------|-------|
| 1 | `Api\DocumentController` | `LegacyDocumentAdapter` (aliased as `Document`) | `required\|in:drawing,specification,contract,report,photo,other` (strict enum) | Own `store()` — creates `Document::create()` directly | Legacy. Uses `FileStorageService`. Missing `tenant_id` in create payload. |
| 2 | `Web\DocumentController` | `LegacyDocumentAdapter` (aliased as `Document`) | `required\|string\|max:100` (free text) | **Delegates** to `SimpleDocumentController::store()` | Legacy import, but upload itself goes through canonical path. Only used for queries (index, show, etc.). |
| 3 | `Api\SimpleDocumentController` | `App\Models\Document` ✅ | `required\|string\|max:100` (free text) | Own `store()` — creates `Document::create()` directly | **Canonical.** Full CRUD, proper tenant scoping, versioning. |
| 4 | `Api\DesignItemController` | `App\Models\Document` ✅ | N/A — no `document_type` field set | Inline `Document::query()->create()` | Design item attachment upload. No `document_type` validation needed (uses linked entity pattern). |

## Validation Inconsistency

Three different `document_type` validation strategies across the same DB column:

1. **Strict enum** (`Api\DocumentController`): `in:drawing,specification,contract,report,photo,other` — matches `Document::VALID_DOCUMENT_TYPES`
2. **Free text** (`SimpleDocumentController` store): `string|max:100` — accepts any value
3. **Free text** (`Web\DocumentController` store): `string|max:100` — accepts any value (delegates to SimpleDocumentController)

**Decision**: Standardise on `Document::VALID_DOCUMENT_TYPES` via `Rule::in()` for all new uploads.
Existing data with non-enum values is NOT migrated in this slice — only new writes are constrained.

## Changes Made (this slice)

1. `Api\DocumentController`: import `LegacyDocumentAdapter` → `App\Models\Document`
2. `Web\DocumentController`: import `LegacyDocumentAdapter` → `App\Models\Document`
3. `SimpleDocumentController`: `document_type` validation tightened from `string|max:100` to `Rule::in(Document::VALID_DOCUMENT_TYPES)` on store and update
4. `Web\DocumentController`: `document_type` validation tightened from `string|max:100` to `Rule::in(Document::VALID_DOCUMENT_TYPES)` (pre-validation before delegation)
5. Architecture guard test blocks new imports of `LegacyDocumentAdapter` in `app/Http/`
