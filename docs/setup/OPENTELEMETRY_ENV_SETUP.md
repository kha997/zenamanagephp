# OpenTelemetry Environment Variables Setup

## Tổng quan

Sau khi cài đặt OpenTelemetry packages, cần cấu hình các biến môi trường trong file `.env`.

## Các biến môi trường cần thêm

Thêm các dòng sau vào file `.env` của bạn:

```env
# ============================================
# OpenTelemetry Configuration
# ============================================

# Enable/Disable OpenTelemetry
OPENTELEMETRY_ENABLED=true

# Service Information
OPENTELEMETRY_SERVICE_NAME=zenamanage
OPENTELEMETRY_SERVICE_VERSION=1.0.0

# Trace Configuration
OPENTELEMETRY_TRACE_ENABLED=true
OPENTELEMETRY_TRACE_EXPORTER=otlp
# Options: 'otlp' (recommended), 'jaeger', 'zipkin', 'console'

# OTLP Exporter (Recommended - works with most backends)
OPENTELEMETRY_OTLP_ENDPOINT=http://localhost:4318/v1/traces
OPENTELEMETRY_OTLP_PROTOCOL=http/protobuf
# Options: 'http/protobuf' (default), 'http/json'

# Jaeger Exporter (Alternative)
# OPENTELEMETRY_JAEGER_ENDPOINT=http://localhost:14268/api/traces

# Zipkin Exporter (Alternative)
# OPENTELEMETRY_ZIPKIN_ENDPOINT=http://localhost:9411/api/v2/spans

# Metrics Configuration
OPENTELEMETRY_METRICS_ENABLED=true
OPENTELEMETRY_METRICS_EXPORTER=otlp
# Options: 'otlp', 'prometheus'

OPENTELEMETRY_METRICS_OTLP_ENDPOINT=http://localhost:4318/v1/metrics

# Sampling Rate (0.0 to 1.0)
# 1.0 = sample all traces, 0.1 = sample 10% of traces
OPENTELEMETRY_SAMPLING_RATE=1.0
```

## Cấu hình theo môi trường

### Development (Local)

```env
OPENTELEMETRY_ENABLED=true
OPENTELEMETRY_TRACE_EXPORTER=console
OPENTELEMETRY_SAMPLING_RATE=1.0
```

**Lưu ý:** Sử dụng `console` exporter để xem traces trong terminal.

### Staging/Production

```env
OPENTELEMETRY_ENABLED=true
OPENTELEMETRY_TRACE_EXPORTER=otlp
OPENTELEMETRY_OTLP_ENDPOINT=https://your-otel-collector.example.com:4318/v1/traces
OPENTELEMETRY_SAMPLING_RATE=0.1
```

**Lưu ý:** 
- Sử dụng OTLP exporter cho production
- Giảm sampling rate để tiết kiệm tài nguyên
- Đảm bảo endpoint HTTPS và có authentication

## Backend Options

### 1. OTLP Collector (Recommended)

OTLP Collector là universal collector có thể export đến nhiều backends:

```env
OPENTELEMETRY_TRACE_EXPORTER=otlp
OPENTELEMETRY_OTLP_ENDPOINT=http://localhost:4318/v1/traces
```

**Setup:**
```bash
# Docker
docker run -p 4318:4318 otel/opentelemetry-collector
```

### 2. Jaeger

```env
OPENTELEMETRY_TRACE_EXPORTER=jaeger
OPENTELEMETRY_JAEGER_ENDPOINT=http://localhost:14268/api/traces
```

**Setup:**
```bash
docker run -d -p 16686:16686 -p 14268:14268 jaegertracing/all-in-one:latest
```

### 3. Zipkin

```env
OPENTELEMETRY_TRACE_EXPORTER=zipkin
OPENTELEMETRY_ZIPKIN_ENDPOINT=http://localhost:9411/api/v2/spans
```

**Setup:**
```bash
docker run -d -p 9411:9411 openzipkin/zipkin
```

### 4. Console (Development Only)

```env
OPENTELEMETRY_TRACE_EXPORTER=console
```

Traces sẽ được in ra console/logs.

## Cloud Providers

### AWS X-Ray

```env
OPENTELEMETRY_TRACE_EXPORTER=otlp
OPENTELEMETRY_OTLP_ENDPOINT=http://localhost:4318/v1/traces
```

Sử dụng OTLP Collector với X-Ray exporter.

### Google Cloud Trace

```env
OPENTELEMETRY_TRACE_EXPORTER=otlp
OPENTELEMETRY_OTLP_ENDPOINT=https://cloudtrace.googleapis.com/v1/projects/YOUR_PROJECT_ID/traces
```

### Azure Application Insights

```env
OPENTELEMETRY_TRACE_EXPORTER=otlp
OPENTELEMETRY_OTLP_ENDPOINT=https://YOUR_INSTRUMENTATION_KEY@dc.applicationinsights.azure.com/v2.1/track
```

## Verification

Sau khi cấu hình, kiểm tra:

1. **Check config loaded:**
```bash
php artisan tinker
>>> config('opentelemetry.enabled')
=> true
```

2. **Check tracer initialized:**
Xem logs để thấy message:
```
OpenTelemetry tracer initialized
```

3. **Test trace:**
Gửi một request và kiểm tra traces trong backend của bạn.

## Troubleshooting

### Tracer không khởi tạo

- Kiểm tra packages đã cài: `composer show | grep opentelemetry`
- Kiểm tra logs: `storage/logs/laravel.log`
- Đảm bảo `OPENTELEMETRY_ENABLED=true`

### Connection refused

- Kiểm tra backend service đang chạy
- Kiểm tra endpoint URL đúng
- Kiểm tra firewall/network

### No traces in backend

- Kiểm tra sampling rate (có thể quá thấp)
- Kiểm tra exporter endpoint đúng
- Kiểm tra authentication nếu cần

## Next Steps

Sau khi cấu hình xong:

1. Clear config cache: `php artisan config:clear`
2. Test với một request: `curl http://localhost/api/v1/app/projects`
3. Kiểm tra traces trong backend UI
4. Xem metrics tại `/api/v1/admin/observability/metrics`

