<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    /**
     * GET /services
     * يرجع خدمات الشركة الحالية بس
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Service::class);

        $company = $request->user()->providerProfile;

        $services = Service::where('company_id', $company->id)
            ->latest()
            ->get();

        return response()->json($services);
    }

    /**
     * POST /services
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Service::class);

        $company = $request->user()->providerProfile;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('services', 'name')->where('company_id', $company->id),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $service = Service::create([
            'company_id'  => $company->id,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json($service, 201);
    }

    public function update(Request $request, string $id)
    {
        $service = Service::findOrFail($id);

        Gate::authorize('update', $service);

        $validated = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('services', 'name')
                    ->where('company_id', $service->company_id)
                    ->ignore($service->id),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $service->update($validated);

        return response()->json($service);
    }

    
    public function destroy(string $id)
    {
        $service = Service::findOrFail($id);

        Gate::authorize('delete', $service);

        if ($service->jobOffers()->exists()) {
            throw ValidationException::withMessages([
                'service' => 'لا يمكن حذف هذه الخدمة لأنها مرتبطة بعرض وظيفة موجود.',
            ]);
        }

        $service->delete();

        return response()->json(['message' => 'تم حذف الخدمة بنجاح.']);
    }
}