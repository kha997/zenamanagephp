# 🔍 CI/CD Pipeline Monitoring Guide

**Workflow:** Automated Deployment  
**Trigger:** Pull Request or Push to `develop`  
**Target:** Staging Environment

---

## 📊 Pipeline Overview

### Workflow Steps

1. **Checkout Code** ✅
2. **Set up Docker Buildx** 🔄
3. **Log in to Container Registry** 🔄
4. **Extract metadata** 🔄
5. **Build and push Docker images** 🔄
6. **Deploy to staging server** 🔄
7. **Run health check** 🔄
8. **Run smoke tests** 🔄
9. **Notify deployment success** 🔄

---

## 🔗 Access Monitoring

### GitHub Actions
- **URL:** `https://github.com/[org]/zenamanage/actions`
- **Workflow:** "Automated Deployment"
- **Filter:** Select "feature/repo-cleanup" branch or PR

### Staging Environment
- **URL:** `https://staging.zenamanage.com`
- **Health Check:** `https://staging.zenamanage.com/health`
- **API Health:** `https://staging-api.zenamanage.com/health`

---

## ✅ What to Monitor

### 1. Build Status
- [ ] Docker images build successfully
- [ ] No build errors
- [ ] Images pushed to registry

### 2. Deployment Status
- [ ] Code pulled to staging server
- [ ] Docker containers started
- [ ] Migrations run successfully
- [ ] Cache optimized

### 3. Health Checks
- [ ] Health endpoint responds (200 OK)
- [ ] API health check passes
- [ ] WebSocket health check passes

### 4. Smoke Tests
- [ ] All smoke tests pass
- [ ] No failing tests
- [ ] Response times acceptable

---

## 🚨 Common Issues & Solutions

### Issue 1: Build Failure
**Symptoms:** Build step fails  
**Possible Causes:**
- Dockerfile issues
- Dependency conflicts
- Build timeout

**Solution:**
- Check build logs
- Verify Dockerfile.prod exists
- Check for dependency issues

### Issue 2: Deployment Failure
**Symptoms:** Deployment step fails  
**Possible Causes:**
- SSH connection issues
- Server out of space
- Permission issues

**Solution:**
- Verify SSH keys configured
- Check server disk space
- Verify server permissions

### Issue 3: Health Check Failure
**Symptoms:** Health check times out or returns error  
**Possible Causes:**
- Application not starting
- Database connection issues
- Configuration errors

**Solution:**
- Check application logs
- Verify database connectivity
- Check environment variables

### Issue 4: Smoke Test Failure
**Symptoms:** Smoke tests fail  
**Possible Causes:**
- API endpoints not responding
- Network issues
- Application errors

**Solution:**
- Check API logs
- Verify endpoints are accessible
- Check network connectivity

---

## 📝 Monitoring Checklist

### During Deployment
- [ ] Monitor GitHub Actions workflow
- [ ] Watch for any step failures
- [ ] Check error logs if failures occur
- [ ] Verify deployment logs on server

### After Deployment
- [ ] Verify health checks pass
- [ ] Test main routes manually
- [ ] Check browser console for errors
- [ ] Verify Navbar functionality
- [ ] Test RBAC for Admin link

---

## 🔔 Notifications

### Slack Notifications
- Deployment success: Sent to `#deployments` channel
- Deployment failure: Sent with error details
- Rollback: Sent if automatic rollback occurs

### Email Notifications
- Configured for critical failures
- Sent to deployment team

---

## 📊 Expected Timeline

**Typical Deployment:**
- Build: 5-10 minutes
- Deploy: 2-5 minutes
- Health checks: 1-2 minutes
- Total: ~10-15 minutes

---

## 🆘 If Deployment Fails

### Automatic Rollback
- GitHub Actions includes automatic rollback
- Triggers if health checks fail
- Restores previous version

### Manual Rollback
If needed:
1. SSH to staging server
2. Navigate to `/opt/zenamanage`
3. Run: `git reset --hard HEAD~1`
4. Restart containers: `docker-compose up -d --build`
5. Clear caches: `php artisan cache:clear`

---

## 📈 Success Criteria

Deployment is successful when:
- ✅ All pipeline steps complete
- ✅ Health checks return 200 OK
- ✅ Smoke tests pass
- ✅ Manual testing successful
- ✅ No errors in logs

---

## 🔄 After Successful Deployment

1. **Verify Functionality:**
   - Navigate to staging URL
   - Test all routes
   - Verify Navbar
   - Test RBAC

2. **Notify Stakeholders:**
   - Send notification (see `STAKEHOLDER_NOTIFICATION_DRAFT.md`)
   - Provide testing checklist
   - Share access information

3. **Monitor:**
   - Monitor for 24 hours
   - Watch error logs
   - Collect user feedback

---

**Last Updated:** [Date]  
**Maintained By:** DevOps Team

