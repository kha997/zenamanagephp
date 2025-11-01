# 🚀 Self-Hosted Runner Setup - Next Steps

## ✅ **Step 1 Complete: Runner Downloaded**

Runner đã được download và extract thành công vào `./actions-runner/`

---

## 📋 **Step 2: Get Registration Token from GitHub**

### **Option A: Via Web Browser**

1. Mở browser và đi tới:
   ```
   https://github.com/kha997/zenamanagephp/settings/actions/runners/new
   ```

2. Chọn:
   - **Runner type**: **Self-hosted**
   - **Operating system**: **macOS**

3. Copy **registration token** (sẽ hiển thị trên màn hình)

### **Option B: Via GitHub CLI** (if you have `gh` installed)

```bash
gh auth login  # If not already logged in
gh api repos/kha997/zenamanagephp/actions/runners/registration-token --jq .token
```

---

## ⚙️ **Step 3: Configure Runner**

Sau khi có token, chạy lệnh sau:

```bash
cd ./actions-runner
./config.sh --url https://github.com/kha997/zenamanagephp --token YOUR_TOKEN_HERE --name zenamanage-e2e-runner --work ../_work --labels e2e,self-hosted,macos
```

**Lưu ý**: Thay `YOUR_TOKEN_HERE` bằng token từ Step 2

---

## ▶️ **Step 4: Start Runner**

### **Option A: Manual Start (Development/Testing)**

```bash
cd ./actions-runner
./run.sh
```

Runner sẽ chạy trong foreground. Press `Ctrl+C` để stop.

### **Option B: Background Service (Recommended for Production)**

```bash
cd ./actions-runner
sudo ./svc.sh install
sudo ./svc.sh start
```

Runner sẽ tự động start mỗi khi máy boot.

**Check status:**
```bash
sudo ./svc.sh status
```

**View logs:**
```bash
tail -f _diag/Runner_*.log
```

---

## ✅ **Step 5: Verify Runner is Online**

1. Check trên GitHub:
   - Vào: https://github.com/kha997/zenamanagephp/settings/actions/runners
   - Should see: `zenamanage-e2e-runner` với status **"Online"** (màu xanh lá)

2. Test bằng cách trigger workflow:
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

## 🔧 **Troubleshooting**

### **Runner không online?**
```bash
cd ./actions-runner
./run.sh status
cat _diag/Runner_*.log | tail -50
```

### **Permission errors?**
```bash
chmod +x ./run.sh
chmod +x ./config.sh
chmod +x ./svc.sh
```

### **Token expired?**
- Tokens expire sau 1 hour
- Get new token từ GitHub và run `./config.sh` lại

---

## 📝 **Quick Reference Commands**

```bash
# Navigate to runner directory
cd ./actions-runner

# Configure (first time)
./config.sh --url https://github.com/kha997/zenamanagephp --token YOUR_TOKEN --name zenamanage-e2e-runner --work ../_work --labels e2e,self-hosted,macos

# Start manually
./run.sh

# Install as service
sudo ./svc.sh install
sudo ./svc.sh start

# Check status
sudo ./svc.sh status

# Stop
./run.sh stop  # Manual
sudo ./svc.sh stop  # Service

# Remove (cleanup)
./run.sh stop
cd ..
rm -rf ./actions-runner
```

---

## 🎯 **Next Steps After Setup**

1. ✅ Runner online
2. ✅ Trigger workflow để test
3. ✅ Monitor logs
4. ✅ Verify tests run successfully

**Happy testing! 🚀**

