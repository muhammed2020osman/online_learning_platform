<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HyperpayService;
use App\Models\Payment;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    protected $hyperpay;

    public function __construct(HyperpayService $hyperpay)
    {
        $this->hyperpay = $hyperpay;
    }

    /**
     * POST /api/payments/direct
     * Receive card info & create payment through HyperPay
     */
    /**
     * @OA\Post(
     *     path="/api/payments/direct",
     *     summary="Create a direct payment via HyperPay",
     *     tags={"Payment"},
     *     @OA\RequestBody(@OA\JsonContent(type="object")),
     *     @OA\Response(response=200, description="HyperPay response")
     * )
     *
     * POST /api/payments/direct
     * Receive card info & create payment through HyperPay
     */
    public function directPayment(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer',
            'teacher_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:1',
            'shopperResultUrl' => 'required|url',
            'currency' => 'required|string|size:3',
            'payment_brand' => 'required|string', // e.g. VISA, MADA, MASTER
            'entity_id' => 'required|string',
            'card.number' => 'required|string',
            'card.holder' => 'required|string',
            'card.expiryMonth' => 'required|string',
            'card.expiryYear' => 'required|string',
            'card.cvv' => 'required|string',
            'customer.email' => 'required|email',
            'billing.street1' => 'required|string',
            'billing.city' => 'required|string',
            'billing.state' => 'nullable|string',
            'billing.country' => 'required|string|size:2',
            'billing.postcode' => 'nullable|string',
        ]);

        $transactionId = Str::random(16);

        $payload = [
            'entityId' => $request->entity_id,
            'amount' => number_format($request->amount, 2, '.', ''), // e.g. 100.00
            'currency' => strtoupper($request->currency),
            'paymentType' => 'DB',
            "shopperResultUrl" => "https://ewan-geniuses.com/api/payment/result",
            'paymentBrand' => $request->payment_brand,
            'merchantTransactionId' => $transactionId,
            'customer.email' => $request->input('customer.email'),
            'customer.givenName' => $request->input('customer.givenName', 'Student'),
            'customer.surname' => $request->input('customer.surname', 'User'),
            'billing.street1' => $request->input('billing.street1'),
            'billing.city' => $request->input('billing.city'),
            'billing.state' => $request->input('billing.state', ''),
            'billing.country' => $request->input('billing.country'),
            'billing.postcode' => $request->input('billing.postcode', ''),
            'customParameters[3DS2_enrolled]' => 'true',
            'card.number' => $request->input('card.number'),
            'card.holder' => $request->input('card.holder'),
            'card.expiryMonth' => $request->input('card.expiryMonth'),
            'card.expiryYear' => $request->input('card.expiryYear'),
            'card.cvv' => $request->input('card.cvv'),
        ];

        $response = $this->hyperpay->directPayment($payload);

        $data = $response->json();

        // Save payment record
        Payment::create([
            'booking_id' => null,
            'student_id' => $request->student_id,
            'teacher_id' => $request->teacher_id,
            'amount' => $request->amount,
            'currency' => strtoupper($request->currency),
            'payment_method' => $request->payment_brand,
            'status' => $data['result']['code'] ?? 'pending',
            'transaction_reference' => $transactionId,
            'gateway_reference' => $data['id'] ?? null,
            'gateway_response' => json_encode($data),
            'paid_at' => isset($data['result']['code']) && str_starts_with($data['result']['code'], '000.000')
                ? Carbon::now() : null,
        ]);

        return response()->json([
            'success' => true,
            'hyperpay_response' => $data,
        ], $response->status());
    }

    /**
     * GET /api/payments/result
     * Check payment status by resourcePath (after 3D secure redirect)
     */
    /**
     * @OA\Get(
     *     path="/api/payments/result",
     *     summary="Check payment result after 3DS redirect",
     *     tags={"Payment"},
     *     @OA\Parameter(name="resourcePath", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=302, description="Redirect to success or failed page")
     * )
     */
    public function paymentResult(Request $request)
    {
        $resourcePath = $request->get('resourcePath');
        $baseUrl = "https://eu-test.oppwa.com"; // Change to production endpoint later
        $url = $baseUrl . $resourcePath;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer YOUR_ACCESS_TOKEN'
        ])->get($url);

        $result = $response->json();

        // Extract important info
        $code = $result['result']['code'];
        $description = $result['result']['description'];

        // Successful payment codes usually start with "000."
        if (str_starts_with($code, '000.')) {
            // Update your DB
            Payment::where('transaction_reference', $result['id'])->update([
                'status' => 'paid',
                'gateway_response' => json_encode($result),
                'paid_at' => now(),
            ]);

            return redirect()->route('payment.success');
        } else {
            // Mark as failed
            Payment::where('transaction_reference', $result['id'])->update([
                'status' => 'failed',
                'gateway_response' => json_encode($result),
            ]);

            return redirect()->route('payment.failed');
        }
    }
}
