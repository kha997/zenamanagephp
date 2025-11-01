<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UniqueRoutesTest extends TestCase
{
    /** @test */
    public function all_route_names_are_unique(): void
    {
        $names = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (!$name) continue;
            $this->assertArrayNotHasKey($name, $names, "Duplicate route name: {$name}");
            $names[$name] = true;
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function all_route_signatures_are_unique(): void
    {
        $sigs = [];
        foreach (Route::getRoutes() as $route) {
            $key = implode('|', $route->methods()).' '.$route->uri();
            $this->assertArrayNotHasKey($key, $sigs, "Duplicate route signature: {$key}");
            $sigs[$key] = true;
        }
        $this->assertTrue(true);
    }
}
