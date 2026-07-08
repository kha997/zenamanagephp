<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTokenPageController extends Controller
{
    public function index(): View
    {
        return view('api-tokens.index', [
            'tokens' => auth()->user()->tokens()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'last_used_at', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->user()->createToken($validated['name']);

        return redirect(route('operator.api-tokens.index'))
            ->with('success', 'Đã tạo API token (lưu ngay, chỉ hiện một lần): ' . $token->plainTextToken);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->tokens()->where('id', $id)->delete();

        return redirect(route('operator.api-tokens.index'))->with('success', 'Đã thu hồi token');
    }
}
