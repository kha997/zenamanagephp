# 🧪 TEST INVITATION TOKEN

**Ngày**: 2025-01-19
**Mục đích**: Test `invitations/accept.blade.php` với layout mới (`auth-layout.blade.php`)

---

## 📋 TEST INVITATION INFO

### Invitation Details:
- **Email**: `test-invitation@example.com`
- **Name**: Test User
- **Role**: member
- **Status**: pending
- **Expires**: 7 days from creation

### Test URLs:
```
http://localhost:8000/invitations/accept/{TOKEN}
http://localhost:8000/app/invitations/accept/{TOKEN}
```

### Token:
*(Will be generated when you run the command)*

---

## 🔧 CREATE TEST INVITATION

Run this command to create a test invitation:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage
php artisan tinker --execute="
\$user = DB::table('users')->first();
\$tenant = DB::table('tenants')->first();

\$invitation = \App\Models\Invitation::create([
    'email' => 'test-invitation@example.com',
    'first_name' => 'Test',
    'last_name' => 'User',
    'role' => 'member',
    'organization_id' => \$tenant->id ?? 1,
    'invited_by' => \$user->id ?? 1,
    'status' => 'pending',
    'expires_at' => now()->addDays(7),
    'message' => 'Test invitation for layout testing',
]);

echo '✅ Test Invitation Created!' . PHP_EOL;
echo '📧 Email: ' . \$invitation->email . PHP_EOL;
echo '🔑 Token: ' . \$invitation->token . PHP_EOL;
echo '🔗 URL: http://localhost:8000/invitations/accept/' . \$invitation->token . PHP_EOL;
"
```

---

## ✅ TEST CHECKLIST

Khi test invitation accept page, verify:

- [ ] Page loads without errors
- [ ] Layout uses `auth-layout.blade.php`
- [ ] Font Awesome icons display correctly
- [ ] Tailwind styles are applied
- [ ] Alpine.js works (form interactions)
- [ ] CSRF token is present
- [ ] Form fields are visible and functional
- [ ] Submit button works
- [ ] No console errors
- [ ] Responsive design works

---

## 🐛 EXPECTED BEHAVIOR

1. **Page Load**: Should show invitation acceptance form
2. **Styling**: Should match auth pages (login/register)
3. **Icons**: Font Awesome icons should display
4. **Form**: Should be functional with Alpine.js
5. **Submission**: Should create user account on successful submit

---

## 📝 NOTES

- Invitation expires in 7 days
- Status is `pending` (can be accepted)
- Organization is set to first tenant
- Invited by is set to first user in tenant

---

**Status**: ⏳ **READY FOR TESTING**

