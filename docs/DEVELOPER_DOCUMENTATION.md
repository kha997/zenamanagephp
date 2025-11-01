# 👨‍💻 DEVELOPER DOCUMENTATION - DASHBOARD SYSTEM

## 📋 OVERVIEW

This document provides comprehensive technical documentation for developers working with the ZenaManage Dashboard System, including architecture, APIs, database schema, and integration guides.

---

## 🏗️ **SYSTEM ARCHITECTURE**

### 📡 **Backend Architecture**

```
┌─────────────────────────────────────────────────────────────┐
│                BACKEND ARCHITECTURE                        │
├─────────────────────────────────────────────────────────────┤
│ 🎯 Controllers Layer                                       │
│ ├── DashboardController                                    │
│ ├── DashboardRoleBasedController                           │
│ ├── DashboardCustomizationController                       │
│ └── DashboardRealTimeController                            │
├─────────────────────────────────────────────────────────────┤
│ 🔧 Services Layer                                         │
│ ├── DashboardService                                       │
│ ├── DashboardRoleBasedService                              │
│ ├── DashboardDataAggregationService                         │
│ ├── DashboardRealTimeService                               │
│ └── DashboardCustomizationService                          │
├─────────────────────────────────────────────────────────────┤
│ 📊 Models Layer                                            │
│ ├── UserDashboard                                          │
│ ├── DashboardWidget                                        │
│ ├── DashboardMetric                                        │
│ ├── DashboardAlert                                         │
│ └── DashboardMetricValue                                   │
├─────────────────────────────────────────────────────────────┤
│ 🗄️ Database Layer                                         │
│ ├── MySQL Database                                         │
│ ├── Redis Cache                                            │
│ └── File Storage                                           │
└─────────────────────────────────────────────────────────────┘
```

### 🎨 **Frontend Architecture**

