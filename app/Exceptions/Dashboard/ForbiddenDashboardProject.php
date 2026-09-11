<?php

namespace App\Exceptions\Dashboard;

use RuntimeException;

class ForbiddenDashboardProject extends RuntimeException
{
    public function __construct(string $projectId)
    {
        parent::__construct('Dashboard project is not accessible: '.$projectId);
    }
}
