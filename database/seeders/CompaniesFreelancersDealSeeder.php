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
 * تعديل: عروض physical_product صارت منتج واقعي (كراسي مناسبات بعدة
 * variants حقيقية: نوع/لون/مادة/سعر) عبر makeChairsListing() بدل
 * placeholder عام "الباقة الأساسية" اللي كانت تجيه من makeListingWithVariant().
 *
 * تعديل جديد: كل الـ variants (hall/service/package/physical_product) صارت
 * تحمل قيمة capacity حقيقية ضمن dynamic_attributes بدل null، حتى يقدر
 * الفرونت يبني فلتر capacity على بيانات جاهزة فعلياً.
 *
 * يتطلب تشغيل CategorySeeder وGovernorateAndDistrictSeeder أولاً
 * (نحتاج category_id وdistrict_id فعليين موجودين بالجداول).
 */
class CompaniesFreelancersDealSeeder extends Seeder
{
    private const BOOKING_STATUSES = ['pending', 'accepted', 'rejected', 'confirmed', 'completed', 'cancelled'];

    public function run(): void
    {
        $categories = Category::query()->get();
        $districts = District::query()->get();

        if ($categories->isEmpty() || $districts->isEmpty()) {
            $this->command?->warn('شغّل CategorySeeder وGovernorateAndDistrictSeeder أولاً قبل هذا الـ seeder.');
            return;
        }

        DB::transaction(function () use ($categories, $districts) {
            $this->seedEverything($categories, $districts);
        });
    }

    private function seedEverything(Collection $categories, Collection $districts): void
    {
      
        $companies = collect(['قصر الأفراح الذهبي', 'مؤسسة الإبداع للفعاليات', 'شركة روائع المناسبات'])
            ->map(fn ($name) => $this->makeCompany($name, $categories, $districts));

        $freelancers = collect(['أحمد المصور', 'سارة منسقة الحفلات', 'خالد الديكوريتور'])
            ->map(fn ($name) => $this->makeFreelancer($name, $categories));

      
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

        $freelancerService = $this->makeListingWithVariant(
            $dealFreelancer,
            'service',
            'تصوير احترافي للمناسبات',
            $categories->first()->id,
            $districts->first()->id,
        );
        $freelancerServiceListing = $freelancerService['listing'];

      
        $bookableVariants = collect();
        $bookableVariants->push(['variant' => $freelancerService['variant'], 'slot' => $freelancerService['slot']]);

        foreach ($companies as $company) {
            $hall = $this->makeListingWithVariant(
                $company, 'hall', "صالة أفراح فاخرة - {$company->brand_name}",
                $categories->random()->id, $districts->random()->id,
            );
            $product = $this->makeChairsListing($company, $categories->random()->id, $districts->random()->id);

            $bookableVariants->push(['variant' => $hall['variant'], 'slot' => $hall['slot']]);
            $bookableVariants->push(['variant' => $product['variant'], 'slot' => $product['slot']]);

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

                $packageVariant->update([
                    'dynamic_attributes' => array_merge(
                        $packageVariant->dynamic_attributes ?? [],
                        ['capacity' => rand(80, 300)],
                    ),
                ]);

                $packageAvailability = \App\Models\ListingAvailability::create([
                    'listing_variant_id' => $packageVariant->id,
                    'available_date'     => now()->addDays(20)->toDateString(),
                    'is_blocked'         => false,
                ]);

                $packageSlot = \App\Models\ListingSlot::create([
                    'listing_availability_id' => $packageAvailability->id,
                    'slot_name'               => 'الفترة الصباحية للباقة',
                    'start_time'              => '10:00:00',
                    'end_time'                => '15:00:00',
                    'remaining_capacity'      => 1,
                ]);

                $packageSlot->setRelation('availability', $packageAvailability);

                $bookableVariants->push(['variant' => $packageVariant, 'slot' => $packageSlot]);
            }
        }

       
        $organizers = $this->makeOrganizers(2);
        $this->bookAllStatusesForOrganizers($organizers, $bookableVariants->filter()->values());

