<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapterUnlockController extends Controller
{
    public function __construct(
        protected AccessControlService $accessControlService
    ) {}

    /**
     * Unlock a chapter using coins.
     */
    public function unlock(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Chapter $chapter */
        $chapter = Chapter::findOrFail($id);

        $result = $this->accessControlService->unlockChapter($user, $chapter);

        return response()->json($result);
    }
}
