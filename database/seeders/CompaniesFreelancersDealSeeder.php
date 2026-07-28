<?php

namespace Database\Seeders;

use App\Actions\Arrangement\CreateArrangementAction;
use App\Models\Booking;
use App\Models\CompanyDetail;
use App\Models\CompanyFreelancerContract;
use App\Models\Category;
use App\Models\District;
use App\Models\FreelancerDetail;
use App\Models\JobOffer;
use App\Models\Listing;
use App\Models\ListingAvailability;
use App\Models\ListingSlot;
use App\Models\ListingVariant;
use App\Models\PackageFreelancer;
use App\Models\PackageItem;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seeder: 3 شركات + 3 فريلانسرز + عقد تعاون بين واحدة من كل نوع + عروض
 * إعلانات لكل شركة (hall, package, physical_product) + خدمة للفريلانسر
 * المرتبط بالعقد.
 *
 * إصلاح جوهري: الـ package لم يكن يُبنى فعلياً عبر CreateArrangementAction
 * (المسار الحقيقي المستخدم بالتطبيق)، بل عبر Listing::create() مباشرة —
 * وهذا يتخطى بالكامل SyncPackageItemsAction وSyncPackageFreelancersAction،
 * فيترك جدولي package_items وpackage_freelancers فارغين تماماً رغم وجود
 * "باقة" شكلياً بجدول listings. الآن نستخدم الـ Action الحقيقي، ونمرر له
 * items (من hall/physical_product تابعين لنفس الشركة) وfreelancers (من
 * عقد التعاون الفعلي) لضمان تعبئة الجدولين.
 *
 * إضافة جديدة (دمج): بعد إنشاء كل العروض الحقيقية فوق، نضيف مستخدمين
 * organizer (منظّمين/عملاء عاديين) ونخليهم يحجزوا فعلياً على هالعروض
 * نفسها (hall, physical_product, service, package) — مع تغطية كل
 * حالات الحجز الست الموجودة بالـ enum: pending, accepted, rejected,
 * confirmed, completed, cancelled. هيك الحجوزات مش بيانات وهمية منفصلة،
 * هي حجوزات حقيقية على listings/variants تم إنشاؤها فعلياً بنفس السيدر.
 *
 * يتطلب تشغيل CategorySeeder وGovernorateAndDistrictSeeder أولاً
 * (نحتاج category_id وdistrict_id فعليين موجودين بالجداول).
 */
class CompaniesFreelancersDealSeeder extends Seeder
{
    /** كل حالات الحجز الموجودة فعلياً بجدول bookings */
    private const BOOKING_STATUSES = ['pending', 'accepted', 'rejected', 'confirmed', 'completed', 'cancelled'];

    public function run(): void
    {
        $categories = Category::query()->get();
        $districts = District::query()->get();

        if ($categories->isEmpty() || $districts->isEmpty()) {
            $this->command?->warn('شغّل CategorySeeder وGovernorateAndDistrictSeeder أولاً قبل هذا الـ seeder.');
            return;
        }

        // كل شي داخل transaction وحدة: بدل ~40 commit منفصل (كل insert لحاله)
        // صار commit واحد بالنهاية — أسرع بكثير، وبيضمن كمان إنو لو صار خطأ
        // بأي خطوة (مثلاً بالـ CreateArrangementAction)، ما تنعمل أي بيانات
        // جزئية ناقصة بقاعدة البيانات (rollback تلقائي للكل مش لجزء بس).
        DB::transaction(function () use ($categories, $districts) {
            $this->seedEverything($categories, $districts);
        });
    }

