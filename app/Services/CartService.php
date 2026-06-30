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
     * إصلاح: لا نبتلع كل QueryException بشكل عام — فقط الحالة المتوقعة
     * فعلياً من تعارض unique constraint على single_active_lock. أي خطأ DB
     * آخر (انقطاع اتصال، deadlock من مصدر مختلف...) يجب أن يُصعَّد ليظهر
     * في الـ logs بدل إخفائه خلف استثناء firstOrFail() مضلِّل.
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
            // 23000 = Integrity constraint violation (الكود القياسي SQLSTATE
            // لتعارض UNIQUE/FK في MySQL). فقط هذه الحالة متوقعة ومقصودة هنا.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

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

        // إصلاح: رفض الإضافة فوراً لو تجاوزت الكمية المخزون المتاح. سابقاً
        // كان بالإمكان إضافة كمية تفوق المخزون الفعلي للسلة بصمت، ولا يُكتشف
        // الخطأ إلا عند checkout. لا نمنع كلياً (المخزون قد يتغير لاحقاً
        // بالإيجاب أيضاً)، لكن نمنع الحالة الواضحة الخاطئة من البداية.
        if ($variant->stock_quantity !== null && $variant->stock_quantity < $data->quantity) {
            throw ValidationException::withMessages([
                'quantity' => "الكمية المطلوبة ({$data->quantity}) تتجاوز المخزون المتاح ({$variant->stock_quantity}).",
            ]);
        }

        if ($data->slotId) {
            $slot = \App\Models\ListingSlot::find($data->slotId);

            if (! $slot) {
                throw ValidationException::withMessages([
                    'listing_slot_id' => 'الـ Time Slot المحدد غير موجود.',
                ]);
            }

            if ($slot->remaining_capacity < $data->quantity) {
                throw ValidationException::withMessages([
                    'listing_slot_id' => "السعة الاستيعابية للـ Slot غير كافية. المتاح: {$slot->remaining_capacity}.",
                ]);
            }
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
     * إصلاح: التحقق من توفر المخزون/السعة قبل قبول الكمية الجديدة، بدل
     * قبولها بصمت دائماً ثم اكتشاف الخطأ لاحقاً عند checkout فقط.
     */
    public function updateQuantity(string $userId, string $cartItemId, int $quantity): CartItem
    {
        $cart = $this->getOrCreateActiveCart($userId);

        $item = $cart->items()->findOrFail($cartItemId);
        $newQuantity = max(1, $quantity);

        $variant = ListingVariant::find($item->listing_variant_id);

        if ($variant && $variant->stock_quantity !== null && $variant->stock_quantity < $newQuantity) {
            throw ValidationException::withMessages([
                'quantity' => "الكمية المطلوبة ({$newQuantity}) تتجاوز المخزون المتاح ({$variant->stock_quantity}).",
            ]);
        }

        if ($item->listing_slot_id) {
            $slot = \App\Models\ListingSlot::find($item->listing_slot_id);

            if ($slot && $slot->remaining_capacity < $newQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => "السعة الاستيعابية للـ Slot غير كافية لهذه الكمية. المتاح: {$slot->remaining_capacity}.",
                ]);
            }
        }

        $item->update(['quantity' => $newQuantity]);

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

        // ── إصلاح: فحص استباقي شامل للتوفر (مخزون + سعة slot) قبل بدء
        // الـ Transaction. سابقاً، كان أول فشل توفر يُكتشف فقط منتصف حلقة
        // foreach داخل الـ Transaction (عبر BookingService::book())، ما يعني
        // المستخدم ينتظر معالجة عدة عناصر ناجحة فعلياً قبل rollback كامل
        // بسبب عنصر واحد غير متاح، دون رسالة واضحة عن أي عنصر بالتحديد فشل. ──
        $availabilityIssues = $this->detectAvailabilityIssues($cart->items);
        if (!empty($availabilityIssues)) {
            throw ValidationException::withMessages([
                'unavailable_items' => 'بعض عناصر السلة لم تعد متاحة بالكمية المطلوبة.',
                'details'           => $availabilityIssues,
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

                // أي فشل هنا يُصعَّد ليُلغي الـ Transaction الخارجية بالكامل.
                // الفحص الاستباقي أعلاه يقلل احتمالية الوصول هنا لكنه لا يلغي
                // الحاجة لـ lockForUpdate الحقيقي داخل bookingService->book()،
                // لأن التوفر قد يتغير فعلياً بين الفحص الاستباقي وبدء الحلقة
                // (Time-of-check إلى Time-of-use يبقى ممكناً نظرياً، لكن
                // النتيجة النهائية تبقى صحيحة بفضل الـ lock الحقيقي،
                // والفحص الاستباقي فقط يحسّن تجربة المستخدم في الحالة الشائعة).
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
     * فحص استباقي (بدون lock، لأغراض رسالة الخطأ المبكرة فقط) لتوفر كل
     * عنصر في السلة: مخزون كافٍ للمنتجات المادية، وسعة كافية لـ slots
     * المرتبطة بحجوزات زمنية. القرار النهائي الملزم يبقى دائماً عند
     * lockForUpdate() الحقيقي داخل كل Strategy.
     */
    private function detectAvailabilityIssues(Collection $items): array
    {
        $issues = [];

        $variantIds = $items->pluck('listing_variant_id')->unique()->toArray();
        $variants = ListingVariant::whereIn('id', $variantIds)->get()->keyBy('id');

        $slotIds = $items->pluck('listing_slot_id')->filter()->unique()->toArray();
        $slots = empty($slotIds)
            ? collect()
            : \App\Models\ListingSlot::whereIn('id', $slotIds)->get()->keyBy('id');

        foreach ($items as $item) {
            $variant = $variants->get($item->listing_variant_id);

            if (!$variant) {
                $issues[] = [
                    'cart_item_id' => $item->id,
                    'reason'       => 'المنتج لم يعد متاحاً.',
                ];
                continue;
            }

            if ($variant->stock_quantity !== null && $variant->stock_quantity < $item->quantity) {
                $issues[] = [
                    'cart_item_id'    => $item->id,
                    'listing_id'      => $item->listing_id,
                    'reason'          => 'الكمية المطلوبة تتجاوز المخزون المتاح.',
                    'available_stock' => $variant->stock_quantity,
                    'requested_qty'   => $item->quantity,
                ];
            }

            if ($item->listing_slot_id) {
                $slot = $slots->get($item->listing_slot_id);

                if (!$slot) {
                    $issues[] = [
                        'cart_item_id' => $item->id,
                        'reason'       => 'الـ Time Slot المحدد لم يعد موجوداً.',
                    ];
                } elseif ($slot->remaining_capacity < $item->quantity) {
                    $issues[] = [
                        'cart_item_id'       => $item->id,
                        'listing_id'         => $item->listing_id,
                        'reason'             => 'السعة الاستيعابية للـ Slot غير كافية.',
                        'remaining_capacity' => $slot->remaining_capacity,
                        'requested_qty'      => $item->quantity,
                    ];
                }
            }
        }

        return $issues;
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