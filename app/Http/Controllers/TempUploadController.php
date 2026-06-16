<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TempUploadController extends Controller
{
    public function upload(Request $request, MediaService $mediaService): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // 5MB Max
        ]);

        $tempPath = $mediaService->storeTempUpload($request->file('image'));

        return response()->json([
            'message' => 'Image uploaded successfully to temporary storage.',
            'temp_path' => $tempPath,
            'url'       => asset($tempPath), // الرابط الكامل المباشر للصورة
        ], 201);
    }
     public function index(string $listingId): JsonResponse
    {
        $listing = Listing::find($listingId);

        if (! $listing) {
            return response()->json([
                'success' => false,
                'message' => 'الـ listing غير موجود.',
            ], 404);
        }

        $images = $listing->images->map(fn ($img) => [
            'id'  => $img->id,
            'url' => $img->url,  // الـ accessor في Image model
            'alt' => $img->alt_text,
        ]);

        return response()->json([
            'success' => true,
            'listing_id' => $listingId,
            'total'   => $images->count(),
            'images'  => $images,
        ]);
    }
}