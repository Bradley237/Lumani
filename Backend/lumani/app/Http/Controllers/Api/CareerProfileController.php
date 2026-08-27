<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CareerProfile;
use Illuminate\Http\JsonResponse;

class CareerProfileController extends Controller
{
    /**
     * List all career profiles (free for all authenticated students).
     */
    public function index(): JsonResponse
    {
        $profiles = CareerProfile::orderBy('title', 'asc')
            ->get()
            ->map(function (CareerProfile $profile): array {
                return [
                    'id' => $profile->id,
                    'title' => $profile->title,
                    'description' => $profile->description,
                    'average_salary' => $profile->average_salary,
                    'job_demand' => $profile->job_demand->value,
                    'job_demand_label' => $profile->job_demand->label(),
                    'related_subjects' => $profile->related_subjects ?? [],
                ];
            });

        return response()->json([
            'career_profiles' => $profiles,
        ]);
    }
}
