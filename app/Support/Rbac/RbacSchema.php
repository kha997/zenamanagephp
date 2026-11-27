<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use Illuminate\Support\Facades\Schema;

/**
 * OWN-2026-024 H2b — once-per-process check that the H2b tables exist, so code deployed ahead of its migration neither crashes
 * every permission check nor fails open on platform authority (PlatformOperators fails closed; revocations cannot exist yet).
 * Reset in tests through reset() whenever the schema is rebuilt.
 */
final class RbacSchema
{
    private static ?bool $revocations = null;

    private static ?bool $operators = null;

    private static ?bool $grantSource = null;

    /**
     * Pivot attributes recording the grant source, or [] before the H2b migration (so seeding / role sync keep working on a
     * schema that does not have the column yet).
     *
     * @return array<string, string>
     */
    public static function grantSourceAttributes(string $source): array
    {
        self::$grantSource ??= Schema::hasColumn('role_permissions', 'grant_source');

        return self::$grantSource ? ['grant_source' => $source] : [];
    }

    public static function hasRevocations(): bool
    {
        // OWN-2026-024 H3 (Gate 7 review): only a PRESENT table is memoized; an absent one is re-checked, so a long-lived worker
        // started before the migration starts honouring revocations as soon as the migration lands (no stale fail-open cache).
        if (self::$revocations !== null) {
            return self::$revocations;
        }
        $present = Schema::hasTable('role_permission_revocations');

        return $present ? (self::$revocations = true) : false;
    }

    public static function hasPlatformOperators(): bool
    {
        if (self::$operators !== null) {
            return self::$operators;
        }
        $present = Schema::hasTable('platform_operators');

        return $present ? (self::$operators = true) : false;
    }

    /**
     * OWN-2026-024 H3 (Gate 3 N): sensitive RBAC writes (durable revocation, re-grant, Platform Operator provisioning) refuse to
     * run on a schema without the H2b tables instead of half-applying or relying on a later SQL error.
     */
    public static function assertMigrated(): void
    {
        if (!self::hasRevocations() || !self::hasPlatformOperators()) {
            throw new \RuntimeException('The OWN-2026-024 H2b RBAC migration is not applied; refusing a sensitive RBAC operation.');
        }
    }

    public static function reset(): void
    {
        self::$revocations = null;
        self::$operators = null;
        self::$grantSource = null;
    }
}
