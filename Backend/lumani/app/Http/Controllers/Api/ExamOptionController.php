<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExamSubsystem;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ExamOptionController extends Controller
{
    /**
     * Return the valid mapping of exam subsystems to their respective academic levels.
     */
    public function index(): JsonResponse
    {
        return response()->json(ExamSubsystem::mapping());
    }
}
