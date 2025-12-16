<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Tracing Service
 * 
 * Provides distributed tracing capabilities using OpenTelemetry (when enabled).
 * Falls back to correlation ID logging when OpenTelemetry is not available.
 */
class TracingService
{
    protected bool $enabled;
    protected ?object $tracer = null;

    public function __construct()
    {
        $this->enabled = config('opentelemetry.enabled', false);
        
        if ($this->enabled) {
            $this->initializeTracer();
        }
    }

    /**
     * Initialize OpenTelemetry tracer
     * 
     * @see docs/architecture/decisions/002-blade-deprecation.md
     * @see Gói 10: Observability End-to-End (OpenTelemetry)
     */
    protected function initializeTracer(): void
    {
        try {
            // Check if OpenTelemetry packages are available
            if (!class_exists(\OpenTelemetry\SDK\Trace\TracerProvider::class)) {
                Log::warning('OpenTelemetry SDK not installed. Run: composer require open-telemetry/opentelemetry open-telemetry/sdk open-telemetry/exporter-otlp');
                return;
            }

            $exporter = $this->createExporter();
            if (!$exporter) {
                Log::warning('OpenTelemetry exporter not configured');
                return;
            }

            $spanProcessor = new \OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor($exporter);

            $resource = \OpenTelemetry\SDK\Resource\ResourceInfo::create(
                \OpenTelemetry\SDK\Common\Attribute\Attributes::create([
                    'service.name' => config('opentelemetry.service_name', 'zenamanage'),
                    'service.version' => config('opentelemetry.service_version', '1.0.0'),
                    'deployment.environment' => config('app.env', 'production'),
                ])
            );

            $tracerProvider = new \OpenTelemetry\SDK\Trace\TracerProvider(
                $spanProcessor,
                null,
                $resource
            );

            $this->tracer = $tracerProvider->getTracer(
                config('opentelemetry.service_name', 'zenamanage'),
                config('opentelemetry.service_version', '1.0.0')
            );

            Log::info('OpenTelemetry tracer initialized', [
                'service_name' => config('opentelemetry.service_name'),
                'exporter' => config('opentelemetry.trace.exporter'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to initialize OpenTelemetry tracer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->enabled = false;
        }
    }

    /**
     * Create exporter based on configuration
     * 
     * @return \OpenTelemetry\SDK\Trace\SpanExporterInterface|null
     */
    protected function createExporter(): ?\OpenTelemetry\SDK\Trace\SpanExporterInterface
    {
        $exporterType = config('opentelemetry.trace.exporter', 'otlp');

        try {
            switch ($exporterType) {
                case 'otlp':
                    return $this->createOtlpExporter();
                case 'jaeger':
                    return $this->createJaegerExporter();
                case 'zipkin':
                    return $this->createZipkinExporter();
                case 'console':
                    if (!class_exists(\OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter::class)) {
                        Log::warning('Console span exporter class not available');
                        return null;
                    }

                    $factory = \OpenTelemetry\SDK\Registry::spanExporterFactory('console');

                    return $factory->create();
                default:
                    Log::warning("Unknown OpenTelemetry exporter: {$exporterType}");
                    return null;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to create OpenTelemetry exporter', [
                'exporter' => $exporterType,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create OTLP exporter
     */
    protected function createOtlpExporter(): ?\OpenTelemetry\SDK\Trace\SpanExporterInterface
    {
        if (!class_exists(\OpenTelemetry\Contrib\Otlp\SpanExporterFactory::class)) {
            return null;
        }

        $endpoint = config('opentelemetry.trace.otlp.endpoint', 'http://localhost:4318');
        $protocol = config('opentelemetry.trace.otlp.protocol', 'http/protobuf');

        if (!getenv('OTEL_EXPORTER_OTLP_TRACES_ENDPOINT') && !getenv('OTEL_EXPORTER_OTLP_ENDPOINT')) {
            putenv('OTEL_EXPORTER_OTLP_ENDPOINT=' . $endpoint);
        }

        if (!getenv('OTEL_EXPORTER_OTLP_TRACES_PROTOCOL') && !getenv('OTEL_EXPORTER_OTLP_PROTOCOL')) {
            putenv('OTEL_EXPORTER_OTLP_PROTOCOL=' . $protocol);
        }

        $factory = new \OpenTelemetry\Contrib\Otlp\SpanExporterFactory();

        return $factory->create();
    }

    /**
     * Create Jaeger exporter
     */
    protected function createJaegerExporter(): ?\OpenTelemetry\SDK\Trace\SpanExporterInterface
    {
        // Jaeger exporter implementation (if available)
        $endpoint = config('opentelemetry.trace.jaeger.endpoint', 'http://localhost:14268/api/traces');
        
        // Note: Jaeger exporter may require additional packages
        Log::warning('Jaeger exporter not fully implemented. Use OTLP exporter instead.');
        return null;
    }

    /**
     * Create Zipkin exporter
     */
    protected function createZipkinExporter(): ?\OpenTelemetry\SDK\Trace\SpanExporterInterface
    {
        // Zipkin exporter implementation (if available)
        $endpoint = config('opentelemetry.trace.zipkin.endpoint', 'http://localhost:9411/api/v2/spans');
        
        // Note: Zipkin exporter may require additional packages
        Log::warning('Zipkin exporter not fully implemented. Use OTLP exporter instead.');
        return null;
    }

    /**
     * Start a new span
     * 
     * @param string $name Span name
     * @param array $attributes Span attributes
     * @return object|null Span object or null if tracing disabled
     */
    public function startSpan(string $name, array $attributes = []): ?object
    {
        if (!$this->enabled || !$this->tracer) {
            // Fallback: log span start
            Log::debug("Span started: {$name}", $attributes);
            return null;
        }

        try {
            if (!method_exists($this->tracer, 'spanBuilder')) {
                Log::debug("Tracer does not support spanBuilder: {$name}", $attributes);
                return null;
            }

            $spanBuilder = $this->tracer->spanBuilder($name);
            
            // Set attributes
            foreach ($attributes as $key => $value) {
                if (is_scalar($value)) {
                    $spanBuilder->setAttribute($key, $value);
                }
            }
            
            $span = $spanBuilder->startSpan();
            
            // Set span as active
            if (class_exists(\OpenTelemetry\Context\Context::class)) {
                $scope = \OpenTelemetry\Context\Context::storage()->scope();
                if ($scope) {
                    $scope->detach();
                }
                \OpenTelemetry\Context\Context::storage()->attach(
                    $span->storeInContext(\OpenTelemetry\Context\Context::getCurrent())
                );
            }
            
            return $span;
        } catch (\Throwable $e) {
            Log::warning('Failed to start span', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * End a span
     * 
     * @param object|null $span Span to end
     * @param array $attributes Additional attributes to add before ending
     */
    public function endSpan(?object $span, array $attributes = []): void
    {
        if (!$span) {
            return;
        }

        try {
            // Set additional attributes before ending
            if (method_exists($span, 'setAttribute')) {
                foreach ($attributes as $key => $value) {
                    if (is_scalar($value)) {
                        $span->setAttribute($key, $value);
                    }
                }
            } elseif (method_exists($span, 'setAttributes')) {
                $span->setAttributes($attributes);
            }

            // End the span
            if (method_exists($span, 'end')) {
                $span->end();
            }

            // Detach scope if using OpenTelemetry context
            if (class_exists(\OpenTelemetry\Context\Context::class)) {
                $scope = \OpenTelemetry\Context\Context::storage()->scope();
                if ($scope) {
                    $scope->detach();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to end span', [
                'error' => $e->getMessage(),
                'attributes' => $attributes,
            ]);
        }

        Log::debug('Span ended', $attributes);
    }

    /**
     * Add event to current span
     * 
     * @param string $name Event name
     * @param array $attributes Event attributes
     */
    public function addEvent(string $name, array $attributes = []): void
    {
        if (!$this->enabled) {
            Log::debug("Event: {$name}", $attributes);
            return;
        }

        try {
            // Get current span from context
            if (class_exists(\OpenTelemetry\Context\Context::class)) {
                $span = \OpenTelemetry\SDK\Trace\Span::fromContext(
                    \OpenTelemetry\Context\Context::getCurrent()
                );
                
                if ($span && method_exists($span, 'addEvent')) {
                    $span->addEvent($name, $attributes);
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to add event to span', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
        }

        Log::debug("Event: {$name}", $attributes);
    }

    /**
     * Record metrics
     * 
     * @param string $name Metric name
     * @param float $value Metric value
     * @param array $attributes Metric attributes
     */
    public function recordMetric(string $name, float $value, array $attributes = []): void
    {
        if (!config('opentelemetry.metrics.enabled', true)) {
            return;
        }

        // TODO: Record metric when OpenTelemetry is available
        Log::debug("Metric: {$name} = {$value}", $attributes);
    }

    /**
     * Calculate percentiles from latency data
     * 
     * @param array $latencies Array of latency values in milliseconds
     * @return array Percentiles (p50, p95, p99)
     */
    public function calculatePercentiles(array $latencies): array
    {
        if (empty($latencies)) {
            return ['p50' => 0, 'p95' => 0, 'p99' => 0];
        }

        sort($latencies);
        $count = count($latencies);

        return [
            'p50' => $latencies[(int)($count * 0.5)] ?? 0,
            'p95' => $latencies[(int)($count * 0.95)] ?? 0,
            'p99' => $latencies[(int)($count * 0.99)] ?? 0,
        ];
    }
}
