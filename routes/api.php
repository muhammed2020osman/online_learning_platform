    <?php
    
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AvailabilityController;
use App\Http\Controllers\API\CourseController;
use App\Http\Controllers\API\LessonController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\WalletController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\ServicesController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\API\EducationLevelController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\OrdersController;
use App\Http\Controllers\API\TeacherApplicationController;
use App\Http\Controllers\API\DisputeController;
use App\Http\Controllers\API\TeacherController;
use App\Http\Controllers\API\PaymentMethodController;
use App\Http\Controllers\API\UserPaymentMethodController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\API\SessionsController;
use App\Http\Controllers\FCMTokenController;
use App\Models\Payment;

// Agora token route for sessions
use App\Services\AgoraService;

// Route::get('/agora/token', function (\Illuminate\Http\Request $request) {

//     $request->validate([
//         'session_id' => 'required',
//         'uid' => 'required',
//         'type' => 'required|in:host,join'
//     ]);

//     $sessionId = $request->session_id;
//     $uid = $request->uid;
//     $isHost = $request->type === 'host';

//     $agora = new AgoraService();
//     $channel = "session_" . $sessionId;
//     $token = $agora->generateToken($channel, $uid, $isHost);

//     return [
//         "appId" => env('AGORA_APP_ID'),
//         "token" => $token,
//         "channel" => $channel,
//         "uid" => $uid
//     ];
// });


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/agora-token', [App\Http\Controllers\AgoraController::class, 'generateRtcToken']);

