<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Booking;
use App\Models\Services;

class CourseController extends Controller
{
    // Student: Browse all published courses
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

        // Enroll student
        DB::table('course_student')->insert([
            'course_id' => $id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

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
    public function store(Request $request): JsonResponse
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
            'available_slots.*.day' => 'required|date_format:Y-m-d',
            'available_slots.*.time_start' => 'required|date_format:H:i',
        ]);

        $course = Course::create([
            'teacher_id' => $request->user()->id,
            'category_id' => $request->input('category_id'),
            'service_id' => $request->input('service_id'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'course_type' => $request->input('course_type'),
            'price' => $request->input('price'),
            'duration_hours' => $request->input('duration_hours'),
            'status' => $request->input('status'),
            'cover_image_id' => $request->input('cover_image_id'),
        ]);

        // Save availability slots
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

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully',
            'data' => $course->load('availability_slots')
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

}