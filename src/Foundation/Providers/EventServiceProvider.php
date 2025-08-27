<?php declare(strict_types=1);

namespace App\Foundation\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Src\Foundation\EventBus;

// Events - sử dụng namespace đúng
use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Events\ProjectCreated;
use Src\CoreProject\Events\TaskStatusChanged;

// Listeners - sử dụng namespace đúng
use Src\Foundation\Listeners\EventLogListener;
use Src\CoreProject\Listeners\ProgressCalculationListener;
use Src\CoreProject\Listeners\ProjectCalculationListener; // Thêm dòng này

/**
 * Event Service Provider cho Z.E.N.A Event Bus system
 * Đăng ký tất cả event-listener mappings
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Event listener mappings cho ứng dụng
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // CoreProject Events
        ComponentProgressUpdated::class => [
            ProgressCalculationListener::class,
        ],
        
        ProjectCreated::class => [
            // Có thể thêm listeners khác như NotificationListener
        ],
        
        TaskStatusChanged::class => [
            // Có thể thêm listeners khác
        ],
    ];

    /**
     * Đăng ký các services
     */
    public function boot(): void
    {
        parent::boot();
        
        // Đăng ký EventLogListener để listen tất cả events
        Event::listen('*', [EventLogListener::class, 'handle']);
        
        // Đăng ký listener tổng hợp cho các sự kiện progress/cost
        EventBus::subscribe('Project.Component.ProgressUpdated', [ProjectCalculationListener::class, 'handle'], 10);
        EventBus::subscribe('Project.Component.CostUpdated', [ProjectCalculationListener::class, 'handle'], 10);
        EventBus::subscribe('Component.Progress.Updated', [ProjectCalculationListener::class, 'handle'], 10);
        EventBus::subscribe('Component.Cost.Updated', [ProjectCalculationListener::class, 'handle'], 10);
        
        // Backward compatibility với ComponentProgressUpdated event
        EventBus::subscribe('ComponentProgressUpdated', [ProjectCalculationListener::class, 'handleComponentProgressUpdated'], 10);
    }

    /**
     * Xác định có nên tự động discover events không
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // Tắt auto-discovery để kiểm soát chặt chẽ
    }
}