<?php

namespace App\Contracts\Dashboard;

use App\Models\DashboardWidget;
use App\Services\Dashboard\WidgetDataContext;
use App\Services\Dashboard\WidgetDataResult;

interface WidgetDataProvider
{
    public function supports(string $widgetCode): bool;

    public function provide(DashboardWidget $widget, WidgetDataContext $context): WidgetDataResult;
}
