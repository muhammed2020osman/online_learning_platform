<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TeacherTeachClasses;
use App\Models\TeacherSubject;
use App\Models\Subject;
use App\Models\ClassModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Services;
use App\Models\TeacherInfo;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Models\Attachment;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class TeacherProfileController extends Controller
{
    // ============== SUBJECTS MANAGEMENT ==============

    /**
     * Display a listing of teacher subjects
     */
    public function indexSubjects()
    {
        $teacherId = Auth::id();
        $subjects = TeacherSubject::where('teacher_id', $teacherId)
            ->with('subject')
            ->paginate(10);

        return view('teacher.subjects.index', compact('subjects'));
    }

    /**
     * Show the form for creating a new subject
     */
    public function createSubject()
    {
        $teacherId = Auth::id();
        $allSubjects = Subject::all();

        // Get already assigned subjects to exclude them
        $assignedSubjectIds = TeacherSubject::where('teacher_id', $teacherId)
            ->pluck('subject_id')
            ->toArray();

        return view('teacher.subjects.create', compact('allSubjects', 'assignedSubjectIds'));
    }

    /**
     * Store newly created subjects
     */
    public function storeSubject(Request $request)
    {
        $request->validate([
            'subjects_id' => 'required|array|min:1',
            'subjects_id.*' => 'exists:subjects,id',
        ], [
            'subjects_id.required' => 'Please select at least one subject.',
            'subjects_id.*.exists' => 'One or more selected subjects are invalid.',
        ]);

        $teacherId = Auth::id();

        foreach ($request->subjects_id as $subjectId) {
            TeacherSubject::firstOrCreate([
                'teacher_id' => $teacherId,
                'subject_id' => $subjectId,
            ]);
        }

        return redirect()->route('teacher.subjects.index')
            ->with('success', 'Subjects assigned successfully!');
    }

    /**
     * Show the form for editing a subject
     */
    public function editSubject($id)
    {
        $teacherId = Auth::id();
        $teacherSubject = TeacherSubject::where('id', $id)
            ->where('teacher_id', $teacherId)
            ->with('subject')
            ->firstOrFail();

        $allSubjects = Subject::all();

        return view('teacher.subjects.edit', compact('teacherSubject', 'allSubjects'));
    }

    /**
     * Update the specified subject
     */
    public function updateSubject(Request $request, $id)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
        ], [
            'subject_id.required' => 'Please select a subject.',
            'subject_id.exists' => 'The selected subject is invalid.',
        ]);

        $teacherId = Auth::id();
        $teacherSubject = TeacherSubject::where('id', $id)
            ->where('teacher_id', $teacherId)
            ->firstOrFail();

        $teacherSubject->update([
            'subject_id' => $request->subject_id,
        ]);

        return redirect()->route('teacher.subjects.index')
            ->with('success', 'Subject updated successfully!');
    }

    /**
     * Remove the specified subject
     */
    public function destroySubject($id)
    {
        $teacherId = Auth::id();
        $teacherSubject = TeacherSubject::where('id', $id)
            ->where('teacher_id', $teacherId)
            ->firstOrFail();

        $teacherSubject->delete();

        return redirect()->route('teacher.subjects.index')
            ->with('success', 'Subject removed successfully!');
    }

    // ============== CLASSES MANAGEMENT ==============

    /**
     * Display a listing of teacher classes
     */
    public function indexClasses()
    {
        $teacherId = Auth::id();
        $classes = TeacherTeachClasses::where('teacher_id', $teacherId)
            ->with('class')
            ->paginate(10);

        return view('teacher.classes.index', compact('classes'));
    }

    /**
     * Show the form for creating a new class
     */
    public function createClass()
    {
        $teacherId = Auth::id();
        $allClasses = ClassModel::all();

        // Get already assigned classes to exclude them
        $assignedClassIds = TeacherTeachClasses::where('teacher_id', $teacherId)
            ->pluck('class_id')
            ->toArray();

        return view('teacher.classes.create', compact('allClasses', 'assignedClassIds'));
    }

    /**
     * Store newly created classes
     */
    public function storeClass(Request $request)
    {
        $request->validate([
            'class_id' => 'required|array|min:1',
            'class_id.*' => 'exists:classes,id',
        ], [
            'class_id.required' => 'Please select at least one class.',
            'class_id.*.exists' => 'One or more selected classes are invalid.',
        ]);

        $teacherId = Auth::id();

        foreach ($request->class_id as $classId) {
            TeacherTeachClasses::firstOrCreate([
                'teacher_id' => $teacherId,
                'class_id' => $classId,
            ]);
        }

        return redirect()->route('teacher.classes.index')
            ->with('success', 'Classes assigned successfully!');
    }

    /**
     * Show the form for editing a class
     */
    public function editClass($id)
    {
        $teacherId = Auth::id();
        $teacherClass = TeacherTeachClasses::where('id', $id)
            ->where('teacher_id', $teacherId)
            ->with('class')
            ->firstOrFail();

        $allClasses = ClassModel::all();

        return view('teacher.classes.edit', compact('teacherClass', 'allClasses'));
    }

    /**
     * Update the specified class
     */
    public function updateClass(Request $request, $id)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
        ], [
            'class_id.required' => 'Please select a class.',
            'class_id.exists' => 'The selected class is invalid.',
        ]);

        $teacherId = Auth::id();
        $teacherClass = TeacherTeachClasses::where('id', $id)
            ->where('teacher_id', $teacherId)
            ->firstOrFail();

        $teacherClass->update([
            'class_id' => $request->class_id,
        ]);

        return redirect()->route('teacher.classes.index')
            ->with('success', 'Class updated successfully!');
    }

    /**
     * Remove the specified class
     */
    public function destroyClass($id)
    {
        $teacherId = Auth::id();
        $teacherClass = TeacherTeachClasses::where('id', $id)
            ->where('teacher_id', $teacherId)
            ->firstOrFail();

        $teacherClass->delete();

        return redirect()->route('teacher.classes.index')
            ->with('success', 'Class removed successfully!');
    }

    // ============== TEACHER Profile MANAGEMENT ==============

    /**
     * Show teacher profile/Profile
     */
    public function showProfile()
    {
        $teacherId = Auth::id();
        $teacherProfile = User::with('teacherInfo', 'attachments')->find($teacherId);

        // load services offered by teacher
        $services = DB::table('teacher_services')
            ->where('teacher_id', $teacherId)
            ->join('services', 'services.id', '=', 'teacher_services.service_id')
            ->select('services.*')
            ->get();

        return view('teacher.profile.show', compact('teacherProfile', 'services'));
    }

    /**
     * Show the form for editing teacher Profile
     */
    public function editProfile()
    {
        $teacherId = Auth::id();
        $teacherProfile =  User::with('teacherInfo', 'attachments')->find($teacherId);

        // available services (active)
        $allServices = Services::where('status', true)->get();

        // currently assigned service ids
        $assignedServiceIds = DB::table('teacher_services')
            ->where('teacher_id', $teacherId)
            ->pluck('service_id')
            ->toArray();

        return view('teacher.profile.edit', compact('teacherProfile', 'allServices', 'assignedServiceIds'));
    }

    /**
     * Update teacher Profile
     */
    public function updateProfile(Request $request)
    {

        $teacherId = Auth::id();

        // Use Validator so we can log validation errors for debugging
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacherId)],
            // We validate phone format here but perform uniqueness check against a digits-only normalized value below
            'phone' => ['nullable', 'string', 'max:20'],
            'bio' => 'nullable|string|max:1000',
            'teach_individual' => 'boolean',
            'individual_hour_price' => 'nullable|numeric|min:0|required_if:teach_individual,1',
            'teach_group' => 'nullable',
            'group_hour_price' => 'nullable|numeric|min:0|required_if:teach_group,1',
            'max_group_size' => 'nullable|integer|min:1|required_if:teach_group,1',
            'min_group_size' => 'nullable|integer|min:1|required_if:teach_group,1|lte:max_group_size',
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
            'profile_picture' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:5120',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            Log::warning('Teacher profile validation failed', [
                'user_id' => $teacherId,
                'errors' => $validator->errors()->toArray(),
                'input' => $request->except('profile_picture')
            ]);

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Normalize phone (digits only) and check uniqueness manually to avoid failures caused by different formatting
        $inputPhone = $request->input('phone');
        if (!empty($inputPhone)) {
            $normalizedInput = preg_replace('/\D+/', '', $inputPhone);
            if (!empty($normalizedInput)) {
                $conflict = User::where('id', '!=', $teacherId)
                    ->get(['id', 'phone_number'])
                    ->first(function ($u) use ($normalizedInput) {
                        return preg_replace('/\D+/', '', $u->phone_number ?? '') === $normalizedInput;
                    });

                if ($conflict) {
                    Log::warning('Teacher profile phone uniqueness conflict (normalized)', [
                        'user_id' => $teacherId,
                        'input_phone' => $inputPhone,
                        'normalized_input' => $normalizedInput,
                        'conflict_user_id' => $conflict->id,
                        'conflict_phone' => $conflict->phone_number,
                    ]);

                    return redirect()->back()->withErrors(['phone' => 'The phone has already been taken.'])->withInput();
                }
            }
        }

        // load user
        $user = User::findOrFail($teacherId);
        $emailChanged = isset($validated['email']) && $validated['email'] !== $user->email;
        $phoneChanged = isset($validated['phone']) && $validated['phone'] !== ($user->phone_number ?? null);

        // Update user basic info
        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->email = $validated['email'];
        $user->phone_number = $validated['phone'] ?? null;
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        if ($phoneChanged) {
            $user->phone_verified_at = null;
        }
        $user->save();

        // handle profile picture upload (store and create attachment with full URL)
        if ($request->hasFile('profile_picture') && $request->file('profile_picture')->isValid()) {
            try {
                $file = $request->file('profile_picture');
                $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9\-_\.]/', '_', $file->getClientOriginalName());
                // store on the "public" disk under images/profile_photos
                $path = $file->storeAs('images/profile_photos', $fileName, 'public');
                if (!Storage::disk('public')->exists($path)) {
                    Log::error('File not saved', ['path' => $path]);
                }
                // Build proper full URL without duplication
                $fullUrl = config('app.url') . '/storage/' . $path;

                // Delete previous profile picture attachment if exists
                Attachment::where('user_id', $user->id)
                    ->where('attached_to_type', 'profile_picture')
                    ->delete();

                // Create new attachment with correct URL
                Attachment::create([
                    'user_id' => $user->id,
                    'attached_to_type' => 'profile_picture',
                    'file_name' => $fileName,
                    'file_path' => $fullUrl,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                Log::info('Profile picture uploaded successfully', [
                    'user_id' => $user->id,
                    'file_name' => $fileName,
                    'full_url' => $fullUrl
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to save profile picture: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // trigger email verification if changed
        if ($emailChanged && method_exists($user, 'sendEmailVerificationNotification')) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Exception $e) {
                Log::error('Failed to send email verification: ' . $e->getMessage(), ['user_id' => $user->id]);
            }
        }

        // handle phone verification: generate code, cache it and notify user
        if ($phoneChanged) {
            $code = random_int(100000, 999999);
            // store verification code
            Cache::put("phone_verification_{$user->id}", $code, now()->addMinutes(10));
            // store pending phone separately so we can apply it after successful verification
            Cache::put("pending_phone_{$user->id}", $validated['phone'], now()->addMinutes(30));

            try {
                $ns = new NotificationService();
                $ns->send($user, 'phone_verification', app()->getLocale() == 'ar' ? 'رمز التحقق' : 'Verification code', "Your verification code is: {$code}", []);
                Log::info('Phone verification initiated for pending phone', [
                    'user_id' => $user->id,
                    'pending_phone' => $validated['phone']
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send phone verification: '.$e->getMessage(), ['user_id' => $user->id]);
            }
        }

        // update or create TeacherInfo
        $teacherInfo = TeacherInfo::updateOrCreate(
            ['teacher_id' => $teacherId],
            [
                'bio' => $validated['bio'] ?? null,
                'teach_individual' => (int) $validated['teach_individual'],
                'individual_hour_price' => $validated['teach_individual'] ? $request->individual_hour_price : null,

                'teach_group' => (int) $validated['teach_group'],
                'group_hour_price' => $validated['teach_group'] ? $request->group_hour_price : null,
                'max_group_size' => $validated['teach_group'] ? $request->max_group_size : null,
                'min_group_size' => $validated['teach_group'] ? $request->min_group_size : null,
            ]
        );

        // sync services
        $selectedServices = $request->input('services', []);
        DB::transaction(function () use ($teacherId, $selectedServices) {
            DB::table('teacher_services')->where('teacher_id', $teacherId)->delete();
            if (!empty($selectedServices)) {
                $insert = [];
                foreach ($selectedServices as $sid) {
                    $insert[] = [
                        'teacher_id' => $teacherId,
                        'service_id' => (int) $sid,  // Cast to integer to ensure proper storage
                    ];
                }
                DB::table('teacher_services')->insert($insert);
            }
        });

        return redirect()->route('teacher.profile.show')
            ->with('status', app()->getLocale() == 'ar' ? 'تم تحديث الملف الشخصي' : 'Profile updated successfully!');
    }
}
