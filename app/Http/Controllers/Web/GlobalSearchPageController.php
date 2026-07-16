<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\Contract;
use App\Models\Material;
use App\Models\Ncr;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\SiteDiary;
use App\Models\Submittal;
use App\Models\Vendor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class GlobalSearchPageController extends Controller
{
    private const RESULT_LIMIT = 10;

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $tenantId = (string) auth()->user()?->tenant_id;

        $results = [];

        if ($term !== '' && mb_strlen($term) >= 2) {
            try {
                $results = $this->search($term, $tenantId);
            } catch (\Illuminate\Database\QueryException $exception) {
                // Degrade gracefully during partial installs/migrations
                report($exception);
                $results = [];
            }
        }

        return view('search.index', [
            'term' => $term,
            'results' => $results,
            'totalCount' => array_sum(array_map('count', $results)),
        ]);
    }

    /**
     * @return array<string, list<array{title: string, subtitle: string, url: string, status: ?string}>>
     */
    private function search(string $term, string $tenantId): array
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        $results = [];

        $results['Dự án'] = Project::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Project $project) => [
                'title' => (string) $project->name,
                'subtitle' => (string) ($project->code ?? ''),
                'url' => '#',
                'status' => (string) ($project->status ?? ''),
            ])->all();

        $results['RFI'] = Rfi::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('rfi_number', 'like', $like)->orWhere('subject', 'like', $like))
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Rfi $rfi) => [
                'title' => (string) ($rfi->title ?? $rfi->subject ?? $rfi->rfi_number),
                'subtitle' => (string) ($rfi->rfi_number ?? ''),
                'url' => route('operator.rfis.show', $rfi->id),
                'status' => (string) ($rfi->status ?? ''),
            ])->all();

        $results['Submittals'] = Submittal::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('submittal_number', 'like', $like))
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Submittal $submittal) => [
                'title' => (string) $submittal->title,
                'subtitle' => (string) ($submittal->submittal_number ?? ''),
                'url' => route('operator.submittals.show', $submittal->id),
                'status' => (string) ($submittal->status ?? ''),
            ])->all();

        $results['Change Requests'] = ChangeRequest::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('change_number', 'like', $like))
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (ChangeRequest $changeRequest) => [
                'title' => (string) $changeRequest->title,
                'subtitle' => (string) ($changeRequest->change_number ?? ''),
                'url' => route('operator.change-requests.show', $changeRequest->id),
                'status' => (string) ($changeRequest->status ?? ''),
            ])->all();

        $results['Hợp đồng'] = Contract::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('contract_number', 'like', $like)->orWhere('code', 'like', $like))
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Contract $contract) => [
                'title' => (string) $contract->title,
                'subtitle' => (string) ($contract->contract_number ?? $contract->code ?? ''),
                'url' => route('operator.contracts.show', $contract->id),
                'status' => (string) ($contract->status ?? ''),
            ])->all();

        $results['Vật tư'] = Material::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Material $material) => [
                'title' => (string) $material->name,
                'subtitle' => (string) ($material->code ?? ''),
                'url' => route('operator.materials.show', $material->id),
                'status' => $material->is_active ? 'active' : 'inactive',
            ])->all();

        $results['Nhà cung cấp'] = Vendor::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Vendor $vendor) => [
                'title' => (string) $vendor->name,
                'subtitle' => (string) ($vendor->code ?? ''),
                'url' => route('operator.vendors.show', $vendor->id),
                'status' => $vendor->is_active ? 'active' : 'inactive',
            ])->all();

        $results['NCR'] = Ncr::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('ncr_number', 'like', $like))
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Ncr $ncr) => [
                'title' => (string) $ncr->title,
                'subtitle' => (string) ($ncr->ncr_number ?? ''),
                'url' => $ncr->inspection_id
                    ? route('operator.inspections.ncrs.show', ['inspection' => $ncr->inspection_id, 'ncr' => $ncr->id])
                    : '#',
                'status' => (string) ($ncr->status ?? ''),
            ])->all();

        $results['Nhật ký công trường'] = SiteDiary::query()
            ->forTenant($tenantId)
            ->where(fn ($q) => $q->where('work_performed', 'like', $like)->orWhere('diary_number', 'like', $like))
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (SiteDiary $siteDiary) => [
                'title' => 'Nhật ký ' . optional($siteDiary->diary_date)->format('d/m/Y'),
                'subtitle' => (string) $siteDiary->diary_number,
                'url' => route('operator.site-diaries.show', $siteDiary->id),
                'status' => (string) $siteDiary->status,
            ])->all();

        return array_filter($results, fn (array $items) => $items !== []);
    }
}
