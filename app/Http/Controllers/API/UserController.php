<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Review;
use App\Models\Course;
use App\Models\TeacherInfo;
use App\Models\TeacherTeachClasses;
use App\Models\TeacherSubject;
use App\Models\Attachment;
use App\Models\TeacherServices;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    private function getFileUrl($path)
    {
        return asset('storage/' . $path);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    // select education levels
    public function educationLevels()
    {
        $levels = DB::table('education_levels')
            ->select('id', 'name_en', 'name_ar')
            ->where('status', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $levels
        ]);
    }

    // select classes based on education level
    public function classes($education_level_id)
    {
        $classes = DB::table('classes')
            ->select('id', 'name_en', 'name_ar')
            ->where('education_level_id', $education_level_id)
            ->get();
        return response()->json([
            'success' => true,
            'data' => $classes
        ]);
    }

    public function showProfile(Request $request)
    {
        $user = $request->user();

        $profile = UserProfile::with(['profilePhoto'])
            ->where('user_id', $user->id)
            ->first();

        if (!$profile) {
            // Create profile if doesn't exist
            $profile = UserProfile::create([
                'user_id' => $user->id,
                'language_pref' => 'ar'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $profile->id,
                'bio' => $profile->bio,
                'description' => $profile->description,
                'profile_photo' => $profile->profilePhoto,
                'terms_accepted' => $profile->terms_accepted,
                'verified' => $profile->verified,
                'language_pref' => $profile->language_pref,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]
        ]);
    }

    // store profile

    // Complete Profile
    public function storeProfile(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'role_id'      => 'required|in:3,4', // Must be teacher or student
            'profile_photo' => 'nullable|image|max:2048', // Optional profile photo
            'certificate'   => 'nullable|mimes:pdf,doc,docx|max:5120', // Optional certificate
            'resume'        => 'nullable|mimes:pdf,doc,docx|max:5120', // Optional resume
            'language_pref' => 'nullable|string|max:255', // Optional language preference
            'terms_accepted' => 'required|boolean|in:1', // Must accept terms
            'bio'           => 'nullable|string|max:1000', // Optional bio
            'education_level' => 'nullable|string|max:255', // Optional education level
            'class_id'      => 'nullable|exists:classes,id', // Optional class association
            'subjects'      => 'nullable|array', // Optional subjects array
            'email'         => 'required|string|email|unique:users,email,' . $request->user()->id,
            'phone_number'   => 'required|string|max:15|unique:users,phone_number,' . $request->user()->id,
        ]);

        // Role-specific validation
        $rules = [];
        if ($user->role && $user->role->name_key === 'teacher') {
            $rules['profile_photo'] = 'nullable|image|max:2048';
            $rules['certificate']   = 'nullable|mimes:pdf,doc,docx|max:5120';
            $rules['resume'] = 'nullable|mimes:pdf,doc,docx|max:5120';
        } elseif ($user->role && $user->role->name_key === 'student') {
            $rules['profile_photo'] = 'nullable|image|max:2048';
        }

        $validated = $request->validate($rules);

        // Prepare profile data (exclude file uploads from direct user update)
        $profileData = $request->only(['bio', 'language_pref', 'terms_accepted']);
        $profileData['verified'] = $user->role_id == 3 ? 0 : 1; // Teachers start unverified

        // Create or update user profile
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        // Update user basic info
        $user->update($request->only(['email', 'phone_number']));

        // Handle file uploads and save to attachments table
        try {
            if ($request->hasFile('profile_photo')) {
                $this->saveAttachmentFile($request, 'profile_photo', 'profile_photos', $user, 'profile_picture');
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile photo',
                'error' => $e->getMessage()
            ], 500);
        }

        if ($user->role && $user->role->name_key === 'teacher') {
            try {
                if ($request->hasFile('certificate')) {
                    $this->saveAttachmentFile($request, 'certificate', 'certificates', $user, 'certificate');
                }
                if ($request->hasFile('resume')) {
                    $this->saveAttachmentFile($request, 'resume', 'resumes', $user, 'resume');
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload teacher files',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        $user->refresh();

        if ($user->role_id == 3) {
            // Teacher: return full teacher data
            $user->load([
                'profile',
                'teacherInfo',
                'teacherClasses',
                'teacherSubjects',
                'availableSlots',
                'reviews',
                'attachments',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Profile created successfully',
                'data' => $this->getFullTeacherData($user)
            ]);
        } else {
            // Student: return basic profile data with profile photo from attachments
            $profilePhoto = $user->attachments()
                ->where('attached_to_type', 'profile_picture')
                ->latest()
                ->value('file_path');

            return response()->json([
                'success' => true,
                'message' => 'Profile created successfully',
                'data' => [
                    'id' => $profile->id,
                    'user_id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'bio' => $profile->bio,
                    'language_pref' => $profile->language_pref,
                    'terms_accepted' => $profile->terms_accepted,
                    'verified' => $profile->verified,
                    'profile_photo' => $profilePhoto,
                ]
            ]);
        }
    }

    // update profile
    public function updateProfile(Request $request)
    {
        Log::info('Update Profile Request: ', $request->all());
        $user = $request->user();
        // update role id if frist time
        if ($user->role_id != $request->input('role_id')) {
            $user->role_id = $request->input('role_id');
            $user->save();
        }
        Log::info('User role updated to: ' . $user->role_id);
        $profileData = $request->only(['bio', 'description', 'profile_photo_id', 'language_pref', 'terms_accepted']);
        $profileData['verified'] = $user->role_id == 4 ? 1 : ($request->input('verified', 0));

        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        // If user, teacher, update teacher info, classes, subjects if provided
        if ($user->role_id == 4 || $user->role_id == 3) {
            if ($request->hasAny(['first_name', 'last_name', 'email', 'phone_number'])) {
                $user->update($request->only(['first_name', 'last_name', 'email', 'phone_number']));
            }
        }
        if ($user->role_id == 3) {
            if ($request->hasAny(['bio', 'teach_individual', 'individual_hour_price', 'teach_group', 'group_hour_price', 'max_group_size', 'min_group_size'])) {
                $this->updateTeacherInfo($request);
            }
            if ($request->has('class_ids')) {
                $this->updateTeacherClasses($request);
            }
            if ($request->has('subject_ids')) {
                $this->updateTeacherSubjects($request);
            }
            if ($request->has('services_id')) {
                $this->updateTeacherServices($request);
            }
        }
        // Handle file uploads and save to attachments table
        try {
            if ($request->hasFile('profile_photo')) {
                $this->saveAttachmentFile($request, 'profile_photo', 'profile_photos', $user, 'profile_picture');
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile photo',
                'error' => $e->getMessage()
            ], 500);
        }

        if ($user->role && $user->role->name_key === 'teacher') {
            try {
                if ($request->hasFile('certificate')) {
                    $this->saveAttachmentFile($request, 'certificate', 'certificates', $user, 'certificate');
                }
                if ($request->hasFile('resume')) {
                    $this->saveAttachmentFile($request, 'resume', 'resumes', $user, 'resume');
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload teacher files',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        $user->refresh();
        $profile->load('profilePhoto');

        if ($user->role_id == 3) {
            // Teacher: return full teacher data
            $user->load([
                'profile.profilePhoto:id,file_path',
                'teacherInfo',
                'teacherClasses',
                'teacherSubjects',
                'availableSlots',
                'reviews',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $this->getFullTeacherData($user)
            ]);
        } else {
            // Student: return basic profile data with profile photo from attachments
            $profilePhoto = $user->attachments()
                ->where('attached_to_type', 'profile_picture')
                ->latest()
                ->value('file_path');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'role_id' => $user->role_id,
                    'id' => $profile->id,
                    'user_id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'terms_accepted' => $profile->terms_accepted,
                    'verified' => $profile->verified,
                    'language_pref' => $profile->language_pref,
                    'profile_photo' => $profilePhoto,
                ]
            ]);
        }
    }

    // List all teachers
    public function listTeachers()
    {
        $teachers = User::where('role_id', 3)
            ->with([
                'profile.profilePhoto:id,file_path',
                'teacherInfo',
                'teacherClasses',
                'teacherSubjects',
                'availableSlots',
                'teacherServices',
                'reviews',
            ])
            ->withCount('reviews')
            ->orderByDesc('reviews_count')
            ->get();

        $teachersData = $teachers->map(function ($teacher) {
            $profileImage = $teacher->attachments()->where('user_id', $teacher->id)->get('file_path')->first();
            $teacherResumes = $teacher->attachments()->where('user_id', $teacher->id)->get('file_path')->first();
            $teacherCertificates = $teacher->attachments()->where('user_id', $teacher->id)->get('file_path')->first();

            // Group available slots by day
            $availableTimes = collect($teacher->availableSlots)
                ->groupBy('day_number')
                ->map(function ($slots, $day) {
                    return [
                        'day_number' => $day,
                        'times' => $slots->map(function ($slot) {
                            return [
                                'id' => $slot->id,
                                'time' => \Carbon\Carbon::parse($slot->start_time)->format('g:i A'),
                            ];
                        })->values(),
                    ];
                })->values();

            // Fetch all classes for this teacher
            $classIds = ($teacher->teacherClasses ?? collect())->pluck('class_id')->unique()->toArray();
            $classes = DB::table('classes')
                ->whereIn('id', $classIds)
                ->get();

            // Fetch all levels for these classes
            $levelIds = $classes->pluck('education_level_id')->unique()->toArray();
            $levels = DB::table('education_levels')
                ->whereIn('id', $levelIds)
                ->get();
            // fetch all subjects for this teacher
            $subjectIds = ($teacher->teacherSubjects ?? collect())->pluck('subject_id')->unique()->toArray();
            $subjects = DB::table('subjects')
                ->whereIn('id', $subjectIds)
                ->get();

            // Build teacher_levels (unique levels from classes)
            $teacherLevels = $levels->map(function ($level) use ($teacher) {
                return [
                    'id' => $level->id,
                    'teacher_id' => $teacher->id,
                    'title' => $level->name_ar ?? $level->name_en,
                ];
            });

            // Build teacher_classes with class details and level_id
            $teacherClasses = ($teacher->teacherClasses ?? collect())->map(function ($tc) use ($classes) {
                $class = $classes->where('id', $tc->class_id)->first();
                return [
                    'id' => $tc->id,
                    'teacher_id' => $tc->teacher_id,
                    'title' => $class ? ($class->name_ar ?? $class->name_en) : null,
                    'level_id' => $class ? $class->education_level_id : null,
                ];
            });

            // Build teacher_subjects as before
            $teacherSubjects = ($teacher->teacherSubjects ?? collect())->map(function ($ts) use ($subjects) {
                $subject = $subjects->where('id', $ts->subject_id)->first();
                return [
                    'id' => $ts->id,
                    'teacher_id' => $ts->teacher_id,
                    'title' => $subject ? ($subject->name_ar ?? $subject->name_en) : null,
                ];
            });

            return [
                'id' => $teacher->id,
                'first_name' => $teacher->first_name,
                'last_name' => $teacher->last_name,
                'email' => $teacher->email,
                'phone_number' => $teacher->phone_number,
                'email_verified_at' => $teacher->email_verified_at,
                'role_id' => $teacher->role_id,
                'gender' => $teacher->gender,
                'nationality' => $teacher->nationality,
                'verification_code' => $teacher->verification_code,
                'social_provider' => $teacher->social_provider,
                'social_provider_id' => $teacher->social_provider_id,
                'is_active' => $teacher->is_active,
                'profile_photo' => $profileImage,
                'resume' => $teacherResumes,
                'certificate' => $teacherCertificates,
                'reviews' => $teacher->reviews,
                'rating' => round($teacher->reviews->avg('rating'), 1) ?? 0,
                'bio' => optional($teacher->profile)->bio,
                'total_students' => optional($teacher->teacherInfo)->total_students ?? 0,
                'verified' => (bool) $teacher->verified ?? false,
                'service' => optional($teacher->teacherInfo)->service ?? 'دروس خصوصية',
                'available_times' => $availableTimes,
                'teach_individual' => optional($teacher->teacherInfo)->teach_individual ?? 0,
                'individual_hour_price' => $teacher->teacherInfo->individual_hour_price ?? 0.00,
                'teach_group' => $teacher->teacherInfo->teach_group ?? 0,
                'group_hour_price' => $teacher->teacherInfo->group_hour_price ?? 0.00,
                'max_group_size' => $teacher->teacherInfo->max_group_size ?? 0,
                'min_group_size' => $teacher->teacherInfo->min_group_size ?? 0,
                'teacher_levels' => $teacherLevels,
                'teacher_classes' => $teacherClasses,
                'teacher_subjects' => $teacherSubjects,
                'teacher_services' => ($teacher->teacherServices ?? collect())->pluck('service_id')->values()->all(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $teachersData,
        ]);
    }



    public function teacherDetails($id)
    {
        $role = DB::table('roles')->where('name_key', 'teacher')->value('id');
        $teacher = User::where('role_id', $role)
            ->with([
                'profile',
                'profile.profilePhoto:id,file_path',
                'teacherInfo',
                'teacherClasses',
                'teacherSubjects',
                'teacherServices',
            ])
            ->findOrFail($id, ['id', 'first_name', 'last_name', 'gender', 'nationality']);

        $teacher->reviews = Review::where('reviewed_id', $teacher->id)->get();
        $teacher->courses = Course::with('courses')->where('teacher_id', $teacher->id)->get();

        return response()->json([
            'success' => true,
            'data' => $teacher
        ]);
    }

    public function createOrUpdateTeacherInfo(Request $request)
    {
        $request->validate([
            'bio' => 'nullable|string|max:2000',
            'teach_individual' => 'required|boolean',
            'individual_hour_price' => 'nullable|numeric|min:0',
            'teach_group' => 'required|boolean',
            'group_hour_price' => 'nullable|numeric|min:0',
            'max_group_size' => 'nullable|integer|max:5',
            'min_group_size' => 'nullable|integer|min:1',
            'class_ids' => 'required|array',
            'class_ids.*' => 'exists:classes,id',
            'subject_ids' => 'required|array',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $teacher = $request->user();

        // Update or create TeacherInfo
        $info = TeacherInfo::updateOrCreate(
            ['teacher_id' => $teacher->id],
            $request->only([
                'bio',
                'teach_individual',
                'individual_hour_price',
                'teach_group',
                'group_hour_price',
                'max_group_size',
                'min_group_size'
            ])
        );

        // Sync classes
        TeacherTeachClasses::where('teacher_id', $teacher->id)->delete();
        foreach ($request->class_ids as $class_id) {
            TeacherTeachClasses::create([
                'teacher_id' => $teacher->id,
                'class_id' => $class_id,
            ]);
        }

        // Sync subjects
        TeacherSubject::where('teacher_id', $teacher->id)->delete();
        foreach ($request->subject_ids as $subject_id) {
            TeacherSubject::create([
                'teacher_id' => $teacher->id,
                'subject_id' => $subject_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Teacher info, classes, and subjects updated successfully',
            'data' => [
                'info' => $info,
                'classes' => $request->class_ids,
                'subjects' => $request->subject_ids,
            ]
        ]);
    }

    // Update or create teacher info only
    public function updateTeacherInfo(Request $request)
    {
        $request->validate([
            'bio' => 'nullable|string|max:2000',
            'teach_individual' => 'required|boolean',
            'individual_hour_price' => 'nullable|numeric|min:0',
            'teach_group' => 'required|boolean',
            'group_hour_price' => 'nullable|numeric|min:0',
            'max_group_size' => 'nullable|integer|max:5',
            'min_group_size' => 'nullable|integer|min:1',
        ]);

        $teacher = $request->user();
        $info = TeacherInfo::updateOrCreate(
            ['teacher_id' => $teacher->id],
            $request->only([
                'bio',
                'teach_individual',
                'individual_hour_price',
                'teach_group',
                'group_hour_price',
                'max_group_size',
                'min_group_size'
            ])
        );

        return response()->json([
            'success' => true,
            'data' => $info
        ]);
    }

    // Update or create teacher classes only
    public function updateTeacherClasses(Request $request)
    {
        $request->validate([
            'class_ids' => 'required|array',
            'class_ids.*' => 'exists:classes,id',
        ]);

        $teacher = $request->user();
        TeacherTeachClasses::where('teacher_id', $teacher->id)->delete();
        foreach ($request->class_ids as $class_id) {
            TeacherTeachClasses::create([
                'teacher_id' => $teacher->id,
                'class_id' => $class_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $request->class_ids
        ]);
    }

    // Update or create teacher services only
    public function updateTeacherServices(Request $request)
    {
        $request->validate([
            'services_id' => 'required|array',
            'services_id.*' => 'exists:services,id',
        ]);

        $teacher = $request->user();
        TeacherServices::where('teacher_id', $teacher->id)->delete();

        try {
            foreach ($request->services_id as $service_id) {
                TeacherServices::create([
                    'teacher_id' => $teacher->id,
                    'service_id' => $service_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('TeacherServices save error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $request->services_id
        ]);
    }

    // Update or create teacher subjects only
    public function updateTeacherSubjects(Request $request)
    {
        $request->validate([
            'subject_ids' => 'required|array',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $teacher = $request->user();
        TeacherSubject::where('teacher_id', $teacher->id)->delete();
        foreach ($request->subject_ids as $subject_id) {
            TeacherSubject::create([
                'teacher_id' => $teacher->id,
                'subject_id' => $subject_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $request->subject_ids
        ]);
    }

    public function getFullTeacherData(User $teacher)
    {
        // Get latest attachments
        $profilePhoto = $teacher->attachments()
            ->where('attached_to_type', 'profile_picture')
            ->latest()
            ->value('file_path');

        $resume = $teacher->attachments()
            ->where('attached_to_type', 'resume')
            ->latest()
            ->value('file_path');

        $certificate = $teacher->attachments()
            ->where('attached_to_type', 'certificate')
            ->latest()
            ->value('file_path');

        // Get earnings data
        $earnings = DB::table('bookings')
            ->where('teacher_id', $teacher->id)
            ->where('status', 'completed')
            ->select(
                DB::raw('COUNT(*) as total_lessons'),
                DB::raw('SUM(total_amount) as total_earnings'),
                DB::raw('SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END) as today_earnings'),
                DB::raw('SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) THEN total_amount ELSE 0 END) as month_earnings')
            )
            ->first();

        // Current lessons count
        $currentLessons = DB::table('bookings')
            ->where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->count();

        // Build profile data array
        $profileData = [
            'id' => optional($teacher->profile)->id,
            'user_id' => $teacher->id,
            'profile_photo' => $profilePhoto ?? null,
            'resume' => $resume ?? null,
            'certificate' => $certificate ?? null,
            'reviews' => $teacher->reviews ?? [],
            'rating' => round($teacher->reviews->avg('rating') ?? 0, 1),
            'bio' => optional($teacher->profile)->bio,
            'total_students' => optional($teacher->teacherInfo)->total_students ?? 0,
            'verified' => (bool) optional($teacher->profile)->verified,
            'is_active' => (bool) $teacher->is_active,
            'service' => optional($teacher->teacherInfo)->service ?? null,
            'teach_individual' => (bool) optional($teacher->teacherInfo)->teach_individual,
            'individual_hour_price' => (float) optional($teacher->teacherInfo)->individual_hour_price ?? 0,
            'teach_group' => (bool) optional($teacher->teacherInfo)->teach_group,
            'group_hour_price' => (float) optional($teacher->teacherInfo)->group_hour_price ?? 0,
            'max_group_size' => (int) optional($teacher->teacherInfo)->max_group_size ?? 0,
            'min_group_size' => (int) optional($teacher->teacherInfo)->min_group_size ?? 0,

            // Add earnings data
            'total_lessons' => (int) ($earnings->total_lessons ?? 0),
            'current_lessons' => (int) $currentLessons,
            'current_balance' => (float) optional($teacher->wallet)->balance ?? 0,
            'todayEarnings' => (float) ($earnings->today_earnings ?? 0),
            'monthEarnings' => (float) ($earnings->month_earnings ?? 0),
            'totalEarnings' => (float) ($earnings->total_earnings ?? 0),

            // Add teacher services (array of service IDs)
            'teacher_services' => optional($teacher->teacherServices)->pluck('service_id')->toArray() ?? [],
        ];

        // Return final structure
        return [
            'id' => $teacher->id,
            'first_name' => $teacher->first_name,
            'last_name' => $teacher->last_name,
            'email' => $teacher->email,
            'phone_number' => $teacher->phone_number,
            'email_verified_at' => $teacher->email_verified_at,
            'role_id' => $teacher->role_id,
            'gender' => $teacher->gender,
            'nationality' => $teacher->nationality,
            'verification_code' => $teacher->verification_code,
            'social_provider' => $teacher->social_provider,
            'social_provider_id' => $teacher->social_provider_id,
            'profile' => $profileData
        ];
    }


    private function handleFileUpload(Request $request, string $key, string $folder, User $user)
    {
        if (!$request->hasFile($key)) {
            return null;
        }

        $file = $request->file($key);
        $path = $file->store($folder, 'public'); // Saves to storage/app/public/$folder

        $attachment = \App\Models\Attachment::create([
            'user_id'   => $user->id,
            'file_path' => asset('storage/' . $path), // Full URL for mobile apps
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
        ]);

        return $attachment->file_path;
    }


    private function saveAttachmentFile(Request $request, string $key, string $folder, User $user, string $attachedToType): ?string
    {
        if (!$request->hasFile($key)) {
            return null;
        }

        try {
            $file = $request->file($key);
            $path = $file->store($folder, 'public'); // Saves to storage/app/public/$folder
            $fileUrl = asset('storage/' . $path);

            // Create attachment record in database
            $attachment = Attachment::create([
                'user_id'           => $user->id,
                'file_path'         => $fileUrl,
                'file_name'         => $file->getClientOriginalName(),
                'file_type'         => $file->getClientMimeType(),
                'file_size'         => $file->getSize(),
                'attached_to_type'      => $attachedToType, // Store as profile-related attachment
            ]);

            Log::info('File uploaded and attachment created', [
                'user_id' => $user->id,
                'file_name' => $file->getClientOriginalName(),
                'attachment_id' => $attachment->id,
                'file_path' => $fileUrl
            ]);

            return $fileUrl;
        } catch (\Exception $e) {
            Log::error('Failed to save attachment file', [
                'user_id' => $user->id,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
