<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Booking;
use App\Models\Services;

class CourseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/",
     *     summary="Get all ",
     *     tags={""},
     *     @OA\Response(
     *         response=200,
     *         description="List of "
     *     )
     * )
     */
    // Student: Browse all published courses
    /**
     * @OA\Get(
     *     path="/api/courses",
     *     summary="Browse published courses",
     *     tags={"Courses"},
     *     @OA\Response(response=200, description="List of courses")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $service_id = 4;
        // Get all courses for this service
        $courses = Course::with(['teacher', 'category', 'coverImage'])
            ->where('service_id', $service_id)
            ->where('status', 'published')
            ->get();

        // Get all unique teacher IDs for these courses
        $teacherIds = $courses->pluck('teacher_id')->unique();

        // Get teacher profiles
        $profiles = \App\Models\UserProfile::whereIn('user_id', $teacherIds)->get()->keyBy('user_id');

        // Get teacher basic info
        $teachers = \App\Models\User::whereIn('id', $teacherIds)->with('profile')
            ->select('id', 'first_name', 'last_name', 'gender', 'nationality')
            ->get()
            ->keyBy('id');
        
        // Get reviews for these teachers
        $reviews = \App\Models\Review::whereIn('reviewed_id', $teacherIds)->get()->groupBy('reviewed_id');

        // Attach profile, basic info, and reviews to each course's teacher
        $courses->transform(function ($course) use ($profiles, $teachers, $reviews) {
            $teacher_id = $course->teacher_id;
            $course['teacher_profile'] = $profiles[$teacher_id] ?? null;
            $course['teacher_basic'] = $teachers[$teacher_id] ?? null;
            $course['teacher_reviews'] = $reviews[$teacher_id] ?? collect();
            return $course;
        });

        return response()->json([
            'success' => true,
            'data' => $courses
        ]);
    }

    

    // Student: Get course details
    /**
     * @OA\Get(
     *     path="/api/courses/{id}",
     *     summary="Get course details",
     *     tags={"Courses"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Course details")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $course = Course::with(['teacher', 'category','availabilitySlots', 'coverImage', 'courselessons'])
            ->where('status', 'published')
            ->findOrFail($id);

        $teacher_id = $course->teacher_id;

        // Get teacher profile
        $teacher_profile = \App\Models\UserProfile::where('user_id', $teacher_id)->first();

        // Get teacher basic info
        $teacher_basic = \App\Models\User::where('id', $teacher_id)
            ->select('id', 'first_name', 'last_name', 'gender', 'nationality')
            ->first();

        // Get reviews for this teacher
        $teacher_reviews = \App\Models\Review::where('reviewed_id', $teacher_id)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'course' => $course,
                'teacher_profile' => $teacher_profile,
                'teacher_basic' => $teacher_basic,
                'teacher_reviews' => $teacher_reviews,
            ]
        ]);
    }

    // Student: Enroll in course
    public function enroll(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $course = Course::where('status', 'published')->findOrFail($id);

        // Check if already enrolled
        $alreadyEnrolled = DB::table('course_student')
            ->where('course_id', $id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'Already enrolled in this course'
            ], 400);
        }

        // Enroll student with pending status (if the pivot supports status column)
        $insert = [
            'course_id' => $id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('course_student', 'status')) {
            $insert['status'] = 'pending';
        }

        DB::table('course_student')->insert($insert);

        return response()->json([
            'success' => true,
            'message' => 'Successfully enrolled in course',
            'data' => [
                'course_id' => $id,
                'enrolled_at' => now()
            ]
        ]);
    }
/////////////////////////////////////////////////////////////////////////////////////////////

    // Teacher: Create new course
    public function  store(Request $request): JsonResponse
    {
        // New store behavior:
        // - accept course data (name, price, duration_hours, description, category_id, status)
        // - accept cover_image file and save as Attachment
        // - accept available_slots in the same shape as teacher availability: [{ day: 1..7, times: ['09:00','2:00 PM'] }, ...]
        // - prevent creating course slots that conflict with the teacher's existing (personal) slots

        $request->validate([
            'category_id' => 'nullable|exists:course_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'course_type' => 'required|in:single,package,subscription',
            'price' => 'nullable|numeric|min:0|max:999999.99',
            'duration_hours' => 'nullable|integer|min:1|max:1000',
            'status' => 'required|in:draft,published',
            'cover_image' => 'nullable|file|image|max:4096',
            'available_slots' => 'required|array',
            // each available_slots entry should be { day: int(1..7), times: [string,...] }
        ]);

        $service = Services::where('name_en', 'Course')->first();
        $service_id = $service ? $service->id : null;

        $teacherId = $request->user()->id;

        // Validate structure of available_slots and parse times
        $timeFormats = ['g:i A', 'h:i A', 'H:i', 'G:i'];

        $parsedSlots = [];
        foreach ($request->available_slots as $entry) {
            $day = $entry['day'] ?? $entry['day_number'] ?? null;
            if (!is_numeric($day) || (int)$day < 1 || (int)$day > 7) {
                return response()->json(['success' => false, 'message' => 'Each available_slots entry must include a valid day (1..7)'], 422);
            }
            if (!isset($entry['times']) || !is_array($entry['times'])) {
                return response()->json(['success' => false, 'message' => 'Each available_slots entry must include a times array'], 422);
            }

            foreach ($entry['times'] as $timeStr) {
                $timeStr = trim($timeStr);
                $parsed = null;
                foreach ($timeFormats as $fmt) {
                    try {
                        $parsed = \Carbon\Carbon::createFromFormat($fmt, $timeStr);
                        if ($parsed) break;
                    } catch (\Exception $e) {
                        // continue trying formats
                    }
                }
                if (!$parsed) {
                    return response()->json(['success' => false, 'message' => "Invalid time format: {$timeStr}"], 422);
                }

                $startTime = $parsed->format('H:i');
                $endTime = $parsed->copy()->addHour()->format('H:i');

                // Check teacher's existing personal slots (those saved with no course_id) for exact start_time conflicts on the same day
                $conflict = \App\Models\AvailabilitySlot::where('teacher_id', $teacherId)
                    ->whereNull('course_id')
                    ->where('day_number', (int)$day)
                    ->where('start_time', $startTime)
                    ->exists();

                if ($conflict) {
                    return response()->json(['success' => false, 'message' => "Cannot add course slot: teacher already has a personal slot on day {$day} at {$startTime}"], 422);
                }

                $parsedSlots[] = [
                    'day_number' => (int)$day,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ];
            }
        }

        // Create the course
        $course = Course::create([
            'teacher_id' => $teacherId,
            'category_id' => $request->input('category_id'),
            'service_id' => $service_id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'course_type' => $request->input('course_type'),
            'price' => $request->input('price'),
            'duration_hours' => $request->input('duration_hours'),
            'status' => $request->input('status'),
        ]);

        // Handle cover image upload (store in public disk and create Attachment)
        if ($request->hasFile('cover_image')) {
            $file = $request->file('cover_image');
            $path = $file->store('course_covers', 'public');
            $fileUrl = asset('storage/' . $path);

            $attachment = Attachment::create([
                'user_id' => $teacherId,
                'file_path' => $fileUrl,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            // Link attachment id to course (courses table uses cover_image_id in other code)
            $course->cover_image_id = $attachment->id;
            $course->save();
        }

        // Create availability slots for the course
        foreach ($parsedSlots as $slot) {
            $course->availabilitySlots()->create([
                'teacher_id' => $teacherId,
                'course_id' => $course->id,
                'day_number' => $slot['day_number'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'is_available' => true,
                'is_booked' => false,
            ]);
        }

        // If the course was marked as published, you may want to set a published_at timestamp here.

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully',
            'data' => $course->load('availabilitySlots')
        ], 201);
    }

    // Teacher: Update own course
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:course_categories,id',
            'service_id' => 'required|exists:services,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'course_type' => 'required|in:single,package,subscription',
            'price' => 'nullable|numeric|min:0|max:999999.99',
            'duration_hours' => 'nullable|integer|min:1|max:1000',
            'status' => 'required|in:draft,published',
            'cover_image_id' => 'nullable|exists:attachments,id',
            'available_slots' => 'required|array',
            'available_slots.*.day' => 'required|string|date_format:Y-m-d',
            'available_slots.*.time_start' => 'required|date_format:H:i',
        ]);

        $course = Course::where('teacher_id', $request->user()->id)
            ->findOrFail($id);

        $course->update($request->only([
            'category_id', 'service_id', 'name', 'description',
            'course_type', 'price', 'duration_hours', 'status', 'cover_image_id'
        ]));

        // Update availability slots if provided
        if ($request->has('available_slots')) {
            // Optionally, delete old slots first:
            $course->availability_slots()->delete();

            foreach ($request->available_slots as $slot) {
                $day = "{$slot['day']}";
                $start = "{$slot['time_start']}:00";
                $end = \Carbon\Carbon::parse($start)->addHour()->format('Y-m-d H:i:s');
                $course->availability_slots()->create([
                    'teacher_id' => $request->user()->id,
                    'day' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'is_booked' => false,
                ]);
            }
        }

        $course->load(['teacher', 'category', 'coverImage', 'availability_slots']);

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully',
            'data' => $course
        ]);
    }

    // Teacher: Delete own course
    public function destroy(Request $request, int $id): JsonResponse
    {
        $course = Course::where('teacher_id', $request->user()->id)
            ->findOrFail($id);

        // Check for any confirmed bookings for this course
        $hasConfirmedBookings = Booking::where('course_id', $id)
            ->where('status', 'confirmed') // or whatever status means "paid"
            ->exists();

        if ($hasConfirmedBookings) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete course: students have already booked and confirmed.'
            ], 403);
        }

        // Delete all bookings for this course (if you want)
        Booking::where('course_id', $id)->delete();

        // Delete lessons, then course
        $course->availability_slots()->delete();
        $course->courseLessons()->delete();
        $course->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully'
        ]);
    }

    // Teacher: Get my courses
    public function myCourses(Request $request): JsonResponse
    {
        $courses = Course::with(['category', 'coverImage'])
            ->where('teacher_id', $request->user()->id)
            ->withCount(['countstudents'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $courses
        ]);
    }


    public function listCategories(): JsonResponse
    {
        $categories = \App\Models\CourseCategory::all();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

}