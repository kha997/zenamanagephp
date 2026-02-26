<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class SettingsNotificationsRouteTest extends TestCase
{
    public function test_notifications_settings_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/settings/notifications')
            ->assertStatus(401);
    }
}
