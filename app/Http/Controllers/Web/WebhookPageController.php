<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WebhookPageController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        $tenantId = (string) Auth::user()?->tenant_id;

        return view('webhooks.index', [
            'endpoints' => WebhookEndpoint::query()
                ->forTenant($tenantId)
                ->with('creator:id,name')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WebhookEndpoint::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => [
                'required',
                'url',
                'starts_with:https://',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (\App\Jobs\DeliverWebhook::resolvesToBlockedAddress((string) $value)) {
                        $fail('URL webhook không được trỏ tới địa chỉ nội bộ (loopback/private/link-local).');
                    }
                },
            ],
            'event_keys' => ['nullable', 'string', 'max:500'],
        ]);

        $eventKeys = collect(explode(',', (string) ($validated['event_keys'] ?? '')))
            ->map(fn (string $key) => trim($key))
            ->filter()
            ->values()
            ->all();

        if ($eventKeys === []) {
            $eventKeys = ['*'];
        }

        $secret = Str::random(48);

        WebhookEndpoint::query()->create([
            'tenant_id' => (string) Auth::user()?->tenant_id,
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $secret,
            'event_keys' => $eventKeys,
            'is_active' => true,
            'created_by' => (string) Auth::id(),
        ]);

        return redirect(route('operator.webhooks.index'))
            ->with('success', 'Đã tạo webhook.')
            ->with('one_time_secret', $secret);
    }

    public function toggle(string $id): RedirectResponse
    {
        $endpoint = $this->findTenantEndpoint($id);
        $this->authorize('update', $endpoint);

        $endpoint->is_active = !$endpoint->is_active;
        $endpoint->save();

        return redirect(route('operator.webhooks.index'))
            ->with('success', $endpoint->is_active ? 'Đã bật webhook' : 'Đã tắt webhook');
    }

    public function destroy(string $id): RedirectResponse
    {
        $endpoint = $this->findTenantEndpoint($id);
        $this->authorize('delete', $endpoint);

        $endpoint->delete();

        return redirect(route('operator.webhooks.index'))->with('success', 'Đã xóa webhook');
    }

    private function findTenantEndpoint(string $id): WebhookEndpoint
    {
        return WebhookEndpoint::query()
            ->forTenant((string) Auth::user()?->tenant_id)
            ->findOrFail($id);
    }
}
