# User Credentials for Testing

## ✅ Users đã được reset password về: `password`

### Main Test Users

| Email | Password | Name | Role | Tenant ID |
|-------|----------|------|------|-----------|
| **`superadmin@zena.com`** | **`password`** | Super Admin | super_admin | 01k964z50tmezcbshm5kcm8qhh |
| `admin@zena.com` | `password` | Admin User | admin | 01k964z50tmezcbshm5kcm8qhh |
| `pm@zena.com` | `password` | Project Manager | project_manager | 01k964z50tmezcbshm5kcm8qhh |
| `admin@zena.local` | `password` | Admin User | N/A | 01k964z50tmezcbshm5kcm8qhh |

### Other Users (Password: `zena1234` - từ seeder)

| Email | Password | Name | Role |
|-------|----------|------|------|
| `designer@zena.com` | `zena1234` | Designer | designer |
| `site@zena.com` | `zena1234` | Site Engineer | site_engineer |
| `qc@zena.com` | `zena1234` | QC Engineer | qc_engineer |
| `procurement@zena.com` | `zena1234` | Procurement | procurement |
| `finance@zena.com` | `zena1234` | Finance Manager | finance |
| `client@zena.com` | `zena1234` | Client User | client |

## 🔍 Test Login

### Recommended Test Account
```
Email: superadmin@zena.com
Password: password
```

### Login URL
```
http://localhost:8000/login
```

## 🔧 Fixed Issues

### API Routes Fix
- ✅ Fixed `/api/v1/auth/me` → `/api/auth/me` (route không có v1 prefix)
- ✅ Fixed `/api/v1/auth/permissions` → `/api/auth/permissions`
- ✅ Updated to use session auth with `withCredentials: true`

## 📝 Notes

- Tất cả users chính đã được reset password về `password` để dễ test
- Login sử dụng session-based auth với `X-Web-Login: true` header
- `/me` và `/permissions` endpoints sử dụng session auth (withCredentials)
- Nếu login không hoạt động, kiểm tra:
  1. Browser console cho errors
  2. Network tab cho API calls
  3. Laravel logs: `storage/logs/laravel.log`
