<?php

namespace App\Providers;

use App\Auth\CustomSanctumGuard;
use App\Models\EventRecord;
use App\Observers\EventRecordObserver;
use App\Services\DocumentContext\ContractContextProvider;
use App\Services\DocumentContext\CertificateContextProvider;
use App\Services\DocumentContext\DocumentContextRegistry;
use App\Services\DocumentContext\ProjectContextProvider;
use App\Services\DocumentContext\QuoteContextProvider;
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
                $app->make(QuoteContextProvider::class),
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
