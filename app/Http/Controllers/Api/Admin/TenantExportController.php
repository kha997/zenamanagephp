<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantIndexRequest;
use App\Models\Tenant;
use App\Services\TenantExporter;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\Log;

class TenantExportController extends Controller
{
    /**
     * Export tenants to CSV with CSV injection protection
     */
    public function export(TenantIndexRequest $request): StreamedResponse
    {
        // Log audit
        Log::info('Tenant export requested', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'filters' => $request->validated(),
            'x_request_id' => $request->header('X-Request-Id')
        ]);

        // Get filtered tenants using the same logic as index
        $rows = TenantExporter::rows($request);

        return response()->streamDownload(function() use ($rows) {
            $out = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($out, [
                'id', 'name', 'domain', 'status', 'users', 'projects', 
                'owner_name', 'owner_email', 'plan', 'created_at', 'updated_at'
            ]);
            
            foreach ($rows as $row) {
                // CSV injection protection
                $safe = array_map(function($value) {
                    $stringValue = (string) $value;
                    // If starts with formula characters, prefix with single quote
                    if (preg_match('/^[=\+\-@]/', $stringValue)) {
                        return "'" . $stringValue;
                    }
                    return $value;
                }, $row);
                
                fputcsv($out, $safe);
            }
            
            fclose($out);
        }, 'tenants_' . now()->format('Y-m-d_H-i-s') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'Content-Disposition' => 'attachment; filename=tenants.csv'
        ]);
    }
}
