# ZenaManage Developer Documentation

## Architecture Overview

ZenaManage is built on Laravel 10 with a multi-tenant architecture, providing scalable project management capabilities with comprehensive API endpoints and modern frontend components.

## Technology Stack

### Backend
- **Laravel 10**: PHP framework
- **MySQL**: Primary database
- **Redis**: Caching and session storage
- **Laravel Sanctum**: API authentication
- **Spatie Permission**: Role and permission management

### Frontend
- **Blade Templates**: Server-side rendering
- **Alpine.js**: Reactive JavaScript framework
- **Tailwind CSS**: Utility-first CSS framework
- **Font Awesome**: Icon library

### Development Tools
- **Composer**: PHP dependency management
- **NPM**: Node.js package management
- **Git**: Version control
- **PHPUnit**: Testing framework

## Project Structure

```
zenamanage/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── KpiController.php
│   │   │   ├── AlertController.php
│   │   │   ├── ActivityController.php
│   │   │   ├── SearchController.php
│   │   │   ├── FilterController.php
│   │   │   ├── AnalysisController.php
│   │   │   ├── ExportController.php
│   │   │   ├── AccessibilityController.php
│   │   │   └── PerformanceOptimizationController.php
│   │   └── Middleware/
│   ├── Services/
│   │   ├── KpiService.php
│   │   ├── AlertService.php
│   │   ├── ActivityService.php
│   │   ├── SearchService.php
│   │   ├── FilterService.php
│   │   ├── AnalysisService.php
│   │   ├── ExportService.php
│   │   ├── AccessibilityService.php
│   │   └── PerformanceOptimizationService.php
│   ├── Policies/
│   │   ├── DocumentPolicy.php
│   │   ├── ComponentPolicy.php
│   │   └── UserPolicy.php
│   └── Models/
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── universal-frame.blade.php
│       ├── components/
│       │   ├── universal-header.blade.php
│       │   ├── universal-navigation.blade.php
│       │   ├── kpi-strip.blade.php
│       │   ├── alert-bar.blade.php
│       │   ├── activity-panel.blade.php
│       │   ├── smart-search.blade.php
│       │   ├── smart-filters.blade.php
│       │   ├── analysis-drawer.blade.php
│       │   ├── export-component.blade.php
│       │   ├── mobile-fab.blade.php
│       │   ├── mobile-drawer.blade.php
│       │   ├── mobile-navigation.blade.php
│       │   ├── accessibility-skip-links.blade.php
│       │   ├── accessibility-focus-manager.blade.php
│       │   ├── accessibility-aria-labels.blade.php
│       │   ├── accessibility-color-contrast.blade.php
│       │   └── accessibility-dashboard.blade.php
│       ├── admin/
│       │   ├── dashboard.blade.php
│       │   ├── users/
│       │   └── tenants/
│       ├── tenant/
│       │   ├── dashboard.blade.php
│       │   ├── projects/
│       │   └── tasks/
│       └── test pages/
├── routes/
│   └── web.php
├── config/
├── database/
└── tests/
```

## Core Components

### Universal Page Frame
The Universal Page Frame provides a consistent layout structure across all pages:

```blade
@extends('layouts.universal-frame')

@section('main-content')
    <!-- Page content -->
@endsection
```

**Components:**
- `universal-header.blade.php`: Top navigation and user menu
- `universal-navigation.blade.php`: Global and page navigation
- `kpi-strip.blade.php`: Key performance indicators
- `alert-bar.blade.php`: System alerts and notifications
- `activity-panel.blade.php`: Recent activity feed

### Smart Tools
Intelligent features for enhanced user experience:

#### Search Service
```php
class SearchService
{
    public function search(string $query, string $context = 'all'): array
    {
        // Fuzzy matching implementation
        // Recent searches tracking
        // Role-aware results
    }
}
```

#### Filter Service
```php
class FilterService
{
    public function getPresets(): array
    {
        // One-tap focus presets
        // Deep filter options
        // Saved filter views
    }
}
```

#### Analysis Service
```php
class AnalysisService
{
    public function getContextAnalysis(string $context): array
    {
        // Interactive charts
        // Key metrics
        // AI-generated insights
    }
}
```

### Mobile Optimization
Mobile-first responsive design with touch-friendly interactions:

#### Mobile Components
- `mobile-fab.blade.php`: Floating Action Button
- `mobile-drawer.blade.php`: Slide-out navigation
- `mobile-navigation.blade.php`: Bottom navigation bar

#### Responsive Features
- Touch-friendly 44px+ targets
- Smooth animations and transitions
- Progressive enhancement
- Mobile-optimized layouts

### Accessibility Implementation
WCAG 2.1 AA compliance with comprehensive accessibility features:

#### Accessibility Components
- `accessibility-skip-links.blade.php`: Keyboard navigation
- `accessibility-focus-manager.blade.php`: Focus management
- `accessibility-aria-labels.blade.php`: Screen reader support
- `accessibility-color-contrast.blade.php`: Color contrast compliance

#### Accessibility Features
- Keyboard navigation support
- Screen reader compatibility
- Focus management
- Color contrast compliance
- High contrast mode
- Reduced motion support

## API Architecture

