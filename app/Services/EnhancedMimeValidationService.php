<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Enhanced MIME Validation Service
 * Provides comprehensive file validation including:
 * - File signature (magic bytes) validation
 * - MIME type verification
 * - Extension validation
 * - Malicious content detection
 */
class EnhancedMimeValidationService
{
    /**
     * File signatures (magic bytes) for common file types
     */
    private array $fileSignatures = [
        'pdf' => ['%PDF'],
        'jpg' => ['FF D8 FF'],
        'jpeg' => ['FF D8 FF'],
        'png' => ['89 50 4E 47 0D 0A 1A 0A'],
        'gif' => ['47 49 46 38'],
        'doc' => ['D0 CF 11 E0 A1 B1 1A E1'],
        'docx' => ['50 4B 03 04'],
        'xls' => ['D0 CF 11 E0 A1 B1 1A E1'],
        'xlsx' => ['50 4B 03 04'],
        'ppt' => ['D0 CF 11 E0 A1 B1 1A E1'],
        'pptx' => ['50 4B 03 04'],
        'zip' => ['50 4B 03 04', '50 4B 05 06', '50 4B 07 08'],
        'rar' => ['52 61 72 21 1A 07 00'],
        '7z' => ['37 7A BC AF 27 1C'],
        'svg' => ['<svg', '<?xml'],
    ];

    /**
     * MIME type mappings
     */
    private array $mimeTypes = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'zip' => ['application/zip'],
        'rar' => ['application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
        'svg' => ['image/svg+xml'],
    ];

    /**
     * Dangerous file patterns to detect malicious content
     */
    private array $dangerousPatterns = [
        'php' => ['<?php', '<?=', '<? '],
        'javascript' => ['<script', 'javascript:', 'eval(', 'function('],
        'executable' => ['MZ', 'PE', 'ELF'],
        'shell' => ['#!/bin/', '#!/usr/bin/', '#!/bin/bash'],
    ];