    private function seedEverything(Collection $categories, Collection $districts): void
    {
        // ────────────────────────────────────────────────────────────
        // 1. إنشاء 3 شركات
        // ────────────────────────────────────────────────────────────
        $companies = collect(['قصر الأفراح الذهبي', 'مؤسسة الإبداع للفعاليات', 'شركة روائع المناسبات'])
            ->map(fn ($name) => $this->makeCompany($name, $categories, $districts));

        // ────────────────────────────────────────────────────────────
        // 2. إنشاء 3 فريلانسرز
        // ────────────────────────────────────────────────────────────
        $freelancers = collect(['أحمد المصور', 'سارة منسقة الحفلات', 'خالد الديكوريتور'])
            ->map(fn ($name) => $this->makeFreelancer($name, $categories));

        // ────────────────────────────────────────────────────────────
        // 3. عقد تعاون بين أول شركة وأول فريلانسر (يحتاج JobOffer أولاً)
        // ────────────────────────────────────────────────────────────
        $dealCompany = $companies->first();
        $dealFreelancer = $freelancers->first();

        $dealService = Service::create([
            'company_id' => $dealCompany->id,
            'name' => 'تنظيم حفلات زفاف متكاملة',
            'description' => 'خدمة تنظيم شاملة تغطي كل تفاصيل حفل الزفاف من الديكور للتصوير',
        ]);

        $jobOffer = JobOffer::factory()->create([
            'company_id' => $dealCompany->id,
            'service_id' => $dealService->id,
            'category_id' => null,
        ]);

        $contract = CompanyFreelancerContract::create([
            'company_id' => $dealCompany->id,
            'freelancer_id' => $dealFreelancer->id,
            'job_offer_id' => $jobOffer->id,
            'status' => 'active',
        ]);

        // ────────────────────────────────────────────────────────────
        // 4. خدمة الفريلانسر (منفصلة عن خدمة التعاقد، تمثّل عرضه الخاص)
        // ────────────────────────────────────────────────────────────
        // ملاحظة: الخدمات (Service) مرتبطة حالياً بالشركات فقط (company_id)،
        // فريلانسر لا يملك جدول Service خاص به بالبنية الحالية للمشروع.
        // لتمثيل "خدمة الفريلانسر" الفعلية، ننشئ Listing من نوع 'service'
        // مملوك للفريلانسر نفسه (provider_id يقبل company أو freelancer معاً).
        $freelancerService = $this->makeListingWithVariant(
            $dealFreelancer,
            'service',
            'تصوير احترافي للمناسبات',
            $categories->first()->id,
            $districts->first()->id,
        );
        $freelancerServiceListing = $freelancerService['listing'];

        // ────────────────────────────────────────────────────────────
        // 5. عروض الشركات — hall وphysical_product أولاً (نحتاجهم كعناصر
        //    للـ package بالخطوة التالية)، ثم الـ package نفسه عبر الـ
        //    Action الحقيقي بدل Listing::create() المباشر.
        // ────────────────────────────────────────────────────────────

        // نجمّع هون كل الـ listings/variants الحقيقية اللي بينشئها هالسيدر،
        // عشان نحجز عليها فعلياً بالقسم 6 تحت (بدل ما ننشئ بيانات موازية).
        // كل عنصر هون هو ['variant' => ListingVariant, 'slot' => ListingSlot|null]
        // — الـ slot موجود لكل hall/service/physical_product (عندها availability
        // slots حقيقية)، وبيضل null بس للـ package (الـ Arrangement Action
        // الحالي ما بينشئ slots له بهذا المشروع، فمنطقياً حجزه بدون slot محدد).
        $bookableVariants = collect();
        $bookableVariants->push(['variant' => $freelancerService['variant'], 'slot' => $freelancerService['slot']]);

        foreach ($companies as $company) {
            $hall = $this->makeListingWithVariant(
                $company, 'hall', "صالة أفراح فاخرة - {$company->brand_name}",
                $categories->random()->id, $districts->random()->id,
            );
            $product = $this->makeListingWithVariant(
                $company, 'physical_product', "مستلزمات وديكورات مناسبات - {$company->brand_name}",
                $categories->random()->id, $districts->random()->id,
            );

            $bookableVariants->push(['variant' => $hall['variant'], 'slot' => $hall['slot']]);
            $bookableVariants->push(['variant' => $product['variant'], 'slot' => $product['slot']]);

            // الفريلانسرز يُضافون فقط لباقة الشركة التي تملك عقداً فعلياً
            // معهم (dealCompany) — باقي الشركات ليس لديها عقود، فباقاتهم
            // تُبنى بعناصر (items) فقط بدون freelancers، وهذا واقعي تماماً.
            $freelancersForPackage = $company->is($dealCompany)
                ? [['freelancer_id' => $dealFreelancer->id, 'contract_id' => $contract->id]]
                : [];

            $packageListing = $this->makePackageArrangement(
                $company,
                "باقة تنسيق متكاملة - {$company->brand_name}",
                $categories->random()->id,
                $districts->random()->id,
                items: [
                    ['variant_id' => $hall['variant']->id, 'quantity' => 1],
                    ['variant_id' => $product['variant']->id, 'quantity' => 2],
                ],
                freelancers: $freelancersForPackage,
            );

$packageVariant = $packageListing->loadMissing('variants')->variants->first();

if ($packageVariant) {
    $packageVariant->setRelation('listing', $packageListing);

    // 1. إنشاء توفر (Availability) للباقة في تاريخ محدد
    $packageAvailability = \App\Models\ListingAvailability::create([
        'listing_variant_id' => $packageVariant->id,
        'available_date'     => now()->addDays(20)->toDateString(),
        'is_blocked'         => false,
    ]);

    // 2. إنشاء فترة زمنية (Slot) مرتبطة بهذا التوفر
    $packageSlot = \App\Models\ListingSlot::create([
        'listing_availability_id' => $packageAvailability->id,
        'slot_name'               => 'الفترة الصباحية للباقة',
        'start_time'              => '10:00:00',
        'end_time'                => '15:00:00',
        'remaining_capacity'      => 1,
    ]);

    // 3. حقن العلاقة في الذاكرة لتسهيل عملية الحجز لاحقاً
    $packageSlot->setRelation('availability', $packageAvailability);

    // تمرير الـ slot الحقيقي بدلاً من null
    $bookableVariants->push(['variant' => $packageVariant, 'slot' => $packageSlot]);
}
        }

        // ────────────────────────────────────────────────────────────
        // 6. مستخدمين organizer + حجوزات حقيقية تغطي كل حالات الحجز
        // ────────────────────────────────────────────────────────────
        $organizers = $this->makeOrganizers(2);
        $this->bookAllStatusesForOrganizers($organizers, $bookableVariants->filter()->values());

        $this->command?->info('CompaniesFreelancersDealSeeder: تم بنجاح.');
        $this->command?->table(['العنصر', 'التفاصيل'], [
            ['الشركات (3)', $companies->pluck('brand_name')->implode(', ')],
            ['الفريلانسرز (3)', $freelancers->pluck('brand_name')->implode(', ')],
            ['عقد التعاون', "{$dealCompany->brand_name} ↔ {$dealFreelancer->brand_name} (status: active)"],
            ['خدمة الفريلانسر', is_array($freelancerServiceListing->title) ? ($freelancerServiceListing->title['ar'] ?? '-') : (string) $freelancerServiceListing->title],
            ['عروض كل شركة', 'صالة (hall) + منتج فيزيائي (physical_product) + باقة (package) تضم الاثنين'],
            ['package_items المُعبّأة', PackageItem::count() . ' صف'],
            ['package_freelancers المُعبّأة', PackageFreelancer::count() . ' صف (لباقة ' . $dealCompany->brand_name . ' فقط)'],
            ['مستخدمين organizer', $organizers->pluck('email')->implode(', ')],
            ['حجوزات organizer', Booking::whereIn('user_id', $organizers->pluck('id'))->count() . ' حجز يغطي حالات: ' . implode(', ', self::BOOKING_STATUSES)],
        ]);
    }

