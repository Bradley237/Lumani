<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XpConversionController extends Controller
{
    public function __construct(
        protected MissionService $missionService
    ) {}

    /**
     * Convert available XP to coins.
     */
    public function convert(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->missionService->convertXp($user);

        return response()->json($result);
    }
}
