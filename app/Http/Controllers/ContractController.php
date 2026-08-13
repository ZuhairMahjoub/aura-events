<?php

namespace App\Http\Controllers;

use App\Models\CompanyFreelancerContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    /**
     * GET /freelancer/contracts
     * كل عقود الفريلانسر الحالي، بكل الحالات (pending/active/rejected/expired).
     * قابلة للفلترة اختيارياً عبر ?status=active
     */
    public function myContracts(Request $request): JsonResponse
    {
        $freelancer = $request->user()->providerProfile;

        $contracts = CompanyFreelancerContract::with(['company', 'jobOffer'])
            ->where('freelancer_id', $freelancer->id)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $contracts,
        ]);
    }

    /**
     * GET /company/contracts
     * كل عقود الشركة الحالية مع الفريلانسرز المرتبطين بها.
     * قابلة للفلترة اختيارياً عبر ?status=active
     */
    public function companyContracts(Request $request): JsonResponse
    {
        $company = $request->user()->providerProfile;

        $contracts = CompanyFreelancerContract::with([
            'freelancer.user',
            'freelancer.freelancerDetails',
            'freelancer.categories',
            'jobOffer'
        ])
            ->where('company_id', $company->id)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $contracts,
        ]);
    }
}