```
┌─────────────────────────────────────────────────────────────┐
│                FRONTEND ARCHITECTURE                       │
├─────────────────────────────────────────────────────────────┤
│ 🎯 Components Layer                                        │
│ ├── RoleBasedDashboard                                     │
│ ├── RoleBasedWidget                                        │
│ ├── DashboardCustomizer                                    │
│ └── WidgetSelector                                         │
├─────────────────────────────────────────────────────────────┤
│ 🎣 Hooks Layer                                             │
│ ├── useRoleBasedPermissions                                │
│ ├── useDashboard                                           │
│ ├── useRealTimeUpdates                                     │
│ └── useAuth                                                │
├─────────────────────────────────────────────────────────────┤
│ 🔧 Services Layer                                          │
│ ├── API Service                                            │
│ ├── WebSocket Service                                      │
│ ├── Cache Service                                          │
│ └── Storage Service                                        │
├─────────────────────────────────────────────────────────────┤
│ 🎨 UI Framework                                            │
│ ├── Chakra UI                                              │
│ ├── React Router                                           │
│ ├── Axios                                                  │
│ └── TypeScript                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🗄️ **DATABASE SCHEMA**

### 📊 **Core Tables**

#### `user_dashboards`
```sql
CREATE TABLE user_dashboards (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    tenant_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    layout JSON NOT NULL,
    preferences JSON NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_tenant (user_id, tenant_id),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### `dashboard_widgets`
```sql
CREATE TABLE dashboard_widgets (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) UNIQUE NOT NULL,
    type ENUM('card', 'chart', 'table', 'alert', 'timeline', 'progress') NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    config JSON,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    tenant_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_type (type),
    INDEX idx_category (category),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### `dashboard_metrics`
```sql
CREATE TABLE dashboard_metrics (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    unit VARCHAR(50),
    type ENUM('gauge', 'counter', 'histogram', 'summary') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    permissions JSON,
    tenant_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_type (type),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### `dashboard_alerts`
```sql
CREATE TABLE dashboard_alerts (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    tenant_id VARCHAR(36) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(100) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    context JSON,
    widget_id VARCHAR(36),
    metric_id VARCHAR(36),
    
    INDEX idx_user_tenant (user_id, tenant_id),
    INDEX idx_type (type),
    INDEX idx_severity (severity),
    INDEX idx_triggered_at (triggered_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (widget_id) REFERENCES dashboard_widgets(id) ON DELETE SET NULL,
    FOREIGN KEY (metric_id) REFERENCES dashboard_metrics(id) ON DELETE SET NULL
);
```

#### `dashboard_metric_values`
```sql
CREATE TABLE dashboard_metric_values (
    id VARCHAR(36) PRIMARY KEY,
    metric_id VARCHAR(36) NOT NULL,
    tenant_id VARCHAR(36) NOT NULL,
    project_id VARCHAR(36),
    value DECIMAL(15,4) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    context JSON,
    
    INDEX idx_metric_timestamp (metric_id, timestamp),
    INDEX idx_tenant_project (tenant_id, project_id),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (metric_id) REFERENCES dashboard_metrics(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

---

## 🔧 **API DEVELOPMENT**

### 📡 **API Structure**

#### **Base Controller**
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BaseApiController extends Controller
{
    protected function successResponse($data = null, $message = 'Success', $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message
        ], $statusCode);
    }

    protected function errorResponse($message = 'Error', $errors = null, $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }
}
```

#### **Dashboard Controller Example**
```php
<?php

namespace App\Http\Controllers\Api;

class DashboardController extends BaseApiController
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function getUserDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $dashboard = $this->dashboardService->getUserDashboard($user);
            
            return $this->successResponse($dashboard);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load dashboard', null, 500);
        }
    }
}
```

### 🔐 **Authentication & Authorization**

#### **Middleware Setup**
```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'getUserDashboard']);
        Route::get('/widgets', [DashboardController::class, 'getAvailableWidgets']);
        // ... other routes
    });
});
```

#### **Permission Middleware**
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckDashboardPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        $user = $request->user();
        
        if (!$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }
        
        return $next($request);
    }
}
```

---

## 🎨 **FRONTEND DEVELOPMENT**

### ⚛️ **React Component Structure**

#### **Base Component**
```typescript
import React, { useState, useEffect } from 'react';
import { Box, VStack, HStack, Text, Button } from '@chakra-ui/react';

interface BaseComponentProps {
  children: React.ReactNode;
  title?: string;
  onRefresh?: () => void;
}

const BaseComponent: React.FC<BaseComponentProps> = ({
  children,
  title,
  onRefresh
}) => {
  return (
    <Box p={4}>
      <VStack spacing={4} align="stretch">
        {title && (
          <HStack justify="space-between">
            <Text fontSize="lg" fontWeight="bold">{title}</Text>
            {onRefresh && (
              <Button size="sm" onClick={onRefresh}>
                Refresh
              </Button>
            )}
          </HStack>
        )}
        {children}
      </VStack>
    </Box>
  );
};

export default BaseComponent;
```

#### **Custom Hook Example**
```typescript
import { useState, useEffect } from 'react';

interface UseDashboardDataOptions {
  projectId?: string;
  refreshInterval?: number;
}

