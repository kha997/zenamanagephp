<?php declare(strict_types=1);

namespace App\Traits;

trait SkipsSchemaIntrospection
{
    /**
     * Determine whether schema/introspection operations should be skipped.
     */
    protected static function shouldSkipSchemaIntrospection(): bool
    {
        $driver = config('database.default');

        return $driver === 'sqlite' || app()->environment('testing');
    }
}
