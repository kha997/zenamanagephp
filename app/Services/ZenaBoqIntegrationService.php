<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only integration with zena-boq-core (spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 2).
 * ZenaManage never writes pricing data to zena-boq-core — only GET.
 */
class ZenaBoqIntegrationService
{
    /**
     * Fail-closed: both an empty configured name AND a configured name that
     * resolves to no Tenant row must deny, never accidentally match.
     */
    public function isTenantAuthorized(string $tenantId): bool
    {
        $configuredName = config('zena_boq.integration_tenant_name');

        if (empty($configuredName)) {
            return false;
        }

        $resolvedId = Tenant::where('name', $configuredName)->value('id');

        if (!$resolvedId) {
            return false;
        }

        return (string) $resolvedId === $tenantId;
    }

    /**
     * @return array{id: string, subtotal: float, vat_amount: float, total: float, status: string, calibration: string, issued_at: ?string}|null
     */
    public function fetchLatestQuote(string $projectCode): ?array
    {
        $baseUrl = rtrim((string) config('zena_boq.base_url'), '/');
        $secret = (string) config('zena_boq.read_api_secret');

        if ($baseUrl === '' || $secret === '') {
            Log::warning('zena_boq.sync_skipped_missing_config');

            return null;
        }

        try {
            $projectResponse = Http::timeout(5)
                ->withToken($secret)
                ->get("{$baseUrl}/api/external/projects/{$projectCode}");

            if (!$projectResponse->successful()) {
                Log::warning('zena_boq.project_fetch_failed', [
                    'status' => $projectResponse->status(),
                    'project_code' => $projectCode,
                ]);

                return null;
            }

            $quoteResponse = Http::timeout(5)
                ->withToken($secret)
                ->get("{$baseUrl}/api/external/quotes/latest", ['projectCode' => $projectCode]);

            if (!$quoteResponse->successful()) {
                Log::warning('zena_boq.quote_fetch_failed', [
                    'status' => $quoteResponse->status(),
                    'project_code' => $projectCode,
                ]);

                return null;
            }

            $quote = $quoteResponse->json();

            if (!is_array($quote) || empty($quote['id'] ?? null) || !array_key_exists('total', $quote)) {
                Log::warning('zena_boq.quote_fetch_malformed', [
                    'project_code' => $projectCode,
                ]);

                return null;
            }

            return [
                'id' => (string) ($quote['id'] ?? ''),
                'subtotal' => (float) ($quote['subtotal'] ?? 0),
                'vat_amount' => (float) ($quote['vatAmount'] ?? 0),
                'total' => (float) ($quote['total'] ?? 0),
                'status' => (string) ($quote['status'] ?? ''),
                'calibration' => (string) ($quote['calibration'] ?? ''),
                'issued_at' => $quote['issuedAt'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('zena_boq.sync_exception', [
                'error' => $e->getMessage(),
                'project_code' => $projectCode,
            ]);

            return null;
        }
    }
}
