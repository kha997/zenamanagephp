<?php declare(strict_types=1);

namespace Src\Foundation\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Foundation\EventBus;
use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Events\ComponentCostUpdated;
use Src\CoreProject\Events\ProjectCreated;
use Src\ChangeRequest\Events\ChangeRequestApproved;
use Src\CoreProject\Listeners\ProjectCalculationListener;
use Src\Foundation\Listeners\EventLogListener;
use Src\Foundation\Listeners\NotificationDispatchListener; // ✅ Sửa namespace đúng
use Src\Foundation\Listeners\InterModuleCommunicationListener;

/**
 * EventBusServiceProvider
 * 
 * Đăng ký tất cả event listeners cho hệ thống EventBus
 * Thay thế EventServiceProvider để tối ưu hóa performance và loại bỏ trùng lặp
 */
class EventBusServiceProvider extends ServiceProvider
{
    /**
     * Mapping giữa events và listeners
     * 
     * @var array<string, array<string>>
     */
    protected array $eventListeners = [
        // Core Project Events
        ComponentProgressUpdated::class => [
            ProjectCalculationListener::class,
            NotificationDispatchListener::class,
        ],
        ComponentCostUpdated::class => [
            ProjectCalculationListener::class,
            NotificationDispatchListener::class,
        ],
        ProjectCreated::class => [
            NotificationDispatchListener::class,
            InterModuleCommunicationListener::class,
        ],
        
        // Change Request Events
        ChangeRequestApproved::class => [
            InterModuleCommunicationListener::class,
            NotificationDispatchListener::class,
        ],
    ];

    /**
     * Bootstrap services
     * 
     * @return void
     */
    public function boot(): void
    {
        $eventBus = $this->app->make(EventBus::class);
        
        // Đăng ký EventLogListener cho tất cả events (*)
        // ✅ ĐÚNG - Truyền array [class, method]
        $eventBus->subscribe('*', [EventLogListener::class, 'handle'], 1000);
        
        // Đăng ký các event listeners cụ thể
        foreach ($this->eventListeners as $eventClass => $listeners) {
            $eventName = $this->getEventName($eventClass);
            
            foreach ($listeners as $listenerClass) {
                $priority = $this->getListenerPriority($listenerClass);
                $eventBus->subscribe($eventName, [$listenerClass, 'handle'], $priority); // ✅ Đúng: truyền array [class, method]
            }
        }
        
        // Enable audit logging
        $eventBus->setAuditEnabled(true);
    }

    /**
     * Register services
     * 
     * @return void
     */
    public function register(): void
    {
        // Đăng ký EventBus như singleton
        $this->app->singleton(EventBus::class, function ($app) {
            return new EventBus();
        });
    }

    /**
     * Lấy tên event từ class name
     * 
     * @param string $eventClass
     * @return string
     */
    protected function getEventName(string $eventClass): string
    {
        // Sử dụng method getEventName() có sẵn trong event class để lấy format đúng
        if (class_exists($eventClass)) {
            $reflection = new \ReflectionClass($eventClass);
            if ($reflection->hasMethod('getEventName')) {
                try {
                    // Tạo instance tạm để lấy event name đúng format Domain.Entity.Action
                    $instance = $reflection->newInstanceWithoutConstructor();
                    return $instance->getEventName();
                } catch (\Exception $e) {
                    // Log lỗi nếu không tạo được instance
                    \Log::warning("Cannot create instance of {$eventClass}: " . $e->getMessage());
                }
            }
        }
        
        // Fallback: sử dụng class name đơn giản cho legacy events
        return class_basename($eventClass);
    }

    /**
     * Lấy priority cho listener
     * 
     * @param string $listenerClass
     * @return int
     */
    protected function getListenerPriority(string $listenerClass): int
    {
        // Priority mapping để đảm bảo thứ tự xử lý đúng
        $priorities = [
            ProjectCalculationListener::class => 900, // Cao nhất cho business logic
            InterModuleCommunicationListener::class => 800,
            NotificationDispatchListener::class => 700, // Thấp nhất cho notifications
        ];
        
        return $priorities[$listenerClass] ?? 500; // Default priority
    }
}