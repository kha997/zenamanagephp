# FEATURE FLAGS DOCUMENTATION
**Version**: 2.2.2  
**Last Updated**: October 6, 2025  
**Status**: Production Ready ✅

## Overview

Feature flags in ZenaManage allow enabling/disabling features without code changes. This system provides granular control over features at global, tenant, and user levels.

## Configuration

Feature flags are configured in `config/features.php`:

```php
return [
    'ui' => [
        'enable_focus_mode' => env('FEATURE_FOCUS_MODE', false),
        'enable_rewards' => env('FEATURE_REWARDS', false),
    ],
    'api' => [
        'enable_advanced_analytics' => env('FEATURE_ADVANCED_ANALYTICS', false),
        'enable_ai_features' => env('FEATURE_AI_FEATURES', false),
    ],
    'security' => [
        'enable_enhanced_security' => env('FEATURE_ENHANCED_SECURITY', false),
    ],
];
```

## Available Feature Flags

### UI Features

#### `ui.enable_focus_mode`
- **Description**: Enables Focus Mode - minimal interface for better concentration
- **Default**: `false`
- **Environment Variable**: `FEATURE_FOCUS_MODE`
- **When Enabled**:
  - Sidebar collapses
  - Secondary KPIs are hidden
  - Only main task/project list is shown
  - Clean, minimal theme with more whitespace

#### `ui.enable_rewards`
- **Description**: Enables celebration animations when tasks are completed
- **Default**: `false`
- **Environment Variable**: `FEATURE_REWARDS`
- **When Enabled**:
  - Confetti/fireworks animation on task completion
  - Congratulatory messages in multiple languages
  - Auto-dismiss after 3-5 seconds

### API Features

#### `api.enable_advanced_analytics`
- **Description**: Enables advanced analytics and reporting features
- **Default**: `false`
- **Environment Variable**: `FEATURE_ADVANCED_ANALYTICS`

#### `api.enable_ai_features`
- **Description**: Enables AI-powered features like smart suggestions
- **Default**: `false`
- **Environment Variable**: `FEATURE_AI_FEATURES`

### Security Features

#### `security.enable_enhanced_security`
- **Description**: Enables enhanced security features like 2FA
- **Default**: `false`
- **Environment Variable**: `FEATURE_ENHANCED_SECURITY`

## Usage

### Service Usage

```php
use App\Services\FeatureFlagService;

$featureFlagService = app(FeatureFlagService::class);

// Check if feature is enabled
$isEnabled = $featureFlagService->isEnabled('ui.enable_focus_mode');

// Check with context
$isEnabled = $featureFlagService->isEnabled('ui.enable_focus_mode', $tenantId, $userId);

// Set feature flag
$featureFlagService->setEnabled('ui.enable_focus_mode', true, $tenantId, $userId);

// Get all flags
$allFlags = $featureFlagService->getAllFlags($tenantId, $userId);
```

### Middleware Usage

```php
// In routes
Route::middleware(['auth:sanctum', 'feature:ui.enable_focus_mode'])->group(function () {
    // Routes that require focus mode feature
});

// Multiple flags
Route::middleware(['auth:sanctum', 'feature:ui.enable_focus_mode,ui.enable_rewards'])->group(function () {
    // Routes that require both features
});
```

### Controller Usage

```php
class FocusModeController extends Controller
{
    public function toggle(Request $request)
    {
        // Check if feature is enabled globally
        if (!$this->featureFlagService->isEnabled('ui.enable_focus_mode')) {
            return ApiResponse::error(
                ['message' => 'Focus Mode feature is not enabled'],
                403,
                'FEATURE_DISABLED'
            )->toResponse($request);
        }
        
        // Feature is enabled, proceed with logic
    }
}
```

## Control Methods

Feature flags can be controlled at different levels:

### Global Control
- Set in environment variables or config files
- Affects all tenants and users
- Highest priority

### Tenant Control
- Set in tenant preferences
- Affects all users in the tenant
- Overrides global settings

### User Control
- Set in user preferences
- Affects only the specific user
- Overrides tenant and global settings

## Hierarchy

Feature flags follow this hierarchy (highest to lowest priority):

1. **User Level** - Individual user preferences
2. **Tenant Level** - Tenant-specific preferences
3. **Global Level** - Environment/config settings

## Caching

Feature flags are cached for performance:

- **Cache TTL**: 5 minutes (300 seconds)
- **Cache Key Format**: `feature_flag:{flag_name}:tenant:{tenant_id}:user:{user_id}`
- **Cache Clearing**: Automatic when flags are updated

## Database Schema

### User Preferences Table

```sql
CREATE TABLE user_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    preferences JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY user_preferences_user_id_unique (user_id),
    KEY user_preferences_user_id_index (user_id),
    CONSTRAINT user_preferences_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

### Tenant Preferences

Tenant preferences are stored in the `preferences` JSON column of the `tenants` table.

## API Endpoints

### Focus Mode

- `POST /api/v1/app/focus-mode/toggle` - Toggle focus mode
- `GET /api/v1/app/focus-mode/status` - Get focus mode status
- `POST /api/v1/app/focus-mode/set-state` - Set focus mode state
- `GET /api/v1/app/focus-mode/config` - Get focus mode configuration

### Rewards

- `POST /api/v1/app/rewards/toggle` - Toggle rewards
- `GET /api/v1/app/rewards/status` - Get rewards status
- `POST /api/v1/app/rewards/trigger-task-completion` - Trigger task completion rewards
- `GET /api/v1/app/rewards/messages` - Get reward messages

## Frontend Integration

### JavaScript Usage

```javascript
// Check if feature is enabled
const isEnabled = await fetch('/api/v1/app/focus-mode/status')
    .then(response => response.json())
    .then(data => data.data.focus_mode_active);

// Toggle feature
await fetch('/api/v1/app/focus-mode/toggle', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    }
});
```

### Alpine.js Integration

```html
<div x-data="focusMode()">
    <button @click="toggle()" :class="isActive ? 'active' : ''">
        <span x-text="toggleText"></span>
    </button>
</div>
```

## Testing

Feature flags are thoroughly tested:

- **Unit Tests**: `FeatureFlagServiceTest`
- **Feature Tests**: `FocusModeTest`, `RewardsTest`
- **Integration Tests**: Full API workflow testing

## Best Practices

1. **Always check feature flags** before implementing feature-specific logic
2. **Use middleware** for route-level feature flag checks
3. **Cache feature flags** for performance
4. **Provide fallbacks** when features are disabled
5. **Test both enabled and disabled states**
6. **Document feature flag behavior** in API documentation

## Migration Guide

### Enabling a New Feature Flag

1. Add flag to `config/features.php`
2. Set environment variable
3. Update middleware if needed
4. Add tests
5. Update documentation

### Disabling a Feature Flag

1. Set environment variable to `false`
2. Clear cache: `php artisan cache:clear`
3. Verify feature is disabled
4. Update documentation

## Troubleshooting

### Feature Not Working

1. Check environment variable is set correctly
2. Verify feature flag is enabled in config
3. Clear cache: `php artisan cache:clear`
4. Check user/tenant preferences
5. Verify middleware is applied correctly

### Cache Issues

1. Clear feature flag cache: `php artisan cache:forget feature_flag:*`
2. Clear all cache: `php artisan cache:clear`
3. Restart application if needed

### Database Issues

1. Run migrations: `php artisan migrate`
2. Check foreign key constraints
3. Verify user_preferences table exists
4. Check tenant preferences JSON structure
