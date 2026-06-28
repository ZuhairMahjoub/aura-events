<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ListingVariant;
use App\DTOs\Cart\CartItemData;
use App\DTOs\BookingData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartService
{
    public function __construct(
        private readonly BookingService $bookingService
    ) {}

    /**
     * جلب السلة النشطة أو إنشاء واحدة جديدة مع ضمان التزامن (Concurrency).
     */
    public function getOrCreateActiveCart(string $userId): Cart
    {
        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($cart) {
            return $cart;
        }

        try {
            return Cart::create([
                'user_id'            => $userId,
                'status'             => 'active',
                'single_active_lock' => $userId,
            ]);
        } catch (QueryException $e) {
            return Cart::where('user_id', $userId)
                ->where('status', 'active')
                ->firstOrFail();
        }
    }

    /**
     * إضافة عنصر للسلة باستخدام الـ DTO.
     */
    public function addItem(string $userId, CartItemData $data): CartItem
    {
        $cart = $this->getOrCreateActiveCart($userId);

        // التحقق من أن الـ Variant ينتمي لـ Listing نشط ومعتمد
        $variant = ListingVariant::with('listing')
            ->findOrFail($data->variantId);

        if (
            !$variant->listing
            || $variant->listing->deleted_at !== null
            || $variant->listing->moderation_status !== 'approved'
        ) {
            throw ValidationException::withMessages([
                'variant_id' => 'هذا المنتج/الخدمة غير متاح حالياً أو لم يحصل على الاعتماد بعد.',
            ]);
        }

        return CartItem::create([
            'cart_id'            => $cart->id,
            'listing_id'         => $variant->listing_id,
            'listing_variant_id' => $variant->id,
            'listing_slot_id'    => $data->slotId,
            'quantity'           => $data->quantity,
            'price_snapshot'     => $variant->price,
            'booked_date'        => $data->bookedDate,
            'metadata'           => $data->metadata,
        ]);
    }

    /**
     * تحديث كمية عنصر.
     */
    public function updateQuantity(string $userId, string $cartItemId, int $quantity): CartItem
    {
        $cart = $this->getOrCreateActiveCart($userId);

        $item = $cart->items()->findOrFail($cartItemId);

        $item->update(['quantity' => max(1, $quantity)]);

        return $item->fresh();
    }

    /**
     * حذف عنصر من السلة.
     */
    public function removeItem(string $userId, string $cartItemId): void
    {
        $cart = $this->getOrCreateActiveCart($userId);

        $cart->items()->findOrFail($cartItemId)->delete();
    }

    /**
     * تحويل السلة إلى حجوزات.
     * ── إصلاح 1.1: Transaction خارجية شاملة (كل شيء أو لا شيء).
     * ── إصلاح 2.2: فحص تغيّر السعر قبل البدء.
     */
    public function checkout(string $userId): array
    {
        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->with('items.variant')   // Eager Loading مبكر (إصلاح 1.4)
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'السلة فارغة، لا يوجد عناصر لتأكيدها.',
            ]);
        }

        // ── فحص تغيّر الأسعار قبل أي Transaction ──────────────────────────
        $priceDiscrepancies = $this->detectPriceDiscrepancies($cart->items);
        if (!empty($priceDiscrepancies)) {
            throw ValidationException::withMessages([
                'price_changed' => 'تغيرت أسعار بعض العناصر منذ إضافتها للسلة.',
                'details'       => $priceDiscrepancies,
            ]);
        }

        // ── Transaction خارجية شاملة ────────────────────────────────────────
        return DB::transaction(function () use ($cart, $userId) {

            $successful = [];

            foreach ($cart->items as $item) {
                $bookingData = new BookingData(
                    userId:        $userId,
                    listingId:     $item->listing_id,
                    variantId:     $item->listing_variant_id,
                    slotId:        $item->listing_slot_id,
                    bookedDate:    $item->booked_date?->toDateString(),
                    quantity:      $item->quantity,
                    metadata:      $item->metadata ?? [],
                    customerNotes: null,
                );

                // أي فشل هنا يُصعَّد ليُلغي الـ Transaction الخارجية بالكامل
                $booking = $this->bookingService->book($bookingData);
                $item->update(['converted_booking_id' => $booking->id]);
                $successful[] = $booking;
            }

            $cart->update([
                'status'             => 'converted',
                'single_active_lock' => null,
            ]);

            return [
                'successful_bookings' => $successful,
                'failed_items'        => [],
            ];
        });
    }

    /**
     * يكتشف الفروق بين السعر المحفوظ في السلة والسعر الحالي.
     */
    private function detectPriceDiscrepancies(Collection $items): array
    {
        $discrepancies = [];

        $variantIds = $items->pluck('listing_variant_id')->unique()->toArray();
        $currentVariants = ListingVariant::whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $current = $currentVariants->get($item->listing_variant_id);

            if (!$current) {
                $discrepancies[] = [
                    'cart_item_id' => $item->id,
                    'reason'       => 'المنتج لم يعد متاحاً.',
                ];
                continue;
            }

            $snapshotPrice = (float) $item->price_snapshot;
            $currentPrice  = (float) $current->price;

            if (abs($snapshotPrice - $currentPrice) > 0.01) {
                $discrepancies[] = [
                    'cart_item_id'   => $item->id,
                    'listing_id'     => $item->listing_id,
                    'snapshot_price' => $snapshotPrice,
                    'current_price'  => $currentPrice,
                    'difference'     => $currentPrice - $snapshotPrice,
                ];
            }
        }

        return $discrepancies;
    }
}
