<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AvailabilitySlot;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TeacherAvailabilityController extends Controller
{
    /**
     * Display availability management page with week view
     */
    public function index()
    {
        $teacher = Auth::user();
        
        // Get all availability slots for this teacher grouped by day_number
        $availabilitySlots = AvailabilitySlot::forTeacher($teacher->id)
            ->orderBy('day_number')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_number');

        // Define days of week
        $daysOfWeek = [
            1 => app()->getLocale() == 'ar' ? 'الأحد' : 'Sunday',
            2 => app()->getLocale() == 'ar' ? 'الإثنين' : 'Monday',
            3 => app()->getLocale() == 'ar' ? 'الثلاثاء' : 'Tuesday',
            4 => app()->getLocale() == 'ar' ? 'الأربعاء' : 'Wednesday',
            5 => app()->getLocale() == 'ar' ? 'الخميس' : 'Thursday',
            6 => app()->getLocale() == 'ar' ? 'الجمعة' : 'Friday',
            7 => app()->getLocale() == 'ar' ? 'السبت' : 'Saturday',
        ];

        // Generate time slots 
        for ($hour = 0; $hour <= 23; $hour++) {
                $timeSlots[] = sprintf('%02d:00', $hour);
            }

        return view('teacher.availability.index', compact('availabilitySlots', 'daysOfWeek', 'timeSlots'));
    }

    /**
     * Store a new availability slot
     */
    public function store(Request $request)
    {
        $request->validate([
            'day_number' => 'required|integer|between:1,7',
            'start_time' => 'required|date_format:H:i',
        ]);

        $teacher = Auth::user();
        
        // Parse start time and add 1 hour for end time
        $startTime = $request->start_time;
        $endTime = Carbon::createFromFormat('H:i', $startTime)
            ->addHour()
            ->format('H:i');

        // Check for overlapping slots
        $exists = AvailabilitySlot::where('teacher_id', $teacher->id)
            ->where('day_number', $request->day_number)
            ->where('start_time', $startTime)
            ->exists();

        if ($exists) {
            return back()->with('error', 
                app()->getLocale() == 'ar' 
                    ? 'هذا الوقت محجوز بالفعل'
                    : 'This time slot already exists'
            );
        }

        AvailabilitySlot::create([
            'teacher_id' => $teacher->id,
            'day_number' => $request->day_number,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_available' => true,
            'is_booked' => false,
            'repeat_type' => 'weekly',
            'duration' => 60,
        ]);

        return back()->with('success', 
            app()->getLocale() == 'ar' 
                ? 'تم إضافة وقت الحصة بنجاح'
                : 'Availability slot added successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $teacher = Auth::user();
        $slot = AvailabilitySlot::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        return response()->json(['data' => $slot]);
    }

    /**
     * Show form for editing availability slot
     */
    public function edit($id)
    {
        $teacher = Auth::user();
        $slot = AvailabilitySlot::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $daysOfWeek = [
            1 => app()->getLocale() == 'ar' ? 'الأحد' : 'Sunday',
            2 => app()->getLocale() == 'ar' ? 'الإثنين' : 'Monday',
            3 => app()->getLocale() == 'ar' ? 'الثلاثاء' : 'Tuesday',
            4 => app()->getLocale() == 'ar' ? 'الأربعاء' : 'Wednesday',
            5 => app()->getLocale() == 'ar' ? 'الخميس' : 'Thursday',
            6 => app()->getLocale() == 'ar' ? 'الجمعة' : 'Friday',
            7 => app()->getLocale() == 'ar' ? 'السبت' : 'Saturday',
        ];

        // Generate time slots
        $timeSlots = [];
        for ($hour = 8; $hour < 20; $hour++) {
            $timeSlots[] = sprintf('%02d:00', $hour);
        }

        return view('teacher.availability.edit', compact('slot', 'daysOfWeek', 'timeSlots'));
    }

    /**
     * Update availability slot
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'day_number' => 'required|integer|between:1,7',
            'start_time' => 'required|date_format:H:i',
        ]);

        $teacher = Auth::user();
        $slot = AvailabilitySlot::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Check if slot is booked, prevent update
        if ($slot->is_booked) {
            return back()->with('error',
                app()->getLocale() == 'ar'
                    ? 'لا يمكن تعديل وقت محجوز'
                    : 'Cannot edit booked time slot'
            );
        }

        // Parse start time and calculate end time
        $startTime = $request->start_time;
        $endTime = Carbon::createFromFormat('H:i', $startTime)
            ->addHour()
            ->format('H:i');

        // Check for overlapping slots (exclude current slot)
        $exists = AvailabilitySlot::where('teacher_id', $teacher->id)
            ->where('day_number', $request->day_number)
            ->where('start_time', $startTime)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return back()->with('error',
                app()->getLocale() == 'ar'
                    ? 'هذا الوقت محجوز بالفعل'
                    : 'This time slot already exists'
            );
        }

        $slot->update([
            'day_number' => $request->day_number,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        return back()->with('success',
            app()->getLocale() == 'ar'
                ? 'تم تحديث وقت الحصة بنجاح'
                : 'Availability slot updated successfully'
        );
    }

    /**
     * Delete availability slot
     */
    public function destroy($id)
    {
        $teacher = Auth::user();
        $slot = AvailabilitySlot::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Check if slot is booked
        if ($slot->is_booked) {
            return back()->with('error',
                app()->getLocale() == 'ar'
                    ? 'لا يمكن حذف وقت محجوز'
                    : 'Cannot delete booked time slot'
            );
        }

        $slot->delete();

        return back()->with('success',
            app()->getLocale() == 'ar'
                ? 'تم حذف وقت الحصة بنجاح'
                : 'Availability slot deleted successfully'
        );
    }
}
