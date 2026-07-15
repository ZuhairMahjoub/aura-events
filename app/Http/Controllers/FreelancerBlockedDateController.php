<?php

namespace App\Http\Controllers;

use App\Models\FreelancerBlockedDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FreelancerBlockedDateController extends Controller
{
    /**
     * GET /freelancer/blocked-dates
     * يرجع كل تواريخ الفريلانسر الحالي (يدوي + تلقائي)
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', FreelancerBlockedDate::class);

        $freelancer = $request->user()->providerProfile;

        $dates = FreelancerBlockedDate::where('freelancer_id', $freelancer->id)
            ->orderBy('blocked_date')
            ->get();

        return response()->json($dates);
    }

    /**
     * POST /freelancer/blocked-dates
     * إضافة دفعة تواريخ يدوية { dates: [...] }
     */
    public function store(Request $request)
    {
        Gate::authorize('create', FreelancerBlockedDate::class);

        $freelancer = $request->user()->providerProfile;

        $validated = $request->validate([
            'dates'   => ['required', 'array', 'min:1'],
            'dates.*' => [
                'required', 'date',
                Rule::unique('freelancer_blocked_dates', 'blocked_date')
                    ->where('freelancer_id', $freelancer->id),
            ],
        ]);

        $records = collect($validated['dates'])->map(function ($date) use ($freelancer) {
            return FreelancerBlockedDate::create([
                'freelancer_id' => $freelancer->id,
                'blocked_date'  => $date,
                'source'        => 'manual',
            ]);
        });

        return response()->json($records, 201);
    }

    /**
     * DELETE /freelancer/blocked-dates/{id}
     * مرفوض لو source = booking
     */
    public function destroy(Request $request, string $id)
    {
        $blockedDate = FreelancerBlockedDate::findOrFail($id);

        Gate::authorize('delete', $blockedDate);

        if ($blockedDate->source === 'booking') {
            throw ValidationException::withMessages([
                'blocked_date' => 'لا يمكن حذف هذا التاريخ لأنه مرتبط بحجز فعلي، الحذف يتم تلقائياً عند إلغاء الحجز.',
            ]);
        }

        $blockedDate->delete();

        return response()->json(['message' => 'تم حذف التاريخ بنجاح.']);
    }
}