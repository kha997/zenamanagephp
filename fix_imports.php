<?php

$files = [
    'app/Models/ZenaRfi.php',
    'app/Models/ZenaDrawing.php',
    'app/Models/ZenaUserRole.php',
    'app/Models/ZenaNotification.php',
    'app/Models/ZenaNcr.php',
    'app/Models/ZenaRolePermission.php',
    'app/Models/ZenaQcPlan.php',
    'app/Models/ZenaSubmittal.php',
    'app/Models/ZenaChangeRequest.php',
    'app/Models/ZenaMaterialRequest.php',
    'app/Models/ZenaQcInspection.php',
    'app/Models/ZenaPurchaseOrder.php',
    'app/Models/ZenaProject.php',
    'app/Models/ZenaInvoice.php',
    'app/Models/ZenaPermission.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Fix the malformed import
        $content = str_replace(
            'use IlluminateDatabaseEloquentConcernsHasUlids;',
            '',
            $content
        );
        
        // Remove duplicate HasUlids imports
        $lines = explode("\n", $content);
        $hasUlidsCount = 0;
        $newLines = [];
        
        foreach ($lines as $line) {
            if (strpos($line, 'use Illuminate\Database\Eloquent\Concerns\HasUlids;') !== false) {
                $hasUlidsCount++;
                if ($hasUlidsCount === 1) {
                    $newLines[] = $line;
                }
            } else {
                $newLines[] = $line;
            }
        }
        
        $content = implode("\n", $newLines);
        file_put_contents($file, $content);
        echo "Fixed: $file\n";
    }
}

echo "All imports fixed!\n";
