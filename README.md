# ZenaManage - Multi-Tenant Project Management System

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-Passing-brightgreen.svg)](tests/)

A comprehensive multi-tenant project management system built with Laravel, designed for construction and engineering projects with advanced features including task management, document handling, calendar integration, and template systems.

## 📚 **DOCUMENTATION**

**👉 [COMPLETE SYSTEM DOCUMENTATION](COMPLETE_SYSTEM_DOCUMENTATION.md)** - Single source of truth for all system architecture, design principles, project rules, and technical implementation details.

This README provides quick start information. For comprehensive documentation including architecture, security, performance, and deployment guides, please refer to the complete documentation above.

## 🚀 **Quick Start**

### **Prerequisites**
- PHP 8.2 or higher
- Composer
- MySQL/PostgreSQL or SQLite
- Node.js & NPM (for frontend assets)

### **Installation**

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-org/zenamanage.git
   cd zenamanage
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Start development server**
   ```bash
   php artisan serve
   npm run dev
   ```

Visit `http://localhost:8000` to access the application.

## 📋 **Features**

### **Core Project Management**
- ✅ **Project CRUD Operations** - Complete project lifecycle management
- ✅ **Task Management** - Task creation, assignment, and tracking
- ✅ **Document Management** - File uploads, versioning, and sharing
- ✅ **Calendar Integration** - Project and task scheduling
- ✅ **Template System** - Reusable project and task templates
- ✅ **Analytics & Reporting** - Project insights and performance metrics

### **Multi-Tenant Architecture**
- ✅ **Tenant Isolation** - Complete data separation between tenants
- ✅ **Role-Based Access Control** - Granular permissions system
- ✅ **Authentication & Authorization** - Secure access management
- ✅ **Audit Logging** - Complete activity tracking

### **Performance & Scalability**
- ✅ **Database Optimization** - Efficient queries with proper indexing
- ✅ **Caching Strategy** - Redis-based caching for performance
- ✅ **API Rate Limiting** - Protection against abuse
- ✅ **N+1 Query Prevention** - Optimized relationship loading

### **Developer Experience**
- ✅ **Comprehensive Testing** - Unit, integration, and performance tests
- ✅ **Error Handling** - Centralized error management
- ✅ **API Documentation** - Complete API reference
- ✅ **Code Quality** - PSR-12 compliant code with policies

## 🏗️ **Architecture**

```
Frontend (Alpine.js + Tailwind CSS)
    ↓
Web Routes (Session Auth) + API Routes (Token Auth)
    ↓
Controllers (Web + API)
    ↓
Services (Business Logic)
    ↓
Models (Eloquent + Relationships)
    ↓
Database (MySQL/PostgreSQL + Migrations)
```

## 🔌 **API Endpoints**

### **Authentication**
```http
POST /api/auth/login
POST /api/auth/logout
POST /api/auth/refresh
```

### **Projects**
```http
GET    /api/v1/app/projects          # List projects
POST   /api/v1/app/projects          # Create project
GET    /api/v1/app/projects/{id}     # Get project
PATCH  /api/v1/app/projects/{id}     # Update project
DELETE /api/v1/app/projects/{id}      # Delete project
```

### **Tasks**
```http
GET    /api/v1/app/tasks             # List tasks
POST   /api/v1/app/tasks             # Create task
GET    /api/v1/app/tasks/{id}        # Get task
PATCH  /api/v1/app/tasks/{id}        # Update task
DELETE /api/v1/app/tasks/{id}        # Delete task
POST   /api/v1/app/tasks/{id}/assign # Assign task
```

### **Templates**
```http
GET    /api/v1/app/templates         # List templates
POST   /api/v1/app/templates         # Create template
GET    /api/v1/app/templates/{id}    # Get template
PATCH  /api/v1/app/templates/{id}    # Update template
DELETE /api/v1/app/templates/{id}     # Delete template
POST   /api/v1/app/templates/{id}/apply # Apply template
```

## 🗄️ **Database Schema**

### **Core Tables**
- `tenants` - Multi-tenant isolation
- `users` - User management with roles
- `projects` - Project entities with relationships
- `tasks` - Task management with assignments
- `documents` - File management with versioning
- `templates` - Reusable project/task templates
- `calendar_events` - Calendar integration

