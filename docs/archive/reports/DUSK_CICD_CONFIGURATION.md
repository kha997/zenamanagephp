# Laravel Dusk CI/CD Configuration

## Environment Setup

### GitHub Actions Workflow
```yaml
name: E2E Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  dusk-tests:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mbstring, dom, fileinfo, mysql
        
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        
    - name: Install Composer dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader
      
    - name: Install NPM dependencies
      run: npm ci
      
    - name: Build assets
      run: npm run build
      
    - name: Setup MySQL
      run: |
        sudo systemctl start mysql
        mysql -e "CREATE DATABASE zenamanage_test;"
        
    - name: Copy environment file
      run: cp .env.example .env
      
    - name: Generate application key
      run: php artisan key:generate
      
    - name: Run migrations
      run: php artisan migrate --force
      
    - name: Seed database
      run: php artisan db:seed --force
      
    - name: Start Laravel server
      run: php artisan serve --host=0.0.0.0 --port=8000 &
      
    - name: Wait for server
      run: sleep 10
      
    - name: Run Dusk tests
      run: php artisan dusk
      
    - name: Upload screenshots
      uses: actions/upload-artifact@v3
      if: failure()
      with:
        name: screenshots
        path: tests/Browser/screenshots/
```

### Docker Configuration
```dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    chromium \
    chromium-driver

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader
RUN npm ci && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www
RUN chmod -R 755 /var/www/storage
RUN chmod -R 755 /var/www/bootstrap/cache

# Expose port
EXPOSE 8000

# Start server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

## Test Configuration

### DuskTestCase Configuration
```php
<?php

namespace Tests;

use Laravel\Dusk\TestCase as BaseTestCase;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    public static function prepare()
    {
        if (! static::runningInSail()) {
            static::startChromeDriver();
        }
    }

    protected function driver()
    {
        $options = (new ChromeOptions)->addArguments([
            '--disable-gpu',
            '--headless',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-extensions',
            '--disable-plugins',
            '--disable-images',
            '--disable-javascript',
            '--window-size=1920,1080',
        ]);

        return RemoteWebDriver::create(
            'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }
}
```

## Performance Optimization

### Test Database Configuration
```php
// config/database.php
'testing' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'zenamanage_test'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```

### Parallel Testing Configuration
```php
// phpunit.xml
<phpunit>
    <testsuites>
        <testsuite name="Browser">
            <directory suffix="Test.php">./tests/Browser</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="testing"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
    </php>
</phpunit>
```

## Monitoring and Reporting

### Test Results Reporting
```php
// tests/Browser/TestReporter.php
<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase;

class TestReporter
{
    public static function generateReport(TestCase $testCase)
    {
        $results = [
            'test_name' => $testCase->getName(),
            'execution_time' => microtime(true) - LARAVEL_START,
            'memory_usage' => memory_get_peak_usage(true),
            'status' => 'passed',
            'timestamp' => now()->toISOString(),
        ];

        // Save to file or send to monitoring service
        file_put_contents(
            storage_path('logs/test-results.json'),
            json_encode($results, JSON_PRETTY_PRINT)
        );
    }
}
```

### Performance Monitoring
```php
// tests/Browser/PerformanceMonitor.php
<?php

namespace Tests\Browser;

class PerformanceMonitor
{
    public static function monitorPageLoad(Browser $browser, string $url)
    {
        $startTime = microtime(true);
        
        $browser->visit($url);
        
        $loadTime = microtime(true) - $startTime;
        
        if ($loadTime > 2.0) {
            throw new \Exception("Page load time exceeded 2 seconds: {$loadTime}s");
        }
        
        return $loadTime;
    }
}
```

## Security Testing

### Security Test Suite
```php
// tests/Browser/SecurityTest.php
<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SecurityTest extends DuskTestCase
{
    public function test_security_headers()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertHeader('X-Content-Type-Options', 'nosniff')
                    ->assertHeader('X-Frame-Options', 'DENY')
                    ->assertHeader('X-XSS-Protection', '1; mode=block');
        });
    }

    public function test_csrf_protection()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertPresent('input[name="_token"]');
        });
    }
}
```

## Troubleshooting

### Common Issues and Solutions

1. **ChromeDriver Issues**
   ```bash
   # Update ChromeDriver
   php artisan dusk:install
   
   # Check ChromeDriver version
   ./vendor/laravel/dusk/bin/chromedriver-mac-intel --version
   ```

2. **Database Connection Issues**
   ```bash
   # Reset database
   php artisan migrate:fresh --seed
   
   # Check database connection
   php artisan tinker
   DB::connection()->getPdo();
   ```

3. **Asset Loading Issues**
   ```bash
   # Build assets
   npm run build
   
   # Check asset paths
   php artisan route:list
   ```

4. **Performance Issues**
   ```bash
   # Clear caches
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   
   # Optimize for production
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

## Best Practices

1. **Test Isolation**: Each test should be independent
2. **Data Cleanup**: Use DatabaseMigrations trait
3. **Wait Strategies**: Use proper wait methods
4. **Error Handling**: Implement proper error handling
5. **Performance**: Monitor test execution time
6. **Security**: Test security features thoroughly
7. **Documentation**: Document test scenarios
8. **Maintenance**: Keep tests up to date