### Authentication
All API endpoints use Laravel Sanctum for token-based authentication:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    // Protected API routes
});
```

### Response Format
Standardized JSON response format:

```json
{
  "data": {...},
  "meta": {
    "pagination": {...}
  },
  "links": {...}
}
```

### Error Handling
Consistent error response format:

```json
{
  "error": {
    "id": "ERR_001",
    "message": "Validation failed",
    "details": {...},
    "timestamp": "2025-09-24T10:00:00Z"
  }
}
```

## Database Design

### Multi-Tenant Architecture
Every table includes `tenant_id` for data isolation:

```sql
CREATE TABLE projects (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('planning', 'in_progress', 'on_hold', 'completed'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_tenant_status (tenant_id, status)
);
```

### Key Tables
- `tenants`: Tenant information
- `users`: User accounts
- `projects`: Project data
- `tasks`: Task management
- `documents`: Document storage
- `activities`: Activity logs

## Security Implementation

### Authentication & Authorization
- **Laravel Sanctum**: API token authentication
- **Spatie Permission**: Role-based access control
- **Policy Classes**: Model-level authorization
- **Middleware**: Route-level protection

### Data Protection
- **Tenant Isolation**: Mandatory tenant_id filtering
- **Input Validation**: Comprehensive validation rules
- **Output Sanitization**: XSS protection
- **CSRF Protection**: Cross-site request forgery protection

### Security Headers
```php
// Security middleware
'headers' => [
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Content-Security-Policy' => "default-src 'self'",
    'Referrer-Policy' => 'strict-origin-when-cross-origin'
]
```

## Performance Optimization

### Caching Strategy
- **Application Cache**: In-memory caching
- **Database Query Cache**: Query result caching
- **API Response Cache**: Response caching
- **Browser Cache**: Static asset caching

### Performance Monitoring
```php
class PerformanceOptimizationService
{
    public function getPerformanceMetrics(): array
    {
        return [
            'page_load_time' => $this->getPageLoadTime(),
            'api_response_time' => $this->getApiResponseTime(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'database_query_time' => $this->getDatabaseQueryTime()
        ];
    }
}
```

### Optimization Features
- Lazy loading for images and components
- Code splitting for JavaScript bundles
- Image compression and optimization
- CSS/JavaScript minification
- CDN integration for static assets

## Testing Strategy

### Test Categories
- **Unit Tests**: Individual component testing
- **Integration Tests**: API endpoint testing
- **Feature Tests**: End-to-end functionality testing
- **Performance Tests**: Load and performance testing

### Testing Suite
Comprehensive testing interface at `/testing-suite`:

```blade
<!-- Testing Suite Components -->
- Route Testing
- Component Testing
- Performance Testing
- Accessibility Testing
- Mobile Testing
```

### Test Coverage
- Route accessibility and response codes
- Component rendering and functionality
- Performance metrics and thresholds
- Accessibility compliance validation
- Mobile responsiveness testing

## Deployment Guide

### Environment Setup
1. **PHP 8.2+**: Required PHP version
2. **Composer**: Install dependencies
3. **MySQL**: Database setup
4. **Redis**: Caching and sessions
5. **Node.js**: Frontend asset compilation

### Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database
# Update .env with database credentials

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Install frontend dependencies
npm install

# Compile assets
npm run build
```

### Production Deployment
1. **Server Requirements**: PHP 8.2+, MySQL 8.0+, Redis 6.0+
2. **Web Server**: Nginx or Apache
3. **SSL Certificate**: HTTPS configuration
4. **Environment Variables**: Production configuration
5. **Asset Compilation**: Production asset build
6. **Database Migration**: Production database setup

### Docker Deployment
```dockerfile
FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www
```

## Development Workflow

### Git Workflow
1. **Feature Branches**: Create feature branches from main
2. **Code Review**: Pull request reviews
3. **Testing**: Automated testing on pull requests
4. **Deployment**: Automated deployment on merge

### Code Standards
- **PSR-12**: PHP coding standards
- **Laravel Conventions**: Laravel-specific standards
- **ESLint**: JavaScript linting
- **Prettier**: Code formatting

### Continuous Integration
- **Automated Testing**: Run tests on every commit
- **Code Quality**: Static analysis and linting
- **Security Scanning**: Vulnerability scanning
- **Performance Testing**: Automated performance tests

## Troubleshooting

### Common Issues

#### Database Connection Issues
```bash
# Check database configuration
php artisan config:show database

# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

#### Cache Issues
```bash
# Clear application cache
php artisan cache:clear

# Clear configuration cache
php artisan config:clear

# Clear route cache
php artisan route:clear
```

#### Performance Issues
- Check database query performance
- Monitor cache hit rates
- Analyze slow queries
- Review server resources

### Debugging Tools
- **Laravel Debugbar**: Development debugging
- **Laravel Telescope**: Application monitoring
- **Performance Profiler**: Performance analysis
- **Error Tracking**: Error monitoring and reporting

## Contributing

### Development Setup
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

### Code Review Process
1. **Automated Checks**: CI/CD pipeline validation
2. **Code Review**: Peer review process
3. **Testing**: Comprehensive testing
4. **Documentation**: Update documentation
5. **Merge**: Merge to main branch

### Contribution Guidelines
- Follow coding standards
- Write comprehensive tests
- Update documentation
- Provide clear commit messages
- Include issue references

## Resources

### Documentation
- **Laravel Documentation**: https://laravel.com/docs
- **Alpine.js Documentation**: https://alpinejs.dev/
- **Tailwind CSS Documentation**: https://tailwindcss.com/docs
- **WCAG Guidelines**: https://www.w3.org/WAI/WCAG21/quickref/

### Tools
- **Laravel IDE Helper**: IDE autocompletion
- **Laravel Debugbar**: Development debugging
- **Laravel Telescope**: Application monitoring
- **PHPUnit**: Testing framework

### Community
- **Laravel Community**: https://laravel.com/community
- **GitHub Issues**: Bug reports and feature requests
- **Discord**: Developer community chat
- **Stack Overflow**: Technical questions

---

*Last updated: September 24, 2025*
*Version: 1.0*
