<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RevisionPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevisionPlanController extends Controller
{
    /**
     * Generate a personalized algorithmic revision plan (free for all students).
     */
    public function generate(Request $request, RevisionPlanService $service): JsonResponse
    {
        $validated = $request->validate([
            'weekly_available_minutes' => ['required', 'integer', 'min:15', 'max:10080'],
            'available_days' => ['required', 'array', 'min:1'],
            'available_days.*' => ['integer', 'between:0,6'],
        ]);

        $plan = $service->generate(
            $request->user(),
            (int) $validated['weekly_available_minutes'],
            $validated['available_days']
        );

        return response()->json([
            'message' => 'Revision plan generated successfully.',
            'plan' => [
                'id' => $plan->id,
                'weekly_available_minutes' => $plan->weekly_available_minutes,
                'available_days' => $plan->available_days,
                'generated_at' => $plan->generated_at->toIso8601String(),
                'plan_data' => $plan->plan_data,
            ],
        ], 201);
    }

    /**
     * Get the student's most recently generated revision plan.
     */
    public function show(Request $request, RevisionPlanService $service): JsonResponse
    {
        $plan = $service->getLatestPlan($request->user());

        if (! $plan) {
            return response()->json([
                'has_plan' => false,
                'message' => 'No revision plan has been generated yet for this student.',
                'plan' => null,
            ]);
        }

        return response()->json([
            'has_plan' => true,
            'plan' => [
                'id' => $plan->id,
                'weekly_available_minutes' => $plan->weekly_available_minutes,
                'available_days' => $plan->available_days,
                'generated_at' => $plan->generated_at->toIso8601String(),
                'plan_data' => $plan->plan_data,
            ],
        ]);
    }
}
