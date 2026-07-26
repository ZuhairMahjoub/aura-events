<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * GET /admin/roles
     * عرض كل الأدوار الموجودة بالنظام مع صلاحياتها.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * POST /admin/roles
     * إنشاء دور جديد، مع إمكانية ربطه بصلاحيات موجودة مسبقاً.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'api')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'api']);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الدور بنجاح.',
            'data' => $role->load('permissions'),
        ], 201);
    }

    /**
     * PUT /admin/users/{id}/roles
     * تعيين دور (أو أكثر) لمستخدم معيّن.
     * body: { "roles": ["admin", "provider"] }
     */
    public function assignToUser(Request $request, string $id): JsonResponse
    {
        $user = \App\Models\User::findOrFail($id);

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        // syncRoles بدل assignRole: تستبدل كل أدوار المستخدم الحالية بالقائمة
        // الجديدة دفعة واحدة، بدل التراكم فوق أدوار سابقة قد لا تعود مناسبة.
        $user->syncRoles($validated['roles']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث أدوار المستخدم بنجاح.',
            'data' => $user->load('roles'),
        ]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'api')->get();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }
}