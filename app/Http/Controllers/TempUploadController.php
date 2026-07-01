<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class TempUploadController extends Controller
{
    public function upload(Request $request, MediaService $mediaService): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // 5MB Max
        ]);

        $tempPath = $mediaService->storeTempUpload($request->file('image'));

        return response()->json([
            'message'    => 'Image uploaded successfully to temporary storage.',
            // الحقل المتوقع فعلياً من ArrangementStoreRequest (images.*.path) هو
            // 'path'، بينما هذا الـ endpoint كان يرجّع 'temp_path' فقط — أي
            // frontend يمرّر نفس الـ response كما هو كان يرسل مفتاحاً غير
            // موجود بالـ validation، فتُتجاهل الصورة بصمت بلا أي رسالة خطأ.
            // أبقيت temp_path للتوافق الخلفي وأضفت path كمصدر حقيقة موحّد.
            'temp_path'  => $tempPath,
            'path'       => $tempPath,
            'url'        => asset('storage/' . $tempPath)
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

        $images = $listing->images->map(fn($img) => [
            'id'  => $img->id,
            // إصلاح: الـ accessor الفعلي على موديل Image هو full_url (بيتحول
            // من fullUrl() حسب اتفاقية Laravel لتسمية الـ attributes)، وليس
            // url — كانت نفس مشكلة ArrangementResource لكن بمكان تالت.
            'url' => $img->full_url,
            'alt' => $img->alt_text
        ]);

        return response()->json([
            'success' => true,
            'listing_id' => $listingId,
            'total'   => $images->count(),
            'images'  => $images,
        ]);
    }
}