export const useDashboardData = (options: UseDashboardDataOptions = {}) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchData = async () => {
    setLoading(true);
    setError(null);
    
    try {
      const params = new URLSearchParams();
      if (options.projectId) {
        params.append('project_id', options.projectId);
      }
      
      const response = await fetch(`/api/v1/dashboard?${params}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      
      const result = await response.json();
      
      if (result.success) {
        setData(result.data);
      } else {
        setError(result.message);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    
    if (options.refreshInterval) {
      const interval = setInterval(fetchData, options.refreshInterval);
      return () => clearInterval(interval);
    }
  }, [options.projectId]);

  return { data, loading, error, refetch: fetchData };
};
```

### 🎣 **Hook Patterns**

#### **Permission Hook**
```typescript
export const usePermissions = () => {
  const { user } = useAuth();
  const [permissions, setPermissions] = useState(null);

  const hasPermission = (resource: string, action: string): boolean => {
    if (!permissions || !permissions[resource]) {
      return false;
    }
    return permissions[resource].includes(action);
  };

  const canAccessWidget = (widgetCode: string): boolean => {
    // Implementation
    return true;
  };

  return {
    permissions,
    hasPermission,
    canAccessWidget,
    isLoading: !permissions
  };
};
```

---

## 🔄 **REAL-TIME FEATURES**

### 📡 **WebSocket Implementation**

#### **Backend WebSocket Handler**
```php
<?php

namespace App\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class DashboardWebSocketHandler implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        
        switch ($data['type']) {
            case 'subscribe':
                $this->handleSubscription($from, $data);
                break;
            case 'unsubscribe':
                $this->handleUnsubscription($from, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->removeUserConnection($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcastToUser($userId, $message)
    {
        if (isset($this->userConnections[$userId])) {
            foreach ($this->userConnections[$userId] as $conn) {
                $conn->send(json_encode($message));
            }
        }
    }
}
```

#### **Frontend WebSocket Client**
```typescript
class WebSocketClient {
  private ws: WebSocket | null = null;
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 5;
  private reconnectInterval = 1000;

  connect(token: string): void {
    try {
      this.ws = new WebSocket(`ws://localhost:8080?token=${token}`);
      
      this.ws.onopen = () => {
        console.log('WebSocket connected');
        this.reconnectAttempts = 0;
      };
      
      this.ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        this.handleMessage(data);
      };
      
      this.ws.onclose = () => {
        console.log('WebSocket disconnected');
        this.handleReconnect();
      };
      
      this.ws.onerror = (error) => {
        console.error('WebSocket error:', error);
      };
    } catch (error) {
      console.error('Failed to connect WebSocket:', error);
    }
  }

  private handleMessage(data: any): void {
    switch (data.type) {
      case 'dashboard_update':
        this.handleDashboardUpdate(data.data);
        break;
      case 'alert':
        this.handleAlert(data.data);
        break;
      case 'metric_update':
        this.handleMetricUpdate(data.data);
        break;
    }
  }

  private handleReconnect(): void {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      setTimeout(() => {
        this.connect(localStorage.getItem('token') || '');
      }, this.reconnectInterval * this.reconnectAttempts);
    }
  }

  subscribe(channel: string): void {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({
        type: 'subscribe',
        channel: channel
      }));
    }
  }

  unsubscribe(channel: string): void {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({
        type: 'unsubscribe',
        channel: channel
      }));
    }
  }
}
```

---

## 🧪 **TESTING**

### 🔧 **Backend Testing**

#### **Service Test Example**
```php
<?php

namespace Tests\Unit\Dashboard;

use Tests\TestCase;
use App\Services\DashboardService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $dashboardService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->dashboardService = new DashboardService();
        
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'project_manager',
            'tenant_id' => 'tenant-1'
        ]);
    }

    /** @test */
    public function it_can_get_user_dashboard()
    {
        $dashboard = $this->dashboardService->getUserDashboard($this->user);

        $this->assertNotNull($dashboard);
        $this->assertArrayHasKey('id', $dashboard);
        $this->assertArrayHasKey('layout', $dashboard);
        $this->assertArrayHasKey('preferences', $dashboard);
    }

    /** @test */
    public function it_creates_default_dashboard_when_none_exists()
    {
        $dashboard = $this->dashboardService->getUserDashboard($this->user);

        $this->assertTrue($dashboard['is_default']);
        $this->assertIsArray($dashboard['layout']);
    }
}
```

### 🎨 **Frontend Testing**

#### **Component Test Example**
```typescript
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { ChakraProvider } from '@chakra-ui/react';
import RoleBasedDashboard from '../RoleBasedDashboard';

const TestWrapper: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <ChakraProvider>
    {children}
  </ChakraProvider>
);