    private function makeCompany(string $name, $categories, $districts): Provider
    {
        $user = User::factory()->verified()->create();

        $providerRole = Role::firstOrCreate(['name' => 'provider', 'guard_name' => 'api']);
        $user->assignRole($providerRole);

        $company = Provider::factory()->state([
            'provider_type' => 'company',
            'moderation_status' => 'approved',
            'brand_name' => $name,
            'is_active' => true,
        ])->create(['user_id' => $user->id]);

        $company->categories()->sync($categories->random(min(2, $categories->count()))->pluck('id'));

        CompanyDetail::factory()->create([
            'provider_id' => $company->id,
            'district_id' => $districts->random()->id,
        ]);

        return $company;
    }

    private function makeFreelancer(string $name, $categories): Provider
    {
        $user = User::factory()->verified()->create();

        $providerRole = Role::firstOrCreate(['name' => 'provider', 'guard_name' => 'api']);
        $user->assignRole($providerRole);

        $freelancer = Provider::factory()->state([
            'provider_type' => 'freelancer',
            'moderation_status' => 'approved',
            'brand_name' => $name,
            'is_active' => true,
        ])->create(['user_id' => $user->id]);

        $freelancer->categories()->sync($categories->random(min(2, $categories->count()))->pluck('id'));

        FreelancerDetail::factory()->create(['provider_id' => $freelancer->id]);

        return $freelancer;
    }

