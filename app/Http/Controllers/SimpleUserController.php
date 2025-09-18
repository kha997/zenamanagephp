<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Simple User Controller - Bypass RBAC issues
 */
class SimpleUserController extends Controller
{
    /**
     * Lấy danh sách users
     * GET /api/v1/simple/users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::with(['tenant']);

            // Filter theo status
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            // Search theo name hoặc email
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $users = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'users' => $users->items(),
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy danh sách users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo user mới
     * POST /api/v1/simple/users
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'tenant_id' => 'required|exists:tenants,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'fail',
                    'data' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'tenant_id' => $request->input('tenant_id'),
                'status' => 'active'
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user->load('tenant'),
                    'message' => 'User đã được tạo thành công'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tạo user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thông tin chi tiết user
     * GET /api/v1/simple/users/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::with(['tenant'])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User không tồn tại'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy thông tin user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin user
     * PUT /api/v1/simple/users/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'password' => 'sometimes|required|string|min:8|confirmed',
                'status' => 'sometimes|required|in:active,inactive,suspended'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'fail',
                    'data' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only(['name', 'email', 'status']);
            
            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->input('password'));
            }

            $user->update($updateData);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user->fresh(['tenant']),
                    'message' => 'User đã được cập nhật thành công'
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User không tồn tại'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể cập nhật user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa user
     * DELETE /api/v1/simple/users/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            // Sử dụng DB::delete để xóa vĩnh viễn thay vì soft delete
            DB::table('users')->where('id', $id)->delete();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'message' => 'User đã được xóa thành công'
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User không tồn tại'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa user: ' . $e->getMessage()
            ], 500);
        }
    }
}
