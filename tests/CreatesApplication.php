<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\URL;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Fix UrlGenerator issue in CLI context
        $this->fixUrlGeneratorInCliContext($app);

        return $app;
    }

    /**
     * Fix UrlGenerator issue in CLI context by injecting a mock request
     */
    private function fixUrlGeneratorInCliContext(Application $app): void
    {
        // Only fix in CLI context (testing)
        if (php_sapi_name() === 'cli') {
            // Create a mock request for URL generation
            $request = Request::create('http://localhost', 'GET');
            $request->setLaravelSession($app['session.store']);
            
            // Bind the request to the container
            $app->instance('request', $request);
            
            // Set the URL generator's request
            $urlGenerator = $app->make(UrlGenerator::class);
            $urlGenerator->setRequest($request);
            
            // Set the global URL facade
            URL::setRequest($request);
        }
    }
}