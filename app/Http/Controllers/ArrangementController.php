<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArrangementStoreRequest;
use App\Http\Requests\ArrangementUpdateRequest;
use App\Models\Listing;
use App\Services\ArrangementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;

class ArrangementController extends Controller
{
    protected ArrangementService $arrangementService;

    public function __construct(ArrangementService $arrangementService)
    {
        $this->arrangementService = $arrangementService;
    }

    /**
     * Store a new ready arrangement with products and freelancers
     */
    public function store(ArrangementStoreRequest $request): JsonResponse
    {
        try {
            $company = $request->user()->providerProfile;

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'ملف الشركة غير موجود.',
                ], 403);
            }

            $arrangement = $this->arrangementService->createArrangement(
                $request->validated(),
                $company->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء التنسيق الجاهز بنجاح وهو قيد المراجعة حالياً.',
                'data'    => $arrangement->load('images')
            ], 201);
        } catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'خطأ: ' . $e->getMessage(), // سيعطيك الرسالة الفعلية للخطأ
        'file' => $e->getFile(),               // الملف الذي حدث فيه الخطأ
        'line' => $e->getLine()                // السطر الذي حدث فيه الخطأ
    ], 500);
}
    }

    /**
     * Get arrangement details with all related data
     */
    public function show(string $arrangementId, Request $request): JsonResponse
    {
        try {
            $arrangement = Listing::where('listing_type', 'package')
                ->with(['variants', 'images', 'category', 'district'])
                ->findOrFail($arrangementId);

            return response()->json([
                'success' => true,
                'data' => $arrangement
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch arrangement', ['id' => $arrangementId, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'التنسيق غير موجود.'
            ], 404);
        }
    }

    /**
     * Update an existing arrangement
     */
    public function update(string $arrangementId, Request $request): JsonResponse
    {
        try {
            $company = $request->user()->providerProfile;

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'ملف الشركة غير موجود.',
                ], 403);
            }

            $arrangement = Listing::where('listing_type', 'package')
                ->where('provider_id', $company->id)
                ->findOrFail($arrangementId);

            $updated = $this->arrangementService->updateArrangement(
                $arrangement,
                $request->validated(),
                $company->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث التنسيق بنجاح.',
                'data' => $updated->load('images')
            ], 200);
        } catch (Exception $e) {
            Log::error('Arrangement update failed', [
                'arrangement_id' => $arrangementId,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            $statusCode = in_array($e->getCode(), [403, 404, 422]) ? $e->getCode() : 500;
            $message = $statusCode === 500
                ? 'فشل تحديث التنسيق. يرجى المحاولة لاحقاً.'
                : $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message
            ], $statusCode);
        }
    }

    /**
     * Get company products for dropdown
     */
    public function getMyProducts(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->providerProfile;
            if (!$company) {
                return response()->json(['message' => 'ملف الشركة غير موجود.'], 403);
            }

            $products = $this->arrangementService->getProviderProducts($company->id);

            return response()->json([
                'success' => true,
                'data' => $products
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch provider products', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب المنتجات.'
            ], 500);
        }
    }

    /**
     * Get available freelancers for collaboration
     */
    public function getFreelancersList(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->providerProfile;

            $freelancers = $this->arrangementService->getAvailableFreelancers($company->id);

            return response()->json([
                'success' => true,
                'data' => $freelancers
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch freelancers', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'فشل جلب الفريلانسرز.',
            ], 500);
        }
    }
}

