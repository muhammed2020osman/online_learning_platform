<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Subject;
use App\Models\EducationLevel;
use App\Models\ClassModel;
use App\Models\Services;
use App\Models\Enrollment;
use App\Models\Sessions;
use App\Models\User;
use App\Models\Attachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// use App\Notifications\YourNotificationClass; // Replace with your actual notification class

class TeacherCourseController extends Controller
{
    /**
     * Display a listing of teacher's courses
     */
    public function index(Request $request)
    {
        $teacher = Auth::user();
        
        $query = Course::where('teacher_id', $teacher->id)
            ->with(['category', 'subject', 'educationLevel', 'classLevel', 'service', 'coverImage']);

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Filter by course type
        if ($request->has('course_type') && !empty($request->course_type)) {
            $query->where('course_type', $request->course_type);
        }

        // Filter by category
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        $courses = $query->paginate(12);

        // Get filter options
        $categories = CourseCategory::all();
        $statuses = ['active', 'inactive', 'draft', 'completed'];
        $courseTypes = ['individual', 'group'];

        // Get statistics
        $stats = [
            'total_courses' => Course::where('teacher_id', $teacher->id)->count(),
            'active_courses' => Course::where('teacher_id', $teacher->id)->where('status', 'active')->count(),
            'total_students' => Enrollment::whereHas('course', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })->distinct('student_id')->count(),
            'total_sessions' => Sessions::where('teacher_id', $teacher->id)->count(),
        ];

