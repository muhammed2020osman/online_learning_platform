<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\Student\StudentCourseController;
use App\Http\Controllers\Student\StudentBookingController;
use App\Http\Controllers\Student\StudentOrderController;
use App\Http\Controllers\Student\StudentPaymentController;
use App\Http\Controllers\Student\StudentReviewController;
use App\Http\Controllers\Student\StudentDisputeController;
use App\Http\Controllers\Student\StudentProfileController;
use App\Http\Controllers\Teacher\TeacherController;
use App\Http\Controllers\Teacher\TeacherCourseController;
use App\Http\Controllers\Teacher\TeacherLessonController;
use App\Http\Controllers\Teacher\TeacherAvailabilityController;
use App\Http\Controllers\Teacher\TeacherOrderController;
use App\Http\Controllers\Teacher\TeacherWalletController;
use App\Http\Controllers\Teacher\TeacherReviewController;
use App\Http\Controllers\Teacher\TeacherDisputeController;
use App\Http\Controllers\Teacher\TeacherSubjectController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\admin\ServicesController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\DisputeController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Api\TeacherApplicationController;
use App\Http\Controllers\Teacher\TeacherProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Web\UserPaymentMethodWebController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\BookingWebController;
use App\Models\Role;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use App\Http\Controllers\FCMTokenController;
use Illuminate\Support\Facades\Artisan;

// Agora Meeting
Route::get('/meet', function () {
    return view('meet');
});

Route::get('/test-firebase-path', function () {
    // Clear config cache first
    Artisan::call('config:clear');

    $configPath = config('firebase.projects.app.credentials');
    $envPath = env('FIREBASE_CREDENTIALS');

    $results = [
        'env_path' => $envPath,
        'config_path' => $configPath,
        'file_exists_env' => $envPath ? file_exists($envPath) : false,
        'file_exists_config' => $configPath ? file_exists($configPath) : false,
    ];

    // Try to read the file
    if ($envPath && file_exists($envPath)) {
        try {
            $contents = file_get_contents($envPath);
            $json = json_decode($contents);
            $results['json_valid'] = json_last_error() === JSON_ERROR_NONE;
            $results['project_id'] = $json->project_id ?? 'not found';
            $results['client_email'] = $json->client_email ?? 'not found';
            $results['has_private_key'] = isset($json->private_key);
            $results['file_size'] = strlen($contents);
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }
    } else {
        $results['error'] = 'File not found at: ' . $envPath;
    }

    // Try to initialize Firebase
    try {
        $firebase = app(\App\Services\FirebaseService::class);
        $results['firebase_initialized'] = true;
    } catch (\Exception $e) {
        $results['firebase_initialized'] = false;
        $results['firebase_error'] = $e->getMessage();
    }

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/test-notification', function () {
    $user = auth()->user();

    if (!$user->fcm_token) {
        return 'No FCM token found for user';
    }

    $firebaseService = app(\App\Services\FirebaseService::class);

    $result = $firebaseService->sendToToken(
        $user->fcm_token,
        'Test Notification',
        'This is a test message from Laravel!',
        ['type' => 'test', 'timestamp' => now()->toString()]
    );

    return $result ? 'Notification sent!' : 'Failed to send notification';
});
Route::get('/lang/{locale}', function ($locale) {
    $supportedLocales = ['en', 'ar'];
    if (in_array($locale, $supportedLocales)) {
        Session::put('locale', $locale);
        App::setLocale($locale);
    }
    return redirect()->back();
})->name('lang.switch');


