<?php

namespace Tests;

use Illuminate\Support\Collection;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Prepare for Dusk test execution.
     *
     * @beforeClass
     */
    public static function prepare(): void
    {
        if (! static::runningInSail() && ! static::hasExternalDriverUrl()) {
            static::startChromeDriver();
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $driverUrl = $this->driverUrl();
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--no-sandbox',
            '--disable-dev-shm-usage',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());
        $chromeBinary = $_ENV['CHROME_BIN'] ?? $_SERVER['CHROME_BIN'] ?? getenv('CHROME_BIN');
        if (is_string($chromeBinary) && $chromeBinary !== '') {
            $options->setBinary($chromeBinary);
        }

        $this->waitForChromeDriver($driverUrl);
        return RemoteWebDriver::create(
            $driverUrl,
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    protected static function hasExternalDriverUrl(): bool
    {
        $driverUrl = $_ENV['DUSK_DRIVER_URL'] ?? $_SERVER['DUSK_DRIVER_URL'] ?? getenv('DUSK_DRIVER_URL');

        return is_string($driverUrl) && $driverUrl !== '';
    }

    protected function driverUrl(): string
    {
        return $_ENV['DUSK_DRIVER_URL']
            ?? $_SERVER['DUSK_DRIVER_URL']
            ?? getenv('DUSK_DRIVER_URL')
            ?: 'http://localhost:9515';
    }

    /**
     * Determine whether the Dusk command has disabled headless mode.
     */
    protected function hasHeadlessDisabled(): bool
    {
        return isset($_SERVER['DUSK_HEADLESS_DISABLED']) ||
               isset($_ENV['DUSK_HEADLESS_DISABLED']);
    }

    /**
     * Determine if the browser window should start maximized.
     */
    protected function shouldStartMaximized(): bool
    {
        return isset($_SERVER['DUSK_START_MAXIMIZED']) ||
               isset($_ENV['DUSK_START_MAXIMIZED']);
    }
    /**
     * CI can be slower to start ChromeDriver. Wait until port is accepting connections.
     */
    protected function waitForChromeDriver(string $driverUrl, int $retries = 120, int $sleepMs = 250): void
    {
        $host = parse_url($driverUrl, PHP_URL_HOST) ?: '127.0.0.1';
        $port = parse_url($driverUrl, PHP_URL_PORT) ?: 9515;

        for ($i = 0; $i < $retries; $i++) {
            $fp = @fsockopen($host, $port, $errno, $errstr, 0.25);
            if ($fp) {
                fclose($fp);
                return;
            }
            usleep($sleepMs * 1000);
        }

        throw new \RuntimeException("ChromeDriver not reachable at {$host}:{$port} after {$retries} retries");
    }

}
