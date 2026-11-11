<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Ncr;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\SiteDiary;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-125 (Gate 2 C-1, D-1) — phone layout of the field pages.
 *
 * - C-1: cards below 768px for the site-diary list (with the submit / approve forms by status), the inspection list and
 *   the NCR table on the inspection detail; the desktop tables are still rendered (visibility per width is CSS).
 * - D-1: on the inspection detail "Thông tin" is marked to come first on the phone, and the description, results and the
 *   two forms are collapsible sections (open by default, closed on the phone by a small script).
 */
class FieldPagesMobileLayoutTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $engineer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->engineer = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư Bình'], ['field_engineer'], [
            'site_diary.view', 'site_diary.create', 'site_diary.approve', 'inspection.view', 'inspection.create', 'inspection.edit',
        ]);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Quận 7', 'code' => 'PRJ-Q7']);
    }

    public function test_site_diary_list_renders_cards_with_details_and_the_right_action(): void
    {
        $draft = $this->diary('2026-11-10', 'draft', 'Đổ bê tông cột tầng 2 trục A-C', 14);
        $submitted = $this->diary('2026-11-09', 'submitted', 'Lắp dựng cốp pha dầm sàn', 12);

        $dom = $this->dom($this->open(route('operator.site-diaries.index')));
        $cards = $this->all($dom, '[data-testid="site-diary-card"]');
        $this->assertCount(2, $cards);

        $draftCard = $this->containing($cards, '10/11/2026');
        foreach (['Nhà phố Quận 7', 'Đổ bê tông cột tầng 2 trục A-C', '14 người', 'Kỹ sư Bình'] as $text) {
            $this->assertStringContainsString($text, $draftCard->textContent);
        }
        $this->assertSame(route('operator.site-diaries.show', $draft->id), $this->sole($dom, $draftCard, 'a[data-testid="site-diary-card-open"]')->getAttribute('href'));
        $this->assertSame(route('operator.site-diaries.submit', $draft->id), $this->sole($dom, $draftCard, 'form')->getAttribute('action'));

        $submittedCard = $this->containing($cards, '09/11/2026');
        $this->assertSame(route('operator.site-diaries.approve', $submitted->id), $this->sole($dom, $submittedCard, 'form')->getAttribute('action'));

        // Desktop table still rendered, inside the wide-only wrapper; cards inside the phone-only wrapper.
        $this->assertCount(1, $this->all($dom, '.operator-wide-only table'));
        $this->assertCount(2, $this->all($dom, '.operator-phone-only [data-testid="site-diary-card"]'));
    }

    public function test_inspection_list_renders_cards(): void
    {
        $inspection = $this->inspection('in_progress');

        $dom = $this->dom($this->open(route('operator.inspections.index')));
        $card = $this->containing($this->all($dom, '[data-testid="inspection-card"]'), 'Nghiệm thu cốt thép sàn tầng 2');
        foreach (['Kế hoạch QC phần thô', 'Kỹ sư Bình', '11/11/2026', 'Đang thực hiện'] as $text) {
            $this->assertStringContainsString($text, $card->textContent);
        }
        $this->assertSame(route('operator.inspections.show', $inspection->id), $this->sole($dom, $card, 'a[data-testid="inspection-card-open"]')->getAttribute('href'));
        $this->assertCount(1, $this->all($dom, '.operator-wide-only table'));
    }

    public function test_inspection_detail_puts_info_first_collapses_long_sections_and_renders_ncr_cards(): void
    {
        $inspection = $this->inspection('in_progress');
        $ncr = Ncr::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'inspection_id' => (string) $inspection->id,
            'title' => 'Thép chờ cột C3 thiếu chiều dài neo', 'severity' => 'high', 'status' => 'open',
            'created_by' => (string) $this->engineer->id, 'assigned_to' => (string) $this->engineer->id,
        ]);

        $dom = $this->dom($this->open(route('operator.inspections.show', $inspection->id)));

        // D-1: "Thông tin" first on the phone; four collapsible sections, open by default (works without JS).
        $info = $this->only($dom, '.operator-phone-first');
        $this->assertStringContainsString('Đang thực hiện', $info->textContent);
        $sections = $this->all($dom, 'details[data-phone-collapse]');
        $this->assertCount(4, $sections);
        foreach ($sections as $section) {
            $this->assertTrue($section->hasAttribute('open'));
            $this->assertCount(1, $this->within($dom, $section, 'summary'));
        }
        $this->assertCount(1, $this->all($dom, 'details[data-phone-collapse] form textarea[name="findings"]'));
        $this->assertCount(1, $this->all($dom, 'details[data-phone-collapse] form input[name="title"]'));
        $this->assertStringContainsString('data-phone-collapse', (string) $dom->document->saveHTML($this->only($dom, 'script[data-phone-collapse-script]')));

        // C-1: NCR card.
        $card = $this->only($dom, '[data-testid="ncr-card"]');
        foreach ([(string) $ncr->ncr_number, 'Thép chờ cột C3 thiếu chiều dài neo', 'Cao', 'Đang mở', 'Kỹ sư Bình'] as $text) {
            $this->assertStringContainsString($text, $card->textContent);
        }
        // The whole card is the link to the NCR.
        $this->assertSame('a', $card->tagName);
        $this->assertSame(route('operator.inspections.ncrs.show', ['inspection' => $inspection->id, 'ncr' => $ncr->id]), $card->getAttribute('href'));
        $this->assertCount(1, $this->all($dom, '.operator-wide-only table'));
    }

    public function test_validation_errors_keep_the_completion_form_open_on_the_phone(): void
    {
        $inspection = $this->inspection('in_progress');
        $response = $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag(['findings' => 'Bắt buộc']))])
            ->open(route('operator.inspections.show', $inspection->id));
        $dom = $this->dom($response);
        // The error bag cannot tell which form failed, so both forms stay open; description and results still collapse.
        $this->assertCount(2, $this->all($dom, 'details[data-phone-collapse]'));
        $this->assertCount(1, $this->all($dom, 'details.operator-phone-collapse:not([data-phone-collapse]) textarea[name="findings"]'));
        $this->assertCount(1, $this->all($dom, 'details.operator-phone-collapse:not([data-phone-collapse]) input[name="title"]'));
    }

    private function diary(string $date, string $status, string $work, int $manpower): SiteDiary
    {
        return SiteDiary::query()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'diary_number' => 'NK-' . $date,
            'diary_date' => $date, 'weather' => 'Nắng', 'manpower_count' => $manpower, 'work_performed' => $work, 'status' => $status,
            'created_by' => (string) $this->engineer->id,
        ]);
    }

    private function inspection(string $status): QcInspection
    {
        $plan = QcPlan::factory()->create(['tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'title' => 'Kế hoạch QC phần thô', 'created_by' => (string) $this->engineer->id]);

        return QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'qc_plan_id' => (string) $plan->id, 'inspector_id' => (string) $this->engineer->id,
            'title' => 'Nghiệm thu cốt thép sàn tầng 2', 'inspection_date' => '2026-11-11', 'status' => $status,
            'description' => 'Kiểm tra cốt thép sàn trước khi đổ bê tông.', 'findings' => 'Thép chờ cột C3 thiếu neo.', 'recommendations' => 'Bổ sung neo.',
        ]);
    }

    private function open(string $url): TestResponse
    {
        return $this->actingAs($this->engineer)->get($url, ['X-Tenant-ID' => (string) $this->tenant->id]);
    }

    private function dom(TestResponse $response): \DOMXPath
    {
        $response->assertOk();
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

    private function only(\DOMXPath $xpath, string $css): \DOMElement
    {
        $nodes = $this->all($xpath, $css);
        $this->assertCount(1, $nodes, $css);

        return $nodes[0];
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
