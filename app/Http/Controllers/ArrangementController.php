<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArrangementStoreRequest;
use App\Http\Requests\ArrangementUpdateRequest;
use App\Http\Resources\ArrangementResource;
use App\Models\Listing;
use App\Services\ArrangementService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Services\ListingService;

class ArrangementController extends Controller
{
    public function __construct(protected ArrangementService $arrangementService, protected ListingService $listingService) {
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Package CRUD
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * POST /arrangements
     * Create a new ready-arrangement (package listing).
     */
    public function store(ArrangementStoreRequest $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        if (! $provider) {
            return response()->json([
                'success' => false,
                'message' => 'ملف الشركة غير موجود.',
            ], 403);
        }

        try {
            $arrangement = $this->arrangementService->createArrangement(
                $request->validated(),
                $provider->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الترتيب الجاهز بنجاح وهو قيد المراجعة حالياً.',
                'data'    => new ArrangementResource($arrangement),
            ], 201);
        } catch (Exception $e) {
            $knownCode = in_array($e->getCode(), [403, 422]) ? $e->getCode() : 500;

            Log::error('ArrangementController@store failed', [
                'provider_id' => $provider->id,
                'error'       => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $knownCode === 500
                    ? 'حدث خطأ أثناء إنشاء الترتيب. يرجى المحاولة لاحقاً.'
                    : $e->getMessage(),
            ], $knownCode);
        }
    }

    /**
     * GET /arrangements/{arrangementId}
     * Retrieve full details for a single package listing.
     */
    public function show(string $arrangementId): JsonResponse
    {
        $arrangement = Listing::where('listing_type', 'package')
            ->with([
                'images',
                'category',
                'district',
                'variants.packageItems.includedVariant.images',
                'variants.packageFreelancers.contract.jobOffer.service',
                'variants.packageItems.includedVariant.listing',
                'variants.packageFreelancers.freelancer',
                'variants.availabilities.slots'
            ])
            ->find($arrangementId);

        if (! $arrangement) {
            return response()->json([
                'success' => false,
                'message' => 'الترتيب غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new ArrangementResource($arrangement),
        ]);
    }

    /**
     * PUT /arrangements/{arrangementId}
     * Update an existing package listing owned by the authenticated company.
     */
    public function update(string $arrangementId, ArrangementUpdateRequest $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        if (! $provider) {
            return response()->json([
                'success' => false,
                'message' => 'ملف الشركة غير موجود.',
            ], 403);
        }

        $arrangement = Listing::where('listing_type', 'package')
            ->where('provider_id', $provider->id)
            ->find($arrangementId);

        if (! $arrangement) {
            return response()->json([
                'success' => false,
                'message' => 'الترتيب غير موجود أو لا تملك صلاحية تعديله.',
            ], 404);
        }

        try {
            $updated = $this->arrangementService->updateArrangement(
                $arrangement,
                $request->validated(),
                $provider->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الترتيب بنجاح.',
                'data'    => new ArrangementResource($updated),
            ]);
        } catch (Exception $e) {
            $knownCode = in_array($e->getCode(), [403, 422]) ? $e->getCode() : 500;

            Log::error('ArrangementController@update failed', [
                'arrangement_id' => $arrangementId,
                'provider_id'    => $provider->id,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $knownCode === 500
                    ? 'فشل تحديث الترتيب. يرجى المحاولة لاحقاً.'
                    : $e->getMessage(),
            ], $knownCode);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Picker helpers (for the front-end dropdown / search UI)
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * GET /provider/my-products
     */
    public function getMyProducts(Request $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        if (! $provider) {
            return response()->json(['success' => false, 'message' => 'ملف الشركة غير موجود.'], 403);
        }

        try {
            return response()->json([
                'success' => true,
                'data'    => $this->arrangementService->getProviderProducts($provider->id),
            ]);
        } catch (Exception $e) {
            Log::error('ArrangementController@getMyProducts failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'فشل جلب المنتجات.'], 500);
        }
    }

    /**
     * GET /provider/my-services
     */
    public function getMyServices(Request $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        if (! $provider) {
            return response()->json(['success' => false, 'message' => 'ملف الشركة غير موجود.'], 403);
        }

        try {
            return response()->json([
                'success' => true,
                // 'data'    => $this->arrangementService->getProviderServices($provider->id),
            ]);
        } catch (Exception $e) {
            Log::error('ArrangementController@getMyServices failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'فشل جلب الخدمات.'], 500);
        }
    }

    /**
     * GET /provider/my-all-products
     */
    public function getMyAllProducts(Request $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        if (! $provider) {
            return response()->json(['success' => false, 'message' => 'ملف الشركة غير موجود.'], 403);
        }

        try {
            return response()->json([
                'success' => true,
                // 'data'    => $this->arrangementService->getProviderAllProducts($provider->id),
            ]);
        } catch (Exception $e) {
            Log::error('ArrangementController@getMyAllProducts failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'فشل جلب جميع البيانات.'], 500);
        }
    }

    /**
     * GET /provider/available-freelancers
     */
    public function getFreelancersList(Request $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        if (! $provider) {
            return response()->json(['success' => false, 'message' => 'ملف الشركة غير موجود.'], 403);
        }

        try {
            return response()->json([
                'success' => true,
                'data'    => $this->arrangementService->getAvailableFreelancers($provider->id),
            ]);
        } catch (Exception $e) {
            Log::error('ArrangementController@getFreelancersList failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'فشل جلب الفريلانسرز.'], 500);
        }
    }
    /**
     * GET /provider/my-arrangements
     * Retrieve a paginated list of all packages belonging to the authenticated company/provider.
     */
    /**
     * GET /provider/my-arrangements
     * Retrieve a paginated list of all packages belonging to the authenticated company/provider.
     */
    public function getMyPackages(Request $request): JsonResponse
    {
        // جلب ملف المزود مباشرة بنفس أسلوبك الأصلي
        $provider = $request->user()->providerProfile;

        if (! $provider) {
            return response()->json([
                'success' => false,
                'message' => 'ملف الشركة غير موجود.',
            ], 403);
        }

        try {
            $packages = Listing::where('listing_type', 'package')
                ->where('provider_id', $provider->id)
                ->with([
                    'images',
                    'category',
                    'district',
                    'variants.packageItems.includedVariant.images',
                    'variants.packageItems.includedVariant.listing',
                    'variants.packageItems.includedVariant.listing.images',
                    'variants.packageFreelancers.freelancer',
                    'variants.availabilities.slots'
                ])
                ->latest()
                ->paginate($request->query('per_page', 15));

            return response()->json([
                'success' => true,
                'meta'    => [
                    'current_page' => $packages->currentPage(),
                    'last_page'    => $packages->lastPage(),
                    'total'        => $packages->total(),
                ],
                'data'    => ArrangementResource::collection($packages->items()),
            ]);
        } catch (Exception $e) {
            Log::error('ArrangementController@getMyPackages failed', [
                'provider_id' => $provider->id,
                'error'       => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الترتيبات الجاهزة الخاصة بشركتكم.',
            ], 500);
        }
    }
    public function destroy(string $arrangementId): JsonResponse
{
    $arrangement = Listing::where('listing_type', 'package')
        ->findOrFail($arrangementId);

    Gate::authorize('delete', $arrangement);

    try {
        $this->listingService->deleteListing($arrangement);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الترتيب بنجاح.'
        ], Response::HTTP_OK);

    } catch (Exception $e) {
        if ($e->getCode() == '23000') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف هذا الترتيب لوجود حجوزات مرتبطة به مسبقاً.'
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ غير متوقع أثناء محاولة الحذف.'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
}
