<?php
// Test debug route
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "=== TEST DEBUG ROUTE ===\n";

// Login as admin
$admin = User::where('email', 'admin@zena.local')->first();
Auth::login($admin);
echo "Logged in as: {$admin->name} ({$admin->email})\n";

// Test debug method
$request = new Illuminate\Http\Request();
$controller = new App\Http\Controllers\Admin\AdminUsersController();

try {
    $response = $controller->debug($request);
    
    if ($response instanceof Illuminate\View\View) {
        $data = $response->getData();
        echo "\nDebug Route Response:\n";
        echo "- Users count: " . $data['users']->count() . "\n";
        echo "- Users total: " . $data['users']->total() . "\n";
        echo "- Table data count: " . $data['tableData']->count() . "\n";
        
        echo "\nTable Data:\n";
        foreach($data['tableData'] as $item) {
            echo "- {$item['name']} ({$item['email']}) - Role: {$item['role']}\n";
        }
        
        echo "\nView name: " . $response->name() . "\n";
        
    } else {
        echo "Response is not a View: " . get_class($response) . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
