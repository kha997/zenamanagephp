<?php

declare(strict_types=1);

namespace App\Services\Admin;

use RuntimeException;

/**
 * GAP-129 — a staff-account action was refused. The message is user-facing (Vietnamese) for the admin pages; the CLI
 * prints its own English messages before calling the service.
 */
final class StaffAccountException extends RuntimeException
{
}
