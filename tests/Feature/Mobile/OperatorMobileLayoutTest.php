<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\TimeTracking\TimeEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-118 (Gate 2 N-1, H-1, C-1, T-1, V-1) — phone layout markup.
 *
 * - N-1 / H-1: the operator top bar carries a "☰ Menu" <details> with the same navigation, the search box, the full user
 *   name, logout, and the user's initials.
 * - C-1: "Việc của tôi" renders task / design-item cards and "Bảng giờ" renders time-entry cards next to the desktop
 *   tables.
 * - T-1: task cards carry a Start form; a fixed running-timer bar with Stop shows on both pages only while a timer runs.
 *
 * Visibility per screen width is CSS; it is measured with Playwright at Gate 3 (V-1).
 */
class OperatorMobileLayoutTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $worker;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Nguyễn Văn Kỹ Sư'], ['mobile_worker'], ['task.view']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Quận 7']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_top_bar_has_a_mobile_menu_with_navigation_search_logout_and_initials(): void
    {
        $dom = $this->dom($this->open('app.my-work.index'));

        $menu = $this->sole($dom, 'header.operator-topbar details.operator-mobile-nav');
        $this->assertStringContainsString('Menu', $this->text($dom, 'header.operator-topbar details.operator-mobile-nav > summary'));
        $this->assertNotEmpty($this->all($dom, 'details.operator-mobile-nav a[href="' . route('app.my-work.index') . '"]'));
        $this->assertNotEmpty($this->all($dom, 'details.operator-mobile-nav a[href="' . route('app.time.index') . '"]'));
        $this->assertNotEmpty($this->all($dom, 'details.operator-mobile-nav input[name="q"]'));
        $this->assertNotEmpty($this->all($dom, 'details.operator-mobile-nav form[action="' . route('logout.post') . '"]'));
        $this->assertStringContainsString('Nguyễn Văn Kỹ Sư', $menu->textContent);
        $this->assertSame('NS', trim($this->text($dom, 'header.operator-topbar .operator-user-initials')));

        // Desktop sidebar is still rendered (hidden on small screens by CSS only).
        $this->assertNotEmpty($this->all($dom, 'aside.operator-sidebar a[href="' . route('app.my-work.index') . '"]'));
    }

    public function test_my_work_renders_cards_with_start_buttons_for_tasks_only(): void
    {
        $task = $this->task(['name' => 'Đổ bê tông móng', 'end_date' => '2026-11-08']);
        DesignItem::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'name' => 'Bản vẽ mặt đứng',
            'review_status' => DesignItem::STATUS_DRAFT, 'assigned_to' => (string) $this->worker->id, 'blocked_at' => null,
        ]);

        $dom = $this->dom($this->open('app.my-work.index'));
        $cards = $this->all($dom, '[data-testid="work-item-card"]');
        $this->assertCount(2, $cards);

        $taskCard = $this->cardContaining($cards, 'Đổ bê tông móng');
        $this->assertStringContainsString('Nhà phố Quận 7', $taskCard->textContent);
        $this->assertStringContainsString('08/11/2026', $taskCard->textContent);
        $this->assertStringContainsString('Quá hạn', $taskCard->textContent);
        $start = $this->within($dom, $taskCard, 'form[data-testid="task-card-start"]');
        $this->assertCount(1, $start);
        $this->assertSame(route('app.time.start'), $start[0]->getAttribute('action'));
        $this->assertCount(1, $this->within($dom, $start[0], 'input[name="task_id"][value="' . $task->id . '"]'));

        $designCard = $this->cardContaining($cards, 'Bản vẽ mặt đứng');
        $this->assertCount(0, $this->within($dom, $designCard, 'form[data-testid="task-card-start"]'));

        // No timer running: no bar.
        $this->assertCount(0, $this->all($dom, '[data-testid="running-timer-bar"]'));
    }

    public function test_running_timer_bar_shows_on_both_pages_while_a_timer_runs(): void
    {
        $task = $this->task(['name' => 'Lắp điện âm tường']);
        app(TimeEntryService::class)->start($this->worker, $task);

        foreach (['app.my-work.index', 'app.time.index'] as $route) {
            $dom = $this->dom($this->open($route));
            $bar = $this->sole($dom, '[data-testid="running-timer-bar"]');
            $this->assertStringContainsString('Đang ghi giờ', $bar->textContent, $route);
            $this->assertStringContainsString('Lắp điện âm tường', $bar->textContent, $route);
            $this->assertStringContainsString('09:00', $bar->textContent, $route);
            $stop = $this->within($dom, $bar, 'form');
            $this->assertCount(1, $stop, $route);
            $this->assertSame(route('app.time.stop'), $stop[0]->getAttribute('action'), $route);
        }
    }

    public function test_timesheet_renders_entry_cards_with_all_details_and_edit_only_when_allowed(): void
    {
        $task = $this->task(['name' => 'Chống thấm sân thượng']);
        $service = app(TimeEntryService::class);
        $service->addManual($this->worker, ['task_id' => (string) $task->id, 'work_date' => '2026-11-10', 'minutes' => 90, 'note' => 'Bù giờ sáng']);
        $approved = $service->addManual($this->worker, ['category' => TimeEntry::CATEGORY_MEETING, 'work_date' => '2026-11-09', 'minutes' => 30, 'note' => 'Họp giao ban']);
        $approved->forceFill(['status' => TimeEntry::STATUS_APPROVED])->save();

        $dom = $this->dom($this->open('app.time.index'));
        $cards = $this->all($dom, '[data-testid="time-entry-card"]');
        $this->assertCount(2, $cards);

        $taskCard = $this->cardContaining($cards, 'Chống thấm sân thượng');
        foreach (['10/11', 'Nhà phố Quận 7', '1:30', 'Bù giờ sáng'] as $expected) {
            $this->assertStringContainsString($expected, $taskCard->textContent);
        }
        $this->assertCount(1, $this->within($dom, $taskCard, 'form[action="' . route('app.time.entries.update', $this->entryId('Bù giờ sáng')) . '"]'));

        $meetingCard = $this->cardContaining($cards, 'Họp giao ban');
        $this->assertStringContainsString('0:30', $meetingCard->textContent);
        $this->assertCount(0, $this->within($dom, $meetingCard, 'form'));
    }

    private function task(array $attributes): Task
    {
        $attributes['title'] = $attributes['name'];

        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'status' => Task::STATUS_IN_PROGRESS,
            'assigned_to' => (string) $this->worker->id, 'end_date' => '2026-11-20', 'blocked_at' => null,
        ], $attributes));
    }

    private function entryId(string $note): string
    {
        return (string) TimeEntry::query()->where('note', $note)->value('id');
    }

    private function open(string $route): TestResponse
    {
        return $this->actingAs($this->worker)->get(route($route), ['X-Tenant-ID' => (string) $this->tenant->id])->assertOk();
    }

    private function dom(TestResponse $response): \DOMXPath
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        libxml_clear_errors();

        return new \DOMXPath($doc);
    }

    /**
     * @return list<\DOMElement>
     */
    private function all(\DOMXPath $xpath, string $css): array
    {
        return $this->nodes($xpath->query((new CssSelectorConverter())->toXPath($css)));
    }

    /**
     * @return list<\DOMElement>
     */
    private function within(\DOMXPath $xpath, \DOMElement $context, string $css): array
    {
        return $this->nodes($xpath->query((new CssSelectorConverter())->toXPath($css, 'descendant::'), $context));
    }

    private function sole(\DOMXPath $xpath, string $css): \DOMElement
    {
        $nodes = $this->all($xpath, $css);
        $this->assertCount(1, $nodes, $css);

        return $nodes[0];
    }

    private function text(\DOMXPath $xpath, string $css): string
    {
        return $this->sole($xpath, $css)->textContent;
    }

    /**
     * @param list<\DOMElement> $cards
     */
    private function cardContaining(array $cards, string $text): \DOMElement
    {
        foreach ($cards as $card) {
            if (str_contains($card->textContent, $text)) {
                return $card;
            }
        }
        $this->fail("No card contains {$text}.");
    }

    /**
     * @return list<\DOMElement>
     */
    private function nodes(\DOMNodeList|false $list): array
    {
        $out = [];
        foreach ($list ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $out[] = $node;
            }
        }

        return $out;
    }
}
