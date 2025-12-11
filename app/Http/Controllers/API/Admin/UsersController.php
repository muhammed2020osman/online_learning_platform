<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\UserController;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->select(['id','first_name','last_name','email','phone_number','gender','role_id','is_active']);
        if ($request->filled('role_id')) $q->where('role_id', $request->role_id);

        // Eager-load attachments but only the ones that matter for the admin table (profile_photo, certificate)
        $q->with(['attachments' => function ($query) {
            $query->whereIn('attached_to_type', ['profile_picture', 'certificate'])
                ->select(['id','user_id','file_path','file_name','attached_to_type','attached_to_id']);
        }]);

        $paginated = $q->orderBy('id', 'desc')->paginate(25);

        // Transform the paginator collection to include profile_photo and certificate urls
        $paginated->getCollection()->transform(function ($user) {
            $profilePhoto = null;
            $certificate = null;

            if ($user->attachments) {
                $pp = $user->attachments->firstWhere('attached_to_type', 'profile_picture');
                $cert = $user->attachments->firstWhere('attached_to_type', 'certificate');
                $profilePhoto = $pp->file_path ?? null;
                $certificate = $cert->file_path ?? null;
            }

            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'gender' => $user->gender,
                'role_id' => $user->role_id,
                'is_active' => $user->is_active,
                'verified' => $user->profile->verified ?? false,
                'profile_photo' => $profilePhoto,
                'certificate' => $certificate,
            ];
        });

        return response()->json(['success' => true, 'data' => $paginated]);
    }

    public function teachers(Request $request)
    {
        $teacherRole = Role::where('name_key', 'teacher')->first();
        $q = User::query()->select(['id','first_name','last_name','email','phone_number','gender','role_id','is_active']);
        $q->where('role_id', $teacherRole->id);

        // Eager-load attachments and teacherServices with service
        $q->with([
            'attachments' => function ($query) {
                $query->whereIn('attached_to_type', ['profile_picture', 'certificate'])
                    ->select(['id','user_id','file_path','file_name','attached_to_type','attached_to_id']);
            },
            'teacherServices.service'
        ]);

        $paginated = $q->orderBy('id', 'desc')->paginate(25);

        // Transform the paginator collection to include profile_photo, certificate, and services
        $paginated->getCollection()->transform(function ($user) {
            $profilePhoto = null;
            $certificate = null;
            $services = [];

            if ($user->attachments) {
                $pp = $user->attachments->firstWhere('attached_to_type', 'profile_picture');
                $cert = $user->attachments->firstWhere('attached_to_type', 'certificate');
                $profilePhoto = $pp->file_path ?? null;
                $certificate = $cert->file_path ?? null;
            }

            if ($user->teacherServices) {
                foreach ($user->teacherServices as $ts) {
                    if ($ts->service) {
                        $services[] = [
                            'id' => $ts->service->id,
                            'key_name' => $ts->service->key_name ?? null,
                            'name_ar' => $ts->service->name_ar ?? null,
                            'name_en' => $ts->service->name_en ?? null,
                            'description_ar' => $ts->service->description_ar ?? null,
                            'description_en' => $ts->service->description_en ?? null,
                            'image' => $ts->service->image ?? null,
                            'status' => $ts->service->status ?? null,
                        ];
                    }
                }
            }

            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'gender' => $user->gender,
                'role_id' => $user->role_id,
                'is_active' => $user->is_active,
                'verified' => $user->profile->verified ?? false,
                'profile_photo' => $profilePhoto,
                'certificate' => $certificate,
                'services' => $services,
            ];
        });

        return response()->json(['success' => true, 'data' => $paginated]);
    }

    public function teacherDetails(Request $request, $id)
    {
        
        $user = User::with('profile')->findOrFail($id);
        $userController = new UserController();
        $user = $userController->getFullTeacherData($user);
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }


    public function show(Request $request, $id)
    {
        $user = User::with('profile')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'nullable|integer'
        ]);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        return response()->json(['success' => true, 'data' => $user], 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->only(['first_name','last_name','email','phone_number','role_id','is_active']);
        if ($request->filled('password')) $data['password'] = Hash::make($request->password);
        $user->update(array_filter($data, function($v){ return $v !== null; }));
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['success' => true]);
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $new = substr(bin2hex(random_bytes(4)),0,8);
        $user->password = Hash::make($new);
        $user->save();
        return response()->json(['success' => true, 'new_password' => $new]);
    }

    public function verifyTeacher(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $profile = UserProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->verified = true;
        $profile->save();
        return response()->json(['success' => true, 'message' => 'Teacher verified']);
    }

    public function suspend(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => false]);
        return response()->json(['success' => true]);
    }

    public function activate(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);
        return response()->json(['success' => true]);
    }
}
