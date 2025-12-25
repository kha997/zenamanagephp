# Docker Production Setup Guide
# ZenaManage Dashboard System

## Overview

This guide provides comprehensive instructions for setting up and managing the ZenaManage Dashboard system using Docker in a production environment.

## Architecture

The production setup includes the following services:

- **Application**: Laravel PHP-FPM application
- **Web Server**: Nginx reverse proxy with SSL termination
- **Database**: MySQL 8.0 with optimized configuration
- **Cache**: Redis with persistence
- **Queue Worker**: Background job processing
- **Scheduler**: Cron job management
- **WebSocket**: Real-time communication
- **Monitoring**: Prometheus + Grafana
- **Logging**: Elasticsearch + Kibana
- **Backup**: Automated backup service

## Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+
- At least 4GB RAM
- At least 20GB disk space
- Domain name configured
- SSL certificates (optional for development)

## Quick Start

### 1. Clone and Setup

```bash
git clone <repository-url>
cd zenamanage
cp production.env.example production.env
# Edit production.env with your settings
# `production.env` is gitignored; keep the working copy local and never commit it.
```

### 2. Deploy

```bash
chmod +x deploy-production.sh
./deploy-production.sh
```

### 3. Verify

```bash
chmod +x docker-manage.sh
./docker-manage.sh status
./docker-manage.sh health
```

## Configuration

### Environment Variables

Edit `production.env` with your production settings (the file is gitignored; use `production.env.example` as the tracked template and keep your copy local):

```bash
# Application
APP_NAME="ZenaManage Dashboard"
APP_ENV=production
APP_KEY=base64:your-production-app-key-here
APP_DEBUG=false
APP_URL=https://dashboard.zenamanage.com

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=zenamanage
DB_USERNAME=zenamanage_user
DB_PASSWORD=your-secure-database-password

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=your-secure-redis-password
REDIS_PORT=6379

# Monitoring
GRAFANA_PASSWORD=your-grafana-admin-password
```

### SSL Certificates

#### Option 1: Self-Signed (Development)

```bash
chmod +x setup-ssl.sh
./setup-ssl.sh self-signed
```

#### Option 2: Let's Encrypt (Production)

```bash
chmod +x setup-ssl.sh
./setup-ssl.sh letsencrypt
```

## Service Management

### Using Docker Management Script

```bash
# Start all services
./docker-manage.sh start

# Stop all services
./docker-manage.sh stop

# Restart all services
./docker-manage.sh restart

# Show status
./docker-manage.sh status

# View logs
./docker-manage.sh logs
./docker-manage.sh logs app

# Open shell in service
./docker-manage.sh shell app

# Scale service
./docker-manage.sh scale queue 3

# Health check
./docker-manage.sh health
```

### Using Docker Compose Directly

```bash
# Start services
docker-compose -f docker-compose.prod.yml up -d

# Stop services
docker-compose -f docker-compose.prod.yml down

# View logs
docker-compose -f docker-compose.prod.yml logs -f

# Execute commands
docker-compose -f docker-compose.prod.yml exec app php artisan migrate
```

## Service Details

### Application Service

- **Image**: Custom PHP 8.2-FPM Alpine
- **Port**: 9000 (internal)
- **Features**: OPcache, Redis, MySQL extensions
- **Health Check**: HTTP endpoint on port 9000

### Nginx Service

- **Image**: Nginx 1.24 Alpine
- **Ports**: 80, 443
- **Features**: SSL termination, rate limiting, security headers
- **Configuration**: `docker/nginx/nginx.prod.conf`

### MySQL Service

- **Image**: MySQL 8.0
- **Port**: 3306
- **Features**: Optimized configuration, binary logging
- **Configuration**: `docker/mysql/my.cnf`

### Redis Service

- **Image**: Redis 7 Alpine
- **Port**: 6379
- **Features**: Persistence, password protection
- **Configuration**: `docker/redis/redis.conf`

### Monitoring Services

#### Prometheus

- **Port**: 9090
- **Purpose**: Metrics collection
- **Configuration**: `docker/prometheus/prometheus.yml`

#### Grafana

- **Port**: 3000
- **Purpose**: Metrics visualization
- **Default Login**: admin / (from GRAFANA_PASSWORD)

### Logging Services

#### Elasticsearch

- **Port**: 9200
- **Purpose**: Log storage and indexing

