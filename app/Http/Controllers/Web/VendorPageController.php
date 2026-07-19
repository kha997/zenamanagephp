<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\VendorController as ApiVendorController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\Vendor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class VendorPageController extends Controller
{
    use DelegatesToApiControllers;

    public function index(Request $request): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $query = Vendor::query()->where('tenant_id', $tenantId);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%");
            });
        }

        return view('vendors.index', [
            'vendors' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'currentSearch' => (string) $request->query('search', ''),
        ]);
    }

    public function create(): View
    {
        return view('vendors.create');
    }

    public function store(Request $request, ApiVendorController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        $validated = array_filter($validated, static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $apiController->store($this->buildApiRequest($request, $validated));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.vendors.index'), 'Tạo nhà cung cấp thành công');
    }

    public function show(string $id): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $vendor = Vendor::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        return view('vendors.show', ['vendor' => $vendor]);
    }
}
