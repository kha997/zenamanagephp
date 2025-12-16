<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

/**
 * W3C Trace Context Service
 * 
 * Handles W3C traceparent header parsing and generation
 * for distributed tracing compatibility.
 * 
 * @see https://www.w3.org/TR/trace-context/
 */
class W3CTraceContextService
{
    /**
     * Parse W3C traceparent header
     * 
     * Format: version-trace_id-parent_id-trace_flags
     * Example: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
     * 
     * @param string|null $traceparent
     * @return array{version: string, trace_id: string|null, parent_id: string|null, flags: string}
     */
    public function parseTraceparent(?string $traceparent): array
    {
        if (!$traceparent) {
            return [
                'version' => '00',
                'trace_id' => null,
                'parent_id' => null,
                'flags' => '01',
            ];
        }

        $parts = explode('-', $traceparent);
        
        if (count($parts) !== 4) {
            // Invalid format, return empty
            return [
                'version' => '00',
                'trace_id' => null,
                'parent_id' => null,
                'flags' => '01',
            ];
        }

        return [
            'version' => $parts[0],
            'trace_id' => $parts[1] ?? null,
            'parent_id' => $parts[2] ?? null,
            'flags' => $parts[3] ?? '01',
        ];
    }

    /**
     * Generate W3C traceparent header
     * 
     * @param string|null $traceId If null, generates new trace ID
     * @param string|null $parentSpanId If null, generates new span ID
     * @return string
     */
    public function generateTraceparent(?string $traceId = null, ?string $parentSpanId = null): string
    {
        $version = '00';
        $traceId = $traceId ?? $this->generateTraceId();
        $spanId = $this->generateSpanId();
        $flags = '01'; // Sampled flag

        return "{$version}-{$traceId}-{$spanId}-{$flags}";
    }

    /**
     * Generate new trace ID (32 hex characters)
     */
    public function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate new span ID (16 hex characters)
     */
    public function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Extract parent span ID from traceparent
     */
    public function extractParentSpanId(string $traceparent): string
    {
        $parsed = $this->parseTraceparent($traceparent);
        return $parsed['parent_id'] ?? $this->generateSpanId();
    }

    /**
     * Extract trace ID from traceparent
     */
    public function extractTraceId(string $traceparent): ?string
    {
        $parsed = $this->parseTraceparent($traceparent);
        return $parsed['trace_id'];
    }
}
