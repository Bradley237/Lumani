<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected AccessControlService $accessControlService,
        protected PaymentGatewayContract $paymentGateway
    ) {}

    /**
     * Get current user subscription status and details.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->accessControlService->getSubscriptionStatus($user);

        return response()->json($data);
    }

    /**
     * Initiate a subscription purchase flow.
     */
    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tier' => ['required', 'string', 'in:tier_2000,tier_5000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $checkoutData = $this->paymentGateway->initiate($user, $validated['tier']);

        return response()->json([
            'message' => 'Subscription purchase initiated successfully.',
            'data' => $checkoutData,
        ], 200);
    }
}
