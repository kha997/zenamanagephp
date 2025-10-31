# 💳 GitHub Actions Billing Issue - Fix Guide

## 🔍 **Vấn Đề**

Workflow `php-unit-tests` và các jobs khác không chạy được vì:

```
❌ Error: The job was not started because recent account payments have failed 
or your spending limit needs to be increased. Please check the 'Billing & plans' 
section in your settings.
```

**Đây KHÔNG phải lỗi code**, mà là vấn đề billing/payment của GitHub Actions.

---

## ✅ **Giải Pháp**

### **Option 1: Fix Billing Issues (Recommended)**

1. **Vào GitHub Settings:**
   - Repository → Settings → Billing & plans
   - Hoặc: https://github.com/settings/billing

2. **Check Payment Status:**
   - Verify payment method có valid không
   - Check có failed payments không
   - Update payment method nếu cần

3. **Check Spending Limits:**
   - Verify spending limit đủ cao
   - Actions có thể có monthly limit (default: $0)
   - Increase limit nếu cần

4. **Fix Failed Payments:**
   - Update credit card nếu expired
   - Add backup payment method
   - Contact GitHub support nếu vẫn có vấn đề

---

### **Option 2: Use Self-Hosted Runner (Free)**

Nếu không muốn trả tiền cho GitHub Actions:

1. **Setup Self-Hosted Runner:**
   ```bash
   # On your local machine or server
   mkdir actions-runner && cd actions-runner
   curl -o actions-runner.tar.gz -L https://github.com/actions/runner/releases/download/v2.311.0/actions-runner-linux-x64-2.311.0.tar.gz
   tar xzf ./actions-runner.tar.gz
   ./config.sh --url https://github.com/kha997/zenamanagephp --token YOUR_TOKEN
   ./run.sh
   ```

2. **Update Workflow:**
   ```yaml
   jobs:
     debug:
       runs-on: self-hosted  # Thay vì ubuntu-latest
   ```

---

### **Option 3: Reduce Actions Usage**

Để giảm costs:

1. **Chỉ chạy workflow khi cần:**
   - Remove auto-trigger trên push
   - Chỉ dùng `workflow_dispatch` (manual trigger)

2. **Optimize workflow:**
   - Combine jobs nếu có thể
   - Cache dependencies tốt hơn
   - Reduce test scope

3. **Skip non-critical jobs:**
   - Chạy unit tests local trước
   - Chỉ chạy E2E tests trên CI

---

## 🔧 **Quick Fix Workflow**

Update workflow để skip khi billing issues:

```yaml
jobs:
  debug:
    runs-on: ubuntu-latest
    if: github.event_name == 'workflow_dispatch' || github.ref == 'refs/heads/main'
    # Chỉ chạy manual hoặc trên main branch
```

---

## 📋 **Checklist**

- [ ] Verify payment method trong GitHub Settings
- [ ] Check spending limits
- [ ] Fix failed payments
- [ ] Consider self-hosted runner nếu không muốn trả tiền
- [ ] Update workflow để reduce usage nếu cần

---

## 🚨 **Important Notes**

1. **GitHub Actions Free Tier:**
   - Public repos: Unlimited
   - Private repos: 2000 minutes/month free
   - Sau đó: $0.008/minute

2. **Matrix Jobs:**
   - Mỗi matrix job = 1 job riêng
   - `playwright-cross-browser` có thể có nhiều jobs

3. **Workflow Usage:**
   - Mỗi workflow run tốn minutes
   - Retries cũng tốn minutes

---

**Sau khi fix billing, workflow sẽ chạy bình thường!**

