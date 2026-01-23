<?php declare(strict_types=1);

namespace App\Models;

use Database\Factories\NotificationFactory;

class ZenaNotification extends Notification
{
    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }
}
