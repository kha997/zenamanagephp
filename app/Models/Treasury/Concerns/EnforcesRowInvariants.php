<?php declare(strict_types=1);

namespace App\Models\Treasury\Concerns;

/**
 * Enforces single-row CHECK-equivalents identically on MySQL and SQLite.
 *
 * Laravel 12's Schema Blueprint has no fluent check() builder, and SQLite
 * cannot ALTER TABLE ADD CONSTRAINT CHECK after table creation — so these
 * design-doc-mandated row-level invariants are enforced here, in the model's
 * `saving` event, instead of as literal SQL CHECK constraints. Multi-row /
 * multi-table invariants (settlement conservation, lock ordering, the
 * reversal state machine) are explicitly NOT covered by this trait — see
 * docs/superpowers/plans/2026-08-17-gap037-treasury-schema-migrations.md's
 * Global Constraints section.
 *
 * Consuming models declare whichever of the five config arrays they need
 * as their own `protected static array $<name> = [...]` properties:
 *
 * - `$positiveAmountColumns` — list<string> columns that must be > 0 when set
 * - `$mutuallyExclusivePairs` — list<array{0:string,1:string}>, at most one of
 *   the pair may be non-null
 * - `$exactlyOneOfGroups` — list<list<string>>, exactly one column in each
 *   group must be non-null
 * - `$coNullablePairs` — list<array{0:string,1:string}>, both null together
 *   or both non-null together
 * - `$allowedValues` — array<string,list<string>>, column => allowed values
 *
 * Deliberately NOT declared as properties on this trait: PHP raises a fatal
 * "definition differs and is considered incompatible" error when a class
 * using a trait redeclares a typed trait property with a different default
 * value (verified on PHP 8.2 — this is not version-specific trait/property
 * override behavior, it applies regardless of `static`). Since every
 * consuming model needs a *different* default for these arrays, the trait
 * cannot declare them itself; `config()` below reads them via
 * `property_exists()` instead, defaulting to `[]` for any array the
 * consuming model doesn't declare.
 */
trait EnforcesRowInvariants
{
    protected static function bootEnforcesRowInvariants(): void
    {
        static::saving(function ($model): void {
            $model->runTreasuryRowChecks();
        });
    }

    /** @return array<mixed> */
    protected static function treasuryRowInvariantConfig(string $property): array
    {
        return property_exists(static::class, $property) ? (array) static::${$property} : [];
    }

    protected function runTreasuryRowChecks(): void
    {
        foreach (static::treasuryRowInvariantConfig('positiveAmountColumns') as $column) {
            $value = $this->getAttribute($column);
            if ($value !== null && (float) $value <= 0) {
                throw new \InvalidArgumentException("{$column} must be > 0, got {$value}");
            }
        }

        foreach (static::treasuryRowInvariantConfig('mutuallyExclusivePairs') as [$a, $b]) {
            if ($this->getAttribute($a) !== null && $this->getAttribute($b) !== null) {
                throw new \InvalidArgumentException("{$a} and {$b} are mutually exclusive — at most one may be set");
            }
        }

        foreach (static::treasuryRowInvariantConfig('exactlyOneOfGroups') as $group) {
            $setCount = 0;
            foreach ($group as $column) {
                if ($this->getAttribute($column) !== null) {
                    $setCount++;
                }
            }
            if ($setCount !== 1) {
                $list = implode(', ', $group);
                throw new \InvalidArgumentException("exactly one of [{$list}] must be set, got {$setCount}");
            }
        }

        foreach (static::treasuryRowInvariantConfig('coNullablePairs') as [$a, $b]) {
            $aSet = $this->getAttribute($a) !== null;
            $bSet = $this->getAttribute($b) !== null;
            if ($aSet !== $bSet) {
                throw new \InvalidArgumentException("{$a} and {$b} must be both null or both set together");
            }
        }

        foreach (static::treasuryRowInvariantConfig('allowedValues') as $column => $values) {
            $value = $this->getAttribute($column);
            if ($value !== null && !in_array($value, $values, true)) {
                $list = implode(', ', $values);
                throw new \InvalidArgumentException("{$column} must be one of [{$list}], got {$value}");
            }
        }
    }
}
