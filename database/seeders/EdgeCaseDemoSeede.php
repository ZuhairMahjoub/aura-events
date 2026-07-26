<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CompanyDetail;
use App\Models\CompanyFreelancerContract;
use App\Models\District;
use App\Models\Favorite;
use App\Models\FreelancerBlockedDate;
use App\Models\FreelancerDetail;
use App\Models\JobOffer;
use App\Models\Listing;
use App\Models\ListingAvailability;
use App\Models\ListingSlot;
use App\Models\ListingVariant;
use App\Models\Notification;
use App\Models\PackageFreelancer;
use App\Models\PackageItem;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * سيدر شامل لكل الحالات الحدّية (edge cases) بالمشروع — الهدف هون مش
 * حجم بيانات كبير، الهدف "تغطية": كل حالة/enum/سيناريو حدّي مرة وحدة بس
 * كافية لاختبار كل مسار بالكود يدوياً (Postman/Tinker) بدون ما تحتاج
 * تبني البيانات من الصفر كل مرة.
 *
 * يفترض إنه RoleSeeder + CategorySeeder + GovernorateAndDistrictSeeder
 * انسحبوا قبله (نفس ترتيب DatabaseSeeder الحالي).
 */
class EdgeCaseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $organizerRole = Role::where('name', 'organizer')->where('guard_name', 'api')->first();
        $providerRole  = Role::where('name', 'provider')->where('guard_name', 'api')->first();

        $categories = Category::query()->get();
        $districts  = District::query()->get();

        if ($categories->isEmpty() || $districts->isEmpty()) {
            $this->command?->warn('شغّل CategorySeeder و GovernorateAndDistrictSeeder قبل هذا السيدر.');
            return;
        }

        // ══════════════════════════════════════════════════════════════
        // 1) المستخدمون (Organizers) — تغطية حالات الحساب المختلفة
        // ══════════════════════════════════════════════════════════════
        $organizerActive = User::factory()->verified()->create([
            'first_name' => 'سارة', 'last_name' => 'المنظمة', 'email' => 'organizer.active@aura.test',
        ]);
        $organizerActive->assignRole($organizerRole);

        $organizerUnverified = User::factory()->create([
            'first_name' => 'خالد', 'last_name' => 'غير_موثق', 'email' => 'organizer.unverified@aura.test',
            'email_verified_at' => null, // حافة: حساب لسا ما فعّل إيميله
        ]);
        $organizerUnverified->assignRole($organizerRole);

        $organizerBanned = User::factory()->verified()->create([
            'first_name' => 'ممدوح', 'last_name' => 'محظور', 'email' => 'organizer.banned@aura.test',
            'status' => 'banned', // حافة: حساب محظور
        ]);
        $organizerBanned->assignRole($organizerRole);

        // ══════════════════════════════════════════════════════════════
        // 2) الشركات (Providers: company) — كل حالات moderation/is_active
        // ══════════════════════════════════════════════════════════════
        $companyApproved = $this->makeProvider('company', $providerRole, [
            'brand_name' => 'شركة الفخامة للفعاليات', 'moderation_status' => 'approved',
            'is_active' => true, 'is_verified' => true,
        ], 'company.approved@aura.test');
        CompanyDetail::create([
            'provider_id' => $companyApproved->id, 'district_id' => $districts->random()->id,
            'address_details' => 'دمشق - المزة', 'tax_number' => 'TAX-0001', 'registration_no' => 'REG-0001',
        ]);

        $companySecondApproved = $this->makeProvider('company', $providerRole, [
            'brand_name' => 'شركة ديكور VIP', 'moderation_status' => 'approved',
            'is_active' => true, 'is_verified' => true,
        ], 'company.second@aura.test');
        CompanyDetail::create([
            'provider_id' => $companySecondApproved->id, 'district_id' => $districts->random()->id,
            'address_details' => 'حلب - الفرقان', 'tax_number' => 'TAX-0002', 'registration_no' => 'REG-0002',
        ]);

        $companyInactive = $this->makeProvider('company', $providerRole, [
            'brand_name' => 'شركة موقوفة مؤقتاً', 'moderation_status' => 'approved',
            'is_active' => false, // حافة: معتمدة لكن موقوفة (approved_provider middleware لازم يرفضها)
        ], 'company.inactive@aura.test');

        $companyPending = $this->makeProvider('company', $providerRole, [
            'brand_name' => 'شركة بانتظار الاعتماد', 'moderation_status' => 'pending',
        ], 'company.pending@aura.test');

        $companyRejected = $this->makeProvider('company', $providerRole, [
            'brand_name' => 'شركة مرفوضة', 'moderation_status' => 'rejected',
            'rejection_reason' => 'أوراق ثبوتية غير مكتملة.',
        ], 'company.rejected@aura.test');

        // ══════════════════════════════════════════════════════════════
        // 3) الفريلانسرز — نفس التغطية
        // ══════════════════════════════════════════════════════════════
        $freelancerApproved = $this->makeProvider('freelancer', $providerRole, [
            'brand_name' => 'أحمد - مصور فعاليات', 'moderation_status' => 'approved',
            'is_active' => true, 'is_verified' => true, 'rating' => 4.7,
        ], 'freelancer.approved@aura.test');
        FreelancerDetail::create([
            'provider_id' => $freelancerApproved->id, 'national_id' => '01234567890', 'experience_years' => 6,
        ]);

        $freelancerInactive = $this->makeProvider('freelancer', $providerRole, [
            'brand_name' => 'فريلانسر موقوف', 'moderation_status' => 'approved', 'is_active' => false,
        ], 'freelancer.inactive@aura.test');
        FreelancerDetail::create(['provider_id' => $freelancerInactive->id, 'national_id' => '01111111111', 'experience_years' => 2]);

        $freelancerPending = $this->makeProvider('freelancer', $providerRole, [
            'brand_name' => 'فريلانسر بانتظار الاعتماد', 'moderation_status' => 'pending',
        ], 'freelancer.pending@aura.test');
        FreelancerDetail::create(['provider_id' => $freelancerPending->id, 'national_id' => '01222222222', 'experience_years' => 1]);

        // ══════════════════════════════════════════════════════════════
        // 4) الخدمات (Services) — خاصة بالشركة المعتمدة الأساسية
        // ══════════════════════════════════════════════════════════════
        $serviceLinked = Service::create([
            'company_id' => $companyApproved->id, 'name' => 'تصوير فعاليات',
            'description' => 'تغطية تصويرية كاملة للأعراس والمناسبات.',
        ]);
        $serviceUnlinked = Service::create([
            'company_id' => $companyApproved->id, 'name' => 'دي جي وإضاءة',
            'description' => 'خدمة موسيقى وإضاءة احترافية.',
        ]); // ⚠️ ما رح تنربط بأي job offer عمداً — لاختبار حذفها بنجاح (422 vs 200)

        // ══════════════════════════════════════════════════════════════
        // 5) عروض الوظائف (JobOffers) — تغطية كل enum values + حافة service_id=null
        // ══════════════════════════════════════════════════════════════
        $jobOfferMain = JobOffer::create([
            'company_id' => $companyApproved->id, 'service_id' => $serviceLinked->id,
            'job_title' => 'مصور فعاليات محترف', 'time_condition' => 'Contract', 'event_type' => 'زفاف',
            'job_start_date' => now()->addDays(15)->toDateString(),
            'application_deadline' => now()->addDays(7)->toDateString(),
            'salary' => 500000, 'payment_system' => 'Per Event', 'experience_level' => 'Senior',
            'company_equipment_provided' => true,
            'job_requirements_and_scope' => 'خبرة لا تقل عن 5 سنوات بتصوير الأعراس.',
            'contact_info' => 'hr@fakhama.test',
        ]);

        JobOffer::create([
            'company_id' => $companyApproved->id, 'service_id' => $serviceLinked->id,
            'job_title' => 'مساعد تصوير (دوام كامل)', 'time_condition' => 'Permanent', 'event_type' => 'مؤتمرات',
            'job_start_date' => now()->addDays(30)->toDateString(),
            'application_deadline' => now()->addDays(20)->toDateString(),
            'salary' => 800000, 'payment_system' => 'Monthly', 'experience_level' => 'Junior',
            'company_equipment_provided' => false,
            'job_requirements_and_scope' => 'مبتدئ، لا يشترط خبرة سابقة.',
            'contact_info' => 'hr@fakhama.test',
        ]);

        // ⚠️ حافة: عرض وظيفة قديم بدون service_id (بيانات "قبل" ما صارت
        // الخدمة إلزامية بالـ validation — العمود nullable بقصد بالـ DB).
        JobOffer::create([
            'company_id' => $companyApproved->id, 'service_id' => null,
            'job_title' => 'عرض وظيفة قديم بدون خدمة مرتبطة', 'time_condition' => 'Temporary', 'event_type' => 'حفلات',
            'job_start_date' => now()->addDays(10)->toDateString(),
            'application_deadline' => now()->addDays(3)->toDateString(),
            'salary' => 300000, 'payment_system' => 'Hourly', 'experience_level' => 'Mid',
            'company_equipment_provided' => true,
            'job_requirements_and_scope' => 'بيانات قديمة قبل ربط الخدمات — لاختبار null-safety بالـ Resources.',
            'contact_info' => 'hr@fakhama.test',
        ]);

        // ══════════════════════════════════════════════════════════════
        // 6) عقود الشركة-الفريلانسر — تغطية كل قيم status
        // ══════════════════════════════════════════════════════════════
        $contractActive = CompanyFreelancerContract::create([
            'company_id' => $companyApproved->id, 'freelancer_id' => $freelancerApproved->id,
            'job_offer_id' => $jobOfferMain->id, 'status' => 'active',
        ]);
        CompanyFreelancerContract::create([
            'company_id' => $companyApproved->id, 'freelancer_id' => $freelancerPending->id,
            'job_offer_id' => $jobOfferMain->id, 'status' => 'pending',
        ]);
        CompanyFreelancerContract::create([
            'company_id' => $companySecondApproved->id, 'freelancer_id' => $freelancerApproved->id,
            'job_offer_id' => $jobOfferMain->id, 'status' => 'rejected',
        ]);
        CompanyFreelancerContract::create([
            'company_id' => $companyApproved->id, 'freelancer_id' => $freelancerInactive->id,
            'job_offer_id' => $jobOfferMain->id, 'status' => 'expired',
        ]);

        // ══════════════════════════════════════════════════════════════
        // 7) الإعلانات (Listings) — كل الأنواع + كل حالات moderation_status
        // ══════════════════════════════════════════════════════════════

        // 7.1 صالة (hall) معتمدة — التركيز الأساسي لاختبار availabilities/slots
        $hallListing = Listing::create([
            'provider_id' => $companyApproved->id, 'category_id' => $categories->random()->id,
            'district_id' => $districts->random()->id,
            'title' => ['en' => 'Grand Ballroom Hall', 'ar' => 'صالة القاعة الكبرى'],
            'description' => ['en' => 'A luxurious hall for weddings.', 'ar' => 'صالة فخمة تناسب الأعراس والمناسبات الكبيرة.'],
            'listing_type' => 'hall', 'moderation_status' => 'approved',
            'cancel_before_acceptance' => true, 'cancel_after_acceptance' => false, 'cancel_before_payment' => true,
            'is_provider_location_based' => true,
            'secondary_contact_number' => '0911111111',
        ]);
        $hallVariant = ListingVariant::create([
            'listing_id' => $hallListing->id,
            'variant_name' => ['en' => 'Full Hall Booking', 'ar' => 'حجز الصالة كاملة'],
            'price' => 1500000, 'currency' => 'SYP', 'price_type' => 'fixed',
            'dynamic_attributes' => ['capacity' => 300],
        ]);

        // تاريخ عادي بثلاث فترات (بينها فترة "ممتلئة بالكامل" remaining_capacity=0)
        $hallDate1 = ListingAvailability::create(['listing_variant_id' => $hallVariant->id, 'available_date' => now()->addDays(20)->toDateString(), 'is_blocked' => false]);
        ListingSlot::create(['listing_availability_id' => $hallDate1->id, 'slot_name' => ['ar' => 'الفترة الصباحية', 'en' => 'Morning'], 'start_time' => '09:00:00', 'end_time' => '13:00:00', 'remaining_capacity' => 2]);
        ListingSlot::create(['listing_availability_id' => $hallDate1->id, 'slot_name' => ['ar' => 'الفترة المسائية', 'en' => 'Evening'], 'start_time' => '17:00:00', 'end_time' => '23:59:00', 'remaining_capacity' => 0]); // ⚠️ حافة: ممتلئة بالكامل

        // ⚠️ حافة: تاريخ محظور بالكامل (is_blocked = true) بدون أي slots
        ListingAvailability::create(['listing_variant_id' => $hallVariant->id, 'available_date' => now()->addDays(25)->toDateString(), 'is_blocked' => true]);

        // ⚠️ حافة: تاريخ بالماضي (بيانات قديمة قبل ما صار فيه after_or_equal:today بالتحقق)
        $pastDate = ListingAvailability::create(['listing_variant_id' => $hallVariant->id, 'available_date' => now()->subDays(10)->toDateString(), 'is_blocked' => false]);
        ListingSlot::create(['listing_availability_id' => $pastDate->id, 'slot_name' => ['ar' => 'فترة قديمة', 'en' => 'Past slot'], 'start_time' => '10:00:00', 'end_time' => '14:00:00', 'remaining_capacity' => 5]);

        // 7.2 خدمة (service) معتمدة — hourly
        $serviceListing = Listing::create([
            'provider_id' => $companyApproved->id, 'category_id' => $categories->random()->id,
            'district_id' => $districts->random()->id,
            'title' => ['en' => 'Professional Sound System', 'ar' => 'نظام صوتي احترافي'],
            'description' => ['en' => 'High quality sound rental with technician.', 'ar' => 'تأجير نظام صوت احترافي مع فني متخصص.'],
            'listing_type' => 'service', 'moderation_status' => 'approved',
            'is_provider_location_based' => false,
            'secondary_contact_number' => '0922222222',
        ]);
        $serviceVariant = ListingVariant::create([
            'listing_id' => $serviceListing->id,
            'variant_name' => ['en' => 'Standard Sound Package', 'ar' => 'باقة الصوت القياسية'],
            'price' => 50000, 'currency' => 'SYP', 'price_type' => 'hourly',
            'dynamic_attributes' => ['capacity' => 1],
        ]);
        $serviceDate = ListingAvailability::create(['listing_variant_id' => $serviceVariant->id, 'available_date' => now()->addDays(12)->toDateString(), 'is_blocked' => false]);
        $freelancerBookingSlot = ListingSlot::create(['listing_availability_id' => $serviceDate->id, 'slot_name' => ['ar' => 'من 2 لـ 4 عصراً', 'en' => '2-4 PM'], 'start_time' => '14:00:00', 'end_time' => '16:00:00', 'remaining_capacity' => 1]);

        // 7.3 منتج مادي (physical_product) معتمد — بدون تواريخ (حسب قاعدة العمل)
        $productListing = Listing::create([
            'provider_id' => $companyApproved->id, 'category_id' => $categories->random()->id,
            'district_id' => $districts->random()->id,
            'title' => ['en' => 'Wedding Chairs Set', 'ar' => 'طقم كراسي أفراح'],
            'description' => ['en' => '50 premium chairs for rent.', 'ar' => '50 كرسي فاخر للإيجار.'],
            'listing_type' => 'physical_product', 'moderation_status' => 'approved',
            'material_composition' => 'خشب وقماش مخملي',
            'is_provider_location_based' => true,
            'secondary_contact_number' => '0933333333',
        ]);
        $productVariant = ListingVariant::create([
            'listing_id' => $productListing->id,
            'variant_name' => ['en' => 'Chair Set (50 pcs)', 'ar' => 'طقم كراسي (50 قطعة)'],
            'price' => 5000, 'currency' => 'SYP', 'price_type' => 'fixed', 'stock_quantity' => 50,
            'dynamic_attributes' => [],
        ]);

        // 7.4 باقة (Arrangement/package) معتمدة — تشمل items + freelancer مرتبط
        $packageListing = Listing::create([
            'provider_id' => $companyApproved->id, 'category_id' => $categories->random()->id,
            'district_id' => $districts->random()->id,
            'title' => 'باقة الزفاف الفاخرة الشاملة',
            'description' => 'باقة متكاملة تشمل الصالة، التصوير، والصوت.',
            'listing_type' => 'package', 'moderation_status' => 'approved',
            'cancel_before_acceptance' => true, 'cancel_after_acceptance' => false, 'cancel_before_payment' => true,
            'secondary_contact_number' => '0944444444',
        ]);
        $packageVariant = ListingVariant::create([
            'listing_id' => $packageListing->id,
            'variant_name' => 'الباقة الكاملة VIP',
            'price' => 2500000, 'currency' => 'SYP', 'price_type' => 'fixed',
            'dynamic_attributes' => ['capacity' => 300],
        ]);
        $packageDate = ListingAvailability::create(['listing_variant_id' => $packageVariant->id, 'available_date' => now()->addDays(20)->toDateString(), 'is_blocked' => false]);
        ListingSlot::create(['listing_availability_id' => $packageDate->id, 'slot_name' => ['ar' => 'اليوم كامل', 'en' => 'Full day'], 'start_time' => '10:00:00', 'end_time' => '23:00:00', 'remaining_capacity' => 1]);

        PackageItem::create(['package_variant_id' => $packageVariant->id, 'included_variant_id' => $productVariant->id, 'quantity' => 2]);
        PackageItem::create(['package_variant_id' => $packageVariant->id, 'included_variant_id' => $serviceVariant->id, 'quantity' => 1]);
        PackageFreelancer::create(['package_variant_id' => $packageVariant->id, 'freelancer_id' => $freelancerApproved->id, 'contract_id' => $contractActive->id]);

        // 7.5 باقة ثانية بانتظار الموافقة (حافة: moderation_status = pending_approval)
        $pendingPackageListing = Listing::create([
            'provider_id' => $companyApproved->id, 'category_id' => $categories->random()->id,
            'district_id' => $districts->random()->id,
            'title' => 'باقة جديدة بانتظار موافقة الأدمن',
            'description' => 'باقة أُنشئت حديثاً ولسا ما اتراجعت من الأدمن.',
            'listing_type' => 'package', 'moderation_status' => 'pending_approval',
        ]);
        ListingVariant::create([
            'listing_id' => $pendingPackageListing->id, 'variant_name' => 'باقة تجريبية',
            'price' => 900000, 'currency' => 'SYP', 'price_type' => 'fixed', 'dynamic_attributes' => ['capacity' => 100],
        ]);

        // 7.6 إعلان مرفوض (حافة: moderation_status = rejected)
        $rejectedListing = Listing::create([
            'provider_id' => $companyApproved->id, 'category_id' => $categories->random()->id,
            'district_id' => $districts->random()->id,
            'title' => ['en' => 'Rejected Listing Example', 'ar' => 'إعلان مرفوض كمثال'],
            'description' => ['en' => 'This was rejected by admin.', 'ar' => 'تم رفض هذا الإعلان من الأدمن.'],
            'listing_type' => 'service', 'moderation_status' => 'rejected',
            'rejection_reason' => 'الوصف غير مطابق للشروط.',
        ]);
        ListingVariant::create([
            'listing_id' => $rejectedListing->id, 'variant_name' => ['en' => 'Rejected Variant', 'ar' => 'نسخة مرفوضة'],
            'price' => 100000, 'currency' => 'SYP', 'price_type' => 'fixed', 'dynamic_attributes' => [],
        ]);

        // 7.7 إعلان مسودة (حافة: moderation_status = draft — لسا ما اتقدّم للمراجعة)
        Listing::create([
            'provider_id' => $companySecondApproved->id, 'category_id' => $categories->random()->id,
            'district_id' => $districts->random()->id,
            'title' => ['en' => 'Draft Listing (not submitted yet)', 'ar' => 'إعلان مسودة (لسا ما انبعت)'],
            'description' => ['en' => 'Still being edited by the provider.', 'ar' => 'لسا الشركة عم تحرره.'],
            'listing_type' => 'hall', 'moderation_status' => 'draft',
        ]);

        // ══════════════════════════════════════════════════════════════
        // 8) الحجوزات — تغطية كل status × payment_status × booking_type
        // ══════════════════════════════════════════════════════════════
        Booking::create([
            'user_id' => $organizerActive->id, 'provider_id' => $companyApproved->id,
            'listing_id' => $productListing->id, 'listing_variant_id' => $productVariant->id,
            'booking_type' => 'physical_product', 'status' => 'pending', 'payment_status' => 'unpaid',
            'quantity' => 3, 'total_price' => 15000, 'currency' => 'SYP', // ⚠️ حافة: quantity > 1
            'metadata' => ['event_type' => 'wedding'],
        ]);

        $acceptedHallBooking = Booking::create([
            'user_id' => $organizerActive->id, 'provider_id' => $companyApproved->id,
            'listing_id' => $hallListing->id, 'listing_variant_id' => $hallVariant->id,
            'listing_slot_id' => null, 'booking_type' => 'hall', 'status' => 'accepted', 'payment_status' => 'paid',
            'quantity' => 1, 'total_price' => 1500000, 'currency' => 'SYP',
            'booked_date' => $hallDate1->available_date->toDateString(),
            'metadata' => ['event_type' => 'wedding', 'guest_count' => 250],
        ]);
        BookingStatusLog::create(['booking_id' => $acceptedHallBooking->id, 'from_status' => 'pending', 'to_status' => 'accepted', 'actor_type' => 'provider', 'actor_id' => $companyApproved->id]);

        $rejectedBooking = Booking::create([
            'user_id' => $organizerActive->id, 'provider_id' => $companyApproved->id,
            'listing_id' => $serviceListing->id, 'listing_variant_id' => $serviceVariant->id,
            'booking_type' => 'service', 'status' => 'rejected', 'payment_status' => 'unpaid',
            'quantity' => 1, 'total_price' => 50000, 'currency' => 'SYP',
        ]);
        BookingStatusLog::create(['booking_id' => $rejectedBooking->id, 'from_status' => 'pending', 'to_status' => 'rejected', 'actor_type' => 'provider', 'actor_id' => $companyApproved->id, 'reason' => 'الفريق غير متاح بهذا التاريخ.']);

        Booking::create([
            'user_id' => $organizerActive->id, 'provider_id' => $companyApproved->id,
            'listing_id' => $packageListing->id, 'listing_variant_id' => $packageVariant->id,
            'booking_type' => 'package', 'status' => 'confirmed', 'payment_status' => 'paid',
            'quantity' => 1, 'total_price' => 2500000, 'currency' => 'SYP',
            'booked_date' => $packageDate->available_date->toDateString(),
        ]);

        $completedBooking = Booking::create([
            'user_id' => $organizerActive->id, 'provider_id' => $companyApproved->id,
            'listing_id' => $serviceListing->id, 'listing_variant_id' => $serviceVariant->id,
            'booking_type' => 'service', 'status' => 'completed', 'payment_status' => 'paid',
            'quantity' => 1, 'total_price' => 50000, 'currency' => 'SYP',
            'completed_at' => now()->subDays(2),
        ]);
        BookingStatusLog::create(['booking_id' => $completedBooking->id, 'from_status' => 'confirmed', 'to_status' => 'completed', 'actor_type' => 'provider', 'actor_id' => $companyApproved->id]);

        Booking::create([
            'user_id' => $organizerActive->id, 'provider_id' => $companyApproved->id,
            'listing_id' => $hallListing->id, 'listing_variant_id' => $hallVariant->id,
            'booking_type' => 'hall', 'status' => 'cancelled', 'payment_status' => 'refunded',
            'quantity' => 1, 'total_price' => 1500000, 'currency' => 'SYP',
            'cancelled_at' => now()->subDay(), 'cancellation_reason' => 'تغيير خطط العميل.', 'cancelled_by' => 'organizer',
        ]);

        // ⚠️ حجز بسعر صفري (حافة: يتخطى حارس الدفع بـ complete() تلقائياً)
        Booking::create([
            'user_id' => $organizerActive->id, 'provider_id' => $companyApproved->id,
            'listing_id' => $serviceListing->id, 'listing_variant_id' => $serviceVariant->id,
            'booking_type' => 'service', 'status' => 'completed', 'payment_status' => 'paid',
            'quantity' => 1, 'total_price' => 0, 'currency' => 'SYP',
            'completed_at' => now()->subDay(), 'customer_notes' => 'عرض ترويجي مجاني.',
        ]);

        // ⚠️ حجز مباشر لخدمة فريلانسر (مقبول) — مع صف FreelancerBlockedDate
        // متسق يدوياً (نفس ما كانت تعمله BookingService::accept() فعلياً).
        $freelancerBooking = Booking::create([
            'user_id' => $organizerActive->id, 'provider_id' => $freelancerApproved->id,
            'listing_id' => $serviceListing->id, 'listing_variant_id' => $serviceVariant->id,
            'listing_slot_id' => $freelancerBookingSlot->id, 'booking_type' => 'service',
            'status' => 'accepted', 'payment_status' => 'paid', 'quantity' => 1, 'total_price' => 50000, 'currency' => 'SYP',
            'booked_date' => $serviceDate->available_date->toDateString(),
            'booked_start_time' => $freelancerBookingSlot->start_time, 'booked_end_time' => $freelancerBookingSlot->end_time,
        ]);
        FreelancerBlockedDate::create([
            'freelancer_id' => $freelancerApproved->id, 'blocked_date' => $serviceDate->available_date->toDateString(),
            'start_time' => $freelancerBookingSlot->start_time, 'end_time' => $freelancerBookingSlot->end_time,
            'source' => 'booking', 'booking_id' => $freelancerBooking->id,
        ]);

        // ══════════════════════════════════════════════════════════════
        // 9) روزنامة الفريلانسر — حظر يدوي إضافي (يوم كامل، بدون علاقة بحجز)
        // ══════════════════════════════════════════════════════════════
        FreelancerBlockedDate::create([
            'freelancer_id' => $freelancerApproved->id, 'blocked_date' => now()->addDays(40)->toDateString(),
            'start_time' => null, 'end_time' => null, 'source' => 'manual',
        ]);

        // ══════════════════════════════════════════════════════════════
        // 10) السلة (Cart) — active / converted / abandoned
        // ══════════════════════════════════════════════════════════════
        $activeCart = Cart::create(['user_id' => $organizerActive->id, 'status' => 'active', 'single_active_lock' => $organizerActive->id]);
        CartItem::create(['cart_id' => $activeCart->id, 'listing_id' => $productListing->id, 'listing_variant_id' => $productVariant->id, 'quantity' => 2, 'price_snapshot' => $productVariant->price]);
        CartItem::create(['cart_id' => $activeCart->id, 'listing_id' => $serviceListing->id, 'listing_variant_id' => $serviceVariant->id, 'listing_slot_id' => $freelancerBookingSlot->id, 'quantity' => 1, 'price_snapshot' => $serviceVariant->price, 'booked_date' => $serviceDate->available_date->toDateString()]);

        $convertedCart = Cart::create(['user_id' => $organizerActive->id, 'status' => 'converted', 'single_active_lock' => null]);
        CartItem::create(['cart_id' => $convertedCart->id, 'listing_id' => $hallListing->id, 'listing_variant_id' => $hallVariant->id, 'quantity' => 1, 'price_snapshot' => $hallVariant->price, 'converted_booking_id' => $acceptedHallBooking->id]);

        $abandonedCart = Cart::create(['user_id' => $organizerUnverified->id, 'status' => 'abandoned', 'single_active_lock' => null]);
        CartItem::create(['cart_id' => $abandonedCart->id, 'listing_id' => $productListing->id, 'listing_variant_id' => $productVariant->id, 'quantity' => 1, 'price_snapshot' => $productVariant->price]);

        // ══════════════════════════════════════════════════════════════
        // 11) التقييمات (Reviews) — بالاتجاهين لنفس الحجز المكتمل
        // ══════════════════════════════════════════════════════════════
        Review::create([
            'booking_id' => $completedBooking->id,
            'reviewer_type' => User::class, 'reviewer_id' => $organizerActive->id,
            'reviewee_type' => Provider::class, 'reviewee_id' => $companyApproved->id,
            'rating' => 5, 'comment' => 'خدمة ممتازة وفريق محترف!',
        ]);
        Review::create([
            'booking_id' => $completedBooking->id,
            'reviewer_type' => Provider::class, 'reviewer_id' => $companyApproved->id,
            'reviewee_type' => User::class, 'reviewee_id' => $organizerActive->id,
            'rating' => 4, 'comment' => 'عميل متعاون وملتزم بالمواعيد.',
        ]);

        // ══════════════════════════════════════════════════════════════
        // 12) المفضلة (Favorites)
        // ══════════════════════════════════════════════════════════════
        Favorite::create(['user_id' => $organizerActive->id, 'listing_id' => $hallListing->id]);
        Favorite::create(['user_id' => $organizerActive->id, 'listing_id' => $packageListing->id]);

        // ══════════════════════════════════════════════════════════════
        // 13) الإشعارات (Notifications) — مقروءة/غير مقروءة
        // ══════════════════════════════════════════════════════════════
        Notification::create(['user_id' => $organizerActive->id, 'title' => 'تم قبول حجزك', 'body' => 'قامت الشركة بقبول طلب حجزك.', 'data' => ['booking_id' => $acceptedHallBooking->id], 'is_read' => false]);
        Notification::create(['user_id' => $organizerActive->id, 'title' => 'حجز مكتمل', 'body' => 'اكتمل حجزك بنجاح، شاركنا رأيك!', 'data' => ['booking_id' => $completedBooking->id], 'is_read' => true]);

        $this->command?->info('✅ EdgeCaseDemoSeeder: تم زرع بيانات شاملة لكل الحالات الحدّية بنجاح.');
    }

    private function makeProvider(string $type, Role $role, array $attributes, string $email): Provider
    {
        $user = User::factory()->verified()->create(['email' => $email]);
        $user->assignRole($role);

        return Provider::factory()->{$type}()->create(array_merge(['user_id' => $user->id], $attributes));
    }
}