<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CareerPathway;
use App\Services\CareerPathwayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerPathwayController extends Controller
{
    /**
     * Generate a personalized career pathway (subscription-gated).
     */
    public function generate(Request $request, CareerPathwayService $service): JsonResponse
    {
        $pathway = $service->generate($request->user());
        $formatted = $service->formatPathway($pathway);

        return response()->json([
            'message' => 'Personalized career pathway generated successfully.',
            'pathway' => $formatted,
        ], 201);
    }

    /**
     * Get the student's most recently generated pathway.
     */
    public function show(Request $request, CareerPathwayService $service): JsonResponse
    {
        /** @var CareerPathway|null $pathway */
        $pathway = CareerPathway::where('user_id', $request->user()->id)
            ->latest('generated_at')
            ->first();

        if (! $pathway) {
            return response()->json([
                'has_pathway' => false,
                'message' => 'No career pathway has been generated yet for this student.',
                'pathway' => null,
            ]);
        }

        $formatted = $service->formatPathway($pathway);

        return response()->json([
            'has_pathway' => true,
            'pathway' => $formatted,
        ]);
    }
}