describe('RoleBasedDashboard', () => {
  beforeEach(() => {
    // Mock fetch
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: {
          dashboard: { id: '1', name: 'Test Dashboard', layout: [] },
          widgets: [],
          metrics: [],
          alerts: [],
          permissions: {},
          role_config: {}
        }
      })
    });
  });

  it('renders dashboard correctly', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    expect(screen.getByText('Test Dashboard')).toBeInTheDocument();
  });

  it('handles refresh button click', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    const refreshButton = screen.getByLabelText('Refresh Dashboard');
    fireEvent.click(refreshButton);

    expect(global.fetch).toHaveBeenCalledWith(
      '/api/v1/dashboard/role-based',
      expect.objectContaining({
        headers: expect.objectContaining({
          'Authorization': 'Bearer mock-token'
        })
      })
    );
  });
});
```

---

## 🚀 **DEPLOYMENT**

### 🐳 **Docker Configuration**

#### **Dockerfile**
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
    unzip

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

# Set permissions
RUN chown -R www-data:www-data /var/www
RUN chmod -R 755 /var/www

# Expose port
EXPOSE 9000

CMD ["php-fpm"]
```

#### **docker-compose.yml**
```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: zenamanage-app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./:/var/www
    networks:
      - zenamanage-network

  nginx:
    image: nginx:alpine
    container_name: zenamanage-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www
      - ./docker/nginx:/etc/nginx/conf.d
    depends_on:
      - app
    networks:
      - zenamanage-network

  db:
    image: mysql:8.0
    container_name: zenamanage-db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: zenamanage
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_USER: zenamanage_user
      MYSQL_PASSWORD: zenamanage_password
    volumes:
      - db_data:/var/lib/mysql
    ports:
      - "3306:3306"
    networks:
      - zenamanage-network

  redis:
    image: redis:alpine
    container_name: zenamanage-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    networks:
      - zenamanage-network

volumes:
  db_data:

networks:
  zenamanage-network:
    driver: bridge
```

### ⚙️ **Environment Configuration**

#### **.env.production**
```env
APP_NAME="ZenaManage Dashboard"
APP_ENV=production
APP_KEY=base64:your-app-key
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=zenamanage
DB_USERNAME=zenamanage_user
DB_PASSWORD=zenamanage_password

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls

WEBSOCKET_HOST=0.0.0.0
WEBSOCKET_PORT=8080
```

---

## 📊 **MONITORING & LOGGING**

### 📈 **Performance Monitoring**

#### **Laravel Telescope Integration**
```php
// config/telescope.php
return [
    'enabled' => env('TELESCOPE_ENABLED', true),
    'domain' => env('TELESCOPE_DOMAIN'),
    'path' => env('TELESCOPE_PATH', 'telescope'),
    'driver' => env('TELESCOPE_DRIVER', 'database'),
    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],
];
```

#### **Custom Metrics Collection**
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MetricsService
{
    public function collectDashboardMetrics(): array
    {
        return [
            'total_dashboards' => UserDashboard::count(),
            'active_widgets' => DashboardWidget::where('is_active', true)->count(),
            'total_alerts' => DashboardAlert::count(),
            'unread_alerts' => DashboardAlert::where('is_read', false)->count(),
            'avg_response_time' => $this->getAverageResponseTime(),
            'memory_usage' => memory_get_usage(true),
            'database_connections' => DB::getConnections(),
        ];
    }

    private function getAverageResponseTime(): float
    {
        // Implementation to calculate average response time
        return 0.0;
    }
}
```

### 📝 **Logging Configuration**

#### **Custom Log Channel**
```php
// config/logging.php
'channels' => [
    'dashboard' => [
        'driver' => 'daily',
        'path' => storage_path('logs/dashboard.log'),
        'level' => 'debug',
        'days' => 14,
    ],
    'websocket' => [
        'driver' => 'daily',
        'path' => storage_path('logs/websocket.log'),
        'level' => 'debug',
        'days' => 7,
    ],
],
```

#### **Log Usage Example**
```php
use Illuminate\Support\Facades\Log;

// In your service
Log::channel('dashboard')->info('Dashboard loaded', [
    'user_id' => $user->id,
    'dashboard_id' => $dashboard->id,
    'load_time' => $loadTime
]);

