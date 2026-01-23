<?php declare(strict_types=1);

namespace App\Support\Facades;

use App\Support\Testing\LogFake;
use Illuminate\Support\Facades\Log as BaseLog;

final class Log extends BaseLog
{
    public static function fake(): LogFake
    {
        $fake = new LogFake();
        static::swap($fake);

        return $fake;
    }
}
