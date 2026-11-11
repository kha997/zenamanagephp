<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\SlackChannelService;
use App\Services\ZenaAuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-124 (Gate 2 S-1) — the company's Slack channel for business notifications (notification.channel.manage). The URL is
 * validated (hooks.slack.com only), stored encrypted and never shown in full; a test button posts one message.
 */
class NotificationSettingsPageController extends Controller
{
    public function __construct(
        private readonly SlackChannelService $slack,
        private readonly ZenaAuditLogger $audit,
    ) {
    }

    public function show(): View
    {
        return view('settings.notifications', ['slack' => $this->slack->status($this->tenantId())]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slack_webhook_url' => ['nullable', 'string', 'max:500'],
            'slack_enabled' => ['nullable', 'boolean'],
            'slack_remove' => ['nullable', 'boolean'],
        ]);
        $url = trim((string) ($data['slack_webhook_url'] ?? ''));
        $enabled = (bool) ($data['slack_enabled'] ?? false);

        // The page never shows the saved URL in full, so an empty field keeps it and only flips the switch;
        // "Gỡ kênh Slack" removes it.
        if ($url === '' && !(bool) ($data['slack_remove'] ?? false)) {
            $url = (string) $this->slack->storedUrl($this->tenantId());
        }
        if ($url !== '' && !$this->slack->isValidUrl($url)) {
            return back()->withErrors(['slack_webhook_url' => 'Đường link phải là link Slack dạng https://hooks.slack.com/services/…']);
        }

        $this->slack->save($this->tenantId(), $url === '' ? null : $url, $enabled);
        $this->audit->log($request, 'notification.channel.slack.updated', 'tenant', $this->tenantId(), 200, null, $this->tenantId(), (string) $this->user()->id, [
            'configured' => $url !== '',
            'enabled' => $url !== '' && $enabled,
        ]);

        return redirect()->route('app.settings.notifications')->with('success', $url === '' ? 'Đã gỡ kênh Slack.' : 'Đã lưu kênh Slack.');
    }

    public function test(): RedirectResponse
    {
        $tenant = Tenant::query()->whereKey($this->tenantId())->first(['id', 'name']);
        $name = $tenant instanceof Tenant ? (string) $tenant->name : '';
        $ok = $this->slack->post($this->tenantId(), "Zena: kết nối Slack của {$name} hoạt động. Thông báo nghiệp vụ sẽ gửi vào kênh này.");

        return $ok
            ? redirect()->route('app.settings.notifications')->with('success', 'Đã gửi tin thử vào Slack.')
            : redirect()->route('app.settings.notifications')->with('error', 'Không gửi được. Kiểm tra đường link Slack và công tắc "Bật".');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function tenantId(): string
    {
        return (string) $this->user()->tenant_id;
    }
}
