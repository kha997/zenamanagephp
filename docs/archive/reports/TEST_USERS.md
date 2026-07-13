# 🧪 Test Users cho Sidebar Customizer

## 📋 Danh sách Test Users

Tất cả users đều có **password**: `zena1234`

| Role | Email | Password | Mô tả |
|------|-------|----------|-------|
| **Super Admin** | `superadmin@zena.com` | `zena1234` | Full system access, có thể quản lý sidebar |
| **Admin** | `admin@zena.com` | `zena1234` | System management |
| **Project Manager** | `pm@zena.com` | `zena1234` | Project management |
| **Designer** | `designer@zena.com` | `zena1234` | Design và creative work |
| **Site Engineer** | `site@zena.com` | `zena1234` | On-site engineering |
| **QC Engineer** | `qc@zena.com` | `zena1234` | Quality control |
| **Procurement** | `procurement@zena.com` | `zena1234` | Material và vendor management |
| **Finance Manager** | `finance@zena.com` | `zena1234` | Financial management |
| **Client** | `client@zena.com` | `zena1234` | Project stakeholder |

## 🌐 Links Test

**Main Application**: http://localhost:8001

### 🔐 Login Page
- **URL**: http://localhost:8001/login
- **Test với**: Bất kỳ email nào ở trên + password `zena1234`

### 🏠 Dashboard
- **URL**: http://localhost:8001/dashboard
- **Sau khi login**: Sẽ thấy sidebar mới với dynamic configuration

### ⚙️ Admin Sidebar Builder (Chỉ Super Admin)
- **URL**: http://localhost:8001/admin/sidebar-builder
- **Login với**: `superadmin@zena.com` / `zena1234`

#### Admin Features:
- **Edit Sidebar**: http://localhost:8001/admin/sidebar-builder/project_manager
- **Preview Sidebar**: http://localhost:8001/admin/sidebar-builder/project_manager/preview
- **Clone Config**: Copy từ role này sang role khác
- **Reset to Default**: Reset về default configuration
- **Export/Import**: JSON backup và migrate
- **Apply Presets**: Apply predefined configurations

## 🧪 Test Cases

### 1. **Test Sidebar Rendering**
1. Login với các role khác nhau
2. Kiểm tra sidebar hiển thị đúng items cho từng role
3. Verify permission filtering hoạt động

### 2. **Test Admin Features (Super Admin only)**
1. Login với `superadmin@zena.com`
2. Truy cập `/admin/sidebar-builder`
3. Test các tính năng:
   - ✅ Edit sidebar cho từng role
   - ✅ Clone configuration
   - ✅ Reset to default
   - ✅ Export/Import JSON
   - ✅ Apply presets
   - ✅ Preview sidebar

### 3. **Test User Preferences**
1. Login với user thường (ví dụ: `pm@zena.com`)
2. Hover over sidebar items để thấy pin buttons
3. Test pin/unpin functionality
4. Test hide/show items
5. Test theme preferences

### 4. **Test API Endpoints**
```bash
# Test với curl (cần authentication token)
curl -X GET http://localhost:8001/api/admin/sidebar-configs/role/project_manager \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"

curl -X GET http://localhost:8001/api/user-preferences \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 🎯 Expected Results

### **Super Admin** (`superadmin@zena.com`)
- ✅ Access to Admin Sidebar Builder
- ✅ Can manage sidebar configurations
- ✅ Can clone, reset, export, import
- ✅ Can apply presets
- ✅ Full sidebar access

### **Project Manager** (`pm@zena.com`)
- ✅ Project management sidebar
- ✅ Can pin/unpin items
- ✅ Can hide/show items
- ✅ Can set custom order
- ❌ No access to Admin Sidebar Builder

### **Designer** (`designer@zena.com`)
- ✅ Design-focused sidebar
- ✅ Creative tools access
- ✅ Can customize personal preferences
- ❌ No access to Admin Sidebar Builder

### **Other Roles**
- ✅ Role-specific sidebar configurations
- ✅ Permission-based item filtering
- ✅ Personal customization options
- ❌ No admin access

## 🔧 Troubleshooting

### **Login Issues**
- Đảm bảo server đang chạy: `php artisan serve --host=0.0.0.0 --port=8001`
- Check database connection
- Verify users exist: `php artisan tinker` → `User::all()`

### **Sidebar Not Loading**
- Check SidebarConfigSeeder đã chạy: `php artisan db:seed --class=SidebarConfigSeeder`
- Verify roles exist: `php artisan tinker` → `Role::all()`
- Check user-role assignments: `php artisan tinker` → `DB::table('user_roles')->get()`

### **Admin Access Denied**
- Chỉ Super Admin có thể access Admin Sidebar Builder
- Login với `superadmin@zena.com` / `zena1234`
- Check role assignment: `php artisan tinker` → `User::where('email', 'superadmin@zena.com')->first()->roles`

## 📊 Database Status

### **Tables Created**
- ✅ `users` - User accounts
- ✅ `roles` - Role definitions
- ✅ `user_roles` - User-role assignments
- ✅ `sidebar_configs` - Sidebar configurations
- ✅ `user_sidebar_preferences` - User preferences

### **Seeders Run**
- ✅ `SimpleRoleSeeder` - Created all roles
- ✅ `TestUsersSeeder` - Created test users with role assignments
- ✅ `SidebarConfigSeeder` - Created default sidebar configurations

## 🚀 Ready to Test!

Tất cả test users đã sẵn sàng để test Sidebar Customizer system. Bắt đầu với:

1. **Login**: http://localhost:8001/login
2. **Test với Super Admin**: `superadmin@zena.com` / `zena1234`
3. **Access Admin Panel**: http://localhost:8001/admin/sidebar-builder
4. **Test các role khác**: Login với các email khác để xem sidebar khác nhau

Happy Testing! 🎉
