<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Listing;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    /**
     * GET /admin/dashboard-stats
     * أرقام سريعة لحالة النظام: providers/listings/bookings لكل حالة،
     * وإجمالي الإيرادات من الحجوزات المدفوعة.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'providers' => [
                    'total' => Provider::count(),
                    'by_moderation_status' => Provider::query()
                        ->selectRaw('moderation_status, count(*) as total')
                        ->groupBy('moderation_status')
                        ->pluck('total', 'moderation_status'),
                    'by_type' => Provider::query()
                        ->selectRaw('provider_type, count(*) as total')
                        ->groupBy('provider_type')
                        ->pluck('total', 'provider_type'),
                ],
                'listings' => [
                    'total' => Listing::count(),
                    'by_moderation_status' => Listing::query()
                        ->selectRaw('moderation_status, count(*) as total')
                        ->groupBy('moderation_status')
                        ->pluck('total', 'moderation_status'),
                    'by_type' => Listing::query()
                        ->selectRaw('listing_type, count(*) as total')
                        ->groupBy('listing_type')
                        ->pluck('total', 'listing_type'),
                ],
                'bookings' => [
                    'total' => Booking::count(),
                    'by_status' => Booking::query()
                        ->selectRaw('status, count(*) as total')
                        ->groupBy('status')
                        ->pluck('total', 'status'),
                    'total_revenue_paid' => (float) Booking::where('payment_status', 'paid')->sum('total_price'),
                    'total_refunded' => (float) Booking::where('payment_status', 'refunded')->sum('total_price'),
                ],
            ],
        ]);
    }
}