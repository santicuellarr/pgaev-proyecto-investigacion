<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUserIndexRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    public function users(AdminUserIndexRequest $request): JsonResponse
    {
        $paginator = $this->adminService->listUsers(
            $request->validated('role'),
            $request->validated('q'),
            (int) ($request->validated('page') ?? 1),
        );

        return response()->json([
            'users' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        return response()->json([
            'user' => $this->adminService->updateRole(
                $request->user(),
                $user,
                $request->validated('role'),
            ),
        ]);
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        return response()->json([
            'user' => $this->adminService->updateStatus(
                $request->user(),
                $user,
                $request->validated('status'),
            ),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        return response()->json($this->adminService->overview());
    }
}
