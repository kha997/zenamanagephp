<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RouteListDoesNotFatalTest extends TestCase
{
    public function test_route_list_command_completes_successfully(): void
    {
        $exitCode = Artisan::call('route:list');

        $this->assertSame(0, $exitCode);
    }
}