Route::post('/save-fcm-token', function (Request $request) {
    $request->validate(['token' => 'required|string']);

    $user = $request->user();
    $user->update([
        'fcm_token' => $request->token,
    ]);

    return response()->json(['message' => 'Token saved successfully']);
});
// Notification route
Route::post('/notifications/send', [NotificationController::class, 'sendToToken']);
// main screen APIs
Route::get('/services', [ServicesController::class, 'listServices']);
Route::get('/services/search', [ServicesController::class, 'searchServices']);
Route::get('/subjects/{id}', [ServicesController::class, 'listSubjects']);
Route::get('/subjects/{id}', [ServicesController::class, 'subjectDetails']);
Route::get('courses', [CourseController::class, 'index']); // browse/search
Route::get('courses/{id}', [CourseController::class, 'show']); // course details
Route::get('/teachers', [UserController::class, 'listTeachers']);
Route::get('/teachers/{id}', [UserController::class, 'teacherDetails']);
Route::get('/education-levels', [EducationLevelController::class, 'levelsWithClassesAndSubjects']);
Route::get('/classes/{education_level_id}', [EducationLevelController::class, 'classes']);
Route::get('subjectsClasses/{class_id}', [EducationLevelController::class, 'getSubjectsByClass']);
Route::post('payments/direct', [PaymentController::class, 'directPayment']);
Route::get('payments/result', [PaymentController::class, 'paymentResult'])->name('api.payment.result');
Route::get('payment-methods', [PaymentMethodController::class, 'index']);
Route::get('banks', [PaymentMethodController::class, 'banks']);
// ======================
// Authentication & User Management
// ======================
Route::prefix('auth')->group(function () {
    Route::middleware('auth:sanctum')->get('user/details', [AuthController::class, 'getUserDetails']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify', [AuthController::class, 'verifyCode']);
    Route::post('resend-code', [AuthController::class, 'resendCode']);
    Route::post('verify-reset-code', [AuthController::class, 'verifyResetCode']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('confirm-password', [AuthController::class, 'confirmPassword'])->middleware('auth:sanctum');
    Route::post('change-password', [AuthController::class, 'updatePassword'])->middleware('auth:sanctum');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('profile', [AuthController::class, 'profile'])->middleware('auth:sanctum');
    
});
// ======================
// Profile (Shared)
// ======================
Route::prefix('profile')->middleware('auth:sanctum')->group(function () {
    Route::post('/teacher/info', [UserController::class, 'updateTeacherInfo']);
    Route::post('/teacher/classes', [UserController::class, 'updateTeacherClasses']);
    Route::post('/teacher/subjects', [UserController::class, 'updateTeacherSubjects']);
    Route::put('/profile/update', [UserController::class, 'updateProfile']);
    Route::post('/profile', [UserController::class, 'storeProfile']);
    Route::get('/profile', [UserController::class, 'showProfile']);
    Route::get('/education-levels', [UserController::class, 'educationLevels']);
    Route::get('/classes/{education_level_id}', [UserController::class, 'classes']);
});
// ======================
// Student & Teacher
// ======================

// Student routes with 'student' role middleware
Route::prefix('student')->middleware(['auth:sanctum', 'role:student'])->group(function () {
    // fcm token
    Route::post('/save-fcm-token', [FCMTokenController::class, 'save']);
    // services
    Route::get('/services', [ServicesController::class, 'studentIndex']);
    Route::get('/services/{serviceId}/subjects', [ServicesController::class, 'getSubjectsByService']);
    // all teachers
    Route::get('/teachers', [UserController::class, 'listTeachers']);
    Route::get('/teachers/{id}', [UserController::class, 'teacherDetails']);
    // subjects
    Route::get('/subjects', [ServicesController::class, 'listSubjects']);
    Route::get('/subjects/{id}', [ServicesController::class, 'subjectDetails']);
    //courses
    Route::get('/courses', [CourseController::class, 'index']); // browse/search
    Route::get('/courses/{id}', [CourseController::class, 'show']); // course details
    Route::post('/courses/{id}/enroll', [CourseController::class, 'enroll']); // book/join course
    // lessons
     Route::get('/courses/{course_id}/lessons', [LessonController::class, 'index']);
    Route::get('/lessons/{id}', [LessonController::class, 'show']);
    Route::post('/lessons/{id}/complete', [LessonController::class, 'markComplete']);
    // Orders
    Route::post('/orders', [OrdersController::class, 'store']);
    Route::get('/orders', [OrdersController::class, 'index']);
    Route::get('/orders/{id}', [OrdersController::class, 'show']);
    Route::put('/orders/{id}', [OrdersController::class, 'update']);
    Route::delete('/orders/{id}', [OrdersController::class, 'destroy']);
    Route::get('/orders/{order_id}/applications', [OrdersController::class, 'getApplications']);
    Route::post('/orders/{order_id}/applications/{application_id}/accept', [OrdersController::class, 'acceptApplication']);
    // bookings
    // Booking endpoints
    Route::post('/booking', [BookingController::class, 'createBooking']); // create booking
    Route::get('/booking', [BookingController::class, 'getStudentBookings']);   // list my bookings
    Route::get('/booking/{bookingId}', [BookingController::class, 'getBookingDetails']); // view specific booking
    Route::put('/booking/{bookingId}/cancel', [BookingController::class, 'cancelBooking']); // cancel booking
    Route::post('/booking/pay', [BookingController::class, 'payBooking']); // pay for booking (card payment)
    // payments history
    Route::post('/payments', [PaymentController::class, 'store']); // pay for booking/course
    Route::get('/payments/history', [PaymentController::class, 'history']); // payment history
    // sessions
    Route::get('/sessions', [SessionsController::class, 'index']); // list my sessions
    Route::get('/sessions/grouped', [SessionsController::class, 'groupedSessions']); // teacher: grouped sessions by time
    Route::get('/sessions/{sessionId}', [SessionsController::class, 'show']); // session details
    Route::post('/sessions/{sessionId}/join', [SessionsController::class, 'join']); // join session
    // add payment method
    Route::get('payment-methods', [UserPaymentMethodController::class, 'index']);
    Route::post('payment-methods', [UserPaymentMethodController::class, 'store']);
    Route::put('payment-methods/{id}', [UserPaymentMethodController::class, 'update']);
    Route::delete('payment-methods/{id}', [UserPaymentMethodController::class, 'destroy']);
    // courses reviews
    Route::get('/courses/{course_id}/reviews', [ReviewController::class, 'index']);
    Route::post('/courses/{course_id}/reviews', [ReviewController::class, 'store']);
    Route::delete('/courses/{course_id}/reviews/{id}', [ReviewController::class, 'destroy']);
    // teacher reviews
    Route::get('/teachers/{teacher_id}/reviews', [ReviewController::class, 'index']); // reviews for a teacher
    Route::post('/teachers/{teacher_id}/reviews', [ReviewController::class, 'storeTeacherReview']); // add review for a teacher
    Route::delete('/teachers/{teacher_id}/reviews/{id}', [ReviewController::class, 'destroy']); // delete review
    //disputes
    Route::post('/disputes', [DisputeController::class, 'store']); // Create new dispute      
    Route::get('/disputes/my', [DisputeController::class, 'index']); // List my disputes
    Route::get('/disputes/{id}', [DisputeController::class, 'show']); // View specific dispute
    Route::delete('/disputes/{id}', [DisputeController::class, 'destroy']); // Delete specific dispute


});

Route::prefix('teacher')->middleware(['auth:sanctum', 'role:teacher'])->group(function () {
    // fcm token
    Route::post('/save-fcm-token', [FCMTokenController::class, 'save']);
    // Teacher Information
    Route::post('/info', [UserController::class, 'createOrUpdateTeacherInfo']);
    // services
    Route::get('/services', [ServicesController::class, 'teacherIndex']);
    // courses
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::get('/courses', [CourseController::class, 'myCourses']);
    // subjects and classes
    Route::get('subjects',[TeacherController::class, 'indexSubjects']);
    Route::post('subjects',[TeacherController::class, 'storeSubject']);
    Route::delete('subjects/{id}',[TeacherController::class, 'destroySubject']);
    Route::get('classes',[TeacherController::class, 'indexClasses']);
    Route::post('classes',[TeacherController::class, 'storeClass']);
    Route::delete('classes/{id}',[TeacherController::class, 'destroyClass']);
    // availability slots
    Route::get('availability', [AvailabilityController::class, 'index']); // List all my slots
    Route::post('availability', [AvailabilityController::class, 'store']); // Add new slot
    Route::get('availability/{id}', [AvailabilityController::class, 'show']); // Show slot details
    Route::put('availability/{id}', [AvailabilityController::class, 'update']); // Update slot
    Route::delete('availability/{id}', [AvailabilityController::class, 'destroy']); // Delete slot
    // lessons
    Route::post('/courses/{course_id}/lessons', [LessonController::class, 'store']);
    Route::put('/lessons/{id}', [LessonController::class, 'update']);
    Route::delete('/lessons/{id}', [LessonController::class, 'destroy']);
    // Orders
    Route::get('/orders/browse', [TeacherApplicationController::class, 'browseOrders']);
    Route::post('/orders/{order_id}/apply', [TeacherApplicationController::class, 'apply']);
    Route::get('/my-applications', [TeacherApplicationController::class, 'myApplications']);
    Route::delete('/applications/{application_id}', [TeacherApplicationController::class, 'cancelApplication']);
    // bookings
    Route::post('booking', [BookingController::class , 'index']);
    //sessions
    Route::get('/sessions', [SessionsController::class, 'index']);
    Route::get('/sessions/{id}', [SessionsController::class, 'show']);
    Route::post('/sessions/{id}/start', [SessionsController::class, 'start']);
    Route::post('/sessions/{id}/end', [SessionsController::class, 'end']);

    Route::get('/teachers', [UserController::class, 'listTeachers']);
    Route::get('/teachers/{id}', [UserController::class, 'teacherDetails']);
    //wallet
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/wallet/withdrawals', [WalletController::class, 'listWithdrawals']);
    Route::get('/wallet/withdrawals/{id}', [WalletController::class, 'getWithdrawal']);
    Route::delete('/wallet/withdrawals/{id}', [WalletController::class, 'cancelWithdrawal']);
    // payments methods
    Route::get('payment-methods', [UserPaymentMethodController::class, 'index']);
    Route::post('payment-methods', [UserPaymentMethodController::class, 'store']);
    Route::put('payment-methods/{id}', [UserPaymentMethodController::class, 'update']);
    Route::put('payment-methods/set-default/{id}', [UserPaymentMethodController::class, 'setDefault']);
    Route::delete('payment-methods/{id}', [UserPaymentMethodController::class, 'destroy']);
    // reviews
    Route::get('/courses/{course_id}/reviews', [ReviewController::class, 'index']);
    Route::post('/courses/{course_id}/reviews', [ReviewController::class, 'store']);
    //disputes
    Route::post('/disputes', [DisputeController::class, 'store']); // Create new dispute      
    Route::get('/disputes/my', [DisputeController::class, 'index']); // List my disputes
    Route::get('/disputes/{id}', [DisputeController::class, 'show']); // View specific dispute
    Route::delete('/disputes/{id}', [DisputeController::class, 'destroy']); // Delete specific dispute

});

// routes/api.php (temporary for testing)
Route::put('admin/payments/{payment}/complete', function(Payment $payment) {
    $payment->markAsCompleted('test_payment_' . time(), ['status' => 'success']);
        $payment->booking->createMeetingsForSessions();

    return response()->json([
        'success' => true,
        'message' => 'Payment completed',
        'booking_status' => $payment->booking->status,
        'sessions_with_zoom' => $payment->booking->sessions->map(function($session) {
            return [
                'id' => $session->id,
                'meeting_id' => $session->meeting_id,
                'join_url' => $session->join_url,
                'host_url' => $session->host_url
            ];
        })
    ]);
});
