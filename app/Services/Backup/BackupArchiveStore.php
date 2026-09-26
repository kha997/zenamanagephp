<?php

namespace App\Services\Backup;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Where finished backup archives live and how long they are kept (GAP-054).
 * disk === null keeps the pre-GAP-054 storage/backups directory.
 */
final class BackupArchiveStore
{
    public const TYPED_ARCHIVE_PATTERN = '/^backup_(full|database|files|config)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/';

    public function __construct(private readonly ?string $disk, private readonly string $path)
    {
    }

    public static function fromConfig(): self
    {
        $disk = config('backup.disk');

        return new self(is_string($disk) && $disk !== '' ? $disk : null, trim((string) config('backup.path', 'backups'), '/'));
    }

    public function stagingRoot(): string
    {
        return storage_path('backups');
    }

    public function store(string $localArchive): string
    {
        if ($this->disk === null) {
            return $localArchive;
        }

        $target = $this->path . '/' . basename($localArchive);
        $stream = fopen($localArchive, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Cannot read backup archive for upload');
        }

        try {
            $written = $this->filesystem()->writeStream($target, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written !== true) {
            throw new \RuntimeException("Backup disk '{$this->disk}' rejected the archive; nothing was pruned");
        }

        unlink($localArchive);

        return $this->disk . ':' . $target;
    }

    public function prune(string $type): int
    {
        $limits = config("backup.retention.{$type}") ?? config('backup.retention.full');
        $maxBackups = (int) ($limits['max_backups'] ?? 30);
        $cutoff = time() - ((int) ($limits['max_age_days'] ?? 30) * 86400);

        $archives = array_values(array_filter(
            $this->typedArchives(),
            static fn (array $a): bool => $a['type'] === $type,
        ));
        usort($archives, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        $deleted = 0;
        foreach ($archives as $index => $archive) {
            if ($index >= $maxBackups || $archive['mtime'] < $cutoff) {
                $this->delete($archive['name']);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function newestTimestamp(): ?int
    {
        $newest = null;

        if ($this->disk === null) {
            foreach (['/backup_*.tar.gz', '/backup_*', '/*.sql', '/*.sql.gz'] as $pattern) {
                foreach (glob($this->stagingRoot() . $pattern) ?: [] as $path) {
                    $mtime = filemtime($path);
                    if ($mtime !== false && ($newest === null || $mtime > $newest)) {
                        $newest = $mtime;
                    }
                }
            }

            return $newest;
        }

        foreach ($this->filesystem()->files($this->path) as $file) {
            if (str_starts_with(basename($file), 'backup_')) {
                $mtime = $this->filesystem()->lastModified($file);
                $newest = $newest === null ? $mtime : max($newest, $mtime);
            }
        }

        return $newest;
    }

    /** @return list<string> absolute local directories a files backup must never copy */
    public function localPathsExcludedFromFileBackups(): array
    {
        $paths = [$this->stagingRoot(), storage_path('app/' . trim((string) config('database.backup.path', 'backups/database'), '/'))];

        if ($this->disk !== null && config("filesystems.disks.{$this->disk}.driver") === 'local') {
            $root = (string) config("filesystems.disks.{$this->disk}.root");
            $paths[] = rtrim($root, '/') . '/' . $this->path;
        }

        return $paths;
    }

    /** @return list<array{name: string, type: string, mtime: int}> */
    private function typedArchives(): array
    {
        $result = [];

        if ($this->disk === null) {
            foreach (glob($this->stagingRoot() . '/backup_*.tar.gz') ?: [] as $path) {
                $name = basename($path);
                if (preg_match(self::TYPED_ARCHIVE_PATTERN, $name, $m) === 1) {
                    $result[] = ['name' => $name, 'type' => $m[1], 'mtime' => (int) filemtime($path)];
                }
            }

            return $result;
        }

        foreach ($this->filesystem()->files($this->path) as $file) {
            $name = basename($file);
            if (preg_match(self::TYPED_ARCHIVE_PATTERN, $name, $m) === 1) {
                $result[] = ['name' => $name, 'type' => $m[1], 'mtime' => $this->filesystem()->lastModified($file)];
            }
        }

        return $result;
    }

    private function delete(string $name): void
    {
        if ($this->disk === null) {
            @unlink($this->stagingRoot() . '/' . $name);

            return;
        }

        $this->filesystem()->delete($this->path . '/' . $name);
    }

    private function filesystem(): Filesystem
    {
        return Storage::disk((string) $this->disk);
    }
}
