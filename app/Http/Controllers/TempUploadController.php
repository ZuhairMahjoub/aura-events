<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
        ], 201);
    }
}