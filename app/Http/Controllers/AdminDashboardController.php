<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Listing;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Models\Review;

class AdminDashboardController extends Controller
{
    /**
     * GET /admin/dashboard-stats
     * أرقام سريعة لحالة النظام: providers/listings/bookings لكل حالة،
     * وإجمالي الإيرادات من الحجوزات المدفوعة.
     */
  public function stats(): JsonResponse
    {
        $totalProviders = Provider::count();

        // حساب عدد الشركات والفريلانسرز بناءً على الـ provider_type 
        // (تأكد أن القيم 'company' و 'freelancer' تطابق المخزن في قاعدة البيانات لديك)
        $companiesCount = Provider::where('provider_type', 'company')->count();
        $freelancersCount = Provider::where('provider_type', 'freelancer')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'providers' => [
                    'total' => $totalProviders,
                    'by_moderation_status' => Provider::query()
                        ->selectRaw('moderation_status, count(*) as total')
                        ->groupBy('moderation_status')
                        ->pluck('moderation_status', 'total'), // أو pluck('total', 'moderation_status') حسب ما هو موجود عندك
                    'by_type' => Provider::query()
                        ->selectRaw('provider_type, count(*) as total')
                        ->groupBy('provider_type')
                        ->pluck('total', 'provider_type'),
                    // إضافة تفصيل النسب والعدادات للشركة والفريلانسر
                    'breakdown' => [
                        'company' => [
                            'count' => $companiesCount,
                            'percentage' => $totalProviders > 0 ? round(($companiesCount / $totalProviders) * 100, 1) . '%' : '0%'
                        ],
                        'freelancer' => [
                            'count' => $freelancersCount,
                            'percentage' => $totalProviders > 0 ? round(($freelancersCount / $totalProviders) * 100, 1) . '%' : '0%'
                        ]
                    ]
                ],
                'users' => [
                    'organizers_total' => User::role('organizer')->count(),
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
            ]
        ]);
    }

    public function getAllProvidersRatings(): JsonResponse
{
    // جلب المزودين وترتيبهم تنازلياً حسب عمود rating الموجود في نفس الجدول
    $providers = Provider::orderByDesc('rating')
        ->get(['id', 'brand_name', 'provider_type', 'rating']); 

    $formattedProviders = $providers->map(function ($provider) {
        return [
            'provider_id'    => $provider->id,
            // بناءً على المودل الخاص بك، الاسم محفوظ في brand_name
            'name'           => $provider->brand_name, 
            'type'           => $provider->provider_type,
            // تحويل القيمة إلى رقم عشري لضمان التنسيق
            'average_rating' => round((float) $provider->rating, 1), 
        ];
    });

    return response()->json([
        'success' => true,
        'data'    => $formattedProviders,
    ]);
}

public function getTopFiveListings(): JsonResponse
{
    // 1. جلب IDs العروض مع حساب متوسط التقييم (أعلى 5)
    $topListingsData = Review::query()
        ->join('bookings', 'reviews.booking_id', '=', 'bookings.id')
        ->select('bookings.listing_id')
        ->selectRaw('ROUND(AVG(reviews.rating), 1) as average_rating')
        ->groupBy('bookings.listing_id')
        ->orderByDesc('average_rating')
        ->limit(5) // طلبنا 5 هنا
        ->get();

    if ($topListingsData->isEmpty()) {
        return response()->json([
            'success' => true,
            'data'    => [],
        ]);
    }

    // 2. جلب بيانات العروض باستخدام الـ IDs لضمان عمل الـ JSON Casts
    $listingIds = $topListingsData->pluck('listing_id');
    $listings = Listing::whereIn('id', $listingIds)->get()->keyBy('id');

    // 3. دمج التقييم مع بيانات العرض وتنسيق النتيجة
    $formatted = $topListingsData->map(function ($item) use ($listings) {
        $listing = $listings->get($item->listing_id);

        if (!$listing) return null;

        return [
            'listing_id'     => $listing->id,
            'title'          => $listing->title, 
            'average_rating' => (float) $item->average_rating,
        ];
    })->filter()->values();

    return response()->json([
        'success' => true,
        'data'    => $formatted,
    ]);
}

}
