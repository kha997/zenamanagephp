<?php

namespace App\Contracts\Dashboard;

use App\Models\DashboardWidget;
use App\Services\Dashboard\WidgetDataContext;
use App\Services\Dashboard\WidgetDataResult;

interface WidgetDataResolver
{
    public function resolve(DashboardWidget $widget, WidgetDataContext $context): WidgetDataResult;

    public function canResolve(DashboardWidget $widget): bool;
}
