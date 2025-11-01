# 🔧 FIX - PHP Version Mismatch

## 🔴 ISSUE
**Error**: `Your Composer dependencies require a PHP version ">= 8.2.0"`
**Current**: XAMPP using PHP 8.0.28
**Required**: PHP >= 8.2.0

## ✅ SOLUTION OPTIONS

### Option 1: Upgrade XAMPP PHP (RECOMMENDED)

**Steps:**

1. Download PHP 8.2 for XAMPP:
```bash
# Use Homebrew to install PHP 8.2
brew install php@8.2
```

2. Stop Apache from XAMPP Control Panel

3. Link new PHP version:
```bash
sudo ln -sf /opt/homebrew/bin/php /Applications/XAMPP/xamppfiles/bin/php-8.2
```

4. Restart Apache

---

### Option 2: Use Existing PHP 8.2.29 (CURRENT CLI)

CLI already has PHP 8.2.29. Update XAMPP to use it.

**Steps:**

1. Find PHP 8.2 location:
```bash
which php
# Output: /opt/homebrew/bin/php (or similar)
```

2. Create symlink in XAMPP:
```bash
sudo ln -s /opt/homebrew/bin/php /Applications/XAMPP/xamppfiles/bin/php-8.2
sudo ln -sf php-8.2 /Applications/XAMPP/xamppfiles/bin/php
```

3. For Apache/FPM:
```bash
# If using PHP-FPM
sudo ln -s /opt/homebrew/bin/php-fpm /Applications/XAMPP/xamppfiles/bin/php-fpm-8.2
```

4. Restart Apache

---

### Option 3: Downgrade Laravel Requirements (NOT RECOMMENDED)

Only if you can't upgrade PHP:

**Update `composer.json`:**
```json
"require": {
    "php": "^8.0"
}
```

Then:
```bash
composer update
```

**Warning**: May break some features requiring PHP 8.2+

---

## 🚀 QUICK COMMAND

**Check current setup:**
```bash
# CLI PHP
php -v

# XAMPP PHP
/Applications/XAMPP/xamppfiles/bin/php -v

# Check which one is used
which php
```

---

## ✅ VERIFICATION

After fix, verify:
```bash
curl -I https://manager.zena.com.vn/login
# Should return: HTTP/1.1 200 OK (not 500)
```

---

## 🎯 RECOMMENDED ACTION

**For now, test with localhost instead:**
```bash
# Use localhost (works with CLI PHP 8.2.29)
http://127.0.0.1:8000/login
```

Then fix XAMPP PHP version when you have time.

