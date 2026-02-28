<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use App\Models\DeliverableTemplateVersion;
use App\Models\WorkInstance;
use App\Models\WorkInstanceFieldValue;
use App\Models\WorkInstanceStep;
use App\Models\WorkInstanceStepAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class WorkInstanceExportBundleService
{
    public function __construct(
        private readonly DeliverableTemplateVersionService $deliverableTemplateVersionService,
        private readonly DeliverablePdfExportService $deliverablePdfExportService,
    ) {
    }

    public function renderHtmlForInstance(WorkInstance $instance, DeliverableTemplateVersion $templateVersion): string
    {
        if ($templateVersion->storage_path === '' || !Storage::disk('local')->exists($templateVersion->storage_path)) {
            throw new RuntimeException('Deliverable template source file not found');
        }

        $templateHtml = Storage::disk('local')->get($templateVersion->storage_path);
        if (!is_string($templateHtml)) {
            throw new RuntimeException('Deliverable template source file not found');
        }

        return $this->deliverableTemplateVersionService->renderHtml(
            $templateHtml,
            $this->buildDeliverableContext($instance)
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array{zip_path: string, zip_filename: string, manifest: array<string, mixed>}
     */
    public function buildZipForInstance(WorkInstance $instance, array $options): array
    {
        $templateVersion = $options['template_version'] ?? null;
        if (!$templateVersion instanceof DeliverableTemplateVersion) {
            throw new RuntimeException('Deliverable template version is required for bundle export.');
        }

        $generatedAt = now()->toIso8601String();
        $renderedHtml = $this->renderHtmlForInstance($instance, $templateVersion);
        $htmlBytes = strlen($renderedHtml);
        $pdfInput = $options['pdf'] ?? [];
        $pdfOptions = $this->deliverablePdfExportService->normalizeOptions(is_array($pdfInput) ? $pdfInput : []);

        $zipPath = $this->temporaryZipPath((string) $instance->id);
        $zipFilename = sprintf('work-instance-%s.zip', (string) $instance->id);
        $manifest = [
            'wi_id' => (string) $instance->id,
            'project_id' => (string) $instance->project_id,
            'tenant_id' => (string) $instance->tenant_id,
            'generated_at' => $generatedAt,
            'html' => [
                'included' => true,
                'bytes' => $htmlBytes,
            ],
            'pdf' => [
                'included' => false,
                'available' => false,
                'bytes' => 0,
                'preset' => $pdfOptions['preset'] ?? null,
                'orientation' => $pdfOptions['orientation'] ?? null,
                'header_footer' => $pdfOptions['header_footer'] ?? null,
                'margin_mm' => $pdfOptions['margin_mm'] ?? null,
            ],
            'attachments' => [],
        ];

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Failed to create work instance export bundle.');
        }

        $usedAttachmentEntries = [];

        try {
            if ($zip->addFromString('deliverable.html', $renderedHtml) === false) {
                throw new RuntimeException('Failed to add deliverable HTML to the export bundle.');
            }

            try {
                $pdf = $this->deliverablePdfExportService->render($renderedHtml, $pdfOptions, [
                    'project_name' => (string) ($instance->project?->name ?? ''),
                    'template_semver' => (string) $templateVersion->semver,
                    'generated_at' => $generatedAt,
                ]);

                if ($zip->addFromString('deliverable.pdf', $pdf) === false) {
                    throw new RuntimeException('Failed to add deliverable PDF to the export bundle.');
                }

                $manifest['pdf'] = [
                    'included' => true,
                    'available' => true,
                    'bytes' => strlen($pdf),
                    'preset' => $pdfOptions['preset'] ?? null,
                    'orientation' => $pdfOptions['orientation'] ?? null,
                    'header_footer' => $pdfOptions['header_footer'] ?? null,
                    'margin_mm' => $pdfOptions['margin_mm'] ?? null,
                ];
            } catch (DeliverablePdfExportUnavailableException $exception) {
                $manifest['pdf']['reason'] = $exception->getMessage();
            }

            foreach ($instance->steps->sortBy('step_order') as $step) {
                foreach ($step->attachments as $attachment) {
                    $entryPath = $this->bundleAttachmentPath($step, $attachment, $usedAttachmentEntries);
                    $contents = Storage::disk('local')->get($attachment->file_path);

                    if (!is_string($contents)) {
                        throw new RuntimeException('Failed to read a work instance attachment for export.');
                    }

                    if ($zip->addFromString($entryPath, $contents) === false) {
                        throw new RuntimeException('Failed to add a work instance attachment to the export bundle.');
                    }

                    $manifest['attachments'][] = [
                        'id' => (string) $attachment->id,
                        'step_id' => (string) $step->id,
                        'file_name' => (string) $attachment->file_name,
                        'mime_type' => (string) ($attachment->mime_type ?? ''),
                        'file_size' => (int) ($attachment->file_size ?? 0),
                        'stored_path' => $entryPath,
                    ];
                }
            }

            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (!is_string($manifestJson)) {
                throw new RuntimeException('Failed to encode work instance bundle manifest.');
            }

            if ($zip->addFromString('manifest.json', $manifestJson) === false) {
                throw new RuntimeException('Failed to add bundle manifest to the export bundle.');
            }

            if ($zip->close() === false) {
                throw new RuntimeException('Failed to finalize work instance export bundle.');
            }

            return [
                'zip_path' => $zipPath,
                'zip_filename' => $zipFilename,
                'manifest' => $manifest,
            ];
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($zipPath);

            throw $exception;
        }
    }

    private function temporaryZipPath(string $instanceId): string
    {
        $directory = storage_path('app/tmp');
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Failed to prepare temporary export directory.');
        }

        return $directory . DIRECTORY_SEPARATOR . sprintf(
            'work-instance-%s-%s.zip',
            $instanceId,
            Str::lower((string) Str::ulid())
        );
    }

    /**
     * @param array<string, true> $usedAttachmentEntries
     */
    private function bundleAttachmentPath(
        WorkInstanceStep $step,
        WorkInstanceStepAttachment $attachment,
        array &$usedAttachmentEntries
    ): string {
        $stepSegment = $step->step_order !== null
            ? sprintf('step-%s', (string) $step->step_order)
            : sprintf('step-%s', (string) $step->id);
        $filename = $this->sanitizeAttachmentFilename((string) $attachment->file_name, (string) $attachment->id);
        $entryPath = 'attachments/' . $stepSegment . '/' . $filename;

        if (!isset($usedAttachmentEntries[$entryPath])) {
            $usedAttachmentEntries[$entryPath] = true;

            return $entryPath;
        }

        $pathInfo = pathinfo($filename);
        $name = (string) ($pathInfo['filename'] ?? $attachment->id);
        $extension = (string) ($pathInfo['extension'] ?? '');
        $deduplicated = $name . '-' . $attachment->id;
        if ($extension !== '') {
            $deduplicated .= '.' . $extension;
        }

        $entryPath = 'attachments/' . $stepSegment . '/' . $deduplicated;
        $usedAttachmentEntries[$entryPath] = true;

        return $entryPath;
    }

    private function sanitizeAttachmentFilename(string $filename, string $fallback): string
    {
        $sanitized = trim(str_replace(['\\', '/'], '-', $filename));
        $sanitized = preg_replace('/[\x00-\x1F\x7F]+/', '', $sanitized) ?? '';

        return $sanitized !== '' ? $sanitized : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeliverableContext(WorkInstance $instance): array
    {
        $context = [
            'project.name' => $instance->project?->name,
            'wi.id' => (string) $instance->id,
        ];

        foreach ($instance->steps->sortBy('step_order') as $step) {
            foreach ($step->values as $value) {
                $context['fields.' . $value->field_key] = $this->fieldValueForExport($value);
            }
        }

        return $context;
    }

    private function fieldValueForExport(WorkInstanceFieldValue $value): mixed
    {
        if ($value->value_number !== null) {
            return $value->value_number;
        }

        if ($value->value_datetime !== null) {
            return $value->value_datetime;
        }

        if ($value->value_date !== null) {
            return $value->value_date;
        }

        if ($value->value_string !== null) {
            return $value->value_string;
        }

        return $value->value_json;
    }
}
