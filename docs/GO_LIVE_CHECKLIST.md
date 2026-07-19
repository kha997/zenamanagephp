# Z.E.N.A Project Management - Go-Live Checklist

> **Cập nhật 2026-07-19** — phần này thay thế vai trò "checklist sống" của tài liệu; danh sách generic phía dưới giữ nguyên để tham khảo. Nguồn: tổng rà production-readiness 19/07 (đối chiếu code thật trên `main`).

## ✅ Đã xác minh sẵn sàng (không cần làm lại)

| Hạng mục | Bằng chứng trong code |
|---|---|
| Backup tự động + kiểm tra thật | `Kernel.php` schedule `backup:run` (all + database); `LaunchChecklistService::checkBackupSystem()` kiểm tra file thật (từ PR #181) |
| Error tracking | Sentry tích hợp (`Handler::reportable` + `HubInterface` guard, PR #187); no-op an toàn khi thiếu DSN |
| Queue worker + scheduler | `docker/supervisor/supervisord.conf`: đủ 4 process `php-fpm`/`nginx`/`laravel-worker`/`laravel-schedule` |
| Redis cache/session | Store `redis` trong `config/cache.php` đã sửa đúng chuẩn (PR #191); defaults redis từ PR #188 |
| Launch checklist tự kiểm | `LaunchChecklistService` 20 check đều là kiểm tra thật, không stub |
| Deploy pipeline | `.github/workflows/production.yml` deploy qua SSH, tự skip an toàn khi thiếu secrets |
| Debug routes | `DebugGateMiddleware` chặn 404 ngoài môi trường local/testing/development |

## 🔧 Cấu hình BẮT BUỘC khi deploy production (.env prod)

| Biến | Giá trị prod | Ghi chú |
|---|---|---|
| `APP_ENV` | `production` | Đồng thời khoá `_debug` routes |
| `APP_DEBUG` | `false` | |
| `QUEUE_CONNECTION` | `redis` | Worker đã chạy sẵn trong supervisor — default `sync` chỉ dành cho dev |
| `CACHE_DRIVER` / `SESSION_DRIVER` | `redis` | Cần Redis service thật |
| `MAIL_*` | SMTP thật | Default hiện là `mailpit` (dev); mail mời user/portal login phụ thuộc cái này |
| `SENTRY_LARAVEL_DSN` | DSN môi trường prod | + `SENTRY_ENVIRONMENT=production` |
| Secrets GitHub cho deploy | `SSH_*` theo `production.yml` | Workflow tự liệt kê secret thiếu trong log |

## 🟠 Việc còn mở (theo dõi đến khi đóng)

- [ ] **npm audit critical** — PR #197 (bump lockfile, mở khoá gate `security-scan` của Production Deployment)
- [ ] **Auth Guard Lint 166 lỗi** — đang sửa theo 3 đợt cơ giới (batch 1: `CrmPageController`)
- [ ] **Vite 4→8 major upgrade** — 3 finding npm còn lại (high vite / moderate esbuild / low laravel-vite-plugin) cần bump major + Node mới trong CI; tách thành task riêng, không chặn gate critical
- [ ] Flake CI hạ tầng (staging-smoke SIGSEGV, browser-tests connection-refused) — playbook: re-run trước, chỉ debug khi fail 3 lần cùng chữ ký

## 🔒 Security Checklist

### Application Security
- [ ] **Environment Variables**: Tất cả sensitive data trong .env
- [ ] **Debug Mode**: APP_DEBUG=false trong production
- [ ] **HTTPS**: SSL certificate đã được cài đặt và cấu hình
- [ ] **Security Headers**: CSP, HSTS, X-Frame-Options đã được set
- [ ] **Input Validation**: Tất cả form inputs được validate
- [ ] **SQL Injection**: Sử dụng Eloquent ORM, không có raw queries
- [ ] **XSS Protection**: Output được escape đúng cách
- [ ] **CSRF Protection**: CSRF tokens hoạt động
- [ ] **File Upload**: File upload có validation type và size
- [ ] **Authentication**: JWT tokens có expiration time hợp lý

### Server Security
- [ ] **Firewall**: Chỉ mở ports 22, 80, 443
- [ ] **SSH**: Disable password authentication, chỉ dùng key
- [ ] **User Permissions**: Web server user có minimal permissions
- [ ] **Database**: Database user chỉ có permissions cần thiết
- [ ] **File Permissions**: 644 cho files, 755 cho directories
- [ ] **Hidden Files**: .env, .git không accessible từ web
- [ ] **Server Tokens**: Ẩn server version information
- [ ] **Rate Limiting**: API rate limiting đã được implement

## 🚀 Performance Checklist

### Application Performance
- [ ] **Caching**: Redis cache hoạt động đúng
- [ ] **Database Indexing**: Các indexes cần thiết đã được tạo
- [ ] **Query Optimization**: Không có N+1 queries
- [ ] **Eager Loading**: Sử dụng eager loading cho relationships
- [ ] **Pagination**: Large datasets được paginate
- [ ] **Image Optimization**: Images được optimize và resize
- [ ] **Asset Minification**: CSS/JS được minify
- [ ] **Gzip Compression**: Web server enable compression

### Server Performance
- [ ] **PHP OPcache**: OPcache enabled và configured
- [ ] **Memory Limits**: PHP memory limit đủ cho application
- [ ] **Connection Pooling**: Database connection pooling
- [ ] **Queue Workers**: Background jobs sử dụng queues
- [ ] **CDN**: Static assets serve qua CDN (nếu cần)
- [ ] **Load Balancing**: Setup load balancer (nếu cần)

## 📊 Monitoring Checklist

### Application Monitoring
- [ ] **Health Endpoint**: /api/health endpoint hoạt động
- [ ] **Error Logging**: Errors được log đầy đủ
- [ ] **Performance Metrics**: Response time monitoring
- [ ] **Uptime Monitoring**: External uptime monitoring service
- [ ] **Database Monitoring**: Database performance metrics
- [ ] **Queue Monitoring**: Queue job processing monitoring
- [ ] **WebSocket Monitoring**: Real-time connection monitoring

### Infrastructure Monitoring
- [ ] **Server Resources**: CPU, Memory, Disk monitoring
- [ ] **Network Monitoring**: Bandwidth và latency monitoring
- [ ] **Log Aggregation**: Centralized log management
- [ ] **Alerting**: Alerts cho critical issues
- [ ] **Backup Monitoring**: Backup success/failure alerts

## 💾 Backup & Recovery Checklist

### Backup Strategy
- [ ] **Database Backup**: Daily automated database backups
- [ ] **File Backup**: Application files backup
- [ ] **Configuration Backup**: Server configuration backup
- [ ] **Backup Testing**: Regular restore testing
- [ ] **Backup Retention**: Backup retention policy
- [ ] **Offsite Backup**: Backups stored offsite

### Disaster Recovery
- [ ] **Recovery Plan**: Documented disaster recovery plan
- [ ] **RTO/RPO**: Recovery time/point objectives defined
- [ ] **Failover Testing**: Regular failover testing
- [ ] **Data Replication**: Database replication setup (nếu cần)

## 🧪 Testing Checklist

### Functional Testing
- [ ] **User Authentication**: Login/logout/register hoạt động
- [ ] **Project Management**: CRUD operations cho projects
- [ ] **Task Management**: Task creation và assignment
- [ ] **Document Management**: File upload/download/versioning
- [ ] **Change Requests**: CR workflow hoạt động đúng
- [ ] **Notifications**: Real-time notifications
- [ ] **RBAC**: Role-based access control
- [ ] **API Endpoints**: Tất cả API endpoints hoạt động
- [ ] **WebSocket**: Real-time features hoạt động
- [ ] **Email Notifications**: Email được gửi đúng

### Performance Testing
- [ ] **Load Testing**: Test với expected concurrent users
- [ ] **Stress Testing**: Test với peak load
- [ ] **Database Performance**: Query performance acceptable
- [ ] **Memory Usage**: Memory leaks không tồn tại
- [ ] **Response Time**: Average response time < 500ms

### Security Testing
- [ ] **Penetration Testing**: Security vulnerabilities scan
- [ ] **Authentication Testing**: Auth bypass attempts
- [ ] **Authorization Testing**: Access control testing
- [ ] **Input Validation Testing**: Malicious input testing
- [ ] **Session Management**: Session security testing

## 📚 Documentation Checklist

### Technical Documentation
- [ ] **API Documentation**: Complete API documentation
- [ ] **Deployment Guide**: Step-by-step deployment guide
- [ ] **Architecture Documentation**: System architecture docs
- [ ] **Database Schema**: Database schema documentation
- [ ] **Configuration Guide**: Server configuration guide

### User Documentation
- [ ] **User Manual**: End-user documentation
- [ ] **Admin Guide**: Administrator documentation
- [ ] **Training Materials**: User training materials
- [ ] **FAQ**: Frequently asked questions
- [ ] **Troubleshooting Guide**: Common issues và solutions

## 🎯 Go-Live Execution

### Pre-Go-Live (T-1 Week)
- [ ] **Final Testing**: Complete final testing cycle
- [ ] **Backup Current System**: Full backup của existing system
- [ ] **DNS Preparation**: DNS changes prepared
- [ ] **SSL Certificate**: SSL certificate ready
- [ ] **Team Notification**: All stakeholders notified
- [ ] **Rollback Plan**: Rollback plan documented và tested

### Go-Live Day (T-0)
- [ ] **Maintenance Window**: Maintenance window announced
- [ ] **Database Migration**: Run production migrations
- [ ] **File Deployment**: Deploy application files
- [ ] **Configuration Update**: Update production configuration
- [ ] **Service Restart**: Restart all services
- [ ] **DNS Switch**: Switch DNS to new server
- [ ] **SSL Verification**: Verify SSL certificate
- [ ] **Smoke Testing**: Basic functionality testing
- [ ] **Performance Check**: Initial performance verification
- [ ] **Go-Live Announcement**: Announce successful go-live

### Post-Go-Live (T+1 Day)
- [ ] **24h Monitoring**: Intensive monitoring first 24h
- [ ] **User Feedback**: Collect initial user feedback
- [ ] **Performance Review**: Review performance metrics
- [ ] **Issue Tracking**: Track và resolve any issues
- [ ] **Backup Verification**: Verify backup systems working
- [ ] **Documentation Update**: Update docs với any changes

## 🆘 Emergency Procedures

### Rollback Procedure
1. **Immediate Actions**
   - [ ] Stop incoming traffic
   - [ ] Switch DNS back to old system
   - [ ] Notify stakeholders

2. **System Restoration**
   - [ ] Restore database from backup
   - [ ] Restore application files
   - [ ] Restart services
   - [ ] Verify functionality

3. **Post-Rollback**
   - [ ] Analyze failure cause
   - [ ] Document lessons learned
   - [ ] Plan remediation

### Emergency Contacts
- **Technical Lead**: [Contact Info]
- **System Administrator**: [Contact Info]
- **Database Administrator**: [Contact Info]
- **Network Administrator**: [Contact Info]
- **Project Manager**: [Contact Info]

---

**Signature**: _____________________ **Date**: _____________________

**Role**: _____________________ **Name**: _____________________