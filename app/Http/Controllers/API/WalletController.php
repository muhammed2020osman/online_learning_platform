<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Payout;
use App\Models\UserPaymentMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/",
     *     summary="Get all ",
     *     tags={""},
     *     @OA\Response(
     *         response=200,
     *         description="List of "
     *     )
     * )
     */
    /**
     * Get teacher's wallet balance and withdrawal history
     * GET /api/teacher/wallet
     */
    /**
     * @OA\Get(
     *     path="/api/teacher/wallet",
     *     summary="Get teacher wallet and withdrawals",
     *     tags={"Wallet"},
     *     @OA\Response(response=200, description="Wallet data")
     * )
     */
    public function show(Request $request)
    {
        $teacher = $request->user();

        // Ensure user is a teacher
        if ($teacher->role_id != 3) {
            return response()->json([
                'success' => false,
                'message' => 'Only teachers can access wallet'
            ], 403);
        }

        // Get or create wallet
        $wallet = $teacher->wallet ?? Wallet::create([
            'user_id' => $teacher->id,
            'balance' => 0
        ]);

        // Get withdrawal requests (pending, completed, failed, rejected)
        $withdrawals = Payout::where('teacher_id', $teacher->id)
            ->with('paymentMethod')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => (float) $wallet->balance,
                'withdrawals' => $withdrawals
            ]
        ]);
    }

    /**
     * Request withdrawal from wallet balance
     * POST /api/teacher/wallet/withdraw
     * 
     * Required fields:
     * - amount: (numeric) Amount to withdraw
     * - payment_method_id: (integer) ID of user's bank account from userPaymentMethod
     */
    public function withdraw(Request $request)

    /**
     * @OA\Post(
     *     path="/api/teacher/wallet/withdraw",
     *     summary="Request a withdrawal from wallet",
     *     tags={"Wallet"},
     *     @OA\RequestBody(@OA\JsonContent(type="object", @OA\Property(property="amount", type="number"), @OA\Property(property="payment_method_id", type="integer"))),
     *     @OA\Response(response=201, description="Withdrawal request created")
     * )
     */
    {
        $teacher = $request->user();

        // Ensure user is a teacher
        if ($teacher->role_id != 3) {
            return response()->json([
                'success' => false,
                'message' => 'Only teachers can request withdrawals'
            ], 403);
        }

        // Validate input
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|integer|exists:user_payment_methods,id',
        ]);

        // Get wallet
        $wallet = $teacher->wallet;
        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        // Check if amount is valid
        $amount = (float) $validated['amount'];
        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Amount must be greater than 0',
                'errors' => ['amount' => ['Amount must be greater than 0']]
            ], 422);
        }

        // Check if teacher has sufficient balance
        if ($wallet->balance < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance',
                'errors' => [
                    'amount' => [
                        "Insufficient balance. Available: {$wallet->balance}, Requested: {$amount}"
                    ]
                ],
                'data' => [
                    'available_balance' => (float) $wallet->balance,
                    'requested_amount' => $amount
                ]
            ], 422);
        }

        // Verify payment method belongs to teacher
        $paymentMethod = UserPaymentMethod::where('id', $validated['payment_method_id'])
            ->where('user_id', $teacher->id)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found or does not belong to you',
                'errors' => ['payment_method_id' => ['Invalid payment method']]
            ], 422);
        }

        try {
            // Create withdrawal request
            $payout = Payout::create([
                'teacher_id' => $teacher->id,
                'amount' => $amount,
                'payment_method_id' => $validated['payment_method_id'],
                'status' => Payout::STATUS_PENDING,
                'requested_at' => now(),
            ]);

            // Log the withdrawal request
            Log::info('Withdrawal request created', [
                'teacher_id' => $teacher->id,
                'payout_id' => $payout->id,
                'amount' => $amount,
                'payment_method_id' => $validated['payment_method_id'],
                'teacher_name' => $teacher->first_name . ' ' . $teacher->last_name,
                'teacher_email' => $teacher->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'data' => [
                    'id' => $payout->id,
                    'amount' => (float) $payout->amount,
                    'status' => $payout->status,
                    'payment_method_id' => $payout->payment_method_id,
                    'requested_at' => $payout->requested_at,
                    'remaining_balance' => (float) $wallet->balance
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Withdrawal request failed', [
                'teacher_id' => $teacher->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create withdrawal request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get withdrawal request details
     * GET /api/teacher/wallet/withdrawals/{id}
     */
    /**
     * @OA\Get(
     *     path="/api/teacher/wallet/withdrawals/{id}",
     *     summary="Get withdrawal details",
     *     tags={"Wallet"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Withdrawal details")
     * )
     */
    public function getWithdrawal(Request $request, $id)
    {
        $teacher = $request->user();

        $payout = Payout::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->with(['paymentMethod', 'teacher'])
            ->first();

        if (!$payout) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $payout->id,
                'amount' => (float) $payout->amount,
                'status' => $payout->status,
                'requested_at' => $payout->requested_at,
                'processed_at' => $payout->processed_at,
                'payment_method' => [
                    'id' => $payout->paymentMethod->id,
                    'account_holder_name' => $payout->paymentMethod->account_holder_name,
                    'account_number' => $payout->paymentMethod->account_number,
                    'bank_name' => optional($payout->paymentMethod->banks)->name,
                    'iban' => $payout->paymentMethod->iban,
                ]
            ]
        ]);
    }

    /**
     * Cancel pending withdrawal request
     * DELETE /api/teacher/wallet/withdrawals/{id}
     */
    /**
     * @OA\Delete(
     *     path="/api/teacher/wallet/withdrawals/{id}",
     *     summary="Cancel a pending withdrawal",
     *     tags={"Wallet"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Withdrawal cancelled")
     * )
     */
    public function cancelWithdrawal(Request $request, $id)
    {
        $teacher = $request->user();

        $payout = Payout::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (!$payout) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found'
            ], 404);
        }

        // Can only cancel pending requests
        if ($payout->status !== Payout::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => "Cannot cancel {$payout->status} withdrawal request",
                'data' => ['current_status' => $payout->status]
            ], 422);
        }

        try {
            $payout->status = Payout::STATUS_CANCELLED;
            $payout->save();

            Log::info('Withdrawal request cancelled', [
                'teacher_id' => $teacher->id,
                'payout_id' => $payout->id,
                'amount' => $payout->amount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request cancelled successfully',
                'data' => [
                    'id' => $payout->id,
                    'status' => $payout->status
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel withdrawal', [
                'teacher_id' => $teacher->id,
                'payout_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel withdrawal request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all withdrawal requests (paginated)
     * GET /api/teacher/wallet/withdrawals
     */
    /**
     * @OA\Get(
     *     path="/api/teacher/wallet/withdrawals",
     *     summary="List withdrawal requests",
     *     tags={"Wallet"},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of withdrawals")
     * )
     */
    public function listWithdrawals(Request $request)
    {
        $teacher = $request->user();

        // Optional filtering by status
        $status = $request->query('status');
        
        $query = Payout::where('teacher_id', $teacher->id)
            ->with('paymentMethod');

        if ($status && in_array($status, [
            Payout::STATUS_PENDING,
            Payout::STATUS_COMPLETED,
            Payout::STATUS_FAILED,
            Payout::STATUS_REJECTED,
            Payout::STATUS_CANCELLED
        ])) {
            $query->where('status', $status);
        }

        $withdrawals = $query->orderByDesc('id')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $withdrawals
        ]);
    }
}
