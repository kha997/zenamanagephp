<?php declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaskFactory;

class ZenaTask extends Task
{
    // Compatibility alias for legacy references/tests.

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }
}
