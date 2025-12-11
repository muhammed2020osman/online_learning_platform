<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;

class BookingAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = Booking::query();
        if ($request->filled('status')) $q->where('status', $request->status);
        $bookings = $q->with(['student','course'])->orderByDesc('id')->paginate(25);
        return response()->json(['success' => true, 'data' => $bookings]);
    }

    public function show(Request $request, $id)
    {
        $booking = Booking::with(['student','course','sessions'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $booking]);
    }

    public function markPaid(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        $booking->status = 'confirmed';
        $booking->save();
        return response()->json(['success' => true, 'message' => 'Booking marked as paid']);
    }

    public function refund(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        $booking->status = 'refunded';
        $booking->save();
        return response()->json(['success' => true, 'message' => 'Booking refunded']);
    }
}