#### Kibana

- **Port**: 5601
- **Purpose**: Log visualization and analysis

## Backup and Recovery

### Create Backup

```bash
./docker-manage.sh backup
```

### Restore from Backup

```bash
./docker-manage.sh restore backups/20240101_120000
```

### Automated Backups

Add to crontab for daily backups:

```bash
0 2 * * * /path/to/zenamanage/docker-manage.sh backup
```

## Monitoring and Alerting

### Grafana Dashboards

Access Grafana at `http://localhost:3000` with:
- Username: `admin`
- Password: Value from `GRAFANA_PASSWORD` in production.env

Available dashboards:
- Dashboard System Overview
- Laravel Application Metrics
- Database Performance
- System Resources
- WebSocket Metrics
- Queue Performance
- Security & Access Logs
- Application Logs

### Prometheus Metrics

Access Prometheus at `http://localhost:9090` to view:
- Application metrics
- Database metrics
- System metrics
- Custom business metrics

### Log Analysis

Access Kibana at `http://localhost:5601` to analyze:
- Application logs
- Access logs
- Error logs
- Security events

## Security Considerations

### Network Security

- All services run in isolated Docker network
- Only necessary ports exposed
- SSL/TLS encryption for all external communication

### Application Security

- Security headers configured in Nginx
- Rate limiting enabled
- File upload restrictions
- SQL injection protection
- XSS protection

### Container Security

- Non-root users where possible
- Minimal base images
- Regular security updates
- Resource limits

## Performance Optimization

### PHP Optimization

- OPcache enabled with optimized settings
- Memory limits configured
- Process limits set

### Database Optimization

- InnoDB buffer pool configured
- Query cache enabled
- Binary logging optimized

### Redis Optimization

- Memory limits set
- Persistence configured
- Connection pooling

### Nginx Optimization

- Gzip compression enabled
- Static file caching
- Connection pooling
- Rate limiting

## Troubleshooting

### Common Issues

#### Services Not Starting

```bash
# Check logs
./docker-manage.sh logs

# Check status
./docker-manage.sh status

# Restart services
./docker-manage.sh restart
```

#### Database Connection Issues

```bash
# Check MySQL logs
./docker-manage.sh logs mysql

# Test connection
./docker-manage.sh shell mysql
mysql -u root -p
```

#### SSL Certificate Issues

```bash
# Test SSL configuration
./setup-ssl.sh test

# Regenerate certificates
./setup-ssl.sh letsencrypt
```

#### Performance Issues

```bash
# Check resource usage
docker stats

# Scale services
./docker-manage.sh scale queue 3
```

### Log Locations

- Application logs: `storage/logs/`
- Nginx logs: Container logs
- MySQL logs: Container logs
- Redis logs: Container logs

### Health Checks

All services include health checks. Check status with:

```bash
./docker-manage.sh health
```

## Maintenance

### Regular Tasks

1. **Daily**: Monitor logs and metrics
2. **Weekly**: Review performance metrics
3. **Monthly**: Update dependencies and security patches
4. **Quarterly**: Review and update configurations

### Updates

```bash
# Update all services
./docker-manage.sh update

# Update specific service
docker-compose -f docker-compose.prod.yml pull [service]
docker-compose -f docker-compose.prod.yml up -d [service]
```

### Cleanup

```bash
# Clean up unused resources
./docker-manage.sh clean
```

## Scaling

### Horizontal Scaling

```bash
# Scale queue workers
./docker-manage.sh scale queue 5

# Scale application instances
./docker-manage.sh scale app 3
```

### Vertical Scaling

Edit `docker-compose.prod.yml` to increase resource limits:

```yaml
services:
  app:
    deploy:
      resources:
        limits:
          memory: 1G
          cpus: '0.5'
```

## Support

For issues and support:

1. Check logs: `./docker-manage.sh logs`
2. Check health: `./docker-manage.sh health`
3. Review documentation
4. Contact support team

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Laravel Documentation](https://laravel.com/docs)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Redis Documentation](https://redis.io/documentation)
- [Prometheus Documentation](https://prometheus.io/docs/)
- [Grafana Documentation](https://grafana.com/docs/)
- [Elasticsearch Documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html)
- [Kibana Documentation](https://www.elastic.co/guide/en/kibana/current/index.html)
