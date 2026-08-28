<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayContract $paymentGateway
    ) {}

    /**
     * Handle incoming payment gateway callback / webhook.
     */
    public function callback(Request $request): JsonResponse
    {
        $success = $this->paymentGateway->handleCallback($request->all());

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed or invalid callback payload.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully.',
        ]);
    }
}
