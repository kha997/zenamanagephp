<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Forward-guard: no new file under app/ should import
 * Src\DocumentManagement\Models\LegacyDocumentAdapter.
 *
 * The adapter is a subclass rỗng (empty shell) of App\Models\Document and is
 * being phased out. All upload paths must use App\Models\Document directly.
 *
 * If you genuinely need the adapter in a new file, add that file to the
 * ALLOWED_FILES list below and note the rationale in
 * docs/architecture/document-upload-path-inventory.md.
 */
class LegacyDocumentAdapterImportGuardTest extends TestCase
{
    /**
     * Files that may still reference LegacyDocumentAdapter.
     * Empty by default — any addition requires inventory doc update first.
     */
    private const ALLOWED_FILES = [];

    public function test_no_new_import_of_legacy_document_adapter_in_app(): void
    {
        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in(base_path('app'));

        $allowedRealPaths = array_map(
            static fn (string $relative): string => base_path($relative),
            self::ALLOWED_FILES
        );

        $unexpected = [];

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();

            if ($realPath === false) {
                continue;
            }

            if (in_array($realPath, $allowedRealPaths, true)) {
                continue;
            }

            $contents = file_get_contents($realPath);

            if ($contents === false) {
                continue;
            }

            if (preg_match('/(?:\\\\)?Src\\\\DocumentManagement\\\\Models\\\\LegacyDocumentAdapter(?![A-Za-z0-9_])/', $contents)) {
                $unexpected[] = str_replace(base_path() . '/', '', $realPath);
            }
        }

        $this->assertSame(
            [],
            $unexpected,
            "New file(s) import LegacyDocumentAdapter under app/: " . implode(', ', $unexpected)
            . ". Use App\\Models\\Document instead. If you truly need the adapter, add the file to ALLOWED_FILES and update docs/architecture/document-upload-path-inventory.md."
        );
    }

    public function test_no_new_import_of_legacy_document_adapter_in_routes(): void
    {
        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in(base_path('routes'));

        $unexpected = [];

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();

            if ($realPath === false) {
                continue;
            }

            $contents = file_get_contents($realPath);

            if ($contents === false) {
                continue;
            }

            if (preg_match('/(?:\\\\)?Src\\\\DocumentManagement\\\\Models\\\\LegacyDocumentAdapter(?![A-Za-z0-9_])/', $contents)) {
                $unexpected[] = str_replace(base_path() . '/', '', $realPath);
            }
        }

        $this->assertSame(
            [],
            $unexpected,
            "File(s) under routes/ import LegacyDocumentAdapter: " . implode(', ', $unexpected)
        );
    }
}
