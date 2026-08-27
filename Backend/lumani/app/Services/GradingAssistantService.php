<?php

namespace App\Services;

use App\Models\WeeklyChallengeQuestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GradingAssistantService
{
    /**
     * Request an AI-assisted score and justification for a student's structural answer using Gemini.
     *
     * @return array{suggested_points: int, suggested_justification: string}|null
     */
    public function suggestScore(WeeklyChallengeQuestion $question, string $studentAnswer): ?array
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (empty($apiKey)) {
            Log::info('GradingAssistantService: GEMINI_API_KEY is not configured, skipping AI suggestion.');

            return null;
        }

        if (trim($studentAnswer) === '') {
            return [
                'suggested_points' => 0,
                'suggested_justification' => 'No answer provided by the student.',
            ];
        }

        $markingScheme = ! empty($question->marking_scheme)
            ? $question->marking_scheme
            : 'Evaluate based on factual correctness, completeness, and clarity according to standard academic criteria.';

        $prompt = <<<PROMPT
You are an expert academic grading assistant for an e-learning platform.
Evaluate the student's answer to the structural/essay question using the provided marking scheme and maximum points allowed.

Question:
{$question->question_text}

Marking Scheme / Model Answer:
{$markingScheme}

Maximum Points:
{$question->max_points}

Student's Answer:
{$studentAnswer}

Instructions:
1. Determine an appropriate integer score between 0 and {$question->max_points}.
2. Provide a concise, constructive justification explaining the score and key strengths or missing elements.
3. Respond strictly with valid JSON with the following schema:
{
  "suggested_points": <integer between 0 and {$question->max_points}>,
  "justification": "<concise feedback and explanation>"
}
PROMPT;

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(12)
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
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('GradingAssistantService: Gemini API returned non-success response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $responseData = $response->json();
            $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (! $rawText || ! is_string($rawText)) {
                Log::warning('GradingAssistantService: Invalid response text structure from Gemini API', [
                    'response' => $responseData,
                ]);

                return null;
            }

            // Strip any markdown fences if model returned them
            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
            $parsed = json_decode((string) $cleanJson, true);

            if (! is_array($parsed) || ! isset($parsed['suggested_points'])) {
                Log::warning('GradingAssistantService: Unable to decode JSON from Gemini output', [
                    'rawText' => $rawText,
                ]);

                return null;
            }

            $rawPoints = is_numeric($parsed['suggested_points']) ? (int) round((float) $parsed['suggested_points']) : 0;
            $clampedPoints = max(0, min($question->max_points, $rawPoints));

            $justification = isset($parsed['justification']) && is_string($parsed['justification'])
                ? trim($parsed['justification'])
                : 'Assessed according to marking criteria.';

            return [
                'suggested_points' => $clampedPoints,
                'suggested_justification' => $justification,
            ];
        } catch (Throwable $e) {
            Log::warning('GradingAssistantService: Exception occurred while calling Gemini API', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
