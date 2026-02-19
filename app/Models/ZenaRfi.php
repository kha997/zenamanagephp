<?php

namespace App\Models;

/**
 * Legacy alias for the RFI model to keep older tests and references intact.
 *
 * @deprecated Use {@see \App\Models\Rfi} directly going forward.
 */
class ZenaRfi extends Rfi
{
    /**
     * Force the alias to use the unified RFIs table so legacy usages
     * share the same datasource as the API.
     */
    protected $table = 'rfis';

    // This class intentionally left thin to preserve legacy references.
}
