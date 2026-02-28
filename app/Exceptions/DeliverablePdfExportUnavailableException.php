<?php declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DeliverablePdfExportUnavailableException extends RuntimeException
{
    public const MESSAGE = 'PDF export not available: install Playwright Chromium (run: npm ci && npx playwright install chromium)';

    public function __construct(string $message = self::MESSAGE)
    {
        parent::__construct($message);
    }
}
