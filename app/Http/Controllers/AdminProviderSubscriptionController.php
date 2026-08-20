<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminProviderSubscriptionController extends Controller
{
    /**
     * POST /admin/providers/{id}/subscriptions/confirm-payment
     *
     * الأدمن بيأكد دفعة إيجار/اشتراك المزوّد لفترة جديدة. لو ما في فترة
     * مفتوحة أصلاً، أو الفترة الحالية منتهية، بننشئ فترة جديدة بدل ما
     * نحاول نجدد وحدة منتهية.
     */
    public function confirmPayment(Request $request, string $providerId): JsonResponse
    {
        $data = $request->validate([
            'period_months'   => ['required', 'integer', 'min:1', 'max:12'],
            'amount'          => ['required', 'numeric', 'min:0'],
            'currency'        => ['nullable', 'string', 'size:3'],
            'admin_notes'     => ['nullable', 'string', 'max:1000'],
            'starts_at'       => ['nullable', 'date'],
        ]);

        $provider = Provider::findOrFail($providerId);

        $startsAt = $data['starts_at'] ?? now()->toDateString();
        $endsAt   = \Carbon\Carbon::parse($startsAt)->addMonths($data['period_months'])->toDateString();

        $subscription = ProviderSubscription::create([
            'provider_id'               => $provider->id,
            'current_period_starts_at'  => $startsAt,
            'current_period_ends_at'    => $endsAt,
            'payment_status'            => 'confirmed',
            'amount'                    => $data['amount'],
            'currency'                  => $data['currency'] ?? 'SYP',
            'confirmed_by'              => $request->user()->id,
            'confirmed_at'              => now(),
            'admin_notes'               => $data['admin_notes'] ?? null,
        ]);

        // إعادة تفعيل المزوّد تلقائياً لو كان مُعطَّلاً بسبب انتهاء اشتراك سابق
        // (الـ scheduler اليومي هو من عطّله — راجع DeactivateExpiredSubscriptions).
        if (! $provider->is_active) {
            $provider->update(['is_active' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد دفعة الاشتراك بنجاح.',
            'data' => $subscription,
        ], 201);
    }

    /**
     * GET /admin/providers/{id}/subscriptions
     * عرض سجل اشتراكات مزوّد معيّن (للمراجعة).
     */
    public function index(string $providerId): JsonResponse
    {
        $provider = Provider::findOrFail($providerId);

        $subscriptions = $provider->subscriptions()
            ->orderByDesc('current_period_ends_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }
}