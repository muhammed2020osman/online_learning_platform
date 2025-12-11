<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SystemController extends Controller
{
    public function runScheduler(Request $request)
    {
        Artisan::call('schedule:run');
        return response()->json(['success' => true, 'message' => 'Scheduler run triggered']);
    }

    public function clearCache(Request $request)
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        return response()->json(['success' => true, 'message' => 'Cache cleared']);
    }
}
