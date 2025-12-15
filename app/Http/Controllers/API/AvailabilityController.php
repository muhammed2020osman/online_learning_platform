<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AvailabilitySlot;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Sessions;

class AvailabilityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/teachers",
     *     summary="Get all teachers",
     *     tags={"Teachers"},
     *     @OA\Response(
     *         response=200,
     *         description="List of teachers"
     *     )
     * )
     */
    /**
     * Display a listing of the resource.
     * Groups availability slots by day_number with time_slots array.
     * Includes session data if slot is booked.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
{
    // Auth Teacher ID
    $teacherId = $request->user()->id;

    // Get all slots for this teacher sorted
    $slots = AvailabilitySlot::forTeacher($teacherId)
        ->orderBy('day_number')
        ->orderBy('start_time')
        ->get();

    // Group by day_number
    $groupedByDay = $slots->groupBy('day_number')->map(function ($daySlots, $dayNumber) {
        
            $timeSlots = $daySlots->map(function ($slot) {
                $slotData = [
                    'id' => $slot->id,
                    'time' => $slot->start_time->format('H:i'),
                    'session' => null,
                ];

                // Prefer session linked directly to this availability slot (by availability_slot_id)
                $session = Sessions::where('availability_slot_id', $slot->id)
                    ->where('status', Sessions::STATUS_SCHEDULED)
                    ->first();

                // Fallback: if no direct session, try sessions by booking_id (scheduled)
                if (!$session && $slot->booking_id) {
                    $session = Sessions::where('booking_id', $slot->booking_id)
                        ->where('status', Sessions::STATUS_SCHEDULED)
                        ->first();
                }

                if ($session && $session->booking) {
                    $booking = $session->booking;
                    $course = $booking->course;

                    $slotData['session'] = [
                        'id' => $session->id,
                        'subject' => $course ? $course->name : null,
                        'lesson_class' => $course ? $course->classLevel?->name ?? null : null,
                        'level' => $course ? $course->educationLevel?->name ?? null : null,
                        'url' => $session->join_url ?? null,
                        'student_count' => $booking->sessions()->count() ?? 0,
                        'price' => $booking->price_per_session ?? null,
                        'completedLectures' => $booking->sessions_completed ?? 0,
                    ];
                }

                return $slotData;
            })->values();

        return [
            'day' => (int)$dayNumber,
            'time_slots' => $timeSlots,
        ];
    })->values();

    return response()->json([
        'success' => true,
        'data' => $groupedByDay
    ]);
}


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Basic validation for container and optional fields
        $request->validate([
            'available_times' => 'required|array',
            'course_id' => 'nullable|exists:courses,id',
            'order_id' => 'nullable|exists:orders,id',
            'repeat_type' => 'nullable|in:none,weekly,daily',
        ]);

        $teacherId = $request->user()->id;
        $createdSlots = [];

        // Accept times in formats like "9:00 AM", "09:00", etc.
        $timeFormats = ['g:i A', 'h:i A', 'H:i', 'G:i'];

        foreach ($request->available_times as $dayEntry) {
            // Support both `day` and `day_number` keys to match incoming payloads
            $day = $dayEntry['day'] ?? $dayEntry['day_number'] ?? null;
            if (!$day || !is_int((int)$day) || $day < 1 || $day > 7) {
                return response()->json(['success' => false, 'message' => 'Invalid or missing day for available_times entries'], 422);
            }

            if (!isset($dayEntry['times']) || !is_array($dayEntry['times'])) {
                return response()->json(['success' => false, 'message' => 'Times array is required for each available_times entry'], 422);
            }

            foreach ($dayEntry['times'] as $time) {
                $timeStr = trim($time);
                $parsed = null;
                foreach ($timeFormats as $fmt) {
                    try {
                        $parsed = \Carbon\Carbon::createFromFormat($fmt, $timeStr);
                        if ($parsed) break;
                    } catch (\Exception $e) {
                        // try next format
                    }
                }

                if (!$parsed) {
                    return response()->json(['success' => false, 'message' => "Invalid time format: {$timeStr}"], 422);
                }

                $startTime = $parsed->format('H:i');
                $endTime = $parsed->copy()->addHour()->format('H:i');

                $slot = AvailabilitySlot::create([
                    'teacher_id' => $teacherId,
                    'course_id' => $request->course_id,
                    'order_id' => $request->order_id,
                    'day_number' => (int)$day,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'is_available' => true,
                    'is_booked' => false,
                    'repeat_type' => $request->repeat_type ?? AvailabilitySlot::REPEAT_NONE,
                ]);
                $createdSlots[] = $slot;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'success',
            'data' => $createdSlots
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $teacherId = $request->user()->id;
        $slot = AvailabilitySlot::where('id', $id)->where('teacher_id', $teacherId)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $slot
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $teacherId = $request->user()->id;

        // If client sends a bulk available_times payload to replace/update availability for days,
        // handle it here: sync by replacing all slots for the given days.
        if ($request->has('available_times')) {
            $request->validate([
                'available_times' => 'required|array',
                'course_id' => 'nullable|exists:courses,id',
                'order_id' => 'nullable|exists:orders,id',
                'repeat_type' => 'nullable|in:none,weekly,daily',
            ]);

            $timeFormats = ['g:i A', 'h:i A', 'H:i', 'G:i'];

            return DB::transaction(function () use ($request, $teacherId, $timeFormats) {
                $created = [];
                foreach ($request->available_times as $entry) {
                    $day = $entry['day'] ?? $entry['day_number'] ?? null;
                    if (!$day) continue;

                    // Remove existing slots for this teacher & day (optionally constrain by course/order if provided)
                    $query = AvailabilitySlot::where('teacher_id', $teacherId)->where('day_number', (int)$day);
                    if ($request->filled('course_id')) $query->where('course_id', $request->course_id);
                    if ($request->filled('order_id')) $query->where('order_id', $request->order_id);
                    $query->delete();

                    if (!isset($entry['times']) || !is_array($entry['times'])) continue;

                    foreach ($entry['times'] as $time) {
                        $timeStr = trim($time);
                        $parsed = null;
                        foreach ($timeFormats as $fmt) {
                            try {
                                $parsed = \Carbon\Carbon::createFromFormat($fmt, $timeStr);
                                if ($parsed) break;
                            } catch (\Exception $e) {
                            }
                        }
                        if (!$parsed) continue;
                        $startTime = $parsed->format('H:i');
                        $endTime = $parsed->copy()->addHour()->format('H:i');

                        $slot = AvailabilitySlot::create([
                            'teacher_id' => $teacherId,
                            'course_id' => $request->course_id,
                            'order_id' => $request->order_id,
                            'day_number' => (int)$day,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'is_available' => true,
                            'is_booked' => false,
                            'repeat_type' => $request->repeat_type ?? AvailabilitySlot::REPEAT_NONE,
                        ]);
                        $created[] = $slot;
                    }
                }

                return response()->json(['success' => true, 'message' => 'Availability synced', 'data' => $created]);
            });
        }

        // Otherwise fall back to single-slot update by id
        $slot = AvailabilitySlot::where('id', $id)->where('teacher_id', $teacherId)->firstOrFail();

        $request->validate([
            'day_number' => 'sometimes|integer|between:1,7',
            'start_time' => 'sometimes|string',
            'is_available' => 'sometimes|boolean',
            'repeat_type' => 'nullable|in:none,weekly,daily',
            'course_id' => 'nullable|exists:courses,id',
            'order_id' => 'nullable|exists:orders,id',
        ]);

        $data = $request->only(['day_number', 'start_time', 'is_available', 'repeat_type', 'course_id', 'order_id']);

        // If start_time is present, try multiple formats and set end_time to start_time + 1 hour
        if (isset($data['start_time'])) {
            $parsed = null;
            foreach (['g:i A', 'h:i A', 'H:i', 'G:i'] as $fmt) {
                try {
                    $parsed = \Carbon\Carbon::createFromFormat($fmt, trim($data['start_time']));
                    if ($parsed) break;
                } catch (\Exception $e) {
                }
            }
            if ($parsed) {
                $data['start_time'] = $parsed->format('H:i');
                $data['end_time'] = $parsed->copy()->addHour()->format('H:i');
            } else {
                return response()->json(['success' => false, 'message' => 'Invalid start_time format'], 422);
            }
        }

        $slot->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Slot updated successfully',
            'data' => $slot
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $teacherId = $request->user()->id;
        $slot = AvailabilitySlot::where('id', $id)->where('teacher_id', $teacherId)->firstOrFail();

        // Prevent deletion of booked slots (handled in model boot)
        $slot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Slot deleted successfully'
        ]);
    }
}
         