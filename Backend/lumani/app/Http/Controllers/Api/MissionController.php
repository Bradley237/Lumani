<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(
        protected MissionService $missionService
    ) {}

    /**
     * List all missions and user progress.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->missionService->getMissionsWithProgress($user);

        return response()->json($data);
    }

    /**
     * Claim daily check-in reward.
     */
    public function checkin(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->missionService->checkin($user);

        return response()->json($result);
    }

    /**
     * Claim watch ad reward.
     */
    public function watchAd(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->missionService->watchAd($user);

        return response()->json($result);
    }

    /**
     * Complete a one-time mission.
     */
    public function complete(Request $request, string $missionKey): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->missionService->completeOneTime($user, $missionKey);

        return response()->json($result);
    }
}
