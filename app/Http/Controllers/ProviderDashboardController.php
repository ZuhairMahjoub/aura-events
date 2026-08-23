<?php

namespace App\Http\Controllers;

use App\Services\ProviderDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderDashboardController extends Controller
{
    public function __construct(
        private readonly ProviderDashboardService $dashboardService,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $pendingListingsLimit = min(
            max((int) $request->integer('pending_listings_limit', 5), 1),
            20
        );

        $topListingsLimit = min(
            max((int) $request->integer('top_listings_limit', 10), 1),
            50
        );

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->summary(
                $request->user(),
                $pendingListingsLimit,
                $topListingsLimit,
            ),
        ]);
    }
}