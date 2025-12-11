<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $users = DB::table('users')->count();
        $teachers = DB::table('users')->where('role_id', 3)->count();
        $courses = DB::table('courses')->count();
        $bookings = DB::table('bookings')->count();

        return response()->json([
            'success' => true,
            'data' => compact('users','teachers','courses','bookings')
        ]);
    }

    public function health(Request $request)
    {
        // Basic health check — DB connectivity and queue length if present
        try {
            DB::select('select 1');
            $db = 'ok';
        } catch (\Throwable $e) {
            $db = 'error';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'database' => $db,
                'app' => env('APP_ENV'),
                'timestamp' => now()->toDateTimeString(),
            ]
        ]);
    }
}