        return view('teacher.courses.index', compact('courses', 'categories', 'statuses', 'courseTypes', 'stats'));
    }

    /**
     * Show the form for creating a new course
     */
    public function create()
    {
        $categories = CourseCategory::all();
        $subjects = Subject::all();
        $educationLevels = EducationLevel::all();
        $classLevels = ClassModel::all();
        $services = Services::all();

        return view('teacher.courses.create', compact(
            'categories',
            'subjects',
            'educationLevels',
            'classLevels',
            'services'
        ));
    }

    /**
     * Store a newly created course in storage
     */
    public function store(Request $request)
    {
        $teacher = Auth::user();

        $validated = $request->validate([
            'category_id' => 'required|exists:course_categories,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'education_level_id' => 'nullable|exists:education_levels,id',
            'class_id' => 'nullable|exists:classes,id',
            'service_id' => 'nullable|exists:services,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'course_type' => 'required|in:individual,group',
            'price' => 'required|numeric|min:0',
            'duration_hours' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive,draft',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5048',
        ]);
        
        try {
            DB::beginTransaction();

            $validated['teacher_id'] = $teacher->id;
            $course = Course::create($validated);

            // Handle cover image upload
            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('images', $filename, 'public');

                Attachment::create([
                    'attached_to_type' => Course::class,
                    'attached_to_id' => $course->id,
                    'file_name' => $filename,
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }

            DB::commit();

            return redirect()->route('teacher.courses.index')
                ->with('success', app()->getLocale() == 'ar' ? 'تم إنشاء الدورة بنجاح' : 'Course created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating course: ' . $e->getMessage());
            return redirect()->back()
                ->withInput()
                ->with('error', __('courses.create_failed'));
        }
    }

    /**
     * Display the specified course with enrolled students and sessions
     */
    public function show($id)
    {
        $teacher = Auth::user();
        $course = Course::where('teacher_id', $teacher->id)
            ->with(['category', 'subject', 'educationLevel', 'classLevel', 'service', 'coverImage'])
            ->findOrFail($id);

        // Get enrolled students
        $enrollments = Enrollment::where('course_id', $course->id)
            ->with(['student', 'sessions'])
            ->paginate(10, ['*'], 'students_page');

        // Get course statistics
        $stats = [
            'total_enrollments' => Enrollment::where('course_id', $course->id)->count(),
            'active_enrollments' => Enrollment::where('course_id', $course->id)->where('status', 'active')->count(),
            'completed_enrollments' => Enrollment::where('course_id', $course->id)->where('status', 'completed')->count(),
            'total_sessions' => Sessions::whereHas('booking', function($q) use ($course) {
                $q->where('course_id', $course->id);
            })->count(),
            'completed_sessions' => Sessions::whereHas('booking', function($q) use ($course) {
                $q->where('course_id', $course->id);
            })->where('status', Sessions::STATUS_COMPLETED)->count(),
        ];

        return view('teacher.courses.show', compact('course', 'enrollments', 'stats'));
    }

    /**
     * Show the form for editing the specified course
     */
    public function edit($id)
    {
        $teacher = Auth::user();
        $course = Course::where('teacher_id', $teacher->id)->findOrFail($id);

        $categories = CourseCategory::all();
        $subjects = Subject::all();
        $educationLevels = EducationLevel::all();
        $classLevels = ClassModel::all();
        $services = Services::all();

        return view('teacher.courses.edit', compact(
            'course',
            'categories',
            'subjects',
            'educationLevels',
            'classLevels',
            'services'
        ));
    }

    /**
     * Update the specified course in storage
     */
    public function update(Request $request, $id)
    {
        $teacher = Auth::user();
        $course = Course::where('teacher_id', $teacher->id)->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'required|exists:course_categories,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'education_level_id' => 'nullable|exists:education_levels,id',
            'class_id' => 'nullable|exists:classes,id',
            'service_id' => 'nullable|exists:services,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'course_type' => 'required|in:individual,group',
            'price' => 'required|numeric|min:0',
            'duration_hours' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive,draft',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            DB::beginTransaction();

            // Track changes for notification
            $changes = [];
            foreach (['name', 'description', 'price', 'duration_hours', 'status'] as $field) {
                if ($course->$field != $validated[$field]) {
                    $changes[$field] = [
                        'old' => $course->$field,
                        'new' => $validated[$field]
                    ];
                }
            }

            $course->update($validated);

            // Handle cover image upload
            if ($request->hasFile('cover_image')) {
                // Delete old image if exists
                if ($course->coverImage) {
                    Storage::disk('public')->delete($course->coverImage->file_path);
                    $course->coverImage->delete();
                }

                $file = $request->file('cover_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('courses/covers', $filename, 'public');

                Attachment::create([
                    'attached_to_type' => Course::class,
                    'attached_to_id' => $course->id,
                    'file_name' => $filename,
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }

            // Notify enrolled students about course updates
            if (!empty($changes)) {
                $enrolledStudents = Enrollment::where('course_id', $course->id)
                    ->where('status', 'active')
                    ->with('student')
                    ->get();

                foreach ($enrolledStudents as $enrollment) {
                    if ($enrollment->student) {
                        // TODO: Replace with your actual notification class
                        // $enrollment->student->notify(new CourseUpdatedNotification($course, $changes));
                        
                        // Example of what data you might pass:
                        // $notificationData = [
                        //     'course_id' => $course->id,
                        //     'course_name' => $course->name,
                        //     'changes' => $changes,
                        //     'message' => 'The course "' . $course->name . '" has been updated.',
                        //     'url' => route('student.courses.show', $course->id)
                        // ];
                        // $enrollment->student->notify(new YourNotificationClass($notificationData));
                    }
                }

                Log::info('Course updated and students notified', [
                    'course_id' => $course->id,
                    'changes' => $changes,
                    'students_notified' => $enrolledStudents->count()
                ]);
            }

            DB::commit();

            return redirect()->route('teacher.courses.show', $course->id)
                ->with('success', __('courses.updated_successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating course: ' . $e->getMessage());
            return redirect()->back()
                ->withInput()
                ->with('error', __('courses.update_failed'));
        }
    }

    /**
     * Remove the specified course from storage
     */
    public function destroy($id)
    {
        $teacher = Auth::user();
        $course = Course::where('teacher_id', $teacher->id)->findOrFail($id);

        try {
            // Check if course has active enrollments
            $activeEnrollments = Enrollment::where('course_id', $course->id)
                ->where('status', 'active')
                ->count();

            if ($activeEnrollments > 0) {
                return redirect()->back()
                    ->with('error', __('courses.cannot_delete_with_active_enrollments'));
            }

            // Delete cover image if exists
            if ($course->coverImage) {
                Storage::disk('public')->delete($course->coverImage->file_path);
                $course->coverImage->delete();
            }

            $course->delete();

            return redirect()->route('teacher.courses.index')
                ->with('success', __('courses.deleted_successfully'));

        } catch (\Exception $e) {
            Log::error('Error deleting course: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('courses.delete_failed'));
        }
    }

    /**
     * Display enrolled students for a specific course
     */
    public function students($courseId)
    {
        $teacher = Auth::user();
        $course = Course::where('teacher_id', $teacher->id)->findOrFail($courseId);

        $enrollments = Enrollment::where('course_id', $course->id)
            ->with(['student', 'sessions'])
            ->paginate(15);

        return view('teacher.courses.students', compact('course', 'enrollments'));
    }

    /**
     * Display sessions for a specific course
     */
    public function sessions(Request $request, $courseId)
    {
        $teacher = Auth::user();
        $course = Course::where('teacher_id', $teacher->id)->findOrFail($courseId);

        $query = Sessions::whereHas('booking', function($q) use ($course) {
            $q->where('course_id', $course->id);
        })->with(['student', 'booking']);

        // Filter by status
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->where('session_date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->where('session_date', '<=', $request->date_to);
        }

        $sessions = $query->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(15);

        $sessionStatuses = Sessions::getStatuses();

        return view('teacher.courses.sessions', compact('course', 'sessions', 'sessionStatuses'));
    }

    /**
     * Start a session
     */
    public function startSession($sessionId)
    {
        $teacher = Auth::user();
        $session = Sessions::where('teacher_id', $teacher->id)->findOrFail($sessionId);

        if (!$session->can_start) {
            return redirect()->back()
                ->with('error', __('sessions.cannot_start_session'));
        }

        try {
            // Create Zoom meeting if not exists
            if (!$session->meeting_id) {
                $session->createMeeting();
            }

            $session->start();

            // Notify student that session has started
            if ($session->student) {
                // TODO: Replace with your actual notification class
                // $notificationData = [
                //     'session_id' => $session->id,
                //     'course_name' => $session->booking->course->name ?? 'Session',
                //     'message' => 'Your session has started. Please join now.',
                //     'join_url' => $session->join_url,
                // ];
                // $session->student->notify(new SessionStartedNotification($notificationData));
            }

            return redirect()->back()
                ->with('success', __('sessions.session_started'))
                ->with('host_url', $session->host_url);

        } catch (\Exception $e) {
            Log::error('Error starting session: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('sessions.start_failed'));
        }
    }

    /**
     * End a session
     */
    public function endSession($sessionId)
    {
        $teacher = Auth::user();
        $session = Sessions::where('teacher_id', $teacher->id)->findOrFail($sessionId);

        if (!$session->can_end) {
            return redirect()->back()
                ->with('error', __('sessions.cannot_end_session'));
        }

        try {
            $session->end();

            // Notify student that session has ended
            if ($session->student) {
                // TODO: Replace with your actual notification class
                // $notificationData = [
                //     'session_id' => $session->id,
                //     'course_name' => $session->booking->course->name ?? 'Session',
                //     'message' => 'Your session has been completed.',
                // ];
                // $session->student->notify(new SessionCompletedNotification($notificationData));
            }

            return redirect()->back()
                ->with('success', __('sessions.session_ended'));

        } catch (\Exception $e) {
            Log::error('Error ending session: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('sessions.end_failed'));
        }
    }

    /**
     * Cancel a session
     */
    public function cancelSession(Request $request, $sessionId)
    {
        $teacher = Auth::user();
        $session = Sessions::where('teacher_id', $teacher->id)->findOrFail($sessionId);

        $request->validate([
            'cancellation_reason' => 'required|string|max:500'
        ]);

        if (!$session->can_cancel) {
            return redirect()->back()
                ->with('error', __('sessions.cannot_cancel_session'));
        }

        try {
            $session->cancel($request->cancellation_reason);

            // Notify student about cancellation
            if ($session->student) {
                // TODO: Replace with your actual notification class
                // $notificationData = [
                //     'session_id' => $session->id,
                //     'course_name' => $session->booking->course->name ?? 'Session',
                //     'reason' => $request->cancellation_reason,
                //     'message' => 'Your session has been cancelled.',
                // ];
                // $session->student->notify(new SessionCancelledNotification($notificationData));
            }

            return redirect()->back()
                ->with('success', __('sessions.session_cancelled'));

        } catch (\Exception $e) {
            Log::error('Error cancelling session: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('sessions.cancel_failed'));
        }
    }

    /**
     * Update course status
     */
    public function updateStatus(Request $request, $id)
    {
        $teacher = Auth::user();
        $course = Course::where('teacher_id', $teacher->id)->findOrFail($id);

        $request->validate([
            'status' => 'required|in:active,inactive,draft,completed'
        ]);

        try {
            $oldStatus = $course->status;
            $course->update(['status' => $request->status]);

            // Notify enrolled students about status change
            if ($oldStatus !== $request->status) {
                $enrolledStudents = Enrollment::where('course_id', $course->id)
                    ->where('status', 'active')
                    ->with('student')
                    ->get();

                foreach ($enrolledStudents as $enrollment) {
                    if ($enrollment->student) {
                        // TODO: Replace with your actual notification class
                        // $notificationData = [
                        //     'course_id' => $course->id,
                        //     'course_name' => $course->name,
                        //     'old_status' => $oldStatus,
                        //     'new_status' => $request->status,
                        //     'message' => 'Course status has been changed from ' . $oldStatus . ' to ' . $request->status,
                        // ];
                        // $enrollment->student->notify(new CourseStatusChangedNotification($notificationData));
                    }
                }
            }

            return redirect()->back()
                ->with('success', __('courses.status_updated'));

        } catch (\Exception $e) {
            Log::error('Error updating course status: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('courses.status_update_failed'));
        }
    }

    /**
     * Add notes to a session
     */
    public function addSessionNotes(Request $request, $sessionId)
    {
        $teacher = Auth::user();
        $session = Sessions::where('teacher_id', $teacher->id)->findOrFail($sessionId);

        $request->validate([
            'notes' => 'required|string|max:2000'
        ]);

        try {
            $session->addTeacherNotes($request->notes);

            return redirect()->back()
                ->with('success', __('sessions.notes_added'));

        } catch (\Exception $e) {
            Log::error('Error adding session notes: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('sessions.notes_failed'));
        }
    }

    /**
     * Set homework for a session
     */
    public function setSessionHomework(Request $request, $sessionId)
    {
        $teacher = Auth::user();
        $session = Sessions::where('teacher_id', $teacher->id)->findOrFail($sessionId);

        $request->validate([
            'homework' => 'required|string|max:2000'
        ]);

        try {
            $session->setHomework($request->homework);

            // Notify student about new homework
            if ($session->student) {
                // TODO: Replace with your actual notification class
                // $notificationData = [
                //     'session_id' => $session->id,
                //     'course_name' => $session->booking->course->name ?? 'Session',
                //     'homework' => $request->homework,
                //     'message' => 'New homework has been assigned.',
                // ];
                // $session->student->notify(new HomeworkAssignedNotification($notificationData));
            }

            return redirect()->back()
                ->with('success', __('sessions.homework_set'));

        } catch (\Exception $e) {
            Log::error('Error setting homework: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('sessions.homework_failed'));
        }
    }
}