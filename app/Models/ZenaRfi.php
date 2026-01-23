<?php declare(strict_types=1);

namespace App\Models;

use Database\Factories\RfiFactory;

class ZenaRfi extends Rfi
{
    protected static function newFactory(): RfiFactory
    {
        return RfiFactory::new();
    }
}
