# Dashboard Context

**Last Updated**: 2025-01-XX  
**Status**: Active

---

## Overview

The Dashboard context handles dashboard widgets, KPIs, metrics, and real-time updates via WebSocket.

---

## Key Components

### Services

- **`DashboardService`** (`app/Services/DashboardService.php`)
  - Aggregates data from multiple sources
  - KPI calculations
  - Widget data preparation

- **`WebSocketMetricsService`** (`app/Services/WebSocketMetricsService.php`)
  - WebSocket connection metrics
  - Message rate tracking

### Controllers

- **`Api\V1\App\DashboardController`** (`app/Http/Controllers/Api/V1/App/DashboardController.php`)
  - Dashboard API endpoints
  - Widget management

### WebSocket

- **`DashboardWebSocketHandler`** (`app/WebSocket/DashboardWebSocketHandler.php`)
  - Real-time dashboard updates
  - Uses AuthGuard and RateLimitGuard

---

## API Endpoints

- `GET /api/v1/app/dashboard` - Get dashboard summary
- `GET /api/v1/app/dashboard/kpis` - Get KPIs
- `GET /api/v1/app/dashboard/widgets` - Get widgets
- `GET /api/v1/metrics/websocket` - WebSocket metrics

---

## Cache Strategy

- KPI cache: 60 seconds per tenant
- Widget cache: 30 seconds per user
- Dashboard summary: 60 seconds per tenant

---

## Test Organization

```bash
# Run all dashboard tests
php artisan test --group=dashboard
```

---

## References

- [WebSocket Architecture](../WEBSOCKET_ARCHITECTURE.md)
- [Architecture Layering Guide](../ARCHITECTURE_LAYERING_GUIDE.md)