Log::channel('websocket')->error('WebSocket connection failed', [
    'user_id' => $userId,
    'error' => $exception->getMessage()
]);
```

---

## 🔒 **SECURITY BEST PRACTICES**

### 🛡️ **Input Validation**

#### **Request Validation**
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'widget_id' => 'required|string|exists:dashboard_widgets,id',
            'config' => 'required|array',
            'config.title' => 'required|string|max:255',
            'config.size' => 'required|in:small,medium,large,extra-large',
        ];
    }

    public function messages(): array
    {
        return [
            'widget_id.required' => 'Widget ID is required',
            'widget_id.exists' => 'Widget not found',
            'config.title.required' => 'Widget title is required',
            'config.size.in' => 'Invalid widget size',
        ];
    }
}
```

### 🔐 **Permission Validation**

#### **Service-Level Permissions**
```php
<?php

namespace App\Services;

class DashboardService
{
    public function addWidget(User $user, string $widgetId, array $config): array
    {
        // Validate widget exists and user has permission
        $widget = DashboardWidget::find($widgetId);
        
        if (!$widget) {
            throw new \Exception('Widget not found');
        }
        
        if (!$this->userCanAccessWidget($user, $widget)) {
            throw new \Exception('User does not have permission to access this widget');
        }
        
        // Proceed with widget addition
        return $this->performAddWidget($user, $widget, $config);
    }

    private function userCanAccessWidget(User $user, DashboardWidget $widget): bool
    {
        $permissions = json_decode($widget->permissions, true) ?? [];
        
        if (empty($permissions)) {
            return true; // No restrictions
        }
        
        return in_array($user->role, $permissions);
    }
}
```

---

## 📚 **INTEGRATION GUIDES**

### 🔌 **Third-Party Integrations**

#### **Slack Integration**
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SlackNotificationService
{
    private string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.slack.webhook_url');
    }

    public function sendDashboardAlert(DashboardAlert $alert): void
    {
        $message = [
            'text' => "Dashboard Alert: {$alert->message}",
            'attachments' => [
                [
                    'color' => $this->getSeverityColor($alert->severity),
                    'fields' => [
                        [
                            'title' => 'Type',
                            'value' => $alert->type,
                            'short' => true
                        ],
                        [
                            'title' => 'Severity',
                            'value' => $alert->severity,
                            'short' => true
                        ],
                        [
                            'title' => 'Time',
                            'value' => $alert->triggered_at->format('Y-m-d H:i:s'),
                            'short' => false
                        ]
                    ]
                ]
            ]
        ];

        Http::post($this->webhookUrl, $message);
    }

    private function getSeverityColor(string $severity): string
    {
        return match($severity) {
            'low' => 'good',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
            default => 'good'
        };
    }
}
```

#### **Email Integration**
```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DashboardAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DashboardAlert $alert
    ) {}

    public function build()
    {
        return $this->subject("Dashboard Alert: {$this->alert->message}")
                    ->view('emails.dashboard-alert')
                    ->with([
                        'alert' => $this->alert,
                        'user' => $this->alert->user
                    ]);
    }
}
```

---

## 📞 **SUPPORT & RESOURCES**

### 🆘 **Developer Support**
- **Technical Documentation**: This document
- **API Reference**: /docs/api
- **Code Examples**: /examples
- **GitHub Repository**: github.com/zenamanage/dashboard
- **Issue Tracker**: github.com/zenamanage/dashboard/issues

### 📚 **Additional Resources**
- **Laravel Documentation**: laravel.com/docs
- **React Documentation**: reactjs.org/docs
- **Chakra UI Documentation**: chakra-ui.com/docs
- **WebSocket Documentation**: developer.mozilla.org/en-US/docs/Web/API/WebSocket

### 🎓 **Learning Resources**
- **Video Tutorials**: youtube.com/zenamanage
- **Blog Posts**: blog.zenamanage.com
- **Community Forum**: community.zenamanage.com
- **Developer Workshops**: workshops.zenamanage.com
