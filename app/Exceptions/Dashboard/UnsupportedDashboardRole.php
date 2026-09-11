<?php

namespace App\Exceptions\Dashboard;

use RuntimeException;

class UnsupportedDashboardRole extends RuntimeException
{
    public function __construct(string $role)
    {
        parent::__construct('Unsupported dashboard role: '.$role);
    }
}