        $this->command?->info('CompaniesFreelancersDealSeeder: تم بنجاح.');
        $this->command?->table(['العنصر', 'التفاصيل'], [
            ['الشركات (3)', $companies->pluck('brand_name')->implode(', ')],
            ['الفريلانسرز (3)', $freelancers->pluck('brand_name')->implode(', ')],
            ['عقد التعاون', "{$dealCompany->brand_name} ↔ {$dealFreelancer->brand_name} (status: active)"],
            ['خدمة الفريلانسر', is_array($freelancerServiceListing->title) ? ($freelancerServiceListing->title['ar'] ?? '-') : (string) $freelancerServiceListing->title],
            ['عروض كل شركة', 'صالة (hall) + كراسي مناسبات (physical_product) + باقة (package) تضم الاثنين'],
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

        $capacity = match ($type) {
            'hall' => rand(150, 500),
            'package' => rand(80, 300),
            'service' => rand(20, 100),
            default => null,
        };

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
            'dynamic_attributes' => array_filter([
                'capacity' => $capacity,
            ], fn ($value) => $value !== null),
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

      
        $variant->setRelation('listing', $listing);
        $slot->setRelation('availability', $availability);

        return ['listing' => $listing, 'variant' => $variant, 'slot' => $slot];
    }

    
    private function makeChairsListing(Provider $provider, int $categoryId, int $districtId): array
    {
        $listing = Listing::create([
            'provider_id' => $provider->id,
            'category_id' => $categoryId,
            'district_id' => $districtId,
            'title' => [
                'ar' => "تأجير كراسي المناسبات - {$provider->brand_name}",
                'en' => "Event Chairs Rental - {$provider->brand_name}",
            ],
            'description' => [
                'ar' => 'كراسي عالية الجودة بعدة أنواع، مناسبة للأعراس والمناسبات الكبيرة.',
                'en' => 'High quality chairs in multiple styles, suitable for weddings and large events.',
            ],
            'listing_type' => 'physical_product',
            'moderation_status' => 'approved',
            'material_composition' => 'معدن وقماش مخملي',
            'is_provider_location_based' => true,
            'cancel_before_acceptance' => true,
            'cancel_after_acceptance' => false,
            'cancel_before_payment' => true,
        ]);

        $variantsData = [
            [
                'name' => ['ar' => 'كرسي عادي (سعر القطعة)', 'en' => 'Standard Chair (per piece)'],
                'price' => 5000,
                'stock' => 300,
                'attributes' => ['color' => 'أبيض', 'material' => 'معدن مطلي'],
            ],
            [
                'name' => ['ar' => 'كرسي تشيفاري ذهبي (سعر القطعة)', 'en' => 'Gold Chiavari Chair (per piece)'],
                'price' => 15000,
                'stock' => 120,
                'attributes' => ['color' => 'ذهبي', 'material' => 'خشب مطلي'],
            ],
        ];

        $variants = collect($variantsData)->map(fn ($data) => ListingVariant::create([
            'listing_id' => $listing->id,
            'variant_name' => $data['name'],
            'price' => $data['price'],
            'currency' => 'SYP',
            'price_type' => 'fixed',
            'stock_quantity' => $data['stock'],
            'dynamic_attributes' => array_merge($data['attributes'], [
                'capacity' => $data['stock'],
            ]),
        ]));

        $primaryVariant = $variants->first();

        $availability = ListingAvailability::create([
            'listing_variant_id' => $primaryVariant->id,
            'available_date' => now()->addDays(rand(10, 40))->toDateString(),
            'is_blocked' => false,
        ]);

        $slot = ListingSlot::create([
            'listing_availability_id' => $availability->id,
            'slot_name' => 'الفترة المسائية',
            'start_time' => '17:00:00',
            'end_time' => '23:00:00',
            'remaining_capacity' => 25,
        ]);

        $primaryVariant->setRelation('listing', $listing);
        $slot->setRelation('availability', $availability);

        return ['listing' => $listing, 'variant' => $primaryVariant, 'slot' => $slot];
    }

   
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
            'capacity' => rand(80, 300),
            'items' => $items,
            'freelancers' => $freelancers,
            'date_range' => [
                'start_date' => now()->addDays(15)->toDateString(),
                'end_date' => now()->addDays(17)->toDateString(),
            ],
        ], $company->id);
    }

    
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