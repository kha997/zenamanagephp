<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ScanFileVirusJob
 * 
 * Scans uploaded files for viruses before making them available.
 * For large files, this runs in the background queue.
 * 
 * Supports:
 * - ClamAV integration (if configured)
 * - Basic security checks (dangerous extensions, suspicious patterns)
 * - File quarantine for infected files
 */
class ScanFileVirusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // Exponential backoff: 1min, 5min, 15min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $fileId,
        public string $filePath
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $file = File::find($this->fileId);
            
            if (!$file) {
                Log::warning('File not found for virus scan', [
                    'file_id' => $this->fileId,
                ]);
                return;
            }
            
            // Check if file exists
            $disk = Storage::disk($file->disk ?? 'local');
            if (!$disk->exists($this->filePath)) {
                Log::warning('File not found for virus scan', [
                    'file_id' => $this->fileId,
                    'file_path' => $this->filePath,
                ]);
                return;
            }
            
            // Perform virus scan
            $scanResult = $this->performVirusScan($this->filePath, $disk);
            
            if ($scanResult['infected']) {
                // Quarantine infected file
                $this->quarantineFile($file, $scanResult, $disk);
                
                // Update file metadata
                $file->update([
                    'metadata' => array_merge($file->metadata ?? [], [
                        'virus_scan_status' => 'infected',
                        'virus_scan_threat' => $scanResult['threat'] ?? 'Unknown',
                        'virus_scan_at' => now()->toISOString(),
                    ]),
                ]);
                
                Log::warning('File infected with virus', [
                    'file_id' => $this->fileId,
                    'file_path' => $this->filePath,
                    'threat' => $scanResult['threat'] ?? 'Unknown',
                    'tenant_id' => $file->tenant_id,
                ]);
            } else {
                // File is clean - mark as scanned
                $file->update([
                    'metadata' => array_merge($file->metadata ?? [], [
                        'virus_scan_status' => 'clean',
                        'virus_scan_at' => now()->toISOString(),
                    ]),
                ]);
                
                Log::info('File virus scan completed - clean', [
                    'file_id' => $this->fileId,
                    'tenant_id' => $file->tenant_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Virus scan job failed', [
                'file_id' => $this->fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to trigger retry
        }
    }
    
    /**
     * Perform virus scan on file
     * 
     * @param string $filePath
     * @param \Illuminate\Contracts\Filesystem\Filesystem $disk
     * @return array ['infected' => bool, 'threat' => string|null]
     */
    private function performVirusScan(string $filePath, $disk): array
    {
        $fullPath = $disk->path($filePath);
        
        // Try ClamAV if enabled
        if (config('media.virus_scan_enabled', true) && config('media.virus_scan_driver') === 'clamav') {
            $clamavResult = $this->scanWithClamAV($fullPath);
            if ($clamavResult !== null) {
                return $clamavResult;
            }
        }
        
        // Fallback to basic checks
        return $this->performBasicSecurityChecks($fullPath);
    }

    /**
     * Scan file with ClamAV
     * 
     * Supports both ClamAV daemon (socket) and ClamAV package (if installed).
     * Part of Gói 11: Media Pipeline An Toàn & Nhẹ.
     * 
     * @param string $filePath
     * @return array|null ['infected' => bool, 'threat' => string|null] or null if ClamAV unavailable
     */
    private function scanWithClamAV(string $filePath): ?array
    {
        $host = config('media.clamav_host', 'localhost');
        $port = config('media.clamav_port', 3310);
        
        try {
            // Try ClamAV package first (if installed: xenolope/clamav-scanner)
            if (class_exists(\Xenolope\ClamAV\Scanner::class)) {
                return $this->scanWithClamAVPackage($filePath);
            }
            
            // Fallback to socket connection
            return $this->scanWithClamAVSocket($filePath, $host, $port);
        } catch (\Exception $e) {
            Log::warning('ClamAV scan error', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);
            return null; // Fallback to basic checks
        }
    }

    /**
     * Scan with ClamAV package (xenolope/clamav-scanner)
     */
    private function scanWithClamAVPackage(string $filePath): ?array
    {
        try {
            $scanner = new \Xenolope\ClamAV\Scanner(
                config('media.clamav_host', 'localhost'),
                config('media.clamav_port', 3310)
            );
            
            $result = $scanner->isInfected($filePath);
            
            if ($result) {
                return [
                    'infected' => true,
                    'threat' => $result,
                ];
            }
            
            return [
                'infected' => false,
                'threat' => null,
            ];
        } catch (\Exception $e) {
            Log::warning('ClamAV package scan failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);
            return null;
        }
    }

    /**
     * Scan with ClamAV daemon via socket
     */
    private function scanWithClamAVSocket(string $filePath, string $host, int $port): ?array
    {
        try {
            // Connect to ClamAV daemon
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            
            if (!$socket) {
                Log::warning('ClamAV connection failed', [
                    'host' => $host,
                    'port' => $port,
                    'error' => "{$errno}: {$errstr}",
                ]);
                return null; // Fallback to basic checks
            }
            
            // Send SCAN command (zSCAN for stream scanning)
            fwrite($socket, "zSCAN {$filePath}\n");
            
            // Read response
            $response = fgets($socket);
            fclose($socket);
            
            // Parse response
            // Format: "stream: OK" or "stream: {virus_name} FOUND"
            if (strpos($response, 'FOUND') !== false) {
                // Extract virus name
                preg_match('/stream: (.+?) FOUND/', $response, $matches);
                $threat = $matches[1] ?? 'Unknown threat';
                
                return [
                    'infected' => true,
                    'threat' => $threat,
                ];
            }
            
            // File is clean
            return [
                'infected' => false,
                'threat' => null,
            ];
        } catch (\Exception $e) {
            Log::warning('ClamAV socket scan error', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);
            return null; // Fallback to basic checks
        }
    }

    /**
     * Perform basic security checks
     * 
     * @param string $filePath
     * @return array ['infected' => bool, 'threat' => string|null]
     */
    private function performBasicSecurityChecks(string $filePath): array
    {
        // 1. Check file extension against known dangerous types
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar'];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (in_array($extension, $dangerousExtensions)) {
            return [
                'infected' => true,
                'threat' => 'Potentially dangerous file type: ' . $extension,
            ];
        }
        
        // 2. Check file content for suspicious patterns
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return [
                'infected' => false,
                'threat' => null,
            ];
        }
        
        $content = fread($handle, 1024); // First 1KB
        fclose($handle);
        
        $suspiciousPatterns = [
            'eval(',
            'base64_decode',
            'shell_exec',
            'system(',
            'exec(',
            'passthru(',
            'preg_replace.*\/e',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match('/' . preg_quote($pattern, '/') . '/i', $content)) {
                return [
                    'infected' => true,
                    'threat' => 'Suspicious content pattern detected: ' . $pattern,
                ];
            }
        }
        
        // File appears clean
        return [
            'infected' => false,
            'threat' => null,
        ];
    }
    
    /**
     * Quarantine infected file
     * 
     * @param File $file
     * @param array $scanResult
     * @param \Illuminate\Contracts\Filesystem\Filesystem $disk
     * @return void
     */
    private function quarantineFile(File $file, array $scanResult, $disk): void
    {
        try {
            $quarantinePath = 'quarantine/' . $file->tenant_id . '/' . $file->id . '_' . time();
            $disk->move($this->filePath, $quarantinePath);
            
            // Update file with quarantine info
            $file->update([
                'path' => $quarantinePath,
                'metadata' => array_merge($file->metadata ?? [], [
                    'quarantined_at' => now()->toISOString(),
                    'quarantine_reason' => $scanResult['threat'] ?? 'Unknown threat',
                ]),
            ]);
            
            Log::warning('File quarantined', [
                'file_id' => $file->id,
                'tenant_id' => $file->tenant_id,
                'quarantine_path' => $quarantinePath,
                'threat' => $scanResult['threat'] ?? 'Unknown',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to quarantine file', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

