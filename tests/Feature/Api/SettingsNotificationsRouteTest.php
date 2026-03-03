<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class SettingsNotificationsRouteTest extends TestCase
{
    public function test_notifications_settings_endpoint_requires_authentication(): void
    {
        $this->getJson($this->notificationsSettingsUri())
            ->assertStatus(401);
    }

    private function notificationsSettingsUri(): string
    {
        return '/api/' . 'v1' . '/settings/notifications';
    }
}
