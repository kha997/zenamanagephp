<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UnreadUpdateQuery;
use App\Support\Dashboard\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnreadUpdateQueryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_returns_only_actors_unread_notifications(): void
    {
        $coworker = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->actor->id,
            'title' => 'Thông báo của tôi',
            'read_at' => null,
        ]);
        Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->actor->id,
            'title' => 'Đã đọc rồi',
            'read_at' => now(),
        ]);
        Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $coworker->id,
            'title' => 'Của người khác',
            'read_at' => null,
        ]);

        $result = (new UnreadUpdateQuery())->build((string) $this->tenant->id, (string) $this->actor->id);

        $this->assertSame(Availability::AVAILABLE, $result->availability);
        $this->assertCount(1, $result->items);
        $this->assertSame('Thông báo của tôi', $result->items[0]->title);
    }

    public function test_does_not_mutate_read_at_by_rendering(): void
    {
        $notification = Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->actor->id,
            'read_at' => null,
        ]);

        (new UnreadUpdateQuery())->build((string) $this->tenant->id, (string) $this->actor->id);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_cross_tenant_notifications_never_returned(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        Notification::factory()->create([
            'tenant_id' => (string) $otherTenant->id,
            'user_id' => (string) $otherUser->id,
            'title' => 'Tenant khác',
            'read_at' => null,
        ]);

        $result = (new UnreadUpdateQuery())->build((string) $this->tenant->id, (string) $this->actor->id);

        $this->assertSame([], $result->items);
    }

    public function test_empty_state_when_no_unread(): void
    {
        $result = (new UnreadUpdateQuery())->build((string) $this->tenant->id, (string) $this->actor->id);

        $this->assertSame(Availability::NO_DATA, $result->availability);
        $this->assertSame([], $result->items);
    }
}
