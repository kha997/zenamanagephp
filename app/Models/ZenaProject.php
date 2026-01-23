<?php declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;

class ZenaProject extends Project
{
    // Compatibility alias for legacy references/tests.

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }
}
