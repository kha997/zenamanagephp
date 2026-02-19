<?php declare(strict_types=1);

namespace App\Models;

/**
 * @deprecated Use {@see Submittal} instead.
 */
class ZenaSubmittal extends Submittal
{
    /** @var string */
    protected $table = 'submittals';

    // Legacy alias to avoid changing dependent tests.
}
