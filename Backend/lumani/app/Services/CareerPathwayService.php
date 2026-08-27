<?php

namespace App\Services;

use App\Enums\ChapterProgressState;
use App\Models\CareerPathway;
use App\Models\CareerProfile;
use App\Models\ChapterProgress;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CareerPathwayService
{
    public function __construct(
        protected AccessControlService $accessControlService
    ) {}

    /**
     * Generate a personalized career pathway for a student using performance data and Gemini AI.
     */
    public function generate(User $user): CareerPathway
    {
        // 1. Subscription Check (free_mode bypasses)
        if (! $this->accessControlService->hasActiveSubscription($user)) {
            abort(403, 'An active subscription is required to generate a personalized career pathway.');
        }

        // 2. Fetch available career profiles from DB
        /** @var Collection<int, CareerProfile> $careerProfiles */
        $careerProfiles = CareerProfile::all();
        if ($careerProfiles->isEmpty()) {
            throw ValidationException::withMessages([
                'careers' => 'No career profiles are currently available in the system.',
            ]);
        }

        // 3. Compile Student Performance Summary & Top Subject
        $performanceSummary = $this->buildStudentPerformanceSummary($user);
        $topSubject = $performanceSummary['top_subject'];

        // 4. Call Gemini API
        $recommendations = $this->queryGeminiForPathway($performanceSummary, $careerProfiles);

        if (empty($recommendations)) {
            throw ValidationException::withMessages([
                'gemini' => 'Unable to generate career pathway recommendations at this time. Please try again later.',
            ]);
        }

        // 5. If student has a qualifying top subject, re-sort recommendations so matching careers lead
        if ($topSubject !== null) {
            $profilesMap = $careerProfiles->keyBy('id');
            $matching = [];
            $nonMatching = [];

            foreach ($recommendations as $rec) {
                /** @var CareerProfile|null $profile */
                $profile = $profilesMap->get($rec['career_profile_id']);
                $related = $profile !== null ? $profile->related_subjects : null;

                $hasTopSubject = false;
                if ($related !== null) {
                    foreach ($related as $subj) {
                        if (strcasecmp(trim($subj), trim($topSubject)) === 0) {
                            $hasTopSubject = true;
                            break;
                        }
                    }
                }

                if ($hasTopSubject) {
                    $matching[] = $rec;
                } else {
                    $nonMatching[] = $rec;
                }
            }

            $recommendations = array_merge($matching, $nonMatching);
        }

        // 6. Persist CareerPathway
        /** @var CareerPathway $pathway */
        $pathway = CareerPathway::create([
            'user_id' => $user->id,
            'generated_at' => now(),
            'recommendations' => $recommendations,
        ]);

        return $pathway;
    }

    /**
     * Build structured performance summary for the student including top qualifying subject.
     *
     * @return array{
     *     subjects: array<int, array<string, mixed>>,
     *     top_subject: string|null
     * }
     */
    public function buildStudentPerformanceSummary(User $user): array
    {
        $subjects = Subject::with(['chapters.quizzes'])->get();
        $userChapterProgress = ChapterProgress::where('user_id', $user->id)->get()->keyBy('chapter_id');
        $userQuizAttempts = QuizAttempt::where('user_id', $user->id)->with('quiz.chapter')->get();

        $summaries = [];
        $qualifyingSubjects = [];

        foreach ($subjects as $subject) {
            $totalChapters = $subject->chapters->count();
            $completedChapters = 0;
            $chapterIds = $subject->chapters->pluck('id')->all();

            foreach ($chapterIds as $cId) {
                if (isset($userChapterProgress[$cId]) && $userChapterProgress[$cId]->state === ChapterProgressState::Completed) {
                    $completedChapters++;
                }
            }

            $attemptsForSubject = $userQuizAttempts->filter(fn (QuizAttempt $att) => in_array($att->quiz->chapter_id, $chapterIds, true));
            $attemptCount = $attemptsForSubject->count();
            $avgScore = $attemptCount > 0
                ? round((float) $attemptsForSubject->avg('score_percent'), 1)
                : null;

            if ($attemptCount >= 2 && $avgScore !== null) {
                $qualifyingSubjects[] = [
                    'subject_name' => $subject->name,
                    'average_score' => $avgScore,
                    'attempt_count' => $attemptCount,
                ];
            }

            $completionRate = $totalChapters > 0
                ? round(($completedChapters / $totalChapters) * 100, 1)
                : 0.0;

            $summaries[] = [
                'subject_name' => $subject->name,
                'completed_chapters' => $completedChapters,
                'total_chapters' => $totalChapters,
                'completion_percent' => $completionRate,
                'average_quiz_score_percent' => $avgScore,
                'quiz_attempts_count' => $attemptCount,
            ];
        }

        $topSubject = null;
        if (! empty($qualifyingSubjects)) {
            usort($qualifyingSubjects, fn (array $a, array $b): int => $b['average_score'] <=> $a['average_score']);
            $topSubject = (string) $qualifyingSubjects[0]['subject_name'];
        }

        return [
            'subjects' => $summaries,
            'top_subject' => $topSubject,
        ];
    }

    /**
     * Query Gemini API to rank career profiles based on performance.
     *
     * @param  array{subjects: array<int, array<string, mixed>>, top_subject: string|null}  $performanceSummary
     * @param  Collection<int, CareerProfile>  $careerProfiles
     * @return array<int, array{career_profile_id: int, match_score: int, reasoning: string}>|null
     */
    protected function queryGeminiForPathway(array $performanceSummary, Collection $careerProfiles): ?array
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (empty($apiKey)) {
            Log::warning('CareerPathwayService: GEMINI_API_KEY is not configured.');

            return null;
        }

        $profilesPayload = $careerProfiles->map(function (CareerProfile $cp): array {
            return [
                'id' => $cp->id,
                'title' => $cp->title,
                'description' => $cp->description,
                'related_subjects' => $cp->related_subjects,
                'job_demand' => $cp->job_demand->value,
                'average_salary' => $cp->average_salary,
            ];
        })->values()->all();

        $performanceJson = json_encode($performanceSummary['subjects'], JSON_PRETTY_PRINT);
        $profilesJson = json_encode($profilesPayload, JSON_PRETTY_PRINT);

        $topSubject = $performanceSummary['top_subject'];
        $topSubjectGuidance = $topSubject !== null
            ? "This student's strongest subject is '{$topSubject}' (highest average quiz score across multiple attempts). Weight related careers accordingly."
            : 'No single subject qualifies as a standout yet (requires at least 2 completed quiz attempts).';

        $prompt = <<<PROMPT
You are an expert career guidance counselor for secondary and high school students in Cameroon and Sub-Saharan Africa.
Evaluate the student's academic progress, subject completion rates, and quiz performance to recommend the best-fit career pathways.

Student Academic Performance Summary:
{$performanceJson}

Top-Performing Subject Highlight:
{$topSubjectGuidance}

Available Career Profiles in System:
{$profilesJson}

Instructions:
1. Analyze the student's strongest subjects, highest quiz scores, and study progress.
2. Rank and recommend the most suitable career profiles STRICTLY from the provided list.
3. For each recommended career profile, output:
   - "career_profile_id": integer ID from the provided list
   - "match_score": integer between 0 and 100 indicating degree of fit
   - "reasoning": 1 to 2 concise, encouraging sentences explaining why this career aligns with their academic strengths.
4. CRITICAL RULE: ONLY recommend career_profile_id values that exist in the provided list. Do not invent new careers.
5. Respond strictly with valid JSON conforming to this schema:
{
  "recommendations": [
    {
      "career_profile_id": <int>,
      "match_score": <int between 0 and 100>,
      "reasoning": "<concise explanation>"
    }
  ]
}
PROMPT;

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(15)
                ->asJson()
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('CareerPathwayService: Gemini API returned error response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $responseData = $response->json();
            $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (! $rawText || ! is_string($rawText)) {
                Log::warning('CareerPathwayService: Invalid response format from Gemini API', [
                    'response' => $responseData,
                ]);

                return null;
            }

            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
            $parsed = json_decode((string) $cleanJson, true);

            if (! is_array($parsed) || ! isset($parsed['recommendations']) || ! is_array($parsed['recommendations'])) {
                Log::warning('CareerPathwayService: Unable to decode recommendations JSON from Gemini output', [
                    'rawText' => $rawText,
                ]);

                return null;
            }

            $validIds = $careerProfiles->pluck('id')->flip();
            $sanitized = [];

            foreach ($parsed['recommendations'] as $rec) {
                if (! is_array($rec)) {
                    continue;
                }
                $cId = (int) ($rec['career_profile_id'] ?? 0);
                if (isset($validIds[$cId])) {
                    $score = is_numeric($rec['match_score'] ?? null) ? (int) round((float) $rec['match_score']) : 50;
                    $reasoning = is_string($rec['reasoning'] ?? null) && trim($rec['reasoning']) !== ''
                        ? trim($rec['reasoning'])
                        : 'Recommended based on academic progress and subject affinities.';

                    $sanitized[] = [
                        'career_profile_id' => $cId,
                        'match_score' => max(0, min(100, $score)),
                        'reasoning' => $reasoning,
                    ];
                }
            }

            return ! empty($sanitized) ? $sanitized : null;
        } catch (Throwable $e) {
            Log::warning('CareerPathwayService: Exception calling Gemini API', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Format and enrich pathway recommendations with career profile details.
     *
     * @return array<string, mixed>|null
     */
    public function formatPathway(?CareerPathway $pathway): ?array
    {
        if (! $pathway) {
            return null;
        }

        $profileIds = collect($pathway->recommendations)->pluck('career_profile_id')->all();
        $profiles = CareerProfile::whereIn('id', $profileIds)->get()->keyBy('id');

        $enrichedRecommendations = collect($pathway->recommendations)->map(function (array $rec) use ($profiles): array {
            /** @var CareerProfile|null $profile */
            $profile = $profiles->get($rec['career_profile_id']);

            return [
                'career_profile_id' => $rec['career_profile_id'],
                'career_title' => $profile?->title,
                'description' => $profile?->description,
                'average_salary' => $profile?->average_salary,
                'job_demand' => $profile?->job_demand?->value,
                'related_subjects' => $profile?->related_subjects,
                'match_score' => $rec['match_score'],
                'reasoning' => $rec['reasoning'],
            ];
        })->values()->all();

        return [
            'id' => $pathway->id,
            'generated_at' => $pathway->generated_at->toIso8601String(),
            'recommendations' => $enrichedRecommendations,
        ];
    }
}
