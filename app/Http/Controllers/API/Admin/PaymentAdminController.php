<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;

class PaymentAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = Payment::query();
        if ($request->filled('status')) $q->where('status', $request->status);
        $payments = $q->orderByDesc('id')->paginate(25);
        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function show(Request $request, $id)
    {
        $payment = Payment::with(['booking','user'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $payment]);
    }

    public function reconcile(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        $payment->reconciled = true;
        $payment->save();
        return response()->json(['success' => true, 'message' => 'Payment reconciled']);
    }
}