    /**
     * Validate file with enhanced MIME checking
     */
    public function validateFile(UploadedFile $file): array
    {
        $results = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => [],
            'file_info' => [
                'original_name' => $file->getClientOriginalName(),
                'extension' => strtolower($file->getClientOriginalExtension()),
                'reported_mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]
        ];

        try {
            // 1. Check file extension
            $extensionCheck = $this->validateExtension($file);
            if (!$extensionCheck['valid']) {
                $results['is_valid'] = false;
                $results['errors'][] = $extensionCheck['error'];
            }

            // 2. Check MIME type consistency
            $mimeCheck = $this->validateMimeType($file);
            if (!$mimeCheck['valid']) {
                $results['is_valid'] = false;
                $results['errors'][] = $mimeCheck['error'];
            }

            // 3. Check file signature (magic bytes)
            $signatureCheck = $this->validateFileSignature($file);
            if (!$signatureCheck['valid']) {
                $results['is_valid'] = false;
                $results['errors'][] = $signatureCheck['error'];
            }

            // 4. Check for malicious content
            $securityCheck = $this->validateSecurity($file);
            if (!$securityCheck['valid']) {
                $results['is_valid'] = false;
                $results['errors'][] = $securityCheck['error'];
            }

            // 5. Check for double extensions
            $doubleExtCheck = $this->validateDoubleExtensions($file);
            if (!$doubleExtCheck['valid']) {
                $results['is_valid'] = false;
                $results['errors'][] = $doubleExtCheck['error'];
            }

            // 6. Check file size
            $sizeCheck = $this->validateFileSize($file);
            if (!$sizeCheck['valid']) {
                $results['is_valid'] = false;
                $results['errors'][] = $sizeCheck['error'];
            }

            // Add file info
            $results['file_info']['detected_mime'] = $signatureCheck['detected_mime'] ?? null;
            $results['file_info']['signature_match'] = $signatureCheck['signature_match'] ?? false;

        } catch (\Exception $e) {
            $results['is_valid'] = false;
            $results['errors'][] = 'Validation error: ' . $e->getMessage();
            Log::error('MIME validation error', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * Validate file extension
     */
    private function validateExtension(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = array_keys($this->fileSignatures);

        if (!in_array($extension, $allowedExtensions, true)) {
            return [
                'valid' => false,
                'error' => "File extension '{$extension}' is not allowed"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate MIME type consistency
     */
    private function validateMimeType(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $reportedMime = $file->getMimeType();
        $expectedMimes = $this->mimeTypes[$extension] ?? [];

        if (empty($expectedMimes)) {
            return [
                'valid' => false,
                'error' => "Unknown file extension '{$extension}'"
            ];
        }

        if (!in_array($reportedMime, $expectedMimes, true)) {
            return [
                'valid' => false,
                'error' => "MIME type '{$reportedMime}' does not match extension '{$extension}'"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate file signature (magic bytes)
     */
    private function validateFileSignature(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $signatures = $this->fileSignatures[$extension] ?? [];

        if (empty($signatures)) {
            return [
                'valid' => false,
                'error' => "No signature defined for extension '{$extension}'"
            ];
        }

        // Read first 512 bytes to check signature
        $handle = fopen($file->getPathname(), 'rb');
        if (!$handle) {
            return [
                'valid' => false,
                'error' => 'Cannot read file for signature validation'
            ];
        }

        $header = fread($handle, 512);
        fclose($handle);

        if ($header === false) {
            return [
                'valid' => false,
                'error' => 'Failed to read file header'
            ];
        }

        // Check for signature match
        foreach ($signatures as $signature) {
            if ($this->checkSignature($header, $signature)) {
                return [
                    'valid' => true,
                    'signature_match' => true,
                    'detected_mime' => $this->mimeTypes[$extension][0] ?? null
                ];
            }
        }

        return [
            'valid' => false,
            'error' => "File signature does not match extension '{$extension}'"
        ];
    }

    /**
     * Check if file header matches signature
     */
    private function checkSignature(string $header, string $signature): bool
    {
        // Handle hex signatures
        if (strpos($signature, ' ') !== false) {
            $hexBytes = explode(' ', $signature);
            $hexString = '';
            foreach ($hexBytes as $byte) {
                $hexString .= chr(hexdec($byte));
            }
            return strpos($header, $hexString) === 0;
        }

        // Handle text signatures
        return strpos($header, $signature) === 0;
    }

    /**
     * Validate for malicious content
     */
    private function validateSecurity(UploadedFile $file): array
    {
        // Read first 1024 bytes to check for malicious patterns
        $handle = fopen($file->getPathname(), 'rb');
        if (!$handle) {
            return ['valid' => true]; // Skip security check if can't read
        }

        $content = fread($handle, 1024);
        fclose($handle);

        if ($content === false) {
            return ['valid' => true]; // Skip security check if can't read
        }

        $contentLower = strtolower($content);

        foreach ($this->dangerousPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($contentLower, strtolower($pattern)) !== false) {
                    return [
                        'valid' => false,
                        'error' => "File contains potentially malicious {$type} content"
                    ];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate for double extensions
     */
    private function validateDoubleExtensions(UploadedFile $file): array
    {
        $filename = $file->getClientOriginalName();
        $parts = explode('.', $filename);

        if (count($parts) > 2) {
            // Check if any part before the last is a dangerous extension
            $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'js', 'jsp', 'asp', 'aspx'];
            
            for ($i = 0; $i < count($parts) - 1; $i++) {
                if (in_array(strtolower($parts[$i]), $dangerousExtensions, true)) {
                    return [
                        'valid' => false,
                        'error' => 'File has suspicious double extension'
                    ];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate file size
     */
    private function validateFileSize(UploadedFile $file): array
    {
        $maxSize = config('app.max_file_size', 10485760); // 10MB default
        $fileSize = $file->getSize();

        if ($fileSize > $maxSize) {
            return [
                'valid' => false,
                'error' => "File size ({$fileSize} bytes) exceeds maximum allowed size ({$maxSize} bytes)"
            ];
        }

        if ($fileSize === 0) {
            return [
                'valid' => false,
                'error' => 'File is empty'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get allowed file types
     */
    public function getAllowedFileTypes(): array
    {
        return array_keys($this->fileSignatures);
    }

    /**
     * Get MIME types for extension
     */
    public function getMimeTypesForExtension(string $extension): array
    {
        return $this->mimeTypes[strtolower($extension)] ?? [];
    }
}