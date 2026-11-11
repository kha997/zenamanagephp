<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-124 (Gate 2 B-1) — the signed-in user's notifications: list (all or unread), open one (marks it read and follows
 * its link, only links inside the app), and mark all read. Always scoped to the user and their company.
 */
class NotificationPageController extends Controller
{
    public function index(Request $request): View
    {
        $unreadOnly = $request->boolean('unread');
        $notifications = $this->mine()
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('notifications.inbox', [
            'notifications' => $notifications,
            'unreadOnly' => $unreadOnly,
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $this->mine()->whereKey($id)->firstOrFail();
        if ($notification->getAttribute('read_at') === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }
        $path = $this->localPath((string) $notification->getAttribute('link_url'), $request->getSchemeAndHttpHost());

        return $path !== null ? redirect($path) : redirect()->route('app.notifications.index');
    }

    /**
     * Only destinations inside the app are followed: a path ("/…", not "//host"), or an absolute URL on this app's own
     * scheme and host (some producers, e.g. the month-end reminders, store route() output), reduced to its path and query.
     */
    private function localPath(string $link, string $origin): ?string
    {
        if (str_starts_with($link, '/') && !str_starts_with($link, '//')) {
            return $link;
        }
        $parts = parse_url($link);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $linkOrigin = strtolower($parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        $appOrigins = [strtolower($origin), strtolower(rtrim((string) config('app.url'), '/'))];
        if (!in_array($linkOrigin, $appOrigins, true)) {
            return null;
        }
        $path = ($parts['path'] ?? '') !== '' ? (string) $parts['path'] : '/';

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    public function readAll(): RedirectResponse
    {
        $this->mine()->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('app.notifications.index')->with('success', 'Đã đánh dấu đã đọc tất cả thông báo.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Notification>
     */
    private function mine(): \Illuminate\Database\Eloquent\Builder
    {
        /** @var User $user */
        $user = Auth::user();

        return Notification::query()
            ->where('tenant_id', (string) $user->tenant_id)
            ->where('user_id', (string) $user->id);
    }
}