### **Key Relationships**
- Projects belong to tenants and have many tasks
- Tasks belong to projects and can be assigned to users
- Documents can be associated with projects or tasks
- Templates can be applied to create projects/tasks

## 🧪 **Testing**

### **Run Tests**
```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test tests/Unit/
php artisan test tests/Feature/

# Run with coverage
php artisan test --coverage
```

### **Test Coverage**
- ✅ **Unit Tests** - Models, policies, services
- ✅ **Integration Tests** - API endpoints, database interactions
- ✅ **Performance Tests** - Query optimization, N+1 prevention
- ✅ **Policy Tests** - Authorization and tenant isolation

## 📚 **Documentation**

- [Complete Documentation](./docs/COMPLETE_DOCUMENTATION.md)
- [API Reference](./docs/API_DOCUMENTATION.md)
- [Template Management System](./docs/TEMPLATE_MANAGEMENT_SYSTEM.md)
- [Database Schema](./docs/DATABASE_SCHEMA.md)
- [Deployment Guide](./docs/DEPLOYMENT.md)

## 🔧 **Configuration**

### **Environment Variables**
```env
# Application
APP_NAME=ZenaManage
APP_ENV=local
APP_DEBUG=true

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zenamanage
DB_USERNAME=root
DB_PASSWORD=

# Cache
CACHE_DRIVER=file
QUEUE_CONNECTION=sync

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:3000
```

### **Multi-Tenant Configuration**
```php
// config/tenancy.php
return [
    'tenant_model' => App\Models\Tenant::class,
    'user_model' => App\Models\User::class,
    'tenant_key' => 'tenant_id',
];
```

## 🚀 **Deployment**

### **Production Setup**
```bash
# Install production dependencies
composer install --optimize-autoloader --no-dev

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### **Docker Deployment**
```bash
# Build and run with Docker
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate
```

## 🤝 **Contributing**

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### **Code Standards**
- Follow PSR-12 coding standards
- Write tests for new features
- Update documentation as needed
- Ensure multi-tenant isolation

## 📊 **Performance**

### **Benchmarks**
- **API Response Time**: < 300ms (p95)
- **Page Load Time**: < 500ms (p95)
- **Database Queries**: Optimized with proper indexing
- **Memory Usage**: Efficient with caching

### **Optimization Features**
- Eager loading for relationships
- Database query optimization
- Redis caching for frequently accessed data
- CDN support for static assets

## 🔒 **Security**

### **Security Features**
- Multi-tenant data isolation
- Role-based access control
- CSRF protection for web routes
- Rate limiting for API endpoints
- Input validation and sanitization
- Audit logging for all actions

### **Authentication Methods**
- Session-based authentication (web)
- Token-based authentication (API)
- Laravel Sanctum integration
- Password hashing with bcrypt

## 📈 **Monitoring**

### **Health Checks**
```http
GET /health                    # Application health
GET /api/health/database       # Database health
GET /api/health/cache          # Cache health
```

### **Metrics**
- Response time tracking
- Error rate monitoring
- Database performance metrics
- User activity analytics

## 🆘 **Support**

### **Getting Help**
- 📖 Check the [documentation](./docs/)
- 🐛 Report bugs via [GitHub Issues](https://github.com/your-org/zenamanage/issues)
- 💬 Join our [Discord community](https://discord.gg/zenamanage)
- 📧 Email support: support@zenamanage.com

### **Troubleshooting**
- [Common Issues](./docs/TROUBLESHOOTING.md)
- [Performance Issues](./docs/PERFORMANCE.md)
- [Security Issues](./docs/SECURITY.md)

## 📄 **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 **Acknowledgments**

- Laravel framework and community
- Alpine.js and Tailwind CSS teams
- All contributors and testers
- Construction industry professionals who provided feedback

---

**Made with ❤️ by the ZenaManage Team**

[Website](https://zenamanage.com) • [Documentation](./docs/) • [API Reference](./docs/API_DOCUMENTATION.md) • [Support](https://github.com/your-org/zenamanage/issues)