<?php declare(strict_types=1);

namespace App\Foundation\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// Import Events
use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Events\ProjectCreated;
use Src\CoreProject\Events\TaskStatusChanged;
use Src\InteractionLogs\Events\InteractionLogCreated;
use Src\InteractionLogs\Events\InteractionLogApproved;
use Src\ChangeRequest\Events\ChangeRequestApproved;
use Src\Notification\Events\NotificationTriggered;

// Import Listeners
use Src\Foundation\Listeners\EventLogListener;
use Src\Foundation\Listeners\NotificationDispatchListener;
use Src\Foundation\Listeners\InterModuleCommunicationListener;
use Src\CoreProject\Listeners\ProgressCalculationListener;
use Src\CoreProject\Listeners\ProjectCalculationListener;

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
            'Src\\Foundation\\Listeners\\NotificationDispatchListener@handleComponentProgressUpdated',
            'Src\\Foundation\\Listeners\\InterModuleCommunicationListener@handleComponentProgressUpdated',
        ],
        
        ProjectCreated::class => [
            'Src\\Foundation\\Listeners\\InterModuleCommunicationListener@handleProjectCreated',
        ],
        
        TaskStatusChanged::class => [
            // Có thể thêm listeners khác
        ],
        
        // InteractionLogs Events
        InteractionLogCreated::class => [
            'Src\\Foundation\\Listeners\\NotificationDispatchListener@handleInteractionLogCreated',
        ],
        
        InteractionLogApproved::class => [
            // Có thể thêm listeners cho approval notifications
        ],
        
        // ChangeRequest Events
        ChangeRequestApproved::class => [
            'Src\\Foundation\\Listeners\\NotificationDispatchListener@handleChangeRequestApproved',
            'Src\\Foundation\\Listeners\\InterModuleCommunicationListener@handleChangeRequestApproved',
        ],
        
        // Notification Events
        NotificationTriggered::class => [
            // Listeners để gửi notifications qua các channels
        ],
    ];

    /**
     * Đăng ký các services
     */
    public function boot(): void
    {
        parent::boot();
        
        // Đăng ký EventLogListener để listen tất cả events cho auditing
        Event::listen('*', [EventLogListener::class, 'handle']);
    }

    /**
     * Xác định có nên tự động discover events không
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // Tắt auto-discovery để kiểm soát chặt chẽ
    }
}