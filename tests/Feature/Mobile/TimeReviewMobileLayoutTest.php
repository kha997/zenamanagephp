<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-122 (Gate 2 L-1, A-1, N-1) — phone layout of "Duyệt giờ".
 *
 * - L-1: one card per submitted entry, grouped by person, with the person's total.
 * - A-1: each card carries its own approve form and reject-with-reason form (exactly that entry's id); each group carries
 *   an "approve all" form with that person's ids. The cards sit outside the desktop table's form and have no tick boxes.
 * - N-1: a one-line week bar for the phone.
 *
 * The desktop table is unchanged; visibility per screen width is CSS, measured with Playwright at Gate 3.
 */
class TimeReviewMobileLayoutTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $reviewer;

    private User $workerA;

    private User $workerB;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->reviewer = $this->createTenantUser($this->tenant, ['name' => 'PM Duyệt'], ['review_pm'], ['task.view', 'project.update']);
        $this->workerA = $this->createTenantUser($this->tenant, ['name' => 'Trần Văn Thợ'], ['review_worker'], ['task.view']);
        $this->workerB = $this->createTenantUser($this->tenant, ['name' => 'Lê Thị Giám Sát'], ['review_worker'], ['task.view']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Quận 7']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cards_grouped_by_person_with_totals_and_all_details(): void
    {
        $this->entry($this->workerA, '2026-11-03', 480, 'Đổ bê tông móng', TimeEntry::SOURCE_MANUAL);
        $this->entry($this->workerA, '2026-11-04', 90, 'Nghiệm thu thép');
        $this->entry($this->workerB, '2026-11-04', 60, 'Họp giao ban');

        $dom = $this->dom($this->open());

        $groups = $this->all($dom, '[data-testid="time-review-group"]');
        $this->assertCount(2, $groups);
        $groupA = $this->containing($groups, 'Trần Văn Thợ');
        $this->assertStringContainsString('9:30', $this->text($dom, $groupA, '[data-testid="time-review-group-total"]'));
        $this->assertCount(2, $this->within($dom, $groupA, '[data-testid="time-review-card"]'));
        $groupB = $this->containing($groups, 'Lê Thị Giám Sát');
        $this->assertStringContainsString('1:00', $this->text($dom, $groupB, '[data-testid="time-review-group-total"]'));

        $card = $this->containing($this->within($dom, $groupA, '[data-testid="time-review-card"]'), 'Đổ bê tông móng');
        foreach (['03/11', 'Nhà phố Quận 7', '8:00', 'Nhập tay'] as $text) {
            $this->assertStringContainsString($text, $card->textContent);
        }

        // No tick boxes in the phone cards, and the cards are not inside the desktop table's form.
        $this->assertCount(0, $this->all($dom, '[data-testid="time-review-cards"] input[type="checkbox"]'));
        $this->assertCount(0, $this->all($dom, '[data-testid="time-review-form"] [data-testid="time-review-card"]'));
        // Desktop table and its bulk form are still rendered.
        $this->assertCount(1, $this->all($dom, '[data-testid="time-review-form"] table'));
        $this->assertCount(3, $this->all($dom, '[data-testid="time-review-form"] input[type="checkbox"][name="ids[]"]'));
    }

    public function test_each_card_and_group_posts_exactly_its_ids(): void
    {
        $a1 = $this->entry($this->workerA, '2026-11-03', 480, 'Đổ bê tông móng');
        $a2 = $this->entry($this->workerA, '2026-11-04', 90, 'Nghiệm thu thép');
        $b1 = $this->entry($this->workerB, '2026-11-04', 60, 'Họp giao ban');

        $dom = $this->dom($this->open());
        $action = route('app.time.review.act');

        $card = $this->containing($this->all($dom, '[data-testid="time-review-card"]'), 'Nghiệm thu thép');
        $approve = $this->sole($dom, $card, 'form[data-testid="time-review-card-approve"]');
        $this->assertSame($action, $approve->getAttribute('action'));
        $this->assertSame([(string) $a2->id], $this->ids($dom, $approve));
        $this->assertCount(1, $this->within($dom, $approve, 'input[name="action"][value="approve"], button[name="action"][value="approve"]'));
        $this->assertCount(1, $this->within($dom, $approve, 'input[name="week"][value="2026-11-02"]'));

        $reject = $this->sole($dom, $card, 'form[data-testid="time-review-card-reject"]');
        $this->assertSame($action, $reject->getAttribute('action'));
        $this->assertSame([(string) $a2->id], $this->ids($dom, $reject));
        $reason = $this->sole($dom, $reject, 'input[name="reason"]');
        $this->assertTrue($reason->hasAttribute('required'));
        $this->assertCount(1, $this->within($dom, $reject, 'input[name="action"][value="reject"], button[name="action"][value="reject"]'));

        $groupA = $this->containing($this->all($dom, '[data-testid="time-review-group"]'), 'Trần Văn Thợ');
        $all = $this->sole($dom, $groupA, 'form[data-testid="time-review-group-approve"]');
        $this->assertEqualsCanonicalizing([(string) $a1->id, (string) $a2->id], $this->ids($dom, $all));
        $this->assertStringContainsString('Duyệt cả 2 dòng của Trần Văn Thợ', $all->textContent);
        $this->assertNotContains((string) $b1->id, $this->ids($dom, $all));
    }

    public function test_card_forms_act_on_one_entry_and_reject_needs_a_reason(): void
    {
        $a1 = $this->entry($this->workerA, '2026-11-03', 480, 'Đổ bê tông móng');
        $a2 = $this->entry($this->workerA, '2026-11-04', 90, 'Nghiệm thu thép');
        $this->open(); // the reviewer opens the page first (session / CSRF token), as on the phone

        $this->actingAs($this->reviewer)->post(route('app.time.review.act'), [
            'ids' => [(string) $a2->id], 'action' => 'reject', 'reason' => '', 'week' => '2026-11-02',
        ], ['X-Tenant-ID' => (string) $this->tenant->id])->assertSessionHasErrors('reason');
        $this->assertSame(TimeEntry::STATUS_SUBMITTED, $a2->fresh()?->status);

        $this->actingAs($this->reviewer)->post(route('app.time.review.act'), [
            'ids' => [(string) $a2->id], 'action' => 'approve', 'week' => '2026-11-02',
        ], ['X-Tenant-ID' => (string) $this->tenant->id])->assertRedirect(route('app.time.review', ['week' => '2026-11-02']));
        $this->assertSame(TimeEntry::STATUS_APPROVED, $a2->fresh()?->status);
        $this->assertSame(TimeEntry::STATUS_SUBMITTED, $a1->fresh()?->status);
    }

    public function test_phone_week_bar_has_previous_next_and_own_timesheet_links(): void
    {
        $this->entry($this->workerA, '2026-11-03', 60, 'Họp');
        $dom = $this->dom($this->open());

        $bar = $this->all($dom, '[data-testid="time-review-week-bar"]');
        $this->assertCount(1, $bar);
        $hrefs = array_map(fn (\DOMElement $a): string => $a->getAttribute('href'), $this->within($dom, $bar[0], 'a'));
        $this->assertContains(route('app.time.review', ['week' => '2026-10-26']), $hrefs);
        $this->assertContains(route('app.time.review', ['week' => '2026-11-09']), $hrefs);
        $this->assertContains(route('app.time.index'), $hrefs);
    }

    public function test_empty_week_shows_no_cards(): void
    {
        $dom = $this->dom($this->open());
        $this->assertCount(0, $this->all($dom, '[data-testid="time-review-card"]'));
        $this->assertStringContainsString('Không có giờ chờ duyệt', (string) $dom->document->textContent);
    }

    private function entry(User $user, string $date, int $minutes, string $note, string $source = TimeEntry::SOURCE_TIMER): TimeEntry
    {
        /** @var TimeEntry $entry */
        $entry = TimeEntry::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $this->tenant->id, 'user_id' => (string) $user->id, 'project_id' => (string) $this->project->id,
            'category' => TimeEntry::CATEGORY_MEETING, 'work_date' => $date, 'minutes' => $minutes, 'source' => $source, 'note' => $note,
            'status' => TimeEntry::STATUS_SUBMITTED,
        ]);

        return $entry;
    }

    private function open(): TestResponse
    {
        return $this->actingAs($this->reviewer)->get(route('app.time.review', ['week' => '2026-11-02']), ['X-Tenant-ID' => (string) $this->tenant->id])->assertOk();
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
     * @return list<string>
     */
    private function ids(\DOMXPath $xpath, \DOMElement $form): array
    {
        return array_map(fn (\DOMElement $input): string => $input->getAttribute('value'), $this->within($xpath, $form, 'input[name="ids[]"]'));
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

    private function sole(\DOMXPath $xpath, \DOMElement $context, string $css): \DOMElement
    {
        $nodes = $this->within($xpath, $context, $css);
        $this->assertCount(1, $nodes, $css);

        return $nodes[0];
    }

    private function text(\DOMXPath $xpath, \DOMElement $context, string $css): string
    {
        return $this->sole($xpath, $context, $css)->textContent;
    }

    /**
     * @param list<\DOMElement> $nodes
     */
    private function containing(array $nodes, string $text): \DOMElement
    {
        foreach ($nodes as $node) {
            if (str_contains($node->textContent, $text)) {
                return $node;
            }
        }
        $this->fail("No element contains {$text}.");
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
