<?php declare(strict_types=1);

namespace App\Support\Navigation;

final class NavAuthorizationRequirement
{
    /**
     * @param 'rbac'|'can'|'baseline'|'unresolvable' $type
     * @param string[] $permissions
     */
    private function __construct(
        public readonly string $type,
        public readonly array $permissions = [],
        public readonly ?string $ability = null,
        public readonly ?string $subjectClass = null,
    ) {
    }

    /**
     * @param string[] $permissions
     */
    public static function rbac(array $permissions): self
    {
        return new self('rbac', $permissions);
    }

    public static function can(string $ability, string $subjectClass): self
    {
        return new self('can', [], $ability, $subjectClass);
    }

    public static function baseline(): self
    {
        return new self('baseline');
    }

    public static function unresolvable(): self
    {
        return new self('unresolvable');
    }
}
