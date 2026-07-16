# 📋 HƯỚNG DẪN SỬ DỤNG USER MANAGEMENT

## 🎯 **TỔNG QUAN**

Hệ thống User Management của ZENA Manage cung cấp đầy đủ các tính năng CRUD cho việc quản lý người dùng với:
- ✅ **Multi-tenancy**: Mỗi user thuộc về một tenant
- ✅ **JWT Authentication**: Xác thực bằng JWT tokens
- ✅ **RBAC**: Phân quyền dựa trên vai trò
- ✅ **Profile Management**: Quản lý thông tin cá nhân
- ✅ **Soft Deletes**: Xóa mềm với khôi phục
- ✅ **Audit Logging**: Ghi log các thay đổi

## 🏗️ **KIẾN TRÚC**

### **Models**
- `App\Models\User` - Model chính cho User
- `App\Models\Tenant` - Model cho Tenant (multi-tenancy)

### **Controllers**
- `App\Http\Controllers\UserController` - CRUD operations
- `Src\RBAC\Controllers\AuthController` - Authentication

### **Services**
- `Src\RBAC\Services\AuthService` - JWT authentication
- `Src\Foundation\Utils\JSendResponse` - API response format

## 📊 **DATABASE SCHEMA**

### **Users Table**
```sql
- id (ULID) - Primary key
- tenant_id (ULID) - Foreign key to tenants
- name (string) - Tên người dùng
- email (string) - Email (unique per tenant)
- password (string) - Mật khẩu đã hash
- phone (string, nullable) - Số điện thoại
- avatar_url (string, nullable) - URL avatar
- status (enum) - active, inactive, suspended
- last_login_at (timestamp, nullable)
- email_verified_at (timestamp, nullable)
- is_active (boolean) - Trạng thái hoạt động
- profile_data (json, nullable) - Dữ liệu profile bổ sung
- created_at, updated_at, deleted_at (timestamps)
```

### **Tenants Table**
```sql
- id (ULID) - Primary key
- name (string) - Tên công ty
- slug (string) - Slug unique
- domain (string) - Domain unique
- database_name (string, nullable)
- settings (json, nullable)
- status (enum) - active, inactive, suspended
- trial_ends_at (timestamp, nullable)
- is_active (boolean)
- created_at, updated_at, deleted_at (timestamps)
```

## 🔧 **CÁCH SỬ DỤNG**

### **1. Tạo Tenant và User**

```php
// Tạo Tenant
$tenant = \App\Models\Tenant::create([
    'name' => 'My Company',
    'domain' => 'mycompany.local',
    'status' => 'active'
]);

// Tạo User
$user = \App\Models\User::create([
    'name' => 'John Doe',
    'email' => 'john@mycompany.com',
    'password' => bcrypt('password123'),
    'tenant_id' => $tenant->id,
    'status' => 'active'
]);
```

### **2. Sử dụng User Model**

```php
// Kiểm tra trạng thái hoạt động
$isActive = $user->isActive(); // true/false

// Lấy thông tin profile
$phone = $user->getProfileData('phone', 'No phone');
$department = $user->getProfileData('department', 'No department');

// Cập nhật profile
$user->updateProfileData('phone', '0123456789');
$user->updateProfileData('department', 'IT');

// Relationships
$tenant = $user->tenant;
$systemRoles = $user->systemRoles;
$projectRoles = $user->projectRoles;

// Scopes
$activeUsers = \App\Models\User::active()->get();
$tenantUsers = \App\Models\User::forTenant($tenantId)->get();
```

### **3. API Endpoints**

#### **Authentication**
```bash
# Đăng nhập
POST /api/v1/auth/login
{
    "email": "john@mycompany.com",
    "password": "password123"
}

# Đăng ký
POST /api/v1/auth/register
{
    "name": "Jane Doe",
    "email": "jane@mycompany.com",
    "password": "password123",
    "password_confirmation": "password123",
    "tenant_id": "tenant_id_here"
}

# Lấy thông tin user hiện tại
GET /api/v1/auth/me
Authorization: Bearer {jwt_token}

# Đăng xuất
POST /api/v1/auth/logout
Authorization: Bearer {jwt_token}
```

