<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * GAP-128: websocket:serve could never start (cboden/ratchet is not installed) and no page connects to it.
 * The server, its deploy restarts and its host provisioning are retired.
 */
class WebSocketServerRetiredTest extends TestCase
{
    public function test_websocket_serve_command_is_not_registered(): void
    {
        $this->assertArrayNotHasKey(
            'websocket:serve',
            Artisan::all(),
            'websocket:serve was retired by GAP-128; it cannot load Ratchet and nothing connects to it.'
        );
    }

    public function test_production_workflow_does_not_restart_a_websocket_unit(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/production.yml'));

        $this->assertStringNotContainsString(
            'zena-websocket',
            $workflow,
            'production.yml must not restart zena-websocket (GAP-128).'
        );
    }

    public function test_host_provisioning_runbook_does_not_provision_a_websocket_unit(): void
    {
        $runbook = (string) file_get_contents(base_path('docs/runbooks/gap-049-host-provisioning.md'));

        $this->assertStringNotContainsString(
            'NOPASSWD: /bin/systemctl restart zena-websocket',
            $runbook,
            'The deploy user must not get sudo for zena-websocket (GAP-128).'
        );
        $this->assertStringNotContainsString(
            'websocket systemd unit',
            $runbook,
            'The runbook must not ask for a websocket systemd unit (GAP-128).'
        );
    }
}
