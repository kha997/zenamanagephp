<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Dompdf\Dompdf;
use Dompdf\Options;

class ExportService
{
    protected $storagePath = 'exports';
    protected $maxFileSize = 50 * 1024 * 1024; // 50MB

    public function __construct()
    {
        // Ensure exports directory exists
        if (!Storage::exists($this->storagePath)) {
            Storage::makeDirectory($this->storagePath);
        }
    }

    /**
     * Export data in various formats
     */
    public function export(array $data, string $format, array $options = []): string
    {
        $filename = $this->generateFilename($format, $options);
        $filePath = $this->storagePath . '/' . $filename;

        switch (strtolower($format)) {
            case 'pdf':
                return $this->exportToPDF($data, $filePath, $options);
            case 'excel':
            case 'xlsx':
                return $this->exportToExcel($data, $filePath, $options);
            case 'csv':
                return $this->exportToCSV($data, $filePath, $options);
            case 'json':
                return $this->exportToJSON($data, $filePath, $options);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Export to PDF format
     */
    protected function exportToPDF(array $data, string $filePath, array $options = []): string
    {
        try {
            $html = $this->generatePDFHTML($data, $options);
            
            $options_pdf = new Options();
            $options_pdf->set('defaultFont', 'Arial');
            $options_pdf->set('isRemoteEnabled', true);
            $options_pdf->set('isHtml5ParserEnabled', true);
            
            $dompdf = new Dompdf($options_pdf);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $output = $dompdf->output();
            Storage::put($filePath, $output);
            
            return $filePath;
        } catch (\Exception $e) {
            Log::error('PDF export error', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            throw $e;
        }
    }

    /**
     * Export to Excel format
     */
    protected function exportToExcel(array $data, string $filePath, array $options = []): string
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set title
            if (isset($options['title'])) {
                $sheet->setCellValue('A1', $options['title']);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->mergeCells('A1:F1');
                $row = 3;
            } else {
                $row = 1;
            }
            
            // Add headers
            if (!empty($data) && is_array($data[0] ?? null)) {
                $headers = array_keys($data[0]);
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue($col . $row, ucfirst(str_replace('_', ' ', $header)));
                    $sheet->getStyle($col . $row)->getFont()->setBold(true);
                    $col++;
                }
                $row++;
            }
            
            // Add data
            foreach ($data as $item) {
                $col = 'A';
                foreach ($item as $value) {
                    $sheet->setCellValue($col . $row, $this->formatCellValue($value));
                    $col++;
                }
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', $col) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), 'export_');
            $writer->save($tempFile);
            
            Storage::put($filePath, file_get_contents($tempFile));
            unlink($tempFile);
            
            return $filePath;
        } catch (\Exception $e) {
            Log::error('Excel export error', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            throw $e;
        }
    }

    /**
     * Export to CSV format
     */
    protected function exportToCSV(array $data, string $filePath, array $options = []): string
    {
        try {
            $csvContent = '';
            
            // Add title if provided
            if (isset($options['title'])) {
                $csvContent .= $options['title'] . "\n";
                $csvContent .= "Generated on: " . now()->format('Y-m-d H:i:s') . "\n\n";
            }
            
            // Add headers
            if (!empty($data) && is_array($data[0] ?? null)) {
                $headers = array_keys($data[0]);
                $csvContent .= implode(',', array_map([$this, 'escapeCSV'], $headers)) . "\n";
            }
            
            // Add data
            foreach ($data as $item) {
                $row = [];
                foreach ($item as $value) {
                    $row[] = $this->escapeCSV($this->formatCellValue($value));
                }
                $csvContent .= implode(',', $row) . "\n";
            }
            
            Storage::put($filePath, $csvContent);
            
            return $filePath;
        } catch (\Exception $e) {
            Log::error('CSV export error', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            throw $e;
        }
    }

    /**
     * Export to JSON format
     */
    protected function exportToJSON(array $data, string $filePath, array $options = []): string
    {
        try {
            $exportData = [
                'metadata' => [
                    'title' => $options['title'] ?? 'Export Data',
                    'generated_at' => now()->toISOString(),
                    'total_records' => count($data),
                    'format' => 'json'
                ],
                'data' => $data
            ];
            
            $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            Storage::put($filePath, $jsonContent);
            
            return $filePath;
        } catch (\Exception $e) {
            Log::error('JSON export error', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            throw $e;
        }
    }

    /**
     * Generate PDF HTML content
     */
    protected function generatePDFHTML(array $data, array $options = []): string
    {
        $title = $options['title'] ?? 'Report';
        $generatedAt = now()->format('Y-m-d H:i:s');
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { font-size: 12px; color: #666; margin-top: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='title'>{$title}</div>
                <div class='subtitle'>Generated on: {$generatedAt}</div>
            </div>
        ";
        
        if (!empty($data)) {
            $html .= "<table>";
            
            // Headers
            if (is_array($data[0] ?? null)) {
                $headers = array_keys($data[0]);
                $html .= "<tr>";
                foreach ($headers as $header) {
                    $html .= "<th>" . ucfirst(str_replace('_', ' ', $header)) . "</th>";
                }
                $html .= "</tr>";
            }
            
            // Data rows
            foreach ($data as $item) {
                $html .= "<tr>";
                foreach ($item as $value) {
                    $html .= "<td>" . htmlspecialchars($this->formatCellValue($value)) . "</td>";
                }
                $html .= "</tr>";
            }
            
            $html .= "</table>";
        }
        
        $html .= "
            <div class='footer'>
                <p>This report was generated automatically by ZenaManage</p>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }

    /**
     * Format cell value for export
     */
    protected function formatCellValue($value): string
    {
        if (is_null($value)) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }
        
        return (string) $value;
    }

    /**
     * Escape CSV value
     */
    protected function escapeCSV($value): string
    {
        $value = (string) $value;
        
        // If value contains comma, newline, or double quote, wrap in quotes
        if (strpos($value, ',') !== false || strpos($value, "\n") !== false || strpos($value, '"') !== false) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        
        return $value;
    }

    /**
     * Generate filename for export
     */
    protected function generateFilename(string $format, array $options = []): string
    {
        $prefix = $options['prefix'] ?? 'export';
        $timestamp = now()->format('Y-m-d_H-i-s');
        $extension = $this->getFileExtension($format);
        
        return "{$prefix}_{$timestamp}.{$extension}";
    }

    /**
     * Get file extension for format
     */
    protected function getFileExtension(string $format): string
    {
        return match (strtolower($format)) {
            'pdf' => 'pdf',
            'excel', 'xlsx' => 'xlsx',
            'csv' => 'csv',
            'json' => 'json',
            default => 'txt'
        };
    }

    /**
     * Clean up old export files
     */
    public function cleanupOldFiles(int $daysOld = 7): int
    {
        $cutoffDate = now()->subDays($daysOld);
        $deletedCount = 0;
        
        $files = Storage::files($this->storagePath);
        
        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);
            if ($lastModified < $cutoffDate->timestamp) {
                Storage::delete($file);
                $deletedCount++;
            }
        }
        
        Log::info('Cleaned up old export files', [
            'deleted_count' => $deletedCount,
            'days_old' => $daysOld
        ]);
        
        return $deletedCount;
    }

    /**
     * Get export file info
     */
    public function getFileInfo(string $filePath): array
    {
        if (!Storage::exists($filePath)) {
            throw new \FileNotFoundException("Export file not found: {$filePath}");
        }
        
        return [
            'path' => $filePath,
            'size' => Storage::size($filePath),
            'last_modified' => Storage::lastModified($filePath),
            'url' => Storage::url($filePath),
            'expires_at' => now()->addHours(24)->toISOString()
        ];
    }
}