<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\WorkTemplateController as ApiWorkTemplateController;
use App\Http\Controllers\Controller;
use App\Models\WorkTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkTemplateApplyController extends Controller
{
    public function templates(Request $request, string $project): JsonResponse
    {
        $tenantId = (string) Auth::user()->tenant_id;

        $templates = WorkTemplate::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('publishedVersions')
            ->with('publishedVersions')
            ->orderBy('name')
            ->get()
            ->map(function (WorkTemplate $template): array {
                $latestPublished = $template->publishedVersions
                    ->sortByDesc('published_at')
                    ->first();

                return [
                    'id' => (string) $template->id,
                    'name' => $template->name,
                    'code' => $template->code,
                    'latest_published_version_id' => $latestPublished ? (string) $latestPublished->id : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function preview(Request $request, string $project): JsonResponse
    {
        $request->merge(['dry_run' => true]);

        return app(ApiWorkTemplateController::class)->applyToProject($request, $project);
    }

    public function apply(Request $request, string $project): JsonResponse
    {
        $request->merge(['dry_run' => false]);

        return app(ApiWorkTemplateController::class)->applyToProject($request, $project);
    }
}
