<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutAdminController extends Controller
{
    public function index(Request $request)
    {
        $payouts = DB::table('payouts')->orderByDesc('id')->paginate(25);
        return response()->json(['success' => true, 'data' => $payouts]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => 'required|integer',
            'amount' => 'required|numeric',
            'currency' => 'nullable|string',
            'method' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        $id = DB::table('payouts')->insertGetId(array_merge($data, ['status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));

        return response()->json(['success' => true, 'data' => DB::table('payouts')->where('id', $id)->first()]);
    }

    public function markSent(Request $request, $id)
    {
        DB::table('payouts')->where('id', $id)->update(['status' => 'sent', 'sent_at' => now()]);
        return response()->json(['success' => true]);
    }
}
