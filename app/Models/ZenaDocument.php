<?php declare(strict_types=1);

namespace App\Models;

use Database\Factories\Src\DocumentManagement\Models\DocumentFactory;
use Src\DocumentManagement\Models\Document;

/**
 * Compatibility alias for legacy references/tests that expect App\Models\ZenaDocument.
 */
class ZenaDocument extends Document
{
    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }
}
