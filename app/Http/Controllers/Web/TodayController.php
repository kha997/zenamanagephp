<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\TodayWorkspaceReadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Hôm nay" — trang tổng hợp read-only theo vai trò.
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md
 */
class TodayController extends Controller
{
    public function __construct(private readonly TodayWorkspaceReadService $todayWorkspaceReadService)
    {
    }

    public function index(): View
    {
        $actor = Auth::user();

        $workspace = $this->todayWorkspaceReadService->build($actor);

        return view('app.today', ['workspace' => $workspace]);
    }
}
