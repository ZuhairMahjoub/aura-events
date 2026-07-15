<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Requests\StoreListingRequest;
use App\Http\Resources\ArrangementResource;
use App\Models\Listing;
use App\Services\ListingService;
use App\Http\Resources\ListingResource;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    protected ListingService $listingService;


    public function __construct(ListingService $listingService)
    {
        $this->listingService = $listingService;
    }
public function getCompanyInventory(Request $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'ملف الشركة غير موجود.',
            ], 403);
        }

        // 1. جلب الصالات (Halls) مع علاقاتها الخاصة (مثل الحجوزات أو الميزات إن وجدت)
      $halls = Listing::where('provider_id', $provider->id)
    ->where('listing_type', 'service') // ✅ فلتر النوع
    ->with(['images', 'category', 'district', 'variants.images', 'variants.availabilities.slots'])
    ->latest()
    ->get();
        // 2. جلب المنتجات المادية (Physical Products)
        $products = Listing::where('provider_id', $provider->id)
            ->where('listing_type', 'physical_product')
            ->with(['images', 'category', 'district', 'variants'])
            ->get();

        // 3. جلب البكجات / الترتيبات الجاهزة (Packages) مع علاقاتها المعقدة كاملة
        $packages = Listing::where('provider_id', $provider->id)
            ->where('listing_type', 'package')
            ->with([
                'variants.packageItems.includedVariant.listing',
                'variants.packageFreelancers.freelancer',
                'images',
                'category',
                'district'
            ])
            ->get();

        // 4. إرجاع النتيجة منسقة ومقسمة نظيفة للفرونت إند
        return response()->json([
            'success' => true,
            'data'    => [
                'halls'    => ListingResource::collection($halls),       // ريسورس الصالات والخدمات
                'products' => ListingResource::collection($products),    // ريسورس المنتجات
                'packages' => ArrangementResource::collection($packages), // ريسورس البكجات المخصص
            ]
        ], Response::HTTP_OK);
    }
  
   public function index(): JsonResponse
{
    // Gate::authorize('viewAny', Listing::class);

    $listings = $this->listingService->getAllListings();

    // إضافة 'success' => true كبيانات إضافية مع الـ Resource
    return ListingResource::collection($listings)
        ->additional(['success' => true])
        ->response()
        ->setStatusCode(Response::HTTP_OK);
}

  
    public function store(StoreListingRequest $request): JsonResponse
    {
        Gate::authorize('create', Listing::class);

        $listing = $this->listingService->createListingWithGraph($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Listing created successfully.',
            'data'    => new ListingResource($listing)
        ], Response::HTTP_CREATED);
    }

   
//    public function show(Listing $listing): JsonResponse
// {
//     // 🔒 لارافيل سيفحص دالة view داخل الـ ListingPolicy ويمرر لها الـ $listing تلقائياً
//     Gate::authorize('view', $listing);

//     // شحن العلاقات مسبقاً (Eager Loading) لحل مشكلة الـ N+1 وضمان سرعة الأداء
//     $listing->load(['category', 'district', 'images', 'variants.images', 'variants.availabilities.slots']);

//     return response()->json([
//         'success' => true,
//         'message' => 'Listing retrieved successfully.',
//         'data'    => new ListingResource($listing)
//     ], 200);
// }
  
   public function update(UpdateListingRequest $request, Listing $listing)
{
    $updatedListing = $this->listingService->updateListingWithGraph($listing, $request->validated());

    return response()->json([
        'message' => 'Hall and its custom packages synchronized successfully!',
        'data'    => new ListingResource($updatedListing)
    ], 200);
}

    

public function destroy(Listing $listing): JsonResponse
{
    Gate::authorize('delete', $listing);

    try {
        $this->listingService->deleteListing($listing);


        
        return response()->json([
            'success' => true,
            'message' => 'Listing deleted successfully.'
        ], Response::HTTP_OK);
        
    } catch (Exception $e) {
        if ($e->getCode() == '23000') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف هذه الفعالية لوجود حجوزات مرتبطة بها مسبقاً.'
            ], Response::HTTP_CONFLICT); }

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ غير متوقع أثناء محاولة الحذف.'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }}

    public function show(string $id, Request $request): JsonResponse
{
    $listing = Listing::with([
        'images',
        'category',
        'district',
        'variants.images',
        'variants.availabilities.slots',
        'variants.packageItems.includedVariant.listing',
        'variants.packageFreelancers.freelancer',
    ])->findOrFail($id);

    return response()->json([
        'success' => true,
        'data'    => new ListingResource($listing)
    ]);
}
    /**
 * GET /provider/my-products
 * جلب المنتجات المادية (Physical Products) الخاصة بالشركة الحالية فقط
 */
public function getCompanyProducts(Request $request): JsonResponse
{
    // 1. جلب ملف المزود الحالي والتحقق من وجوده
    $provider = $request->user()->providerProfile;

    if (!$provider) {
        return response()->json([
            'success' => false,
            'message' => 'ملف الشركة غير موجود.',
        ], 403);
    }

    try {
        // 2. تصفية النتائج بناءً على معرف الشركة ونوع الـ listing ليجلب المنتجات المادية فقط
        $products = Listing::where('provider_id', $provider->id)
            ->where('listing_type', 'physical_product') 
            ->with(['images', 'category', 'district', 'variants']) // شحن العلاقات المسبق للأداء المنظم
            ->latest()
            ->paginate($request->query('per_page', 15));

        // 3. إرجاع البيانات منسقة عبر الـ Resource مع الـ Pagination Meta
        return response()->json([
            'success' => true,
            'meta'    => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
                'per_page'     => $products->perPage(),
            ],
            'data'    => ListingResource::collection($products),
        ], Response::HTTP_OK);

    } catch (\Exception $e) {
        // 4. تسجيل أي خطأ غير متوقع في الـ Log لتسهيل معالجته ومراقبته
        \Illuminate\Support\Facades\Log::error('ListingController@getCompanyProducts failed', [
            'provider_id' => $provider->id,
            'error'       => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء جلب المنتجات الخاصة بشركتكم.',
        ], 500);
    }
}
    /**
 * GET /provider/my-services
 * جلب الخدمات والصـالات الخاصة بالشركة الحالية فقط
 */
public function getCompanyServices(Request $request): JsonResponse
{
    // جلب ملف المزود والتحقق منه بنفس أسلوبك المعتمد
    $provider = $request->user()->providerProfile;

    if (!$provider) {
        return response()->json([
            'success' => false,
            'message' => 'ملف الشركة غير موجود.',
        ], 403);
    }

    try {
        // فحص نوع الـ listing_type ليكون 'service' فقط وتصفية النتائج حسب الشركة
        $services = Listing::where('provider_id', $provider->id)
            ->where('listing_type', 'service') 
            ->with(['images', 'category', 'district', 'variants']) // تحميل العلاقات المعتمدة للخدمات
            ->latest()
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'meta'    => [
                'current_page' => $services->currentPage(),
                'last_page'    => $services->lastPage(),
                'total'        => $services->total()
            ],
            'data'    => ListingResource::collection($services),
        ], Response::HTTP_OK);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('ListingController@getCompanyServices failed', [
            'provider_id' => $provider->id,
            'error'       => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء جلب الخدمات الخاصة بشركتكم.',
        ], 500);
    }
}
}