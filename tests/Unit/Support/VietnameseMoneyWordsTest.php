<?php declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\VietnameseMoneyWords;
use Tests\TestCase;

class VietnameseMoneyWordsTest extends TestCase
{
    /**
     * @dataProvider amountProvider
     */
    public function test_to_words_returns_correct_vietnamese_string(float $amount, string $expected): void
    {
        $this->assertSame($expected, VietnameseMoneyWords::toWords($amount));
    }

    /** @return array<string, array{float, string}> */
    public static function amountProvider(): array
    {
        return [
            'zero' => [0, 'Không đồng'],
            'one_thousand' => [1000, 'Một nghìn đồng'],
            'fifteen_million' => [15000000, 'Mười lăm triệu đồng'],
            'two_hundred_twenty_five_million' => [225000000, 'Hai trăm hai mươi lăm triệu đồng'],
            'six_hundred_twenty_million' => [620000000, 'Sáu trăm hai mươi triệu đồng'],
            'one_billion_two_hundred...' => [1234567890, 'Một tỷ hai trăm ba mươi tư triệu năm trăm sáu mươi bảy nghìn tám trăm chín mươi đồng'],
            'hundred_one_with_linh' => [101, 'Một trăm linh một đồng'],
            'twenty_one_with_mot' => [21, 'Hai mươi mốt đồng'],
            'five_million_five_with_linh' => [5000005, 'Năm triệu không trăm linh năm đồng'],
        ];
    }
}
