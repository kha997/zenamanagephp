# WebSocket Runbook

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Operational guide for WebSocket server in production

---

## Overview

ZenaManage uses WebSocket for real-time dashboard updates, notifications, and live data synchronization. This runbook provides procedures for starting, stopping, monitoring, and troubleshooting the WebSocket server.

---

## Architecture

### Components

- **WebSocket Server**: `websocket_server.php` (standalone) or `php artisan websocket:serve` (Artisan command)
- **Handler**: `app/WebSocket/DashboardWebSocketHandler.php`
- **Config**: `config/websocket.php`
- **Port**: 8080 (default, configurable via `WEBSOCKET_PORT`)

### Entry Points

1. **Standalone Script**: `php websocket_server.php`
2. **Artisan Command**: `php artisan websocket:serve --host=0.0.0.0 --port=8080`

**Recommended:** Use Artisan command for production (better error handling, logging)

---

## Starting the Server

### Development

```bash
# Option 1: Artisan command (recommended)
php artisan websocket:serve --host=0.0.0.0 --port=8080

# Option 2: Standalone script
php websocket_server.php
```

### Production (Docker)

```bash
# Start WebSocket service
docker-compose -f docker-compose.prod.yml up -d websocket

# View logs
docker-compose -f docker-compose.prod.yml logs -f websocket
```

### Production (Systemd)

Create `/etc/systemd/system/zenamanage-websocket.service`:

```ini
[Unit]
Description=ZenaManage WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/zenamanage
ExecStart=/usr/bin/php artisan websocket:serve --host=0.0.0.0 --port=8080
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl enable zenamanage-websocket
sudo systemctl start zenamanage-websocket
```

---

## Stopping the Server

### Development

Press `Ctrl+C` to stop gracefully.

### Production (Docker)

```bash
docker-compose -f docker-compose.prod.yml stop websocket
```

### Production (Systemd)

```bash
sudo systemctl stop zenamanage-websocket
```

---

## Restarting the Server

### Graceful Restart

1. **Stop**: Send SIGTERM signal (allows connections to close gracefully)
2. **Wait**: 10-30 seconds for connections to close
3. **Start**: Start server again

### Production (Docker)

```bash
docker-compose -f docker-compose.prod.yml restart websocket
```

### Production (Systemd)

```bash
sudo systemctl restart zenamanage-websocket
```

---

## Health Check

### Endpoint

**URL:** `GET /api/v1/ws/health`

**Response:**
```json
{
  "status": "healthy",
  "connections": 42,
  "uptime": 3600,
  "memory_usage": "128MB"
}
```

### Monitoring

**Prometheus Metrics:**
- `websocket_connections_total` - Total active connections
- `websocket_messages_total` - Total messages sent
- `websocket_errors_total` - Total errors
- `websocket_memory_usage_bytes` - Memory usage

**Grafana Dashboard:**
- Connection count over time
- Message rate
- Error rate
- Memory usage

---

## Viewing Logs

### Development

Logs are output to stdout/stderr.

### Production (Docker)

```bash
# View logs
docker-compose -f docker-compose.prod.yml logs -f websocket

# View last 100 lines
docker-compose -f docker-compose.prod.yml logs --tail=100 websocket
```

### Production (Systemd)

```bash
# View logs
sudo journalctl -u zenamanage-websocket -f

# View last 100 lines
sudo journalctl -u zenamanage-websocket -n 100
```

### Log Format

```
[2025-01-19 10:30:45] INFO: User 123 connected
[2025-01-19 10:30:46] INFO: User 123 subscribed to project.456
[2025-01-19 10:30:50] ERROR: Authentication failed for user 123
[2025-01-19 10:31:00] INFO: User 123 disconnected
```

---

## Troubleshooting

### Issue: Error Rate Increases

**Symptoms:**
- High error rate in metrics
- Users report connection issues
- Logs show authentication failures

**Check:**
1. **Connection Limits**: Check if server is at connection limit
   ```bash
   # Check active connections
   netstat -an | grep :8080 | wc -l
   ```

2. **Memory Usage**: Check if server is running out of memory
   ```bash
   # Check memory usage
   ps aux | grep websocket
   ```

