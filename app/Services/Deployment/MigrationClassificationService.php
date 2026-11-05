<?php

declare(strict_types=1);

namespace App\Services\Deployment;

class MigrationClassificationService
{
    /** @var array<string, string|null> migration basename => "expand"|"breaking"|null classification. */
    private array $manifest;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $migrationsPath,
    ) {
        $raw = is_file($this->manifestPath) ? file_get_contents($this->manifestPath) : '{}';
        $decoded = json_decode($raw ?: '{}', true);
        $this->manifest = is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, string> migration file basenames (no .php) present on disk but not yet recorded as ran.
     */
    public function pendingMigrationFiles(): array
    {
        $files = glob(rtrim($this->migrationsPath, '/') . '/*.php') ?: [];
        $names = array_map(static fn (string $f): string => basename($f, '.php'), $files);
        sort($names);

        // GAP-115: a brand-new database has no migrations table yet — nothing has run, so every file is pending (the
        // migrator creates the table itself).
        $ran = \Illuminate\Support\Facades\Schema::hasTable('migrations')
            ? \Illuminate\Support\Facades\DB::table('migrations')->pluck('migration')->all()
            : [];

        return array_values(array_diff($names, $ran));
    }

    /**
     * GAP-115: true when no migration has ever run on this database (no migrations table, or an empty one) — a first
     * install, where no running release or data depends on the existing schema.
     */
    public function isFreshDatabase(): bool
    {
        return !\Illuminate\Support\Facades\Schema::hasTable('migrations')
            || !\Illuminate\Support\Facades\DB::table('migrations')->exists();
    }

    /**
     * @param array<int, string> $files
     * @return array<string, string|null>
     */
    public function classificationsFor(array $files): array
    {
        $result = [];
        foreach ($files as $file) {
            $result[$file] = $this->manifest[$file] ?? null;
        }

        return $result;
    }

    /**
     * @param array<int, string> $files
     */
    public function hasUnclassified(array $files): bool
    {
        return in_array(null, $this->classificationsFor($files), true);
    }

    /**
     * @param array<int, string> $files
     */
    public function hasBreaking(array $files): bool
    {
        return in_array('breaking', $this->classificationsFor($files), true);
    }
}