#### **User Management**
```bash
# Lấy danh sách users
GET /api/v1/users
Authorization: Bearer {jwt_token}
Query params: ?search=john&status=active&sort_by=name&sort_order=asc&per_page=15

# Tạo user mới
POST /api/v1/users
Authorization: Bearer {jwt_token}
{
    "name": "New User",
    "email": "newuser@mycompany.com",
    "password": "password123",
    "password_confirmation": "password123",
    "tenant_id": "tenant_id_here"
}

# Lấy thông tin user
GET /api/v1/users/{user_id}
Authorization: Bearer {jwt_token}

# Cập nhật user
PUT /api/v1/users/{user_id}
Authorization: Bearer {jwt_token}
{
    "name": "Updated Name",
    "email": "updated@mycompany.com",
    "status": "active"
}

# Xóa user
DELETE /api/v1/users/{user_id}
Authorization: Bearer {jwt_token}

# Lấy profile
GET /api/v1/users/profile
Authorization: Bearer {jwt_token}

# Cập nhật profile
PUT /api/v1/users/profile
Authorization: Bearer {jwt_token}
{
    "name": "My Name",
    "email": "myemail@mycompany.com",
    "current_password": "oldpassword",
    "password": "newpassword",
    "password_confirmation": "newpassword"
}
```

## 🔐 **PERMISSIONS & RBAC**

### **User Permissions**
- `user.view` - Xem danh sách users
- `user.create` - Tạo user mới
- `user.update` - Cập nhật user
- `user.delete` - Xóa user

### **RBAC Middleware**
```php
// Trong routes
Route::middleware(['rbac:user.view'])->get('/users', [UserController::class, 'index']);
Route::middleware(['rbac:user.create'])->post('/users', [UserController::class, 'store']);
Route::middleware(['rbac:user.update'])->put('/users/{id}', [UserController::class, 'update']);
Route::middleware(['rbac:user.delete'])->delete('/users/{id}', [UserController::class, 'destroy']);
```

## 📝 **VÍ DỤ SỬ DỤNG**

### **1. Tạo User với Profile Data**

```php
$user = \App\Models\User::create([
    'name' => 'John Doe',
    'email' => 'john@company.com',
    'password' => bcrypt('password123'),
    'tenant_id' => $tenant->id,
    'status' => 'active'
]);

// Thêm thông tin profile
$user->updateProfileData('phone', '0123456789');
$user->updateProfileData('department', 'IT');
$user->updateProfileData('position', 'Developer');
$user->updateProfileData('hire_date', '2024-01-01');
```

### **2. Tìm kiếm và Lọc Users**

```php
// Tìm kiếm theo tên hoặc email
$users = \App\Models\User::where(function($query) {
    $query->where('name', 'LIKE', '%john%')
          ->orWhere('email', 'LIKE', '%john%');
})->get();

// Lọc theo tenant
$tenantUsers = \App\Models\User::forTenant($tenantId)->get();

// Lọc theo trạng thái
$activeUsers = \App\Models\User::active()->get();
```

### **3. JWT Authentication**

```php
// Login và lấy token
$token = auth('api')->attempt([
    'email' => 'john@company.com',
    'password' => 'password123'
]);

// Lấy thông tin từ token
$payload = auth('api')->payload();
$userId = $payload->get('user_id');
$tenantId = $payload->get('tenant_id');
```

## 🚀 **DEMO SCRIPTS**

### **1. Test User Management**
```bash
php test_user_management.php
```

### **2. Test API Endpoints**
```bash
php test_user_api.php
```

## ⚠️ **LƯU Ý QUAN TRỌNG**

### **1. Multi-tenancy**
- Mỗi user phải thuộc về một tenant
- Không thể truy cập users của tenant khác
- Tenant ID được kiểm tra trong mọi operations

### **2. Security**
- Passwords được hash bằng bcrypt
- JWT tokens có thời hạn (TTL)
- RBAC middleware kiểm tra permissions
- Soft deletes để bảo vệ dữ liệu

### **3. Validation**
- Email phải unique trong tenant
- Password phải có ít nhất 8 ký tự
- Status phải là active, inactive, hoặc suspended
- Tenant ID phải tồn tại

## 🔧 **TROUBLESHOOTING**

### **1. Lỗi JWT**
- Kiểm tra JWT_SECRET trong .env
- Chạy `php artisan jwt:secret`
- Clear cache: `php artisan config:clear`

### **2. Lỗi Database**
- Chạy migrations: `php artisan migrate`
- Kiểm tra database connection
- Kiểm tra tenant_id foreign key

### **3. Lỗi Permissions**
- Kiểm tra RBAC middleware
- Kiểm tra user roles và permissions
- Kiểm tra tenant access

## 📚 **TÀI LIỆU THAM KHẢO**

- [Laravel Authentication](https://laravel.com/docs/authentication)
- [JWT Auth Package](https://github.com/tymon/jwt-auth)
- [Laravel Multi-tenancy](https://laravel.com/docs/tenancy)
- [RBAC Implementation](https://laravel.com/docs/authorization)

---

**🎉 Chúc bạn sử dụng thành công hệ thống User Management!**
