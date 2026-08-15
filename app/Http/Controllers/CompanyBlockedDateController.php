<?php

namespace App\Http\Controllers;

use App\Models\CompanyBlockedDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyBlockedDateController extends Controller
{
   public function index(Request $request)
    {
        $company = $request->user()->providerProfile;

        $dates = CompanyBlockedDate::where('company_id', $company->id)
            ->orderBy('blocked_date')
            ->get()
            // 💡 هذه الدالة تقوم بإخفاء الحقول التي لا نريدها أن تظهر في البوستمان أو واجهة React
            ->makeHidden(['source', 'booking_id']); 

        return response()->json($dates);
    }

   public function store(Request $request)
{
    $company = $request->user()->providerProfile;

    $validated = $request->validate([
        'dates'      => ['required', 'array', 'min:1'],
        'dates.*'    => ['required', 'date'],
        
        // 💡 التحقق من الملاحظة والوقت
        'note'       => ['nullable', 'string', 'max:255'],
        'start_time' => ['nullable', 'date_format:H:i'], // صيغة الوقت مثل 14:30
        'end_time'   => ['nullable', 'date_format:H:i', 'after:start_time'], // يجب أن يكون بعد البداية
    ]);

    // نمرر الـ $validated للداخل باستخدام use
    $records = collect($validated['dates'])->map(function ($date) use ($company, $validated) {
        return CompanyBlockedDate::create([
            'company_id'   => $company->id,
            'blocked_date' => $date,
            'source'       => 'manual',
            
            // 💡 حفظ البيانات الجديدة
            'start_time'   => $validated['start_time'] ?? null,
            'end_time'     => $validated['end_time'] ?? null,
            'note'         => $validated['note'] ?? null,
        ]);
    });

    return response()->json($records, 201);
}

    public function destroy(Request $request, string $id)
    {
        $blockedDate = CompanyBlockedDate::findOrFail($id);

        // Gate::authorize('delete', $blockedDate);

        if ($blockedDate->source === 'booking') {
            throw ValidationException::withMessages([
                'blocked_date' => 'لا يمكن حذف هذا التاريخ لأنه مرتبط بحجز فعلي، الحذف يتم تلقائياً عند إلغاء الحجز.',
            ]);
        }

        $blockedDate->delete();

        return response()->json(['message' => 'تم حذف التاريخ بنجاح.']);
    }
}