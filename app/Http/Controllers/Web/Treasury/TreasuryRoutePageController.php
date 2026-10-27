<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Treasury\TreasuryPaymentRoute;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryDuplicateSuspected;
use App\Services\Treasury\TreasuryRouteService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * GAP-094 S4b-1 — operator pages for via-route payments: create a route
 * (from the project Treasury page), the route page with its legs, record a
 * leg, reverse a leg, cancel. Rules live in TreasuryRouteService.
 * GAP-095: trace a paid contract payment from the project page.
 */
class TreasuryRoutePageController extends Controller
{
    private const AMOUNT_RULES = ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'];

    public function __construct(
        private readonly TreasuryRouteService $routes,
        private readonly TreasuryBalanceService $balances,
    ) {
    }

    public function show(string $project, string $treasuryRoute): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $route = $this->findRoute($model, $treasuryRoute);
        $user = $this->user();
        $wallets = TreasuryWallet::query()
            ->where('tenant_id', (string) $model->tenant_id)
            ->where('project_id', (string) $model->id)
            ->orderBy('name')
            ->get();

        return view('treasury.route', [
            'project' => $model,
            'route' => $this->routes->detail($route),
            'wallets' => $wallets,
            'walletBalances' => $this->balances->projectSummary($model)['wallets'],
            'canManageRoutes' => Gate::forUser($user)->allows('treasury.manage-routes', $model),
            // Anyone who may record at least one leg sees the form; the service checks the wallets.
            'canRecordLeg' => Gate::forUser($user)->allows('treasury.manage-routes', $model)
                || Gate::forUser($user)->allows('treasury.create-transfer', $model),
        ]);
    }

    public function store(Request $request, string $project): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-routes', $model);
        $data = $request->validate([
            'document_type' => ['required', Rule::in(TreasuryRouteService::ROUTE_TYPES)],
            'source_party_id' => ['nullable', 'string'],
            'source_wallet_id' => ['nullable', 'string'],
            'destination_wallet_id' => ['required', 'string'],
            'amount' => self::AMOUNT_RULES,
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $route = $this->routes->createRoute($model, $this->user(), $data, $request->boolean('confirm_duplicate'));
        } catch (TreasuryDuplicateSuspected $e) {
            return back()->withInput()->with('treasury_duplicate', $e->getMessage());
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'route' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.routes.show', [$model->id, $route->id])->with('success', 'Đã tạo tuyến tiền. Ghi từng chặng khi tiền đi qua.');
    }

    /** GAP-095: trace (part of) a paid contract payment from the project page. */
    public function storeContractPaymentRoute(Request $request, string $project, string $contractPayment): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-routes', $model);
        $payment = $this->routes->projectPayment($model, $contractPayment);
        abort_if($payment === null, 404);
        $data = $request->validate([
            'expected_destination_wallet_id' => ['required', 'string'],
            'amount' => self::AMOUNT_RULES,
        ]);

        try {
            $route = $this->routes->createContractPaymentRoute($model, $this->user(), $payment, $data, $request->boolean('confirm_duplicate'));
        } catch (TreasuryDuplicateSuspected $e) {
            return back()->withInput()->with('treasury_duplicate', $e->getMessage());
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'route' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.routes.show', [$model->id, $route->id])->with('success', 'Đã tạo tuyến truy vết đợt thanh toán. Ghi từng chặng khi tiền đi qua.');
    }

    public function storeLeg(Request $request, string $project, string $treasuryRoute): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $route = $this->findRoute($model, $treasuryRoute);
        $data = $request->validate([
            'from_wallet_id' => ['nullable', 'string'],
            'to_wallet_id' => ['required', 'string'],
            'amount' => self::AMOUNT_RULES,
            'occurred_at' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $this->routes->recordLeg($model, $this->user(), $route, $data);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'leg' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.routes.show', [$model->id, $route->id])->with('success', 'Đã ghi chặng.');
    }

    public function reverseLeg(Request $request, string $project, string $treasuryRouteLeg): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-routes', $model);
        $leg = $this->routes->projectLeg($model, $treasuryRouteLeg);
        abort_if($leg === null, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        try {
            $this->routes->reverseLeg($model, $this->user(), $leg, (string) $data['reason']);
        } catch (TreasuryRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'leg' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.routes.show', [$model->id, $leg->payment_route_id])->with('success', 'Đã đảo chặng.');
    }

    public function cancel(Request $request, string $project, string $treasuryRoute): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-routes', $model);
        $route = $this->findRoute($model, $treasuryRoute);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        try {
            $this->routes->cancelRoute($model, $this->user(), $route, (string) $data['reason']);
        } catch (TreasuryRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'route' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.routes.show', [$model->id, $route->id])->with('success', 'Đã huỷ tuyến.');
    }

    private function findProject(string $id): Project
    {
        /** @var Project $project */
        $project = Project::query()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $project;
    }

    private function findRoute(Project $project, string $id): TreasuryPaymentRoute
    {
        $route = $this->routes->projectRoute($project, $id);
        abort_if($route === null, 404);

        return $route;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
