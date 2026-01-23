<?php declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChangeRequestFactory;

class ZenaChangeRequest extends ChangeRequest
{
    // Compatibility alias for legacy references/tests.

    protected static function newFactory(): ChangeRequestFactory
    {
        return ChangeRequestFactory::new();
    }
}