    /**
     * ينشئ listing + variant + availability + slot، ويرجعهم مع بعض بدون
     * أي استعلام إضافي (relations محقونة بالذاكرة مباشرة)، عشان الحجز
     * لاحقاً يقدر ياخذ listing_slot_id حقيقي وتاريخ/وقت متطابقين مع
     * الـ slot الفعلي، بدل ما يضل عمود listing_slot_id فاضي (null)
     * وتاريخ الحجز عشوائي منفصل عن أي slot موجود فعلاً.
     */
    private function makeListingWithVariant(
        Provider $provider,
        string $type,
        string $titleAr,
        int $categoryId,
        int $districtId,
    ): array {
        $listing = Listing::create([
            'provider_id' => $provider->id,
            'category_id' => $categoryId,
            'district_id' => $districtId,
            'title' => ['ar' => $titleAr, 'en' => $titleAr],
            'description' => [
                'ar' => 'وصف تفصيلي لهذا العرض يشمل كل الخدمات المقدمة.',
                'en' => 'Detailed description covering all provided services.',
            ],
            'listing_type' => $type,
            'moderation_status' => 'approved',
            'material_composition' => $type === 'physical_product' ? 'خامات فاخرة مقاومة للاهتراء' : null,
            'is_provider_location_based' => $type !== 'service',
            'cancel_before_acceptance' => true,
            'cancel_after_acceptance' => false,
            'cancel_before_payment' => true,
        ]);

        $variant = ListingVariant::create([
            'listing_id' => $listing->id,
            'variant_name' => ['ar' => 'الباقة الأساسية', 'en' => 'Basic Package'],
            'price' => match ($type) {
                'hall' => 1500000,
                'package' => 800000,
                'physical_product' => 50000,
                default => 200000,
            },
            'currency' => 'SYP',
            'price_type' => $type === 'service' ? 'hourly' : 'fixed',
            'stock_quantity' => $type === 'physical_product' ? 25 : null,
        ]);

        $availability = ListingAvailability::create([
            'listing_variant_id' => $variant->id,
            'available_date' => now()->addDays(rand(10, 40))->toDateString(),
            'is_blocked' => false,
        ]);

        $slot = ListingSlot::create([
            'listing_availability_id' => $availability->id,
            'slot_name' => 'الفترة المسائية',
            'start_time' => '17:00:00',
            'end_time' => '23:00:00',
            'remaining_capacity' => $type === 'physical_product' ? 25 : 1,
        ]);

        // نحقن الـ relations بالذاكرة مباشرة (setRelation) بدل ما نترك
        // Eloquent يعيد جلبهم بعدين بـ query جديد لو حد نادى $variant->listing
        // أو $slot->availability لاحقاً — توفير استعلامات فعلي، مش بس تنظيم.
        $variant->setRelation('listing', $listing);
        $slot->setRelation('availability', $availability);

        return ['listing' => $listing, 'variant' => $variant, 'slot' => $slot];
    }

    /**
     * يبني باقة (package) عبر CreateArrangementAction الحقيقي — بدل
     * Listing::create() المباشر — ليضمن تعبئة package_items وpackage_freelancers
     * فعلياً عبر SyncPackageItemsAction وSyncPackageFreelancersAction.
     */
    private function makePackageArrangement(
        Provider $company,
        string $titleAr,
        int $categoryId,
        int $districtId,
        array $items,
        array $freelancers,
    ): Listing {
        $action = app(CreateArrangementAction::class);

        return $action->execute([
            'category_id' => $categoryId,
            'district_id' => $districtId,
            'title' => ['ar' => $titleAr, 'en' => $titleAr],
            'description' => [
                'ar' => 'باقة متكاملة تجمع بين أفضل خدمات الشركة بسعر مخفّض.',
                'en' => 'A comprehensive package combining the company\'s best services.',
            ],
            'price' => 2000000,
            'price_type' => 'fixed',
            'currency' => 'SYP',
            'capacity' => 1,
            'items' => $items,
            'freelancers' => $freelancers,
            'date_range' => [
                'start_date' => now()->addDays(15)->toDateString(),
                'end_date' => now()->addDays(17)->toDateString(),
            ],
        ], $company->id);
    }

