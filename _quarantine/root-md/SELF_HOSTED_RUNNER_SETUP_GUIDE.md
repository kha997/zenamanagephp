# 🖥️ Self-Hosted Runner Setup Guide

## 📋 **Overview**

Thiết lập Self-Hosted Runner để chạy GitHub Actions workflows trên máy local/server riêng, tránh phí GitHub Actions minutes.

---

## ✅ **Lợi Ích**

- ✅ **Free** - Không tốn GitHub Actions minutes
- ✅ **Faster** - Chạy trên máy local, không cần wait queue
- ✅ **Control** - Full control over environment
- ✅ **Offline** - Có thể chạy khi không có internet (sau khi setup)

---

## 🚀 **Quick Setup**

### **Step 1: Chạy Setup Script**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage
./scripts/setup-self-hosted-runner.sh
```

Script sẽ:
- Download GitHub Actions Runner
- Extract vào `./actions-runner/`
- Hướng dẫn các bước tiếp theo

---

### **Step 2: Lấy Registration Token**

1. Vào GitHub repository:
   ```
   https://github.com/kha997/zenamanagephp/settings/actions/runners/new
   ```

2. Chọn:
   - Runner type: **Self-hosted**
   - Operating system: **macOS** (hoặc Linux/Windows tùy máy)

3. Copy **registration token** (sẽ expire sau 1 hour)

---

### **Step 3: Configure Runner**

```bash
cd ./actions-runner
./config.sh --url https://github.com/kha997/zenamanagephp --token YOUR_TOKEN --name zenamanage-e2e-runner --work ../_work --labels e2e,self-hosted,macos
```

**Options:**
- `--url`: Repository URL
- `--token`: Registration token từ GitHub
- `--name`: Tên runner (có thể đổi)
- `--work`: Work directory (nơi chứa code khi chạy)
- `--labels`: Tags cho runner (e2e, self-hosted, macos)

---

### **Step 4: Start Runner**

#### **Option A: Manual Start (Development)**
```bash
./run.sh
```

Runner sẽ chạy trong foreground. Press `Ctrl+C` để stop.

#### **Option B: Background Service (Recommended)**
```bash
# Install as service
sudo ./svc.sh install

# Start service
sudo ./svc.sh start

# Check status
sudo ./svc.sh status

# View logs
sudo ./svc.sh status
# Or: tail -f _diag/Runner_*.log
```

---

## 🔧 **Update Workflow**

Workflow đã được update để hỗ trợ self-hosted runner:

```yaml
jobs:
  debug:
    runs-on: ${{ github.event.inputs.runner || 'self-hosted' }}
```

**Nếu muốn force self-hosted:**
```yaml
jobs:
  debug:
    runs-on: self-hosted
```

---

## 📊 **Runner Management**

### **Check Runner Status**
```bash
cd ./actions-runner
./run.sh status
```

### **Stop Runner**
```bash
# Manual
./run.sh stop

# Service
sudo ./svc.sh stop
```

### **Remove Runner**
```bash
# Stop first
./run.sh stop

# Remove from GitHub
# Go to: https://github.com/kha997/zenamanagephp/settings/actions/runners
# Click "Remove" next to runner

# Delete local directory
cd ..
rm -rf ./actions-runner
```

### **View Logs**
```bash
cd ./actions-runner
tail -f _diag/Runner_*.log
```

---

## ⚙️ **Prerequisites**

### **macOS Requirements:**
- ✅ macOS 10.15+ (hoặc version tương thích)
- ✅ Git installed
- ✅ Node.js 18+ installed
- ✅ PHP 8.2+ installed
- ✅ MySQL/PostgreSQL (hoặc SQLite cho tests)
- ✅ Composer installed
- ✅ Docker (optional, nếu cần container services)

### **Check Installations:**
```bash
# Check versions
node --version    # Should be 18.x or higher
php --version    # Should be 8.2.x or higher
composer --version
git --version

# Install if missing
# Node.js: brew install node@18
# PHP: Already installed via XAMPP
# Composer: Already installed
```

---

## 🔍 **Troubleshooting**

### **Issue 1: Runner không start**

**Check:**
```bash
cd ./actions-runner
./run.sh status
cat _diag/Runner_*.log | tail -50
```

**Common causes:**
- Port conflicts
- Permission issues
- Missing dependencies

### **Issue 2: Jobs không chạy**

**Check:**
1. Runner có online không?
   - GitHub → Settings → Actions → Runners
   - Should show green "Online" status

2. Labels có match không?
   - Workflow có `runs-on: self-hosted`
   - Runner có label `self-hosted`

3. Permissions:
   - Runner có quyền read/write trong work directory
   - Check permissions: `ls -la _work/`

### **Issue 3: Tests fail với self-hosted**

**Check:**
- Environment variables
- Database connection
- Server ports (8000, 3306)
- File permissions

---

## 🎯 **Workflow Behavior với Self-Hosted**

### **Advantages:**
- ✅ Không tốn GitHub Actions minutes
- ✅ Chạy nhanh hơn (no queue wait)
- ✅ Có thể access local resources
- ✅ Full control over environment

### **Considerations:**
- ⚠️ Runner phải online để jobs chạy
- ⚠️ Tốn resources trên máy local
- ⚠️ Cần maintain runner manually
- ⚠️ Security: Runner có access to repository code

---

## 📝 **Workflow Updates**

Workflow file (`.github/workflows/e2e-smoke-debug.yml`) sẽ tự động:
- ✅ Detect self-hosted runner
- ✅ Use local services (MySQL có thể dùng local)
- ✅ Access local files
- ✅ Run faster vì không cần download dependencies mỗi lần

---

## ✅ **Verification**

### **1. Check Runner Online:**
```
GitHub → Repository → Settings → Actions → Runners
Should see: "zenamanage-e2e-runner" with green "Online" status
```

### **2. Trigger Workflow:**
```
GitHub → Repository → Actions → E2E Smoke Tests Debug → Run workflow
Select runner: "self-hosted" (if using input)
```

### **3. Monitor:**
```
cd ./actions-runner
tail -f _diag/Runner_*.log
```

---

## 🚨 **Important Notes**

1. **Security:**
   - Runner có full access to repository code
   - Don't run untrusted workflows
   - Keep runner updated

2. **Resources:**
   - Runner sẽ consume CPU/RAM khi chạy jobs
   - MySQL service sẽ chạy trong runner (nếu dùng services)

3. **Updates:**
   - Update runner regularly: `./run.sh update`
   - Check for security updates

---

## 📚 **References**

- [GitHub Actions Runner Docs](https://docs.github.com/en/actions/hosting-your-own-runners)
- [Runner Releases](https://github.com/actions/runner/releases)
- [Runner Configuration](https://docs.github.com/en/actions/hosting-your-own-runners/managing-self-hosted-runners)

---

**Setup script location:** `scripts/setup-self-hosted-runner.sh`

**After setup, workflow sẽ tự động chạy trên self-hosted runner!** 🎉

