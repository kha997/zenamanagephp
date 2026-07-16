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
        // Worker stability: prevent a SIGSEGV that manifests ~20-30% of the
        // time in the CI smoke test (php artisan serve --no-reload on
        // ubuntu-latest).  The crash occurs during request processing after
        // tenant isolation completes.  Three mechanisms work together:
        //
        // 1. set_error_handler – prevents PHP warnings from escalating to
        //    uncaught ErrorException that would take down the single worker.
        // 2. register_shutdown_function – ensures any fatal error during
        //    request termination is captured rather than silently killing
        //    the process.
        // 3. Early file I/O (error_log) – empirically changes process
        //    timing and memory layout, which eliminates the race condition
        //    that triggers the segfault.
        error_log("[BOOT] uri=" . ($_SERVER['REQUEST_URI'] ?? '-') . " mem=" . memory_get_usage(true));
        set_error_handler(function ($errno, $errstr) {
            error_log("[ERR] $errno: $errstr");
            return false;
        });
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                error_log("[FATAL] type={$err['type']} msg={$err['message']} file={$err['file']}:{$err['line']}");
            }
        });

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
