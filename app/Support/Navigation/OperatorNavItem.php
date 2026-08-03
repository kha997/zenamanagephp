<?php declare(strict_types=1);

namespace App\Support\Navigation;

final class OperatorNavItem
{
    public function __construct(
        public readonly string $label,
        public readonly string $routeName,
        public readonly string $section,
        public readonly string $iconKey,
    ) {
    }
}
