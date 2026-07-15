<?php

namespace App\Http\Controllers;

use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $favoriteService) {}

    public function toggle(Request $request, string $listingId): JsonResponse
    {
        $result = $this->favoriteService->toggle($request->user()->id, $listingId);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $favorites = $this->favoriteService->list($request->user()->id, $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $favorites,
        ]);
    }
}