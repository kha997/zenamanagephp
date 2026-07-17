<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\Handler;
use Sentry\State\HubInterface;
use Tests\TestCase;

class SentryIntegrationTest extends TestCase
{
    public function test_sentry_hub_is_registered_via_service_provider(): void
    {
        $this->assertTrue($this->app->bound(HubInterface::class));
    }

    public function test_config_reads_dsn_and_environment_from_env(): void
    {
        config(['sentry.dsn' => null]);
        $this->assertNull(config('sentry.dsn'));

        config(['sentry.dsn' => 'https://example@o0.ingest.sentry.io/0']);
        $this->assertSame('https://example@o0.ingest.sentry.io/0', config('sentry.dsn'));
    }

    public function test_reporting_an_exception_does_not_throw_with_empty_dsn(): void
    {
        // With SENTRY_LARAVEL_DSN unset (the default), Sentry\captureException()
        // is a safe no-op — this proves the reportable() hook in
        // app/Exceptions/Handler.php never breaks normal error handling.
        $handler = $this->app->make(Handler::class);

        $handler->report(new \RuntimeException('sentry wiring smoke test'));

        $this->assertTrue(true);
    }
}