3. **Authentication Failures**: Check logs for auth errors
   ```bash
   # Filter auth errors
   grep "Authentication failed" /var/log/websocket.log
   ```

4. **Tenant Isolation Issues**: Check if tenant context is missing
   ```bash
   # Filter tenant errors
   grep "tenant" /var/log/websocket.log | grep -i error
   ```

**Actions:**
- Increase connection limits in config
- Increase memory limit for PHP
- Check authentication service
- Verify tenant isolation middleware

### Issue: Server Crashes

**Symptoms:**
- Server stops responding
- Process dies
- Logs show fatal errors

**Check:**
1. **Error Logs**: Check for fatal errors
   ```bash
   tail -100 /var/log/websocket.log | grep -i fatal
   ```

2. **System Resources**: Check CPU, memory, disk
   ```bash
   top
   df -h
   ```

3. **PHP Errors**: Check PHP error log
   ```bash
   tail -100 /var/log/php-errors.log
   ```

**Actions:**
- Fix code errors
- Increase system resources
- Enable auto-restart (systemd)
- Add monitoring alerts

### Issue: High Latency

**Symptoms:**
- Messages delayed
- Users report slow updates
- High latency in metrics

**Check:**
1. **Message Queue**: Check if messages are queued
   ```bash
   # Check queue size
   redis-cli LLEN websocket:queue
   ```

2. **Network**: Check network latency
   ```bash
   ping websocket-server
   ```

3. **Database**: Check database query performance
   ```bash
   # Check slow queries
   tail -100 /var/log/mysql/slow.log
   ```

**Actions:**
- Optimize message processing
- Scale horizontally (multiple workers)
- Optimize database queries
- Use message queue for heavy operations

---

## Configuration

### Environment Variables

```env
WEBSOCKET_HOST=0.0.0.0
WEBSOCKET_PORT=8080
WEBSOCKET_WORKERS=1
```

### Config File

**Location:** `config/websocket.php`

**Key Settings:**
- `host`: Server host (default: 0.0.0.0)
- `port`: Server port (default: 8080)
- `workers`: Number of worker processes (default: 1)
- `auth.guard`: Authentication guard (default: sanctum)
- `heartbeat.interval`: Heartbeat interval in seconds (default: 30)
- `heartbeat.timeout`: Heartbeat timeout in seconds (default: 60)

---

## Scaling

### Horizontal Scaling

Run multiple WebSocket server instances behind a load balancer:

1. **Load Balancer**: Use Nginx or HAProxy with sticky sessions
2. **Multiple Workers**: Run multiple `websocket:serve` processes
3. **Shared State**: Use Redis for shared connection state

### Vertical Scaling

Increase resources for single server:

1. **Memory**: Increase PHP memory limit
2. **Workers**: Increase worker count (if supported)
3. **Connection Limits**: Increase max connections

---

## Security

### Authentication

- All connections require valid Sanctum token
- Token validated on connection
- Invalid tokens rejected immediately

### Rate Limiting

- Connection rate limiting per IP
- Message rate limiting per user
- Configurable limits in `config/websocket.php`

### Tenant Isolation

- All messages scoped to user's tenant
- Users cannot subscribe to other tenant channels
- Enforced in `DashboardWebSocketHandler`

---

## Monitoring

### Metrics to Track

- **Connection Count**: Active connections
- **Message Rate**: Messages per second
- **Error Rate**: Errors per minute
- **Latency**: Message delivery latency
- **Memory Usage**: Server memory usage
- **CPU Usage**: Server CPU usage

### Alerts

Set up alerts for:
- Error rate > 1%
- Connection count > 80% of limit
- Memory usage > 80%
- Server down (no health check response)

---

## Backup & Recovery

### Backup

WebSocket server is stateless. No backup needed.

### Recovery

If server crashes:
1. Check logs for root cause
2. Fix issue
3. Restart server
4. Verify health check

---

## References

- [WebSocket Architecture](WEBSOCKET_ARCHITECTURE.md)
- [Authentication & Tenant Flow](AUTH_TENANT_FLOW.md)
- [Service Catalog](SERVICE_CATALOG.md)

---

*This runbook should be updated whenever WebSocket operations change.*

