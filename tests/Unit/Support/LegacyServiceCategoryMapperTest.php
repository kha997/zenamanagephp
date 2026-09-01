<?php declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LegacyServiceCategoryMapper;
use App\Support\ServiceLine;
use PHPUnit\Framework\TestCase;

class LegacyServiceCategoryMapperTest extends TestCase
{
    public static function designFamilyProvider(): array
    {
        return [
            ['architecture'], ['interior'], ['landscape'], ['structure'], ['mep'],
        ];
    }

    /** @dataProvider designFamilyProvider */
    public function test_design_family_maps_to_design(string $legacy): void
    {
        $this->assertSame(ServiceLine::DESIGN, LegacyServiceCategoryMapper::mapToServiceLine($legacy));
    }

    public function test_construction_maps_to_construction(): void
    {
        $this->assertSame(ServiceLine::CONSTRUCTION, LegacyServiceCategoryMapper::mapToServiceLine('construction'));
    }

    public static function ambiguousProvider(): array
    {
        return [['inspection'], ['consulting'], ['combined_package'], [null], ['not_a_real_value']];
    }

    /** @dataProvider ambiguousProvider */
    public function test_ambiguous_or_unrecognized_maps_to_null(?string $legacy): void
    {
        $this->assertNull(LegacyServiceCategoryMapper::mapToServiceLine($legacy));
    }
}
