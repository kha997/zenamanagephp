<?php

/*
|--------------------------------------------------------------------------
| Debug Routes
|--------------------------------------------------------------------------
|
| GAP-011 canonical boundary: this file is the sole legal declaration
| site for `_debug/*` routes and their compatibility aliases. It is
| registered from exactly one place — app/Providers/RouteServiceProvider.php
| — only when app()->environment(['local', 'testing', 'development']) is
| true, so the routes below do not exist at all in the production route
| table (uncached or cached). DebugGateMiddleware remains on the `_debug`
| group as defense-in-depth against a route being declared or mounted
| incorrectly.
|
| See: docs/owner-decisions/GAP-011/02-design-v4.md
|
*/

use Illuminate\Support\Facades\Route;

Route::prefix('_debug')->middleware([\App\Http\Middleware\DebugGateMiddleware::class])->group(function () {
    // Mock dashboard KPI JSON fixture, consumed by resources/views/app/dashboard-content.blade.php
    Route::get('/dashboard-data', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'totalTasks' => 15,
                    'completedTasks' => 8,
                    'teamMembers' => 5,
                    'totalProjects' => 7,
                ],
                'recentActivity' => [
                    ['user' => 'John Doe', 'action' => 'completed task', 'time' => '2 minutes ago'],
                    ['user' => 'Jane Smith', 'action' => 'created project', 'time' => '15 minutes ago'],
                    ['user' => 'Mike Johnson', 'action' => 'updated status', 'time' => '1 hour ago'],
                ],
                'quickActions' => [
                    ['title' => 'Create Project', 'icon' => 'fas fa-plus', 'url' => '/app/projects/create'],
                    ['title' => 'Add Task', 'icon' => 'fas fa-tasks', 'url' => '/app/tasks/create'],
                    ['title' => 'Invite Team', 'icon' => 'fas fa-user-plus', 'url' => '/app/team/invite'],
                ],
            ],
        ]);
    });

    // Quick-login helper, consumed by resources/views/auth/login.blade.php's
    // demo-user links (itself only rendered under local/testing/development)
    Route::get('/test-login/{email}', function (string $email) {
        $user = \App\Models\User::where('email', $email)->first();

        if (!$user) {
            return "No such user: {$email}. Run php artisan db:seed (UserSeeder) first.";
        }

        \Illuminate\Support\Facades\Auth::login($user);
        request()->session()->regenerate();

        return redirect('/app/dashboard');
    });
});

// Legacy typing-convenience alias (GAP-011 Class B, local-only, unchanged
// from its pre-existing condition): a developer typing /debug/x is
// forwarded to /_debug/x. Not a production compatibility concern — no
// evidence anything outside a developer's own browser bar ever used the
// bare /debug/ form.
if (app()->environment('local')) {
    Route::get('/debug/{path?}', function ($path = '') {
        return redirect("/_debug/{$path}", 301);
    })->where('path', '.*');
}
