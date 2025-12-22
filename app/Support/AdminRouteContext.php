<?php declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class AdminRouteContext
{
    /**
     * Determine whether the provided request is within an admin context.
     */
    public static function matches(?Request $request = null): bool
    {
        $request ??= request();

        if (!$request) {
            return false;
        }

        return $request->is('api/admin/*')
            || $request->is('api/v1/admin/*')
            || $request->routeIs('admin.*');
    }
}