Route::middleware('LocaleMiddleware')->group(function () {
    require __DIR__ . '/auth.php';

    Route::get('/', function () {
        return redirect()->route('login');
    });

    Route::get('/check-role', function () {
        // This will never render, always redirects
    })->middleware(['auth', 'check.role'])->name('check.role');

    // 🔒 All routes require login
    Route::middleware(['auth'])->group(function () {
        // Generic FCM token endpoint for all authenticated users
        Route::post('/save-fcm-token', [FCMTokenController::class, 'save']);

        // ===========================
        // Visitor (incomplete profile)
        // ===========================
        Route::prefix('visitor')->group(function () {
            Route::get('complete-profile', [UserController::class, 'completeProfile'])
                ->name('profile.complete');
            Route::post('complete-profile', [UserController::class, 'storeProfile'])
                ->name('profile.store');
        });

        // ===========================
        // Admin
        // ===========================
        Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {
            Route::post('/save-fcm-token', [FCMTokenController::class, 'save']);
            Route::get('dashboard', [AdminController::class, 'index'])
                ->name('admin.dashboard');
            // Services CRUD
            Route::resource('services', ServicesController::class)->names('admin.services');
            Route::post('services/{id}/status', [ServicesController::class, 'updateStatus'])->name('admin.services.status');
            // Users CRUD
            Route::resource('users', UserController::class)->names('admin.users');

            // Courses CRUD
            Route::resource('courses', CourseController::class)->names('admin.courses');

            // Bookings CRUD
            Route::resource('bookings', BookingController::class)->names('admin.bookings');

            // Payments CRUD
            Route::resource('payments', PaymentController::class)->names('admin.payments');

            // Disputes CRUD
            Route::resource('disputes', DisputeController::class)->names('admin.disputes');

            // Support Tickets CRUD
            Route::resource('support-tickets', SupportTicketController::class)->names('admin.support-tickets');

            // Notifications CRUD
            Route::resource('notifications', NotificationController::class)->names('admin.notifications');

            // Settings (usually just edit/update)
            Route::get('settings', [SettingController::class, 'index'])->name('admin.settings.index');
            Route::post('settings', [SettingController::class, 'update'])->name('admin.settings.update');

            // Reports
            Route::get('reports/finance', [ReportsController::class, 'finance'])->name('admin.reports.finance');
            Route::get('reports/users', [ReportsController::class, 'users'])->name('admin.reports.users');
        });
    });

    // ==========================================
    // Student Routes
    // ==========================================
    Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {

        // fcm token
        Route::post('/save-fcm-token', [FCMTokenController::class, 'save']);
        // Dashboard
        Route::get('/test-notification', function () {
            $user = auth()->user();

            if (!$user->fcm_token) {
                return 'No FCM token found for user';
            }

            $firebaseService = app(\App\Services\FirebaseService::class);

            $result = $firebaseService->sendToToken(
                $user->fcm_token,
                'Test Notification',
                'This is a test message from Laravel!',
                ['type' => 'test', 'timestamp' => now()->toString()]
            );

            return $result ? 'Notification sent!' : 'Failed to send notification';
        });
        Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
        Route::get('/books', function () {
            return view('student.mybooks');
        })->name('books');
        // profile 
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [StudentProfileController::class, 'show'])->name('show');

            Route::get('/edit', [StudentProfileController::class, 'edit'])->name('edit');
            Route::put('/update', [StudentProfileController::class, 'update'])->name('update');
            Route::get('/verify-phone', [StudentProfileController::class, 'showPhoneVerification'])->name('verify-phone');
            Route::post('/verify-phone', [StudentProfileController::class, 'verifyPhone'])->name('verify-phone.submit');
            Route::post('/resend-phone-code', [StudentProfileController::class, 'resendPhoneCode'])->name('resend-phone-code');
            Route::get('/verify-email/{token}', [StudentProfileController::class, 'verifyEmail'])->name('verify-email');
            Route::post('/resend-email-verification', [StudentProfileController::class, 'resendEmailVerification'])->name('resend-email');
        });
        // Teachers
        Route::prefix('teachers')->name('teachers.')->group(function () {
            Route::get('/', [StudentController::class, 'teachers'])->name('index');
            Route::get('/{id}', [StudentController::class, 'showTeacher'])->name('show');
        });
        // Courses
        Route::prefix('courses')->name('courses.')->group(function () {
            Route::get('/', [StudentCourseController::class, 'index'])->name('index');
            Route::get('/{id}', [StudentCourseController::class, 'show'])->name('show');
        });
        // Languages
        Route::get('/languages', [StudentController::class, 'languages'])->name('languages');
        // My Courses (Enrolled)
        Route::get('/my-courses', [StudentCourseController::class, 'myCourses'])->name('my-courses');

        // Orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [StudentOrderController::class, 'index'])->name('index');
            Route::get('/create', [StudentOrderController::class, 'create'])->name('create');
            Route::post('/', [StudentOrderController::class, 'store'])->name('store');
            Route::get('/{id}', [StudentOrderController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [StudentOrderController::class, 'edit'])->name('edit');
            Route::put('/{id}', [StudentOrderController::class, 'update'])->name('update');
            Route::delete('/{id}', [StudentOrderController::class, 'destroy'])->name('destroy');
            Route::get('/{id}/applications', [StudentOrderController::class, 'applications'])->name('applications');
            Route::post('/{order_id}/applications/{application_id}/accept', [StudentOrderController::class, 'acceptApplication'])->name('applications.accept');
        });

        // Payments
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/history', [StudentPaymentController::class, 'history'])->name('history');
            Route::get('/methods', [StudentPaymentController::class, 'methods'])->name('methods');
            Route::post('/methods', [StudentPaymentController::class, 'addMethod'])->name('methods.add');
        });

        // Reviews
        Route::prefix('reviews')->name('reviews.')->group(function () {
            Route::get('/courses/{course_id}', [StudentReviewController::class, 'courseReviews'])->name('courses');
            Route::post('/courses/{course_id}', [StudentReviewController::class, 'storeCourseReview'])->name('courses.store');
            Route::get('/teachers/{teacher_id}', [StudentReviewController::class, 'teacherReviews'])->name('teachers');
            Route::post('/teachers/{teacher_id}', [StudentReviewController::class, 'storeTeacherReview'])->name('teachers.store');
            Route::delete('/{id}', [StudentReviewController::class, 'destroy'])->name('destroy');
        });

        // Disputes
        Route::prefix('disputes')->name('disputes.')->group(function () {
            Route::get('/', [StudentDisputeController::class, 'index'])->name('index');
            Route::get('/create', [StudentDisputeController::class, 'create'])->name('create');
            Route::post('/', [StudentDisputeController::class, 'store'])->name('store');
            Route::get('/{id}', [StudentDisputeController::class, 'show'])->name('show');
            Route::delete('/{id}', [StudentDisputeController::class, 'destroy'])->name('destroy');
        });

        Route::get('/favorites', [StudentController::class, 'favorites'])->name('favorites.index');
        Route::get('/language-study', [StudentCourseController::class, 'language'])->name('language.study');
        Route::get('/calendar', [StudentController::class, 'calendar'])->name('calendar');
        // calendar
        Route::get('/calendar', [StudentController::class, 'calendar'])->name('calendar');
    Route::get('/session/{id}', [StudentController::class, 'sessionDetails'])->name('session.details');
        // Profile extra routes
        Route::get('/profile/payment-method', [UserController::class, 'paymentMethod'])->name('profile.paymentMethod');
        Route::get('/profile/booking-history', [UserController::class, 'bookingHistory'])->name('profile.bookingHistory');
        Route::get('/profile/transactions', [UserController::class, 'transactions'])->name('profile.transactions');

        // Bookings
        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::get('/', [BookingWebController::class, 'index'])->name('index');
            Route::get('/create', [BookingWebController::class, 'create'])->name('create');
            Route::post('/', [BookingWebController::class, 'store'])->name('store');
            Route::get('/{id}', [BookingWebController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [BookingWebController::class, 'edit'])->name('edit');
            Route::put('/{id}', [BookingWebController::class, 'update'])->name('update');
            Route::delete('/{id}', [BookingWebController::class, 'destroy'])->name('destroy');
            Route::get('/{id}/payment', [BookingWebController::class, 'payment'])->name('payment');
            Route::post('/{id}/payment/process', [BookingWebController::class, 'processDirectPayment'])->name('payment.process');
            Route::get('/payment/result', [BookingWebController::class, 'paymentResult'])->name('payment.result');
            Route::get('/{id}/success', [BookingWebController::class, 'paymentSuccess'])->name('success');
            Route::delete('/{id}/cancel', [BookingWebController::class, 'cancel'])->name('cancel');
        });
    });

    Route::middleware(['auth'])->group(function () {
        Route::prefix('payment-methods')->name('payment-methods.')->group(function () {
            Route::get('/', [UserPaymentMethodWebController::class, 'index'])->name('index');
            Route::post('/', [UserPaymentMethodWebController::class, 'store'])->name('store');
            Route::put('/{id}', [UserPaymentMethodWebController::class, 'update'])->name('update');
            Route::delete('/{id}', [UserPaymentMethodWebController::class, 'destroy'])->name('destroy');
            Route::patch('/{id}/set-default', [UserPaymentMethodWebController::class, 'setDefault'])->name('set-default');
        });
    });
    // ==========================================
    // Teacher Routes
    // ==========================================
    Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
        // fcm token
        Route::post('/save-fcm-token', [FCMTokenController::class, 'save']);
        // Dashboard
        Route::get('/dashboard', [TeacherController::class, 'index'])->name('dashboard');
        // Calendar
         Route::get('/calendar', [TeacherController::class, 'calendar'])->name('calendar');
    Route::get('/session/{id}', [TeacherController::class, 'sessionDetails'])->name('session.details');
        // Teacher Info Setup
        Route::get('/info', [TeacherController::class, 'showInfo'])->name('info');
        Route::post('/info', [TeacherController::class, 'updateInfo'])->name('info.update');

        // Courses
        Route::prefix('courses')->name('courses.')->group(function () {
            Route::get('/', [TeacherCourseController::class, 'index'])->name('index');
            Route::get('/create', [TeacherCourseController::class, 'create'])->name('create');
            Route::post('/', [TeacherCourseController::class, 'store'])->name('store');
            Route::get('/{id}', [TeacherCourseController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [TeacherCourseController::class, 'edit'])->name('edit');
            Route::put('/{id}', [TeacherCourseController::class, 'update'])->name('update');
            Route::delete('/{id}', [TeacherCourseController::class, 'destroy'])->name('destroy');
            Route::get('/{id}/sessions', [TeacherCourseController::class, 'sessions'])->name('sessions');
            Route::post('update-status', [TeacherCourseController::class, 'updateStatus'])->name('update-status');
            Route::get('/{id}/students', [TeacherCourseController::class, 'students'])->name('students');
        });

        // Lessons
        Route::prefix('lessons')->name('lessons.')->group(function () {
            Route::get('/courses/{course_id}', [TeacherLessonController::class, 'index'])->name('index');
            Route::get('/courses/{course_id}/create', [TeacherLessonController::class, 'create'])->name('create');
            Route::post('/courses/{course_id}', [TeacherLessonController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [TeacherLessonController::class, 'edit'])->name('edit');
            Route::put('/{id}', [TeacherLessonController::class, 'update'])->name('update');
            Route::delete('/{id}', [TeacherLessonController::class, 'destroy'])->name('destroy');
        });

        // Availability
        Route::prefix('availability')->name('availability.')->group(function () {
            Route::get('/', [TeacherAvailabilityController::class, 'index'])->name('index');
            Route::get('/create', [TeacherAvailabilityController::class, 'create'])->name('create');
            Route::post('/', [TeacherAvailabilityController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [TeacherAvailabilityController::class, 'edit'])->name('edit');
            Route::put('/{id}', [TeacherAvailabilityController::class, 'update'])->name('update');
            Route::delete('/{id}', [TeacherAvailabilityController::class, 'destroy'])->name('destroy');
        });
        // Teacher Languages
        Route::get('/languages', [TeacherProfileController::class, 'indexLanguages'])->name('languages.index');
        Route::get('/languages/create', [TeacherProfileController::class, 'createLanguage'])->name('languages.create');
        Route::post('/languages', [TeacherProfileController::class, 'storeLanguage'])->name('languages.store');
        Route::get('/languages/{id}/edit', [TeacherProfileController::class, 'editLanguage'])->name('languages.edit');
        Route::put('/languages/{id}', [TeacherProfileController::class, 'updateLanguage'])->name('languages.update');
        Route::delete('/languages/{id}', [TeacherProfileController::class, 'destroyLanguage'])->name('languages.destroy');

        // Orders (Browse & Apply)
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/browse', [TeacherApplicationController::class, 'browse'])->name('browse');
            Route::get('/{id}', [TeacherApplicationController::class, 'show'])->name('show');
            Route::post('/{id}/apply', [TeacherApplicationController::class, 'apply'])->name('apply');
            Route::get('/applications/my', [TeacherApplicationController::class, 'myApplications'])->name('applications');
            Route::delete('/applications/{id}', [TeacherApplicationController::class, 'cancelApplication'])->name('applications.cancel');
        });

        // Bookings
        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::get('/', [TeacherController::class, 'bookings'])->name('index');
            Route::get('/{id}', [TeacherController::class, 'bookingDetails'])->name('show');
        });

        // Reviews
        Route::prefix('reviews')->name('reviews.')->group(function () {
            Route::get('/', [TeacherReviewController::class, 'index'])->name('index');
        });

        // Disputes
        Route::prefix('disputes')->name('disputes.')->group(function () {
            Route::get('/', [TeacherDisputeController::class, 'index'])->name('index');
            Route::get('/create', [TeacherDisputeController::class, 'create'])->name('create');
            Route::post('/', [TeacherDisputeController::class, 'store'])->name('store');
            Route::get('/{id}', [TeacherDisputeController::class, 'show'])->name('show');
            Route::delete('/{id}', [TeacherDisputeController::class, 'destroy'])->name('destroy');
        });
        // Wallet
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/wallet', [TeacherWalletController::class, 'index'])->name('index');
            Route::get('/wallet/request-payout', [TeacherWalletController::class, 'create'])->name('create');
            Route::post('/wallet/request-payout', [TeacherWalletController::class, 'store'])->name('store');
            // Bank Account Routes (new)
            Route::get('/wallet/bank-accounts', [TeacherWalletController::class, 'bankAccounts'])->name('bank-accounts');
            Route::get('/wallet/bank-accounts/create', [TeacherWalletController::class, 'createBankAccount'])->name('bank-accounts.create');
            Route::post('/wallet/bank-accounts', [TeacherWalletController::class, 'storeBankAccount'])->name('bank-accounts.store');
            Route::get('/wallet/bank-accounts/{id}/edit', [TeacherWalletController::class, 'editBankAccount'])->name('bank-accounts.edit');
            Route::put('/wallet/bank-accounts/{id}', [TeacherWalletController::class, 'updateBankAccount'])->name('bank-accounts.update');
            Route::delete('/wallet/bank-accounts/{id}', [TeacherWalletController::class, 'destroyBankAccount'])->name('bank-accounts.destroy');
            Route::patch('/wallet/bank-accounts/{id}/set-default', [TeacherWalletController::class, 'setDefaultBankAccount'])->name('bank-accounts.set-default');
        });
        // Subjects
        Route::get('/subjects', [TeacherSubjectController::class, 'index'])->name('subjects.index');
        Route::get('/subjects/create', [TeacherSubjectController::class, 'create'])->name('subjects.create');
        Route::post('/subjects', [TeacherSubjectController::class, 'store'])->name('subjects.store');
        Route::get('/subjects/{id}/edit', [TeacherSubjectController::class, 'edit'])->name('subjects.edit');
        Route::put('/subjects/{id}', [TeacherSubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{id}', [TeacherSubjectController::class, 'destroy'])->name('subjects.destroy');
        Route::get('/subjects/get-classes', [TeacherSubjectController::class, 'getClasses'])->name('subjects.get-classes');
        Route::get('/subjects/get-subjects', [TeacherSubjectController::class, 'getSubjects'])->name('subjects.get-subjects');

        // Teacher Info
        Route::get('/profile', [TeacherProfileController::class, 'showProfile'])->name('profile.show');
        Route::get('/profile/edit', [TeacherProfileController::class, 'editProfile'])->name('profile.edit');
        Route::put('/profile', [TeacherProfileController::class, 'updateProfile'])->name('profile.update');
    });
});
// Add this near top (outside auth middleware group) so HyperPay can call it publicly:
Route::get('/bookings/payment/result', [BookingWebController::class, 'paymentResult'])
    ->name('bookings.payment.result');

// booking success page (used after payment)
Route::get('/bookings/{id}/success', [BookingWebController::class, 'paymentSuccess'])
    ->name('bookings.success');
