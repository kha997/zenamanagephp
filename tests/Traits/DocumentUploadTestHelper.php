<?php declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Http\UploadedFile;

trait DocumentUploadTestHelper
{
    protected function documentUploadPayload(object $project, UploadedFile $file, array $overrides = []): array
    {
        $base = [
            'project_id' => $project->id,
            'name' => 'Test Document',
            'title' => 'Test Document',
            'description' => 'Test document description',
            'document_type' => 'drawing',
            'version' => '1.0',
            'tags' => ['test', 'drawing'],
        ];

        $payload = array_merge($base, $overrides);
        $payload['file'] = $file;

        return $payload;
    }

    protected function fakePdfFile(string $filename): UploadedFile
    {
        $content = "%PDF-1.4\n%âãÏÓ\n1 0 obj\n<< /Title (fake) >>\nendobj\n";

        return UploadedFile::fake()->createWithContent($filename, $content);
    }
}
