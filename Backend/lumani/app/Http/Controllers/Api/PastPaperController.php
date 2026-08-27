<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PastPaper;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PastPaperController extends Controller
{
    public function __construct(
        protected AccessControlService $accessControlService
    ) {}

    /**
     * Unlock a past paper questions document.
     */
    public function unlockPaper(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PastPaper $pastPaper */
        $pastPaper = PastPaper::findOrFail($id);

        $result = $this->accessControlService->unlockPastPaper($user, $pastPaper);

        return response()->json($result);
    }

    /**
     * Unlock a past paper solution document.
     */
    public function unlockSolution(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PastPaper $pastPaper */
        $pastPaper = PastPaper::findOrFail($id);

        $result = $this->accessControlService->unlockPastPaperSolution($user, $pastPaper);

        return response()->json($result);
    }
}
