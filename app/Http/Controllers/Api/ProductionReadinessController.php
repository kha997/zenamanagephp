<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GAP-049 minimal production readiness probe.
 *
 * Purpose-built: genuine DB/cache/storage round-trips only, no diagnostic
 * internals (no PHP/Laravel version, APP_ENV, memory/load, credentials).
 * 200 only when every synchronous dependency required to serve a request
 * is ready; 503 otherwise. See docs/superpowers/specs/2026-09-03-gap-049-production-deployment-gate2-design.md §A-4.
 */
class ProductionReadinessController extends Controller
{
    public function check(): JsonResponse
    {
        $failed = [];

        if (!$this->probeDatabase()) {
            $failed[] = 'database';
        }
        if (!$this->probeCache()) {
            $failed[] = 'cache';
        }
        if (!$this->probeStorage()) {
            $failed[] = 'storage';
        }

        if ($failed === []) {
            return response()->json(['status' => 'ready'], 200);
        }

        return response()->json(['status' => 'not_ready', 'failed' => $failed], 503);
    }

    private function probeDatabase(): bool
    {
        try {
            $result = DB::select('SELECT 1');
            return !empty($result);
        } catch (\Throwable) {
            return false;
        }
    }

    private function probeCache(): bool
    {
        try {
            $key = 'gap049-readiness-' . Str::random(12);
            $value = Str::random(8);

            Cache::put($key, $value, 10);
            $read = Cache::get($key);
            Cache::forget($key);

            return $read === $value;
        } catch (\Throwable) {
            return false;
        }
    }

    private function probeStorage(): bool
    {
        try {
            $disk = Storage::disk(config('filesystems.default'));
            $path = 'gap049-readiness-probes/' . Str::random(12) . '.probe';
            $value = Str::random(8);

            $disk->put($path, $value);
            $read = $disk->exists($path) ? $disk->get($path) : null;
            $disk->delete($path);

            return $read === $value;
        } catch (\Throwable) {
            return false;
        }
    }
}
