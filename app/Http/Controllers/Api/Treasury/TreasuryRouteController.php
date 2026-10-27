<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Project;
use App\Models\Treasury\TreasuryPaymentRoute;
use App\Models\User;
use App\Services\Treasury\TreasuryDuplicateSuspected;
use App\Services\Treasury\TreasuryRouteService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * GAP-094 S4b-1 — via-route payments: list, detail, create, legs, leg reversal, cancel.
 * GAP-095 S4b-2 — paid contract payments: reminder list and tracing routes.
 */
class TreasuryRouteController extends BaseApiController
{
    private const AMOUNT_RULES = ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'];

    public function __construct(private readonly TreasuryRouteService $routes)
    {
    }

    public function index(Request $request, string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', Rule::in([
                TreasuryPaymentRoute::STATUS_PLANNED, TreasuryPaymentRoute::STATUS_PARTIAL,
                TreasuryPaymentRoute::STATUS_COMPLETED, TreasuryPaymentRoute::STATUS_CANCELLED,
            ])],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        return $this->successResponse($this->routes->routes($model, $request->input('status')), 'Treasury routes retrieved successfully');
    }

    public function show(string $project, string $treasuryRoute): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        $routeModel = $this->routes->projectRoute($model, $treasuryRoute);
        if ($routeModel === null) {
            return $this->notFound('Treasury route not found');
        }

        return $this->successResponse($this->routes->detail($routeModel), 'Treasury route retrieved successfully');
    }

    /** GAP-095: paid contract payments of the project with traced / arrived / remaining. */
    public function contractPayments(string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);

        return $this->successResponse($this->routes->contractPayments($model), 'Treasury contract payments retrieved successfully');
    }

    /** GAP-095: trace (part of) a paid contract payment through a new route. */
    public function storeContractPaymentRoute(Request $request, string $project, string $contractPayment): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.manage-routes', $model);
        $payment = $this->routes->projectPayment($model, $contractPayment);
        if ($payment === null) {
            return $this->notFound('Contract payment not found');
        }
        $validator = Validator::make($request->all(), [
            'expected_destination_wallet_id' => ['required', 'string'],
            'amount' => self::AMOUNT_RULES,
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $route = $this->routes->createContractPaymentRoute($model, $this->user(), $payment, $validator->validated(), $request->boolean('confirm_duplicate'));
        } catch (TreasuryDuplicateSuspected $e) {
            return $this->errorResponse($e->getMessage(), 409, ['code' => 'DUPLICATE_SUSPECTED', 'existing_document_id' => (string) $e->existing->id]);
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'route' => [$e->getMessage()]]);
        }

        return $this->successResponse($this->routes->detail($route), 'Treasury contract payment route created', 201);
    }

    public function store(Request $request, string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.manage-routes', $model);
        $validator = Validator::make($request->all(), [
            'document_type' => ['required', Rule::in(TreasuryRouteService::ROUTE_TYPES)],
            'source_party_id' => ['nullable', 'string'],
            'source_wallet_id' => ['nullable', 'string'],
            'destination_wallet_id' => ['required', 'string'],
            'amount' => self::AMOUNT_RULES,
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $route = $this->routes->createRoute($model, $this->user(), $validator->validated(), $request->boolean('confirm_duplicate'));
        } catch (TreasuryDuplicateSuspected $e) {
            return $this->errorResponse($e->getMessage(), 409, ['code' => 'DUPLICATE_SUSPECTED', 'existing_document_id' => (string) $e->existing->id]);
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'route' => [$e->getMessage()]]);
        }

        return $this->successResponse($this->routes->detail($route), 'Treasury route created', 201);
    }

    public function storeLeg(Request $request, string $project, string $treasuryRoute): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        $routeModel = $this->routes->projectRoute($model, $treasuryRoute);
        if ($routeModel === null) {
            return $this->notFound('Treasury route not found');
        }
        $validator = Validator::make($request->all(), [
            'from_wallet_id' => ['nullable', 'string'],
            'to_wallet_id' => ['required', 'string'],
            'amount' => self::AMOUNT_RULES,
            'occurred_at' => ['required', 'date_format:Y-m-d'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            // The leg permission depends on the wallets (Owner answer 2); the service checks it.
            $this->routes->recordLeg($model, $this->user(), $routeModel, $validator->validated());
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'leg' => [$e->getMessage()]]);
        }

        return $this->successResponse($this->routes->detail($routeModel->refresh()), 'Treasury route leg recorded', 201);
    }

    public function reverseLeg(Request $request, string $project, string $treasuryRouteLeg): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.manage-routes', $model);
        $legModel = $this->routes->projectLeg($model, $treasuryRouteLeg);
        if ($legModel === null) {
            return $this->notFound('Treasury route leg not found');
        }
        $reason = $this->reason($request);
        if ($reason instanceof JsonResponse) {
            return $reason;
        }

        try {
            $this->routes->reverseLeg($model, $this->user(), $legModel, $reason);
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'leg' => [$e->getMessage()]]);
        }
        $routeModel = $this->routes->projectRoute($model, (string) $legModel->payment_route_id);

        return $this->successResponse($routeModel === null ? null : $this->routes->detail($routeModel), 'Treasury route leg reversed');
    }

    public function cancel(Request $request, string $project, string $treasuryRoute): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.manage-routes', $model);
        $routeModel = $this->routes->projectRoute($model, $treasuryRoute);
        if ($routeModel === null) {
            return $this->notFound('Treasury route not found');
        }
        $reason = $this->reason($request);
        if ($reason instanceof JsonResponse) {
            return $reason;
        }

        try {
            $cancelled = $this->routes->cancelRoute($model, $this->user(), $routeModel, $reason);
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'route' => [$e->getMessage()]]);
        }

        return $this->successResponse($this->routes->detail($cancelled), 'Treasury route cancelled');
    }

    private function reason(Request $request): string|JsonResponse
    {
        $validator = Validator::make($request->all(), ['reason' => ['required', 'string', 'max:2000']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        return (string) $request->input('reason');
    }

    private function findProject(string $id): ?Project
    {
        return Project::query()
            ->where('tenant_id', (string) data_get(Auth::user(), 'tenant_id'))
            ->whereKey($id)
            ->first();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
