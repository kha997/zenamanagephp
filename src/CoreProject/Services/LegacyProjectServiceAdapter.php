<?php declare(strict_types=1);

namespace Src\CoreProject\Services;

use App\Services\ProjectService as BaseProjectService;

/**
 * Canonical CoreProject adapter that preserves current App service behavior.
 */
class LegacyProjectServiceAdapter extends BaseProjectService
{
}
