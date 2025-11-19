<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Course;
use App\Models\Services;
use App\Models\Sessions;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    /**
     * Student Dashboard - Main Page
     */
    public function dashboard()
    {
        // Get main services for students
        $services = Services::where('status', 1)
            ->where('role_id', 4) // Student services
            ->get();

        // Get featured teachers (top rated, limit 5)
        $featuredTeachers = User::where('role_id', 3)
            ->with([
                'profile.profilePhoto',
                'teacherInfo',
                'teacherClasses',
                'teacherSubjects',
                'availableSlots',
                'reviews',
            ])
            ->withCount('reviews')
            ->orderByDesc('reviews_count')
            ->limit(5)
            ->get()
            ->map(fn($teacher) => $this->formatTeacherData($teacher));

        // Get featured courses (latest or popular)
        $featuredCourses = Course::with(['teacher', 'teacher.profile.profilePhoto'])
            ->where('status', 1)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('student.dashboard', compact('services', 'featuredTeachers', 'featuredCourses'));
    }

    /**
     * List all teachers with filters
     */
    public function teachers(Request $request)
    {
        $private_lessons_service = Services::where('key_name', 'private_lessons')
            ->orWhere('name_en', 'LIKE', '%private lessons%')
            ->pluck('id');
        
        $query = User::where('role_id', 3)
            ->with([
                'profile.profilePhoto',
                'teacherInfo',
                'teacherClasses',
                'teacherSubjects',
                'teacherServices',
                'availableSlots',
                'reviews',
            ])
            ->whereHas('teacherServices', function ($q) use ($private_lessons_service) {
                $q->whereIn('service_id', $private_lessons_service);
            })
            ->withCount('reviews');

        // Filter by service
        if ($request->has('service_id') && $request->service_id) {
            $query->whereHas('teacherServices', function ($q) use ($request) {
                $q->where('service_id', $request->service_id);
            });
        }

        // Filter by subject
        if ($request->has('subject_id') && $request->subject_id) {
            $query->whereHas('teacherSubjects', function ($q) use ($request) {
                $q->where('subject_id', $request->subject_id);
            });
        }

        // Filter by education level
        if ($request->has('education_level_id') && $request->education_level_id) {
            $query->whereHas('teacherClasses', function ($q) use ($request) {
                $q->whereHas('class', function ($q2) use ($request) {
                    $q2->where('education_level_id', $request->education_level_id);
                });
            });
        }

        // Filter by class
        if ($request->has('class_id') && $request->class_id) {
            $query->whereHas('teacherClasses', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->whereHas('teacherInfo', function ($q) use ($request) {
                $q->where('individual_hour_price', '>=', $request->min_price);
            });
        }
        if ($request->has('max_price')) {
            $query->whereHas('teacherInfo', function ($q) use ($request) {
                $q->where('individual_hour_price', '<=', $request->max_price);
            });
        }

        // Filter by gender
        if ($request->has('gender') && $request->gender) {
            $query->where('gender', $request->gender);
        }

        // Filter by nationality
        if ($request->has('nationality') && $request->nationality) {
            $query->where('nationality', $request->nationality);
        }

        // Filter by rating
        if ($request->has('min_rating') && $request->min_rating) {
            $query->whereHas('reviews', function ($q) use ($request) {
                $q->select('reviewed_id')
                  ->groupBy('reviewed_id')
                  ->havingRaw('AVG(rating) >= ?', [$request->min_rating]);
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'rating');
        switch ($sortBy) {
            case 'price_low':
                $query->join('teacher_info', 'users.id', '=', 'teacher_info.teacher_id')
                    ->select('users.*')
                    ->orderBy('teacher_info.individual_hour_price', 'asc');
                break;
            case 'price_high':
                $query->join('teacher_info', 'users.id', '=', 'teacher_info.teacher_id')
                    ->select('users.*')
                    ->orderBy('teacher_info.individual_hour_price', 'desc');
                break;
            case 'newest':
                $query->orderBy('users.created_at', 'desc');
                break;
            case 'rating':
            default:
                $query->orderByDesc('reviews_count');
                break;
        }

        $teachers = $query->paginate(12)->through(fn($teacher) => $this->formatTeacherData($teacher));

        // Get filter options
        $services = Services::where('status', 1)->where('role_id', 3)->get();
        $educationLevels = DB::table('education_levels')->where('status', true)->get();
        $subjects = Subject::where('status', 1)->get();

        return view('student.teachers.index', compact('teachers', 'services', 'educationLevels', 'subjects'));
    }

    /**
     * Show teacher profile
     */
    public function showTeacher($id)
    {
        $teacher = User::where('role_id', 3)
            ->with([
                'profile.profilePhoto',
                'teacherInfo',
                'teacherClasses',
                'teacherSubjects',
                'teacherServices',
                'availableSlots',
                'reviews.reviewer',
                'courses',
            ])
            ->findOrFail($id);

        $teacherData = $this->formatTeacherData($teacher);

        // Get available slots grouped by day
        $availableSlots = $teacher->availableSlots()
            ->orderBy('day_number')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_number');

        return view('student.teachers.show', compact('teacherData', 'availableSlots'));
    }

    /**
     * List all courses with filters
     */
    public function courses(Request $request)
    {
        $query = Course::with(['teacher', 'teacher.profile.profilePhoto'])
            ->where('status', 'published');

        // Filter by service
        if ($request->has('service_id') && $request->service_id) {
            $query->where('service_id', $request->service_id);
        }

        // Filter by subject
        if ($request->has('subject_id') && $request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by price
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'latest');
        switch ($sortBy) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'popular':
                $query->orderBy('students_count', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $courses = $query->paginate(12);

        $services = Services::where('status', 1)->get();
        $subjects = Subject::where('status', 1)->get();

        return view('student.courses.index', compact('courses', 'services', 'subjects'));
    }

    /**
     * Show course details
     */
    public function showCourse($id)
    {
        $course = Course::with([
            'teacher',
            'teacher.profile.profilePhoto',
            'teacher.reviews',
            'courseLessons',
            'enrollments',
        ])->findOrFail($id);

        return view('student.courses.show', compact('course'));
    }

    /**
     * Language learning page
     */
    public function languages(Request $request)
    {
        // Get language learning service
        $languageService = Services::where('name_key', 'language_learning')
            ->orWhere('name_en', 'LIKE', '%language%')
            ->first();

        $query = User::where('role_id', 3)
            ->with([
                'profile.profilePhoto',
                'teacherInfo',
                'teacherSubjects',
                'availableSlots',
                'reviews',
            ])
            ->withCount('reviews');

        if ($languageService) {
            $query->whereHas('teacherServices', function ($q) use ($languageService) {
                $q->where('service_id', $languageService->id);
            });
        }

        // Filter by language (subject)
        if ($request->has('subject_id') && $request->subject_id) {
            $query->whereHas('teacherSubjects', function ($q) use ($request) {
                $q->where('subject_id', $request->subject_id);
            });
        }

        $teachers = $query->orderByDesc('reviews_count')
            ->paginate(12)
            ->through(fn($teacher) => $this->formatTeacherData($teacher));

        $languages = Subject::where('service_id', $languageService->id ?? null)
            ->where('status', 1)
            ->get();

        return view('student.languages.index', compact('teachers', 'languages'));
    }

    /**
     * Format teacher data for views
     * This is the same method from your API controller
     */
    private function formatTeacherData(User $teacher)
    {
        $profileImage = $teacher->attachments()
            ->where('user_id', $teacher->id)
            ->first();

        $availableTimes = collect($teacher->availableSlots)
            ->groupBy('day_number')
            ->map(function ($slots, $day) {
                return [
                    'day_number' => $day,
                    'times' => $slots->map(function ($slot) {
                        return [
                            'id' => $slot->id,
                            'time' => \Carbon\Carbon::parse($slot->start_time)->format('g:i A'),
                            'start_time' => \Carbon\Carbon::parse($slot->start_time)->format('H:i'),
                        ];
                    })->values(),
                ];
            })->values();

        $classIds = ($teacher->teacherClasses ?? collect())->pluck('class_id')->unique()->toArray();
        $classes = DB::table('classes')->whereIn('id', $classIds)->get();
        $levelIds = $classes->pluck('education_level_id')->unique()->toArray();
        $levels = DB::table('education_levels')->whereIn('id', $levelIds)->get();
        $subjectIds = ($teacher->teacherSubjects ?? collect())->pluck('subject_id')->unique()->toArray();
        $subjects = DB::table('subjects')->whereIn('id', $subjectIds)->get();

        $teacherLevels = $levels->map(function ($level) use ($teacher) {
            return [
                'id' => $level->id,
                'teacher_id' => $teacher->id,
                'title' => app()->getLocale() == 'ar' ? $level->name_ar : $level->name_en,
            ];
        });

        $teacherClasses = ($teacher->teacherClasses ?? collect())->map(function ($tc) use ($classes) {
            $class = $classes->where('id', $tc->class_id)->first();
            return [
                'id' => $tc->id,
                'teacher_id' => $tc->teacher_id,
                'title' => $class ? (app()->getLocale() == 'ar' ? $class->name_ar : $class->name_en) : null,
                'level_id' => $class ? $class->education_level_id : null,
            ];
        });

        $teacherSubjects = ($teacher->teacherSubjects ?? collect())->map(function ($ts) use ($subjects) {
            $subject = $subjects->where('id', $ts->subject_id)->first();
            return [
                'id' => $ts->id,
                'teacher_id' => $ts->teacher_id,
                'title' => $subject ? (app()->getLocale() == 'ar' ? $subject->name_ar : $subject->name_en) : null,
            ];
        });

        return [
            'id' => $teacher->id,
            'first_name' => $teacher->first_name,
            'last_name' => $teacher->last_name,
            'email' => $teacher->email,
            'phone_number' => $teacher->phone_number,
            'gender' => $teacher->gender,
            'nationality' => $teacher->nationality,
            'profile_photo' => $profileImage,
            'reviews' => $teacher->reviews,
            'rating' => round($teacher->reviews->avg('rating'), 1) ?? 0,
            'reviews_count' => $teacher->reviews->count(),
            'bio' => optional($teacher->profile)->bio,
            'verified' => (bool) optional($teacher->profile)->verified,
            'available_times' => $availableTimes,
            'teach_individual' => optional($teacher->teacherInfo)->teach_individual ?? 0,
            'individual_hour_price' => optional($teacher->teacherInfo)->individual_hour_price ?? 0.00,
            'teach_group' => optional($teacher->teacherInfo)->teach_group ?? 0,
            'group_hour_price' => optional($teacher->teacherInfo)->group_hour_price ?? 0.00,
            'max_group_size' => optional($teacher->teacherInfo)->max_group_size ?? 0,
            'min_group_size' => optional($teacher->teacherInfo)->min_group_size ?? 0,
            'teacher_levels' => $teacherLevels,
            'teacher_classes' => $teacherClasses,
            'teacher_subjects' => $teacherSubjects,
            'teacher_services' => $teacher->teacherServices,
        ];
    }

    /**
     * Display student calendar with all sessions
     */
    public function calendar(Request $request)
    {
        $student = Auth::user();
        
        // Get month and year from request or use current
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);
        
        // Create Carbon instance for the selected month
        $date = Carbon::createFromDate($year, $month, 1);
        
        // Get all sessions for this student
        $sessions = Sessions::where('student_id', $student->id)
            ->whereYear('session_date', $year)
            ->whereMonth('session_date', $month)
            ->with(['teacher', 'booking'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();
        
        // Group sessions by date for calendar view
        $sessionsByDate = $sessions->groupBy(function($session) {
            return Carbon::parse($session->session_date)->format('Y-m-d');
        });
        
        // Get upcoming sessions (next 5)
        $upcomingSessions = Sessions::where('student_id', $student->id)
            ->where('session_date', '>=', now()->toDateString())
            ->where('status', '!=', 'completed')
            ->with(['teacher', 'booking'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();
        
        // Get past sessions (last 5)
        $pastSessions = Sessions::where('student_id', $student->id)
            ->where(function($query) {
                $query->where('session_date', '<', now()->toDateString())
                      ->orWhere('status', 'completed');
            })
            ->with(['teacher', 'booking'])
            ->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->limit(5)
            ->get();
        
        // Calendar statistics
        $stats = [
            'total_sessions' => Sessions::where('student_id', $student->id)->count(),
            'completed_sessions' => Sessions::where('student_id', $student->id)->where('status', 'completed')->count(),
            'upcoming_sessions' => Sessions::where('student_id', $student->id)
                ->where('session_date', '>=', now()->toDateString())
                ->where('status', '!=', 'completed')
                ->count(),
            'cancelled_sessions' => Sessions::where('student_id', $student->id)->where('status', 'cancelled')->count(),
        ];
        
        return view('student.calendar', compact(
            'sessions',
            'sessionsByDate',
            'upcomingSessions',
            'pastSessions',
            'date',
            'month',
            'year',
            'stats'
        ));
    }
    
    /**
     * Show session details
     */
    public function sessionDetails($id)
    {
        $student = Auth::user();
        
        $session = Sessions::where('student_id', $student->id)
            ->where('id', $id)
            ->with(['teacher', 'booking', 'student'])
            ->firstOrFail();
        
        return view('student.session-details', compact('session'));
    }
}

