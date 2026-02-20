<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationTokenBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_command_updates_legacy_token_hash_and_does_not_leak_plaintext_token(): void
    {
        $legacyToken = 'legacy-token-' . Str::random(24);

        $invitationId = DB::table('invitations')->insertGetId([
            'tenant_id' => null,
            'team_id' => null,
            'token' => $legacyToken,
            'token_hash' => null,
            'token_version' => null,
            'email' => 'legacy+' . Str::random(6) . '@example.com',
            'role' => 'member',
            'organization_id' => 1,
            'project_id' => null,
            'invited_by' => 1,
            'invited_by_user_id' => null,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
            'accepted_by' => null,
            'accepted_by_user_id' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'metadata' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('invitations:backfill-token-hash', ['--chunk' => 100]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString($legacyToken, $output);

        $invitation = Invitation::query()->findOrFail($invitationId);

        $this->assertSame($legacyToken, $invitation->token);
        $this->assertSame(hash('sha256', $legacyToken), $invitation->token_hash);
        $this->assertSame(Invitation::TOKEN_VERSION_HASH_ONLY, $invitation->token_version);
    }

    public function test_backfill_command_dry_run_reports_without_writing(): void
    {
        $legacyToken = 'legacy-dry-run-token-' . Str::random(20);

        $invitationId = DB::table('invitations')->insertGetId([
            'tenant_id' => null,
            'team_id' => null,
            'token' => $legacyToken,
            'token_hash' => null,
            'token_version' => null,
            'email' => 'dryrun+' . Str::random(6) . '@example.com',
            'role' => 'member',
            'organization_id' => 1,
            'project_id' => null,
            'invited_by' => 1,
            'invited_by_user_id' => null,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
            'accepted_by' => null,
            'accepted_by_user_id' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'metadata' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('invitations:backfill-token-hash', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('would be updated', $output);
        $this->assertStringNotContainsString($legacyToken, $output);

        $invitation = Invitation::query()->findOrFail($invitationId);

        $this->assertSame($legacyToken, $invitation->token);
        $this->assertNull($invitation->token_hash);
        $this->assertNull($invitation->token_version);
    }
}