    /**
     * ينشئ مستخدمين organizer (منظّمين/عملاء عاديين) بـ role='organizer'.
     */
    private function makeOrganizers(int $count): Collection
    {
        $organizerRole = Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'api']);

        return User::factory()
            ->count($count)
            ->verified()
            ->create()
            ->each(function (User $user) use ($organizerRole) {
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($organizerRole);
                }
            });
    }

    /**
     * لكل organizer: حجز واحد لكل حالة من حالات الحجز الست، على variants
     * حقيقية تم إنشاؤها فعلياً بهذا السيدر (hall/product/service/package)
     * بدوران بينها، بدل ما نعتمد نوع واحد فقط.
     */
    private function bookAllStatusesForOrganizers(Collection $organizers, Collection $pairs): void
    {
        if ($pairs->isEmpty()) {
            $this->command?->warn('ما في variants صالحة للحجز عليها — تحقق من قسم 5.');
            return;
        }

        foreach ($organizers as $organizer) {
            foreach (self::BOOKING_STATUSES as $statusIndex => $status) {
                $pair = $pairs[$statusIndex % $pairs->count()];
                $this->createBookingWithStatus($organizer, $pair['variant'], $pair['slot'], $status);
            }
        }
    }

    /**
     * ينشئ حجز واحد بحالة محددة، مع تنويع payment_status وحقول الإلغاء/الإكمال
     * بما يطابق منطق BookingService الفعلي (مش قيم عشوائية).
     *
     * لو في slot حقيقي مرتبط بالـ variant (hall/service/physical_product)،
     * منربط الحجز فيه فعلياً (listing_slot_id + نفس تاريخ ووقت الـ availability)
     * بدل ما يضل العمود null وتاريخ الحجز عشوائي غير مرتبط بأي شي حقيقي.
     * الـ package بدون slot (null) — منولّد إله تاريخ عشوائي مستقل، وهذا متوقع.
     */
    private function createBookingWithStatus(User $organizer, ListingVariant $variant, ?ListingSlot $slot, string $status): Booking
    {
        $listing = $variant->listing;

        $base = [
            'user_id' => $organizer->id,
            'provider_id' => $listing->provider_id,
            'listing_id' => $listing->id,
            'listing_variant_id' => $variant->id,
            'listing_slot_id' => $slot?->id,
            'booking_type' => $listing->listing_type,
            'quantity' => 1,
            'total_price' => $variant->price ?? 100000,
            'currency' => $variant->currency ?? 'SYP',
            'booked_date' => $slot ? $slot->availability->available_date->toDateString() : now()->addDays(random_int(5, 60))->toDateString(),
            'booked_start_time' => $slot?->start_time,
            'booked_end_time' => $slot?->end_time,
            'metadata' => ['source' => 'organizer_all_statuses_seeder'],
        ];

        $stateByStatus = match ($status) {
            'pending' => [
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ],
            'accepted' => [
                'status' => 'accepted',
                'payment_status' => 'unpaid',
            ],
            'rejected' => [
                'status' => 'rejected',
                'payment_status' => 'unpaid',
                'cancelled_by' => 'provider',
                'cancellation_reason' => 'الشركة/الفريلانسر مش متوفرين بهاد التاريخ.',
                'cancelled_at' => now(),
            ],
            'confirmed' => [
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_reference' => 'seed_pay_' . uniqid(),
            ],
            'completed' => [
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_reference' => 'seed_pay_' . uniqid(),
                'completed_at' => now()->subDays(2),
            ],
            'cancelled' => [
                'status' => 'cancelled',
                'payment_status' => 'refunded',
                'cancelled_by' => 'organizer',
                'cancellation_reason' => 'المنظّم غيّر رأيه وألغى الحجز.',
                'cancelled_at' => now(),
            ],
            default => ['status' => $status, 'payment_status' => 'unpaid'],
        };

        return Booking::factory()->create(array_merge($base, $stateByStatus));
    }
}