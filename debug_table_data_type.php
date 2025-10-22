<?php
// Debug table data type
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "=== DEBUG TABLE DATA TYPE ===\n";

// Login as admin
$admin = User::where('email', 'admin@zena.local')->first();
Auth::login($admin);

// Test controller
$request = new Illuminate\Http\Request();
$controller = new App\Http\Controllers\Admin\AdminUsersController();

$response = $controller->index($request);
$data = $response->getData();

echo "Users type: " . get_class($data['users']) . "\n";
echo "Users count: " . $data['users']->count() . "\n";

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

echo "TableData type: " . get_class($tableData) . "\n";
echo "TableData count: " . $tableData->count() . "\n";
echo "TableData toArray count: " . count($tableData->toArray()) . "\n";
echo "TableData isEmpty: " . ($tableData->isEmpty() ? 'true' : 'false') . "\n";

// Test component logic
$items = $tableData;
$hasItems = !empty($items) && count($items) > 0;
echo "hasItems: " . ($hasItems ? 'true' : 'false') . "\n";

echo "\n=== DEBUG COMPLETE ===\n";
