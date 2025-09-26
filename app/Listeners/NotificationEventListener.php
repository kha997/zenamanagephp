<?php

namespace App\Listeners;

use App\Models\Notification;
use App\Models\User;
use App\Events\NotificationCreated;
use App\Events\NotificationSent;
use App\Events\NotificationRead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotificationEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleNotificationCreated(NotificationCreated $event)
    {
        $notification = $event->notification;
        
        Log::info('Notification created', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
            'priority' => $notification->priority
        ]);
    }

    public function handleNotificationSent(NotificationSent $event)
    {
        $notification = $event->notification;
        
        Log::info('Notification sent', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'channels' => $notification->channels
        ]);
    }

    public function handleNotificationRead(NotificationRead $event)
    {
        $notification = $event->notification;
        
        Log::info('Notification read', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'read_at' => now()
        ]);
    }
}