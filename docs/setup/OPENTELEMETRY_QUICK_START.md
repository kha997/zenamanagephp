# OpenTelemetry Quick Start Guide

## Bước 1: Cài đặt Packages (Đã hoàn thành ✅)

```bash
composer require open-telemetry/opentelemetry open-telemetry/sdk open-telemetry/exporter-otlp
```

## Bước 2: Cấu hình Environment Variables

### Option A: Sử dụng Script (Khuyến nghị)

```bash
# Cho môi trường local
./scripts/setup-opentelemetry-env.sh local

# Cho môi trường production
./scripts/setup-opentelemetry-env.sh production
```

### Option B: Thêm thủ công vào .env

Thêm các dòng sau vào file `.env`:

```env
# ============================================
# OpenTelemetry Configuration
# ============================================

OPENTELEMETRY_ENABLED=true
OPENTELEMETRY_SERVICE_NAME=zenamanage
OPENTELEMETRY_SERVICE_VERSION=1.0.0

OPENTELEMETRY_TRACE_ENABLED=true
OPENTELEMETRY_TRACE_EXPORTER=console
OPENTELEMETRY_SAMPLING_RATE=1.0

OPENTELEMETRY_METRICS_ENABLED=true
OPENTELEMETRY_METRICS_EXPORTER=otlp
OPENTELEMETRY_METRICS_OTLP_ENDPOINT=http://localhost:4318/v1/metrics
```

## Bước 3: Clear Config Cache

```bash
php artisan config:clear
```

## Bước 4: Verify Configuration

```bash
php artisan tinker
```

Trong tinker:
```php
config('opentelemetry.enabled')
// => true

config('opentelemetry.trace.exporter')
// => "console" (hoặc "otlp" nếu đã set)
```

## Bước 5: Test Tracing

1. Gửi một request:
```bash
curl http://localhost/api/v1/app/projects
```

2. Kiểm tra logs:
```bash
tail -f storage/logs/laravel.log | grep -i "span\|trace"
```

3. Nếu dùng console exporter, bạn sẽ thấy traces trong logs.

## Cấu hình cho Production

### Sử dụng OTLP Collector

1. **Update .env:**
```env
OPENTELEMETRY_TRACE_EXPORTER=otlp
OPENTELEMETRY_OTLP_ENDPOINT=https://your-collector.example.com:4318/v1/traces
OPENTELEMETRY_SAMPLING_RATE=0.1
```

2. **Setup OTLP Collector (Docker):**
```bash
docker run -p 4318:4318 otel/opentelemetry-collector
```

### Hoặc sử dụng Jaeger

1. **Update .env:**
```env
OPENTELEMETRY_TRACE_EXPORTER=jaeger
OPENTELEMETRY_JAEGER_ENDPOINT=http://localhost:14268/api/traces
```

2. **Start Jaeger:**
```bash
docker run -d -p 16686:16686 -p 14268:14268 jaegertracing/all-in-one:latest
```

3. **Access UI:** http://localhost:16686

## Kiểm tra Metrics

Sau khi cấu hình, kiểm tra metrics tại:

```bash
curl http://localhost/api/v1/admin/observability/metrics
```

## Troubleshooting

### Tracer không khởi tạo

1. Kiểm tra packages:
```bash
composer show | grep opentelemetry
```

2. Kiểm tra logs:
```bash
tail -f storage/logs/laravel.log
```

3. Đảm bảo `.env` có `OPENTELEMETRY_ENABLED=true`

### Connection refused

- Kiểm tra backend service đang chạy
- Kiểm tra endpoint URL đúng
- Kiểm tra firewall/network

## Tài liệu chi tiết

Xem `docs/setup/OPENTELEMETRY_ENV_SETUP.md` để biết thêm chi tiết về:
- Các exporter options
- Cloud provider setup
- Advanced configuration

