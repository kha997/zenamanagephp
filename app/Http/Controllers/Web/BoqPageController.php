<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\BoqController as ApiBoqController;
use App\Http\Controllers\Api\BoqLineItemController as ApiBoqLineItemController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\Boq;
use App\Models\Project;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BoqPageController extends Controller
{
    use DelegatesToApiControllers;

    public function index(Request $request): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $query = Boq::query()
            ->where('tenant_id', $tenantId)
            ->with('project:id,tenant_id,name,code')
            ->withCount('lineItems');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return view('boqs.index', [
            'boqs' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'currentSearch' => (string) $request->query('search', ''),
        ]);
    }

    public function create(): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        return view('boqs.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }

    public function store(Request $request, ApiBoqController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated = array_filter($validated, static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $apiController->store($this->buildApiRequest($request, $validated));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        $payload = $response->getData(true);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $boqId = (string) data_get($payload, 'data.id');

            return redirect()
                ->route('operator.boqs.show', $boqId)
                ->with('success', 'Tạo BOQ thành công');
        }

        return $this->handleErrorResponse($response);
    }

    public function show(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $boq = Boq::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'project:id,tenant_id,name,code',
                'lineItems' => fn ($q) => $q->orderBy('created_at'),
            ])
            ->findOrFail($id);

        return view('boqs.show', ['boq' => $boq]);
    }

    public function storeLine(Request $request, string $boq, ApiBoqLineItemController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        $validated = array_filter($validated, static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $apiController->store($this->buildApiRequest($request, $validated), $boq);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể thêm hạng mục.');
        }

        return $this->handleMutationResponse($response, route('operator.boqs.show', $boq), 'Đã thêm hạng mục');
    }
}
