<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * كلاس فلترة موحّد لكل endpoints تصفح العروض (halls/services/packages/products).
 * الهدف: فصل منطق الفلترة عن الكنترولر، وإعادة استخدامه على الأربع أنواع
 * بدون تكرار كود.
 *
 * قرارات العمل المحسومة (راجع القسم 5 بخطة الفلترة):
 * - فلتر التاريخ: مطابقة تاريخ واحد بالضبط (available_date = التاريخ المُرسل).
 * - الفلاتر غير المنطقية لنوع معيّن (متل capacity على physical_product) تبقى
 *   شغالة بالباك اند دايماً؛ الفرونت هو يلي بيقرر يعرضها ولا لأ حسب النوع.
 */
class ListingFilter
{
    public function __construct(private Request $request)
    {
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->request->filled('category_id'), fn (Builder $q) =>
                $q->where('category_id', $this->request->query('category_id')))
            ->when($this->request->filled('district_id'), fn (Builder $q) =>
                $q->where('district_id', $this->request->query('district_id')))
            ->when($this->request->filled('governorate_id'), fn (Builder $q) =>
                $this->applyGovernorate($q))
            ->when($this->request->filled('search'), fn (Builder $q) =>
                $this->applySearch($q))
            ->when($this->request->filled('min_price') || $this->request->filled('max_price'), fn (Builder $q) =>
                $this->applyPriceRange($q))
            ->when($this->request->filled('price_type'), fn (Builder $q) =>
                $q->whereHas('variants', fn (Builder $v) =>
                    $v->where('price_type', $this->request->query('price_type'))))
            ->when($this->request->filled('min_capacity'), fn (Builder $q) =>
                $this->applyCapacity($q))
            ->when($this->request->filled('available_date'), fn (Builder $q) =>
                $this->applyAvailability($q))
            ->when($this->request->filled('min_rating'), fn (Builder $q) =>
                $q->whereHas('provider', fn (Builder $p) =>
                    $p->where('rating', '>=', $this->request->query('min_rating'))));
    }

    private function applyGovernorate(Builder $query): void
    {
        $query->whereHas('district', fn (Builder $q) =>
            $q->where('governorate_id', $this->request->query('governorate_id')));
    }

    private function applyPriceRange(Builder $query): void
    {
        $min = $this->request->query('min_price');
        $max = $this->request->query('max_price');

        $query->whereHas('variants', function (Builder $q) use ($min, $max) {
            if ($min !== null && $min !== '') {
                $q->where('price', '>=', $min);
            }
            if ($max !== null && $max !== '') {
                $q->where('price', '<=', $max);
            }
        });
    }

    private function applyCapacity(Builder $query): void
    {
        $query->whereHas('variants', fn (Builder $q) =>
            $q->where('capacity', '>=', $this->request->query('min_capacity')));
    }

    /**
     * فلتر التوفر بتاريخ محدد: لازم يكون في variant إله availability
     * بهاد التاريخ بالذات، مش محظور (is_blocked = false)، وعنده على الأقل
     * slot واحد فيه سعة متبقية (remaining_capacity > 0).
     */
    private function applyAvailability(Builder $query): void
    {
        $date = $this->request->query('available_date');

        $query->whereHas('variants.availabilities', function (Builder $q) use ($date) {
            $q->where('available_date', $date)
                ->where('is_blocked', false)
                ->whereHas('slots', fn (Builder $s) => $s->where('remaining_capacity', '>', 0));
        });
    }

    private function applySearch(Builder $query): void
    {
        $search = $this->request->query('search');
        $query->where(function (Builder $q) use ($search) {
            $q->where('title->ar', 'like', "%{$search}%")
                ->orWhere('title->en', 'like', "%{$search}%");
        });
    }
}