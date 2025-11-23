<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Controllers\API\UserController;

class AuthController extends Controller
{
    // Register
    public function register(Request $request)
    {
        $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|string|email|unique:users',
            'phone_number'  => 'required|string|max:15',
            'gender'        => 'required|in:male,female',
            'nationality'   => 'required|string|max:255',
            'role_id'       => 'required',
        ]);
        $password = 'password'; // Default password
        $verification_code = 999999;//rand(100000, 999999);

        $user = User::create([
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
            'email'         => $request->email,
            'phone_number'  => $request->phone_number,
            'gender'        => $request->gender,
            'nationality'   => $request->nationality,
            'password'      => Hash::make($password),
            'role_id'       => $request->role_id,
            'verified'      => false,
            'verification_code' => $verification_code,
        ]);
        $user_response = [
            "id" => $user->id,
            "first_name" => $user->first_name,
            "last_name" => $user->last_name,
            "email" => $user->email,
            "phone_number" => $user->phone_number,
            "gender" => $user->gender,
            "role_id" => $user->role_id,
        ];
        // Send SMS
        $smsResponse = $this->sendVerificationSMS($request->phone_number, $verification_code);

        return response()->json([
            'message' => 'Verification code sent. Please verify your phone number.',
            'user' => $user_response,
            'sms_response' => $smsResponse // For debugging, remove in production
        ]);
    }

    /**
     * Send SMS using dreams.sa API
     */
    protected function sendVerificationSMS($to, $code)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://www.dreams.sa/index.php/api/sendsms/', [
            'form_params' => [
                'user'       => config('services.sms.user'),
                'secret_key' => config('services.sms.secret_key'),
                'sender'     => config('services.sms.sender'),
                'to'         => $to,
                'message'   => "Welcome to the Geniuses Family! Your verification code is: $code\n مرحبًا بك في عائلة العباقرة! رمز التحقق الخاص بك هو: $code\n"
            ]
        ]);
        return json_decode($response->getBody(), true);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([

            'new_password'     => 'required|min:8|confirmed',
        ]);
        $user = $request->user();
        $user->password = Hash::make($request->new_password);
        $user->save();
        return response()->json(['message' => 'Password updated successfully']);
    }
    
    public function login(Request $request)
{
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    $role = Role::find($user->role_id);

    if ($role->name_key == 'visitor') {
        throw ValidationException::withMessages([
            'email' => ['Please complete your profile first.'],
        ]);
    }

    
    if ($user->role_id == 3) {
        $userController = new UserController();
        $fullTeacherData = $userController->getFullTeacherData($user);

        $userData = [
            "role" => $role->name_key,
            "data" => $fullTeacherData,
        ];
    } else {
        //  For non-teacher roles
        $userProfile = $user->profile;
        $userData = [
            "role" => $role->name_key,
            "data" => $user,
            "profile" => $userProfile,
        ];
    }

    
    $token = $user->createToken('mobile-app-token')->plainTextToken;

    return response()->json([
        'user' => $userData,
        'token' => $token,
    ]);
}


    // Profile
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    // Verify Code
    public function verifyCode(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code'    => 'required|digits:6'
        ]);

        $user = User::find($request->user_id);
        if (! $user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->verification_code == $request->code) {
            $user->verified = true;
            $user->verification_code = null;
            $user->save();

            $token = $user->createToken('mobile-app-token')->plainTextToken;

            return response()->json([
                'message' => 'Verification successful.',
                'token'   => $token,
                'user'    => $user
            ]);
        } else {
            return response()->json([
                'message' => 'Invalid verification code.'
            ], 422);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|exists:users,phone_number',
        ]);

        $user = User::where('phone_number', $request->phone_number)->first();
        $verification_code = rand(100000, 999999);
        $user->verification_code = $verification_code;
        $user->save();

        // Send SMS
        $smsResponse = $this->sendVerificationSMS($request->phone_number, $verification_code);

        return response()->json([
            'message' => 'Verification code sent. Please check your phone.',
            'user' => $user,
            'sms_response' => $smsResponse // For debugging, remove in production
        ]);
    }

    // verify code for reset password
    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code'    => 'required|digits:6'
        ]);
        $user = User::find($request->user_id);
        if (! $user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }
        if ($user->verification_code == $request->code) {
            return response()->json([
                'message' => 'Code verified. You can now reset your password.',
                'user_id' => $user->id
            ], 200);
        } else {
            return response()->json([
                'message' => 'Invalid verification code.'
            ], 422);
        }
    }

    // Confirm Reset Password
    public function confirmResetPassword(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6,users,verification_code',
            'user_id' => 'required|exists:users,id',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = User::find($request->user_id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // Update password and clear verification code
        $user->password = Hash::make($request->new_password);
        $user->verification_code = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.'
        ]);
    }

    // resend code
    public function resendCode(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        $user = User::find($request->user_id);
        if (! $user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        $verification_code = rand(100000, 999999);
        $user->verification_code = $verification_code;
        $user->save();

        // Send SMS
        $smsResponse = $this->sendVerificationSMS($user->phone_number, $verification_code);

        return response()->json([
            'message' => 'Verification code resent.',
            'sms_response' => $smsResponse // For debugging, remove in production
        ]);
    }

    
    // Get User Details
    public function getUserDetails(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.'
            ], 401);
        }
        if( $user->role_id == 3 ) { // Teacher
            $userController = new UserController();
            $user = $userController->getFullTeacherData($user);
        } else {
            $profilePhoto = $user->attachments()
                ->where('attached_to_type', 'profile_picture')
                ->latest()
                ->value('file_path');
            // For other roles, you can customize the data as needed
            $user = [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'nationality' => $user->nationality,
                'phone_number' => $user->phone_number,
                'role_id' => $user->role_id,
                'profile' => 
                    ['profile_photo' => $profilePhoto]
            ];
        }
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }
}
