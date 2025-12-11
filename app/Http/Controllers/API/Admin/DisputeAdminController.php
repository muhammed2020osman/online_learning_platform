<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DisputeAdminController extends Controller
{
    public function index(Request $request)
    {
        $disputes = DB::table('disputes')->orderByDesc('id')->paginate(25);
        return response()->json(['success' => true, 'data' => $disputes]);
    }

    public function show(Request $request, $id)
    {
        $dispute = DB::table('disputes')->where('id', $id)->first();
        return response()->json(['success' => true, 'data' => $dispute]);
    }

    public function resolve(Request $request, $id)
    {
        $data = $request->validate([
            'resolution' => 'required|string',
            'amount' => 'nullable|numeric',
            'notes' => 'nullable|string'
        ]);

        DB::table('disputes')->where('id', $id)->update(array_merge($data, ['status' => 'resolved', 'resolved_at' => now()]));

        return response()->json(['success' => true, 'message' => 'Dispute resolved']);
    }
}
