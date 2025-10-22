<?php
// Debug script để test admin users view
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "=== DEBUG ADMIN USERS VIEW ===\n";

// 1. Check users in database
$users = User::all();
echo "Total users in DB: " . $users->count() . "\n";
foreach($users as $user) {
    echo "- {$user->name} ({$user->email}) - Role: {$user->role} - Tenant: {$user->tenant_id}\n";
}

// 2. Login as admin
$admin = User::where('email', 'admin@zena.local')->first();
if (!$admin) {
    echo "ERROR: Admin user not found!\n";
    exit(1);
}

Auth::login($admin);
echo "\nLogged in as: {$admin->name} ({$admin->email})\n";

// 3. Test controller
$request = new Illuminate\Http\Request();
$controller = new App\Http\Controllers\Admin\AdminUsersController();

try {
    $response = $controller->index($request);
    
    if ($response instanceof Illuminate\View\View) {
        $data = $response->getData();
        echo "\nController Response:\n";
        echo "- Users count: " . $data['users']->count() . "\n";
        echo "- Users total: " . $data['users']->total() . "\n";
        echo "- Current page: " . $data['users']->currentPage() . "\n";
        echo "- Per page: " . $data['users']->perPage() . "\n";
        
        // Test tableData
        $tableData = collect($data['users']->items() ?? [])->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name ?? 'Unknown',
                'email' => $user->email ?? '',
                'role' => $user->role ?? 'member',
                'status' => $user->is_active ? 'active' : 'inactive',
                'tenant' => $user->tenant->name ?? 'No Tenant',
                'last_login' => $user->last_login_at ? $user->last_login_at->format('M d, Y') : 'Never',
                'created_at' => $user->created_at->format('M d, Y'),
                'updated_at' => $user->updated_at->format('M d, Y')
            ];
        });
        
        echo "\nTable Data:\n";
        echo "- Table data count: " . $tableData->count() . "\n";
        foreach($tableData as $item) {
            echo "- {$item['name']} ({$item['email']}) - Role: {$item['role']}\n";
        }
        
    } else {
        echo "Response is not a View: " . get_class($response) . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
