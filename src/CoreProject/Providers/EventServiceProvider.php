<?php declare(strict_types=1);

namespace Src\CoreProject\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Src\Foundation\Events\BaseEvent;
use Src\Foundation\Listeners\EventLogListener;
// Tạm thời comment các import chưa tồn tại
// use Src\CoreProject\Events\ComponentProgressUpdated;
// use Src\CoreProject\Listeners\UpdateProjectProgressListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Tạm thời comment các event mapping chưa tồn tại
        /*
        ComponentProgressUpdated::class => [
            UpdateProjectProgressListener::class,
        ],
        */
    ];

    public function boot(): void
    {
        parent::boot();
        
        // Đăng ký EventLogListener cho tất cả events
        $this->app['events']->listen('*', EventLogListener::class);
    }
}