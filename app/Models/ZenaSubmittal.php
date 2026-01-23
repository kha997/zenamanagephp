<?php declare(strict_types=1);

namespace App\Models;

use Database\Factories\SubmittalFactory;

class ZenaSubmittal extends Submittal
{
    // Compatibility alias for legacy references/tests.

    protected static function newFactory(): SubmittalFactory
    {
        return SubmittalFactory::new();
    }
}
