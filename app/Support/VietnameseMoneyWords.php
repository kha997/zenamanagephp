<?php declare(strict_types=1);

namespace App\Support;

class VietnameseMoneyWords
{
    /** @var list<string> */
    private static array $ones = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

    /** @var list<string> */
    private static array $tens = ['', 'mười', 'hai mươi', 'ba mươi', 'bốn mươi', 'năm mươi', 'sáu mươi', 'bảy mươi', 'tám mươi', 'chín mươi'];

    /**
     * Convert a float amount to Vietnamese words.
     * Rounds to nearest integer dong.
     */
    public static function toWords(float $amount): string
    {
        $rounded = (int) round($amount);

        if ($rounded === 0) {
            return 'Không đồng';
        }

        $result = self::convertNumber($rounded);

        $result = mb_strtoupper(mb_substr($result, 0, 1)) . mb_substr($result, 1);

        return $result . ' đồng';
    }

    private static function convertNumber(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        $parts = [];

        $tyBillion = intdiv($number, 1_000_000_000);
        $number %= 1_000_000_000;

        $trieuMillion = intdiv($number, 1_000_000);
        $number %= 1_000_000;

        $nghinThousand = intdiv($number, 1_000);
        $number %= 1_000;

        $donVi = $number;

        if ($tyBillion > 0) {
            $parts[] = self::convertGroup($tyBillion) . ' tỷ';
        }

        if ($trieuMillion > 0) {
            $parts[] = self::convertGroupWithLeadingZero($trieuMillion, $tyBillion > 0) . ' triệu';
        }

        if ($nghinThousand > 0) {
            $parts[] = self::convertGroupWithLeadingZero($nghinThousand, $tyBillion > 0 || $trieuMillion > 0) . ' nghìn';
        }

        if ($donVi > 0) {
            $parts[] = self::convertGroupWithLeadingZero($donVi, $tyBillion > 0 || $trieuMillion > 0 || $nghinThousand > 0);
        }

        return implode(' ', $parts);
    }

    private static function convertGroup(int $num): string
    {
        $result = '';

        $hangTram = intdiv($num, 100);
        $num %= 100;

        $hangChuc = intdiv($num, 10);
        $hangDonVi = $num % 10;

        if ($hangTram > 0) {
            $result .= self::$ones[$hangTram] . ' trăm';
        }

        if ($hangChuc > 0) {
            $result .= ($result !== '' ? ' ' : '') . self::$tens[$hangChuc];
        }

        if ($hangDonVi > 0) {
            if ($hangChuc >= 2) {
                $word = match ($hangDonVi) {
                    1 => 'mốt',
                    4 => 'tư',
                    5 => 'lăm',
                    default => self::$ones[$hangDonVi],
                };
                $result .= ' ' . $word;
            } elseif ($hangChuc === 1) {
                $word = $hangDonVi === 5 ? 'lăm' : ($hangDonVi === 4 ? 'tư' : self::$ones[$hangDonVi]);
                $result .= ' ' . $word;
            } elseif ($hangTram > 0) {
                $result .= ' linh ' . self::$ones[$hangDonVi];
            } else {
                $result .= self::$ones[$hangDonVi];
            }
        }

        return $result;
    }

    private static function convertGroupWithLeadingZero(int $num, bool $hasHigherGroup): string
    {
        $hangTram = intdiv($num, 100);
        $rest = $num % 100;

        if ($hangTram === 0 && $hasHigherGroup && $rest > 0) {
            $hangChuc = intdiv($rest, 10);
            $hangDonVi = $rest % 10;
            if ($hangChuc === 0 && $hangDonVi > 0) {
                return 'không trăm linh ' . self::$ones[$hangDonVi];
            }
            return 'không trăm ' . self::convertGroup($rest);
        }

        return self::convertGroup($num);
    }
}
