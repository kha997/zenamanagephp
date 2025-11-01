# I18N Implementation Guide

## Overview
This document outlines the implementation of internationalization (i18n) and timezone support in ZenaManage, providing multi-language support and proper timezone handling for global users.

## Architecture

### Core Components
- **I18nService**: Central service for managing language, timezone, and formatting
- **I18nController**: API endpoints for i18n functionality
- **Blade Components**: UI components for language and timezone selection
- **Translation Files**: Language-specific translation files

### Supported Languages
- English (en) - Default
- Vietnamese (vi) - Tiếng Việt
- Spanish (es) - Español
- French (fr) - Français
- German (de) - Deutsch
- Japanese (ja) - 日本語
- Chinese (zh) - 中文

### Supported Timezones
- UTC - Coordinated Universal Time
- America/New_York - Eastern Time (US & Canada)
- America/Chicago - Central Time (US & Canada)
- America/Denver - Mountain Time (US & Canada)
- America/Los_Angeles - Pacific Time (US & Canada)
- Europe/London - London (GMT)
- Europe/Paris - Paris (CET)
- Asia/Tokyo - Tokyo (JST)
- Asia/Shanghai - Shanghai (CST)
- Asia/Ho_Chi_Minh - Ho Chi Minh (ICT)

### Supported Currencies
- USD - US Dollar
- EUR - Euro
- GBP - British Pound
- JPY - Japanese Yen
- CAD - Canadian Dollar
- AUD - Australian Dollar
- VND - Vietnamese Dong

## Implementation Details

### 1. I18nService
```php
<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use NumberFormatter;

class I18nService
{
    // Language management
    public function setLanguage(string $language): bool
    public function getCurrentLanguage(): string
    public function getSupportedLanguages(): array
    
    // Timezone management
    public function setTimezone(string $timezone): bool
    public function getCurrentTimezone(): string
    public function getSupportedTimezones(): array
    
    // Currency management
    public function getCurrentCurrency(): string
    public function getSupportedCurrencies(): array
    
    // Formatting methods
    public function formatDate(\DateTimeInterface $date, ?string $format = null): string
    public function formatTime(\DateTimeInterface $time, ?string $format = null): string
    public function formatDateTime(\DateTimeInterface $datetime, ?string $format = null): string
    public function formatNumber(float $number, int $decimals = 2): string
    public function formatCurrency(float $amount, ?string $currency = null): string
    
    // Configuration
    public function getConfiguration(): array
    public function initializeFromUserPreferences(array $preferences): void
}
```

### 2. API Endpoints
```php
// Public i18n routes
Route::prefix('i18n')->group(function () {
    Route::get('/config', [I18nController::class, 'getConfiguration']);
    Route::post('/language', [I18nController::class, 'setLanguage']);
    Route::post('/timezone', [I18nController::class, 'setTimezone']);
    Route::post('/format/date', [I18nController::class, 'formatDate']);
    Route::post('/format/time', [I18nController::class, 'formatTime']);
    Route::post('/format/datetime', [I18nController::class, 'formatDateTime']);
    Route::post('/format/number', [I18nController::class, 'formatNumber']);
    Route::post('/format/currency', [I18nController::class, 'formatCurrency']);
    Route::get('/locale', [I18nController::class, 'getCurrentLocale']);
});
```

### 3. Blade Components

#### Language Selector
```html
<div class="language-selector">
    <select id="language-select" onchange="window.setLanguage(this.value)">
        @foreach($languages as $code => $name)
            <option value="{{ $code }}" @if($currentLanguage === $code) selected @endif>
                {{ $name }}
            </option>
        @endforeach
    </select>
</div>
```

#### Timezone Selector
```html
<div class="timezone-selector">
    <select id="timezone-select" onchange="window.setTimezone(this.value)">
        @foreach($timezones as $tzId => $tzName)
            <option value="{{ $tzId }}" @if($currentTimezone === $tzId) selected @endif>
                {{ $tzName }}
            </option>
        @endforeach
    </select>
</div>
```

### 4. Translation Files
Located in `lang/{locale}/` directory:

- `settings.php` - Settings page translations
- `tasks.php` - Task management translations
- `quotes.php` - Quote management translations

Example Vietnamese translation:
```php
<?php
// lang/vi/settings.php
return [
    'title' => 'Cài đặt',
    'subtitle' => 'Quản lý tài khoản và cài đặt ứng dụng của bạn.',
    'language' => 'Ngôn ngữ',
    'timezone' => 'Múi giờ',
    // ... more translations
];
```

## Usage Examples

### 1. Setting Language
```javascript
// Frontend
fetch('/api/i18n/language', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    },
    body: JSON.stringify({ language: 'vi' })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        window.location.reload(); // Reload to apply new language
    }
});
```

### 2. Formatting Dates
```php
// Backend
$i18nService = app(I18nService::class);
$date = new DateTime('2024-01-15');
$formatted = $i18nService->formatDate($date);
// English: "Jan 15, 2024"
// Vietnamese: "15/01/2024"
```

### 3. Formatting Currency
```php
// Backend
$amount = 1234.56;
$formatted = $i18nService->formatCurrency($amount, 'VND');
// Vietnamese: "1.234,56 ₫"
```

## Testing

### Unit Tests
- `I18nServiceTest` - Tests core i18n functionality
- Language/timezone setting and validation
- Date/time/number/currency formatting
- Session persistence
- Configuration retrieval

### Feature Tests
- `I18nFeatureTest` - Tests API endpoints
- Language switching workflow
- Timezone switching workflow
- Format validation
- Error handling

## Security Considerations

### Input Validation
- All language codes validated against supported languages
- Timezone codes validated against supported timezones
- Currency codes validated against supported currencies
- Date/time inputs validated for proper format

### Error Handling
- Graceful fallback to default language/timezone
- Proper HTTP status codes (400 for validation errors, 422 for format errors)
- Structured error responses with clear messages

## Performance Considerations

### Session Management
- Language/timezone preferences stored in session
- Automatic application of locale settings
- Minimal overhead for formatting operations

### Caching
- Translation files cached by Laravel
- NumberFormatter instances reused
- Carbon locale settings applied once per request

## Future Enhancements

### Planned Features
1. **RTL Support**: Right-to-left language support (Arabic, Hebrew)
2. **Pluralization**: Advanced pluralization rules for different languages
3. **Date/Time Localization**: More sophisticated date/time formatting
4. **Currency Conversion**: Real-time currency conversion
5. **User Preferences**: Persistent user language/timezone preferences

### Integration Points
- User profile settings
- Dashboard preferences
- Email templates
- PDF generation
- API responses

## Troubleshooting

### Common Issues

1. **Language not switching**
   - Check if language is in supported languages list
   - Verify session is working properly
   - Check browser cache

2. **Timezone not applying**
   - Verify timezone is in supported timezones list
   - Check server timezone settings
   - Verify Carbon timezone setting

3. **Formatting issues**
   - Check if NumberFormatter extension is installed
   - Verify locale data is available
   - Check for proper date/time input format

### Debug Mode
Enable debug logging in `I18nService`:
```php
Log::info("Language set to: {$language}");
Log::info("Timezone set to: {$timezone}");
```

## Conclusion

The i18n implementation provides comprehensive multi-language and timezone support for ZenaManage, enabling global users to interact with the system in their preferred language and timezone. The implementation follows Laravel best practices and provides a solid foundation for future internationalization enhancements.
