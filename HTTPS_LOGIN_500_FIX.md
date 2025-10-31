# 🔧 FIX 500 ERROR - HTTPS Login Page

## 🔴 PROBLEM
- Error: 500 Internal Server Error
- URL: `https://manager.zena.com.vn/login`
- Root Cause: Mixed Content Issue (HTTPS page loading HTTP assets)

## 🔍 DIAGNOSIS

When accessing via HTTPS domain:
- Page is served over HTTPS ✅
- But assets are loaded from `http://localhost:3000/` ❌
- Browser blocks mixed content (HTTPS + HTTP)

**Evidence:**
```html
<script type="module" src="http://localhost:3000/@vite/client"></script>
```

## ✅ SOLUTION

### Option 1: Use Production Builds (RECOMMENDED)

The build files already exist in `public/build/`. Just need to configure Laravel to use them.

**Steps:**

1. **Build latest assets:**
```bash
npm run build
```

2. **Verify build:**
```bash
ls -la public/build/assets/
```

3. **Clear Laravel cache:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

4. **Test:**
- Visit: `https://manager.zena.com.vn/login`
- Should work without 500 error

---

### Option 2: Configure Vite for HTTPS

If you need dev server in production:

1. **Generate SSL cert for localhost:**
```bash
mkcert localhost 127.0.0.1
```

2. **Update vite.config.mjs:**
```javascript
export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 3000,
        https: {
            key: '/path/to/localhost-key.pem',
            cert: '/path/to/localhost.pem',
        },
        hmr: {
            protocol: 'wss',
            host: 'manager.zena.com.vn',
            port: 3000
        },
    },
});
```

3. **Restart Vite:**
```bash
npm run dev
```

---

## 🎯 QUICK FIX (NOW)

Since build files exist, try this first:

```bash
# Step 1: Build fresh
npm run build

# Step 2: Clear cache  
php artisan config:clear
php artisan view:clear

# Step 3: Test
curl -I https://manager.zena.com.vn/login
```

---

## 📊 WHAT'S HAPPENING

**Development Mode (localhost:8000):**
- Uses Vite dev server at `http://localhost:3000`
- Works fine because both HTTP

**Production Mode (manager.zena.com.vn):**
- Uses Vite dev server at `http://localhost:3000` ❌
- Fails because HTTPS + HTTP = Mixed Content
- Need to use built assets OR HTTPS Vite server

---

## ✅ EXPECTED RESULT

After fix:
- No 500 error
- Login page loads correctly
- Assets load from `https://manager.zena.com.vn/build/...`
- Or from `https://localhost:3000` if using HTTPS Vite

