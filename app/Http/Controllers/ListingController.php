<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Models\Listing;
use App\Services\ListingService;
use App\Http\Resources\ListingResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('view', $listing);

        $enrichedListing = $this->listingService->getListingById($listing);

        return response()->json([
            'success' => true,
            'data'    => new ListingResource($enrichedListing)
        ], Response::HTTP_OK);
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