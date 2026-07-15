<?php

namespace App\Providers;

use App\Auth\CustomSanctumGuard;
use App\Models\EventRecord;
use App\Observers\EventRecordObserver;
use App\Services\DocumentContext\ContractContextProvider;
use App\Services\DocumentContext\CertificateContextProvider;
use App\Services\DocumentContext\DocumentContextRegistry;
use App\Services\DocumentContext\ProjectContextProvider;
use App\Services\PaymentCertificateSummaryService;
use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(DocumentContextRegistry::class, function ($app) {
            return new DocumentContextRegistry([
                $app->make(ContractContextProvider::class),
                $app->make(CertificateContextProvider::class),
                $app->make(ProjectContextProvider::class),
            ]);
        });

        $this->app->singleton(PaymentCertificateSummaryService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // ── DIAG GLOBAL: earliest possible logging, uses error_log() not Laravel ──
        // UNCONDITIONAL: env() is unreliable during boot (caching, CI). error_log() is zero-cost.
        if (true) {
            $bootId = substr(md5(uniqid('', true)), 0, 12);
            error_log(sprintf(
                '[DIAG-BOOT %s] uri=%s mem=%s peak=%s time=%.3f',
                $bootId,
                $_SERVER['REQUEST_URI'] ?? 'cli',
                memory_get_usage(true),
                memory_get_peak_usage(true),
                microtime(true)
            ));

            // Fatal-error shutdown handler — writes to error_log (synchronous, survives logger crash)
            register_shutdown_function(function () use ($bootId) {
                $error = error_get_last();
                if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                    error_log(sprintf(
                        '[DIAG-SHUTDOWN %s] FATAL type=%d file=%s line=%d mem=%s peak=%s err=%s',
                        $bootId,
                        $error['type'],
                        $error['file'] ?? 'unknown',
                        $error['line'] ?? 0,
                        memory_get_usage(true),
                        memory_get_peak_usage(true),
                        $error['message'] ?? 'unknown'
                    ));
                }
            });

            // Catch any uncaught error before Laravel exception handler takes over
            set_error_handler(function ($severity, $message, $file, $line) use ($bootId) {
                error_log(sprintf(
                    '[DIAG-ERROR %s] sev=%d file=%s line=%d msg=%s',
                    $bootId,
                    $severity,
                    $file,
                    $line,
                    $message
                ));
                return false; // let Laravel handle it too
            });
        }
        // ── END DIAG GLOBAL ──

        if (config('database.default') === 'sqlite') {
            try {
                $connection = DB::connection();
                $grammar = $connection->getQueryGrammar();
                $grammar->macro('compileJsonContains', function ($column, $value) {
                    [$field, $path] = $this->wrapJsonFieldAndPath($column);

                    return sprintf('json_extract(%s%s) LIKE \'%%\' || %s || \'%%\'', $field, $path, $value);
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        EventRecord::observe(EventRecordObserver::class);

        Auth::resolved(function ($auth) {
            $auth->extend('sanctum', function ($app, $name, array $config) use ($auth) {
                return tap(new RequestGuard(
                    new CustomSanctumGuard($auth, config('sanctum.expiration'), $config['provider'] ?? null),
                    request(),
                    $auth->createUserProvider($config['provider'] ?? null)
                ), function ($guard) {
                    app()->refresh('request', $guard, 'setRequest');
                });
            });
        });
    }
}
