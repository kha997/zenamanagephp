<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Document;
use App\Services\SecureUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecureUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        $this->service = new SecureUploadService();
        
        Storage::fake('local');
    }

    /** @test */
    public function it_can_validate_file_types()
    {
        // Test valid file types
        $validFiles = [
            UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('image.jpg', 100, 'image/jpeg'),
            UploadedFile::fake()->create('spreadsheet.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            UploadedFile::fake()->create('text.txt', 100, 'text/plain'),
        ];

        foreach ($validFiles as $file) {
            $result = $this->service->validateFileType($file);
            $this->assertTrue($result['valid'], "File type {$file->getMimeType()} should be valid");
        }
    }

    /** @test */
    public function it_rejects_invalid_file_types()
    {
        // Test invalid file types
        $invalidFiles = [
            UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload'),
            UploadedFile::fake()->create('malware.bat', 100, 'application/x-msdos-program'),
            UploadedFile::fake()->create('virus.scr', 100, 'application/x-screensaver'),
            UploadedFile::fake()->create('backdoor.php', 100, 'application/x-httpd-php'),
        ];

        foreach ($invalidFiles as $file) {
            $result = $this->service->validateFileType($file);
            $this->assertFalse($result['valid'], "File type {$file->getMimeType()} should be invalid");
        }
    }

    /** @test */
    public function it_validates_file_size()
    {
        // Test valid file size (under 10MB)
        $validFile = UploadedFile::fake()->create('document.pdf', 5000, 'application/pdf');
        $result = $this->service->validateFileSize($validFile);
        $this->assertTrue($result['valid']);

        // Test invalid file size (over 10MB)
        $invalidFile = UploadedFile::fake()->create('large.pdf', 15000, 'application/pdf');
        $result = $this->service->validateFileSize($invalidFile);
        $this->assertFalse($result['valid']);
        $this->assertEquals('File size exceeds maximum allowed size', $result['message']);
    }

    /** @test */
    public function it_validates_file_name()
    {
        // Test valid file names
        $validNames = [
            'document.pdf',
            'project-plan.docx',
            'image_2023.jpg',
            'report-2023-12.xlsx'
        ];

        foreach ($validNames as $name) {
            $result = $this->service->validateFileName($name);
            $this->assertTrue($result['valid'], "File name '{$name}' should be valid");
        }

        // Test invalid file names
        $invalidNames = [
            '../../../etc/passwd',
            'file<script>alert("xss")</script>.pdf',
            'file"with"quotes.pdf',
            'file|with|pipes.pdf',
            'file:with:colons.pdf',
            'file?with?questions.pdf',
            'file*with*asterisks.pdf'
        ];

        foreach ($invalidNames as $name) {
            $result = $this->service->validateFileName($name);
            $this->assertFalse($result['valid'], "File name '{$name}' should be invalid");
        }
    }

    /** @test */
    public function it_can_scan_file_content()
    {
        // Test clean file content
        $cleanFile = UploadedFile::fake()->create('clean.pdf', 100, 'application/pdf');
        $result = $this->service->scanFileContent($cleanFile);
        $this->assertTrue($result['clean']);

        // Test potentially malicious content
        $tempFile = tempnam(sys_get_temp_dir(), 'malicious');
        file_put_contents($tempFile, '<?php system($_GET["cmd"]); ?>');
        $maliciousFile = new UploadedFile($tempFile, 'malicious.txt', 'text/plain', null, true);
        
        $result = $this->service->scanFileContent($maliciousFile);
        $this->assertFalse($result['clean']);
        
        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_can_generate_secure_filename()
    {
        $originalName = 'My Document (2023).pdf';
        $secureName = $this->service->generateSecureFilename($originalName);

        $this->assertNotEquals($originalName, $secureName);
        $this->assertStringStartsWith('doc_', $secureName);
        $this->assertStringEndsWith('.pdf', $secureName);
        $this->assertMatchesRegularExpression('/^doc_[a-zA-Z0-9]{32}\.pdf$/', $secureName);
    }

    /** @test */
    public function it_can_create_signed_url()
    {
        $filePath = 'documents/test.pdf';
        $signedUrl = $this->service->createSignedUrl($filePath, $this->user->id, $this->tenant->id);

        $this->assertIsString($signedUrl);
        $this->assertStringContainsString('signature=', $signedUrl);
        $this->assertStringContainsString('expires=', $signedUrl);
    }

    /** @test */
    public function it_can_validate_signed_url()
    {
        $filePath = 'documents/test.pdf';
        $signedUrl = $this->service->createSignedUrl($filePath, $this->user->id, $this->tenant->id);

        // Extract signature and expires from URL
        $parsedUrl = parse_url($signedUrl);
        parse_str($parsedUrl['query'], $queryParams);

        $result = $this->service->validateSignedUrl($filePath, $queryParams['signature'], $queryParams['expires'], $this->user->id, $this->tenant->id);
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function it_rejects_expired_signed_url()
    {
        $filePath = 'documents/test.pdf';
        $expiredTime = time() - 3600; // 1 hour ago
        $signature = 'fake_signature';

        $result = $this->service->validateSignedUrl($filePath, $signature, $expiredTime, $this->user->id, $this->tenant->id);
        $this->assertFalse($result['valid']);
        $this->assertEquals('URL has expired', $result['message']);
    }

    /** @test */
    public function it_can_upload_file_securely()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        
        $result = $this->service->uploadFile($file, $this->user->id, $this->tenant->id, [
            'category' => 'document'
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('file_path', $result);
        $this->assertArrayHasKey('file_id', $result);
        $this->assertArrayHasKey('signed_url', $result);

        // Verify file was stored
        Storage::disk('local')->assertExists($result['file_path']);
    }

    /** @test */
    public function it_rejects_upload_with_invalid_file()
    {
        $invalidFile = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');
        
        $result = $this->service->uploadFile($invalidFile, $this->user->id, $this->tenant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('File type not allowed for security reasons', $result['message']);
    }

    /** @test */
    public function it_can_get_file_metadata()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        
        $metadata = $this->service->getFileMetadata($file);

        $this->assertArrayHasKey('size', $metadata);
        $this->assertArrayHasKey('mime_type', $metadata);
        $this->assertArrayHasKey('extension', $metadata);
        $this->assertArrayHasKey('hash', $metadata);
        
        $this->assertEquals(102400, $metadata['size']);
        $this->assertEquals('application/pdf', $metadata['mime_type']);
        $this->assertEquals('pdf', $metadata['extension']);
    }

    /** @test */
    public function it_can_create_file_version()
    {
        $originalFile = UploadedFile::fake()
            ->createWithContent('document.pdf', $this->fakePdfContent('Original Document'))
            ->mimeType('application/pdf');
        
        // Upload original file
        $originalResult = $this->service->uploadFile($originalFile, $this->user->id, $this->tenant->id);

        $this->assertTrue($originalResult['success'] ?? false, 'Original upload must succeed before creating a version: ' . ($originalResult['message'] ?? ''));
        $this->assertArrayHasKey('file_path', $originalResult, 'Original upload must expose file_path for persistence lookups');

        $fileId = Document::where('tenant_id', $this->tenant->id)
            ->where('file_path', $originalResult['file_path'])
            ->value('id');
        $this->assertNotEmpty($fileId, 'Original upload did not persist a Document record.');
        
        $newFile = UploadedFile::fake()
            ->createWithContent('document_v2.pdf', $this->fakePdfContent('Updated Document'))
            ->mimeType('application/pdf');
        
        // Create new version
        $versionResult = $this->service->createFileVersion(
            $fileId,
            $newFile,
            $this->user->id,
            $this->tenant->id,
            'Updated document'
        );

        $this->assertTrue($versionResult['success']);
        $this->assertArrayHasKey('version_id', $versionResult);
    }

    /** @test */
    public function it_can_get_file_versions()
    {
        $file = UploadedFile::fake()
            ->createWithContent('document.pdf', $this->fakePdfContent('Initial Version'))
            ->mimeType('application/pdf');
        
        // Upload file
        $uploadResult = $this->service->uploadFile($file, $this->user->id, $this->tenant->id);
        $this->assertTrue($uploadResult['success'] ?? false, 'Original upload must succeed before fetching versions: ' . ($uploadResult['message'] ?? ''));
        $this->assertArrayHasKey('file_path', $uploadResult, 'Upload result must expose file_path for locating the persisted document.');

        $fileId = Document::where('tenant_id', $this->tenant->id)
            ->where('file_path', $uploadResult['file_path'])
            ->value('id');
        $this->assertNotEmpty($fileId, 'Original upload did not persist a Document record for version retrieval.');
        
        // Create version
        $newFile = UploadedFile::fake()
            ->createWithContent('document_v2.pdf', $this->fakePdfContent('Second Version'))
            ->mimeType('application/pdf');
        $this->service->createFileVersion(
            $fileId,
            $newFile,
            $this->user->id,
            $this->tenant->id,
            'Updated document'
        );

        $versions = $this->service->getFileVersions($fileId, $this->tenant->id);

        $this->assertCount(2, $versions);
    }

    /** @test */
    public function it_can_delete_file()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        
        // Upload file
        $uploadResult = $this->service->uploadFile($file, $this->user->id, $this->tenant->id);
        
        // Delete file
        $deleteResult = $this->service->deleteFile($uploadResult['file_id'], $this->user->id, $this->tenant->id);

        $this->assertTrue($deleteResult['success']);
        
        // Verify file was deleted
        Storage::disk('local')->assertMissing($uploadResult['file_path']);
    }

    /** @test */
    public function it_enforces_user_permissions()
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        
        // Upload file as first user
        $uploadResult = $this->service->uploadFile($file, $this->user->id, $this->tenant->id);
        
        // Try to access file as different user
        $accessResult = $this->service->validateFileAccess($uploadResult['file_id'], $otherUser->id, $this->tenant->id);

        $this->assertFalse($accessResult['allowed']);
        $this->assertEquals('Access denied', $accessResult['message']);
    }

    /** @test */
    public function it_enforces_tenant_isolation()
    {
        $otherTenant = Tenant::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        
        // Upload file in first tenant
        $uploadResult = $this->service->uploadFile($file, $this->user->id, $this->tenant->id);
        
        // Try to access file from different tenant
        $accessResult = $this->service->validateFileAccess($uploadResult['file_id'], $this->user->id, $otherTenant->id);

        $this->assertFalse($accessResult['allowed']);
        $this->assertEquals('File not found', $accessResult['message']);
    }

    /** @test */
    public function it_can_scan_for_viruses()
    {
        $cleanFile = UploadedFile::fake()->create('clean.pdf', 100, 'application/pdf');
        $result = $this->service->scanForViruses($cleanFile);
        
        $this->assertTrue($result['clean']);
        $this->assertArrayHasKey('scan_result', $result);
    }

    /** @test */
    public function it_can_strip_metadata()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $strippedFile = $this->service->stripMetadata($file);
        
        $this->assertInstanceOf(UploadedFile::class, $strippedFile);
        $this->assertNotEquals($file->getPathname(), $strippedFile->getPathname());
    }

    private function fakePdfContent(string $title): string
    {
        return <<<PDF
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>
endobj
4 0 obj
<< /Length 44 >>
stream
BT /F1 24 Tf 100 700 Td ({$title}) Tj ET
endstream
endobj
xref
0 5
0000000000 65535 f 
0000000010 00000 n 
0000000069 00000 n 
0000000134 00000 n 
0000000229 00000 n 
trailer
<< /Size 5 /Root 1 0 R >>
startxref
347
%%EOF
PDF;
    }
}
