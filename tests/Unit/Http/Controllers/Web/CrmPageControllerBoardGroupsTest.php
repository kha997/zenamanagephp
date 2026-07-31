<?php declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Web;

use App\Http\Controllers\Web\CrmPageController;
use App\Models\Opportunity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CrmPageControllerBoardGroupsTest extends TestCase
{
    /** @return array<string, array{label:string,stages:list<string>,default_entry_stage:?string,requires_choice?:bool,choice_options?:list<array{stage:string,label:string,requires_reason:bool,terminal:bool}>}> */
    private function boardGroups(): array
    {
        return (new ReflectionClass(CrmPageController::class))->getConstant('BOARD_GROUPS');
    }

    public function test_group_key_set_matches_expected_stable_keys(): void
    {
        // KHÔNG dùng array_keys() === array_unique(array_keys()): PHP associative
        // array không thể có key literal trùng nhau (key sau ghi đè key trước ngay ở
        // compile-time), nên so sánh đó là tautology, không bảo vệ được gì. Test này
        // khóa đúng TẬP HỢP 6 key ổn định — sort cả 2 phía trước khi so sánh vì thứ tự
        // hiển thị (nếu là contract) được kiểm ở test riêng bên dưới, không trộn vào đây.
        $expected = ['consulting_survey', 'lost_nurture', 'negotiation_contract', 'new', 'quote', 'won'];
        $actual = array_keys($this->boardGroups());
        sort($actual);

        $this->assertSame($expected, $actual);

        foreach (array_keys($this->boardGroups()) as $key) {
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*$/',
                $key,
                "Group key '{$key}' không đúng định dạng snake_case."
            );
        }
    }

    public function test_no_stage_belongs_to_more_than_one_group_and_union_matches_valid_stages(): void
    {
        $groups = $this->boardGroups();
        $seen = [];

        foreach ($groups as $groupKey => $group) {
            foreach ($group['stages'] as $stage) {
                $previousGroupKey = $seen[$stage] ?? '';
                $this->assertArrayNotHasKey(
                    $stage,
                    $seen,
                    "Stage '{$stage}' xuất hiện ở cả group '{$previousGroupKey}' và '{$groupKey}'."
                );
                $seen[$stage] = $groupKey;
            }
        }

        $union = array_keys($seen);
        sort($union);
        $validStages = Opportunity::VALID_STAGES;
        sort($validStages);
        $this->assertSame($validStages, $union);
    }

    public function test_default_entry_stage_belongs_to_its_own_group_stages(): void
    {
        foreach ($this->boardGroups() as $groupKey => $group) {
            if ($group['default_entry_stage'] === null) {
                continue;
            }
            $this->assertContains(
                $group['default_entry_stage'],
                $group['stages'],
                "Group '{$groupKey}': default_entry_stage không thuộc stages của chính nó."
            );
        }
    }

    public function test_requires_choice_group_has_no_default_entry_stage(): void
    {
        foreach ($this->boardGroups() as $groupKey => $group) {
            if (!empty($group['requires_choice'])) {
                $this->assertNull(
                    $group['default_entry_stage'],
                    "Group '{$groupKey}' có requires_choice=true nhưng vẫn có default_entry_stage — gây mơ hồ target stage."
                );
            }
        }
    }

    public function test_choice_options_stages_belong_to_their_own_group_stages(): void
    {
        foreach ($this->boardGroups() as $groupKey => $group) {
            if (empty($group['choice_options'])) {
                continue;
            }
            foreach ($group['choice_options'] as $option) {
                $this->assertContains(
                    $option['stage'],
                    $group['stages'],
                    "Group '{$groupKey}': choice_option stage '{$option['stage']}' không thuộc stages."
                );
            }
        }
    }
}
