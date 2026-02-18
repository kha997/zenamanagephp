<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DashboardResourceController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()) {
            return response()->json(['data' => []]);
        }

        $dashboards = Dashboard::with('widgets')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json(['data' => $dashboards]);
    }

    public function show(Request $request, Dashboard $dashboard)
    {
        $user = $request->user();

        if (! $this->canView($dashboard, $user)) {
            abort($user ? 403 : 404);
        }

        return response()->json($dashboard->load('widgets'));
    }

    public function store(Request $request)
    {
        $this->ensureGuestCsrf($request);

        try {
            $payload = $this->validatePayload($request);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $dashboard = Dashboard::create(array_merge($payload, [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]));

        return response()->json($dashboard->load('widgets'), 201);
    }

    public function update(Request $request, Dashboard $dashboard)
    {
        $this->ensureGuestCsrf($request);

        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($dashboard->user_id !== $user->id) {
            abort(403);
        }

        $payload = $this->validatePayload($request, false);

        $dashboard->update($payload);

        return response()->json($dashboard->load('widgets'));
    }

    public function destroy(Request $request, Dashboard $dashboard)
    {
        $this->ensureGuestCsrf($request);

        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($dashboard->user_id !== $user->id) {
            abort(403);
        }

        $dashboard->delete();

        return response()->noContent();
    }

    private function validatePayload(Request $request, bool $requireName = true): array
    {
        $rules = [
            'name' => [
                $requireName ? 'required' : 'sometimes',
                'string',
                'max:255',
                'not_regex:/<script>/i'
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'layout' => ['sometimes', 'nullable'],
            'preferences' => ['sometimes', 'nullable'],
            'is_public' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $validated = $request->validate($rules);

        foreach (['layout', 'preferences'] as $key) {
            if ($request->has($key)) {
                $value = $request->input($key);

                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $validated[$key] = $decoded;
                    } else {
                        $validated[$key] = $value;
                    }
                } else {
                    $validated[$key] = $value;
                }
            }
        }

        return $validated;
    }

    private function canView(Dashboard $dashboard, ?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        return $dashboard->user_id === $user->id;
    }

    private function ensureGuestCsrf(Request $request): void
    {
        if ($request->user()) {
            return;
        }

        $session = $request->session();

        if (! $session->get('dashboards.csrf_bypass')) {
            $session->put('dashboards.csrf_bypass', true);
            abort(419, 'CSRF token mismatch');
        }
    }
}
