<?php declare(strict_types=1);

namespace Src\DocumentManagement\Models;

use App\Models\Document as BaseDocument;

/**
 * Canonical DocumentManagement adapter that preserves current App model behavior.
 */
class LegacyDocumentAdapter extends BaseDocument
{
}
