<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected $documentId;
    protected $fileId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $documentId, int $fileId)
    {
        $this->documentId = $documentId;
        $this->fileId = $fileId;
        $this->onQueue('document-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $document = Document::find($this->documentId);
            $file = File::find($this->fileId);

            if (!$document || !$file) {
                Log::warning('DocumentProcessingJob: Document or file not found', [
                    'document_id' => $this->documentId,
                    'file_id' => $this->fileId
                ]);
                return;
            }

            Log::info('Starting document processing', [
                'document_id' => $this->documentId,
                'file_id' => $this->fileId,
                'file_name' => $file->name
            ]);

            // Update document status
            $document->update(['status' => 'processing']);

            // Process based on file type
            $this->processDocumentByType($document, $file);

            // Update document status to completed
            $document->update(['status' => 'completed']);

            Log::info('Document processing completed successfully', [
                'document_id' => $this->documentId,
                'file_id' => $this->fileId
            ]);

        } catch (\Exception $e) {
            // Update document status to failed
            if (isset($document)) {
                $document->update([
                    'status' => 'failed',
                    'processing_error' => $e->getMessage()
                ]);
            }

            Log::error('Document processing failed', [
                'document_id' => $this->documentId,
                'file_id' => $this->fileId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Process document based on file type
     */
    protected function processDocumentByType(Document $document, File $file): void
    {
        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'pdf':
                $this->processPdfDocument($document, $file);
                break;
            case 'doc':
            case 'docx':
                $this->processWordDocument($document, $file);
                break;
            case 'xls':
            case 'xlsx':
                $this->processExcelDocument($document, $file);
                break;
            case 'txt':
                $this->processTextDocument($document, $file);
                break;
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                $this->processImageDocument($document, $file);
                break;
            default:
                $this->processGenericDocument($document, $file);
                break;
        }
    }

    /**
     * Process PDF document
     */
    protected function processPdfDocument(Document $document, File $file): void
    {
        // Extract text content from PDF
        $textContent = $this->extractPdfText($file);
        
        // Generate thumbnail
        $thumbnailPath = $this->generatePdfThumbnail($file);
        
        // Update document with extracted content
        $document->update([
            'content' => $textContent,
            'thumbnail_path' => $thumbnailPath,
            'word_count' => str_word_count($textContent),
            'page_count' => $this->getPdfPageCount($file)
        ]);
    }

    /**
     * Process Word document
     */
    protected function processWordDocument(Document $document, File $file): void
    {
        // Extract text content from Word document
        $textContent = $this->extractWordText($file);
        
        // Update document with extracted content
        $document->update([
            'content' => $textContent,
            'word_count' => str_word_count($textContent)
        ]);
    }

    /**
     * Process Excel document
     */
    protected function processExcelDocument(Document $document, File $file): void
    {
        // Extract data from Excel
        $excelData = $this->extractExcelData($file);
        
        // Update document with extracted data
        $document->update([
            'content' => json_encode($excelData),
            'metadata' => array_merge($document->metadata ?? [], [
                'excel_sheets' => count($excelData),
                'excel_rows' => array_sum(array_map('count', $excelData))
            ])
        ]);
    }

    /**
     * Process text document
     */
    protected function processTextDocument(Document $document, File $file): void
    {
        $content = Storage::disk($file->disk)->get($file->path);
        
        $document->update([
            'content' => $content,
            'word_count' => str_word_count($content),
            'character_count' => strlen($content)
        ]);
    }

    /**
     * Process image document
     */
    protected function processImageDocument(Document $document, File $file): void
    {
        // Generate thumbnail
        $thumbnailPath = $this->generateImageThumbnail($file);
        
        // Extract metadata
        $metadata = $this->extractImageMetadata($file);
        
        $document->update([
            'thumbnail_path' => $thumbnailPath,
            'metadata' => array_merge($document->metadata ?? [], $metadata)
        ]);
    }

    /**
     * Process generic document
     */
    protected function processGenericDocument(Document $document, File $file): void
    {
        // Just update basic metadata
        $document->update([
            'metadata' => array_merge($document->metadata ?? [], [
                'processed_at' => now()->toISOString(),
                'file_size' => Storage::disk($file->disk)->size($file->path)
            ])
        ]);
    }

    /**
     * Extract text from PDF
     */
    protected function extractPdfText(File $file): string
    {
        // This is a placeholder - in a real implementation, you'd use a PDF library
        // like Smalot\PdfParser or similar
        return "PDF text extraction not implemented yet";
    }

    /**
     * Generate PDF thumbnail
     */
    protected function generatePdfThumbnail(File $file): ?string
    {
        // This is a placeholder - in a real implementation, you'd use ImageMagick
        // or similar to generate thumbnails
        return null;
    }

    /**
     * Get PDF page count
     */
    protected function getPdfPageCount(File $file): int
    {
        // This is a placeholder - in a real implementation, you'd use a PDF library
        return 1;
    }

    /**
     * Extract text from Word document
     */
    protected function extractWordText(File $file): string
    {
        // This is a placeholder - in a real implementation, you'd use PhpOffice\PhpWord
        return "Word text extraction not implemented yet";
    }

    /**
     * Extract data from Excel
     */
    protected function extractExcelData(File $file): array
    {
        // This is a placeholder - in a real implementation, you'd use PhpOffice\PhpSpreadsheet
        return [];
    }

    /**
     * Generate image thumbnail
     */
    protected function generateImageThumbnail(File $file): ?string
    {
        // This is a placeholder - in a real implementation, you'd use GD or ImageMagick
        return null;
    }

    /**
     * Extract image metadata
     */
    protected function extractImageMetadata(File $file): array
    {
        // This is a placeholder - in a real implementation, you'd use getimagesize()
        return [
            'width' => 0,
            'height' => 0,
            'format' => 'unknown'
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DocumentProcessingJob: Job failed permanently', [
            'error' => $exception->getMessage(),
            'document_id' => $this->documentId,
            'file_id' => $this->fileId
        ]);

        // Update document status to failed
        $document = Document::find($this->documentId);
        if ($document) {
            $document->update([
                'status' => 'failed',
                'processing_error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'document-processing',
            'document:' . $this->documentId,
            'file:' . $this->fileId
        ];
    }
}
