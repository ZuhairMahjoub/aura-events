<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Requests\StoreListingRequest;
use App\Models\Listing;
use App\Services\ListingService;
use App\Http\Resources\ListingResource;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;


class ListingController extends Controller
{
    protected ListingService $listingService;

    public function __construct(ListingService $listingService)
    {
        $this->listingService = $listingService;
    }

  
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Listing::class);

        $listings = $this->listingService->getAllListings();

        return response()->json([
            'success' => true,
            'data'    => ListingResource::collection($listings),
            'meta'    => [
                'current_page' => $listings->currentPage(),
                'last_page'    => $listings->lastPage(),
                'total'        => $listings->total()
            ]
        ], Response::HTTP_OK);
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

   
   public function show(Listing $listing): JsonResponse
{
    // 🔒 لارافيل سيفحص دالة view داخل الـ ListingPolicy ويمرر لها الـ $listing تلقائياً
    Gate::authorize('view', $listing);

    // شحن العلاقات مسبقاً (Eager Loading) لحل مشكلة الـ N+1 وضمان سرعة الأداء
    $listing->load(['category', 'district', 'images', 'variants.images', 'variants.availabilities.slots']);

    return response()->json([
        'success' => true,
        'message' => 'Listing retrieved successfully.',
        'data'    => new ListingResource($listing)
    ], 200);
}
  
   public function update(UpdateListingRequest $request, Listing $listing)
{
    $updatedListing = $this->listingService->updateListingWithGraph($listing, $request->validated());

    return response()->json([
        'message' => 'Hall and its custom packages synchronized successfully!',
        'data'    => $updatedListing
    ], 200);
}

    
    public function destroy(Listing $listing): JsonResponse
    {
        Gate::authorize('delete', $listing);

        $this->listingService->deleteListing($listing);

        return response()->json([
            'success' => true,
            'message' => 'Listing soft-deleted successfully.'
        ], Response::HTTP_OK);
    }
}