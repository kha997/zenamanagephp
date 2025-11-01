# 🚀 Quick Start: Self-Hosted Runner

## 📋 **Tóm Tắt**

Setup self-hosted runner trong **5 phút** để chạy E2E tests miễn phí!

---

## ✅ **Bước 1: Chạy Setup Script**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage
./scripts/setup-self-hosted-runner.sh
```

Script sẽ tự động download và extract runner.

---

## ✅ **Bước 2: Lấy Token từ GitHub**

1. Mở browser: 
   ```
   https://github.com/kha997/zenamanagephp/settings/actions/runners/new
   ```

2. Chọn:
   - **Runner type**: Self-hosted
   - **Operating system**: macOS

3. Copy **registration token** (hiển thị sau khi chọn)

---

## ✅ **Bước 3: Configure Runner**

```bash
cd ./actions-runner
./config.sh --url https://github.com/kha997/zenamanagephp --token PASTE_TOKEN_HERE --name zenamanage-e2e-runner --work ../_work --labels e2e,self-hosted,macos
```

Thay `PASTE_TOKEN_HERE` bằng token từ bước 2.

---

## ✅ **Bước 4: Start Runner**

### **Option A: Manual (Development)**
```bash
./run.sh
```

Giữ terminal mở. Press `Ctrl+C` để stop.

### **Option B: Background Service (Recommended)**
```bash
sudo ./svc.sh install
sudo ./svc.sh start
```

Runner sẽ chạy tự động mỗi khi máy boot.

---

## ✅ **Bước 5: Verify**

1. Check GitHub:
   - Vào: https://github.com/kha997/zenamanagephp/settings/actions/runners
   - Should see: `zenamanage-e2e-runner` với status **"Online"** (màu xanh)

2. Trigger workflow:
   - Vào: https://github.com/kha997/zenamanagephp/actions/workflows/e2e-smoke-debug.yml
   - Click **"Run workflow"**
   - Select runner: **self-hosted** (hoặc để default)
   - Click **"Run workflow"**

3. Monitor:
   ```bash
   cd ./actions-runner
   tail -f _diag/Runner_*.log
   ```

---

## ⚠️ **MySQL Setup (Optional)**

Nếu máy có MySQL:
- Workflow sẽ tự động detect và dùng MySQL
- Nếu không có: sẽ tự động dùng SQLite

**Check MySQL:**
```bash
mysql --version
# hoặc
brew services list | grep mysql
```

**Nếu cần cài MySQL:**
```bash
brew install mysql
brew services start mysql
```

---

## 🔧 **Troubleshooting**

### **Runner không online?**
```bash
cd ./actions-runner
./run.sh status
cat _diag/Runner_*.log | tail -50
```

### **Jobs không chạy?**
- Check runner có label `self-hosted` không
- Check workflow có `runs-on: self-hosted` không
- Check runner có online không (GitHub Settings)

### **Permission errors?**
```bash
chmod +x ./run.sh
chmod +x ./config.sh
```

---

## ✅ **Done!**

Sau khi setup, workflow sẽ:
- ✅ Chạy trên máy local (free!)
- ✅ Không tốn GitHub Actions minutes
- ✅ Chạy nhanh hơn (no queue wait)

**Xem hướng dẫn chi tiết:** `SELF_HOSTED_RUNNER_SETUP_GUIDE.md`

