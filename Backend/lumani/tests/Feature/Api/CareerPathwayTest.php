<?php

use App\Enums\ChapterProgressState;
use App\Enums\JobDemand;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\AppSetting;
use App\Models\CareerPathway;
use App\Models\CareerProfile;
use App\Models\Chapter;
use App\Models\ChapterProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();
    Config::set('services.gemini.api_key', 'test-gemini-api-key');
});

test('career-profiles list is accessible without a subscription', function () {
    $user = User::factory()->student()->create();

    CareerProfile::factory()->create([
        'title' => 'Software Engineer',
        'description' => 'Builds modern web and mobile systems.',
        'job_demand' => JobDemand::VeryHigh,
        'average_salary' => '800,000 - 2,000,000 FCFA/mo',
        'related_subjects' => ['Computer Science', 'Mathematics'],
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/career-profiles');

    $response->assertOk()
        ->assertJsonCount(1, 'career_profiles')
        ->assertJsonPath('career_profiles.0.title', 'Software Engineer')
        ->assertJsonPath('career_profiles.0.job_demand', 'very_high')
        ->assertJsonPath('career_profiles.0.job_demand_label', 'Very High Demand');
});

test('generate rejects without an active subscription unless free_mode is on', function () {
    $user = User::factory()->student()->create();
    CareerProfile::factory()->create();

    // 1. Rejected with 403 when no subscription and free_mode off
    $resp1 = $this->actingAs($user, 'sanctum')->postJson('/api/career-pathway/generate');
    $resp1->assertStatus(403);

    // 2. Allowed when free_mode is enabled
    $setting = AppSetting::current();
    $setting->free_mode_enabled = true;
    $setting->save();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'recommendations' => [
                                        [
                                            'career_profile_id' => 1,
                                            'match_score' => 90,
                                            'reasoning' => 'Excellent match.',
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $resp2 = $this->actingAs($user, 'sanctum')->postJson('/api/career-pathway/generate');
    $resp2->assertStatus(201)
        ->assertJsonPath('pathway.recommendations.0.career_profile_id', 1);
});

test('generate compiles real academic performance data and stores pathway', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'tier' => SubscriptionTier::Tier2000,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);

    $math = Subject::factory()->create(['name' => 'Mathematics']);
    $physics = Subject::factory()->create(['name' => 'Physics']);

    $mChapter1 = Chapter::factory()->create(['subject_id' => $math->id]);
    $mChapter2 = Chapter::factory()->create(['subject_id' => $math->id]);
    $pChapter1 = Chapter::factory()->create(['subject_id' => $physics->id]);

    $mQuiz1 = Quiz::factory()->create(['chapter_id' => $mChapter1->id]);

    // Student completed math chapter 1 with 95% quiz score
    ChapterProgress::factory()->completed()->create([
        'user_id' => $user->id,
        'chapter_id' => $mChapter1->id,
    ]);
    QuizAttempt::factory()->create([
        'user_id' => $user->id,
        'quiz_id' => $mQuiz1->id,
        'score_percent' => 95.0,
    ]);

    // Student has in_progress math chapter 2
    ChapterProgress::factory()->create([
        'user_id' => $user->id,
        'chapter_id' => $mChapter2->id,
        'state' => ChapterProgressState::InProgress,
    ]);

    $dataScientist = CareerProfile::factory()->create([
        'title' => 'Data Scientist',
        'description' => 'Extracts insights and builds predictive models.',
        'job_demand' => JobDemand::High,
        'related_subjects' => ['Mathematics', 'Computer Science'],
    ]);

    $civilEngineer = CareerProfile::factory()->create([
        'title' => 'Civil Engineer',
        'description' => 'Designs infrastructure and buildings.',
        'job_demand' => JobDemand::Moderate,
        'related_subjects' => ['Physics'],
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => function ($request) use ($dataScientist, $civilEngineer) {
            $body = $request->body();
            // Verify performance data is included in prompt sent to Gemini
            expect($body)->toContain('Mathematics');
            expect($body)->toContain('Data Scientist');

            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'recommendations' => [
                                            [
                                                'career_profile_id' => $dataScientist->id,
                                                'match_score' => 94,
                                                'reasoning' => 'Outstanding 95% score in Mathematics shows analytical mastery.',
                                            ],
                                            [
                                                'career_profile_id' => $civilEngineer->id,
                                                'match_score' => 78,
                                                'reasoning' => 'Solid foundation for engineering paths.',
                                            ],
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200);
        },
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/career-pathway/generate');

    $response->assertStatus(201)
        ->assertJsonPath('pathway.recommendations.0.career_profile_id', $dataScientist->id)
        ->assertJsonPath('pathway.recommendations.0.career_title', 'Data Scientist')
        ->assertJsonPath('pathway.recommendations.0.match_score', 94)
        ->assertJsonPath('pathway.recommendations.1.career_profile_id', $civilEngineer->id)
        ->assertJsonPath('pathway.recommendations.1.match_score', 78);

    expect(CareerPathway::where('user_id', $user->id)->count())->toBe(1);
});

test('recommendations strictly filter out non-existent career profile IDs', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);

    $profile1 = CareerProfile::factory()->create(['title' => 'Biomedical Engineer']);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'recommendations' => [
                                        [
                                            'career_profile_id' => $profile1->id, // Real ID
                                            'match_score' => 88,
                                            'reasoning' => 'Great alignment with biological sciences.',
                                        ],
                                        [
                                            'career_profile_id' => 999999, // Hallucinated ID
                                            'match_score' => 99,
                                            'reasoning' => 'Fake career path.',
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/career-pathway/generate');

    $response->assertStatus(201);
    $recommendations = $response->json('pathway.recommendations');

    // Only real profile preserved, hallucinated ID stripped
    expect($recommendations)->toHaveCount(1);
    expect($recommendations[0]['career_profile_id'])->toBe($profile1->id);
    expect($recommendations[0]['career_title'])->toBe('Biomedical Engineer');
});

test('generate handles Gemini failure gracefully with clear error without saving corrupt data', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);

    CareerProfile::factory()->create();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Rate limit'], 500),
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/career-pathway/generate');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['gemini']);

    expect(CareerPathway::where('user_id', $user->id)->count())->toBe(0);
});

test('get career-pathway returns most recently generated pathway or clear empty state', function () {
    $user = User::factory()->student()->create();

    // 1. Initial state when none generated
    $resp1 = $this->actingAs($user, 'sanctum')->getJson('/api/career-pathway');
    $resp1->assertOk()
        ->assertJson([
            'has_pathway' => false,
            'pathway' => null,
        ]);

    // 2. After generating a pathway
    $profile = CareerProfile::factory()->create(['title' => 'Renewable Energy Specialist']);
    CareerPathway::factory()->create([
        'user_id' => $user->id,
        'generated_at' => now(),
        'recommendations' => [
            [
                'career_profile_id' => $profile->id,
                'match_score' => 86,
                'reasoning' => 'Strong physics foundation.',
            ],
        ],
    ]);

    $resp2 = $this->actingAs($user, 'sanctum')->getJson('/api/career-pathway');
    $resp2->assertOk()
        ->assertJson([
            'has_pathway' => true,
        ])
        ->assertJsonPath('pathway.recommendations.0.career_title', 'Renewable Energy Specialist')
        ->assertJsonPath('pathway.recommendations.0.match_score', 86);
});

test('guarantees top-performing subject leads career pathway results and preserves scores', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);

    $chemistry = Subject::factory()->create(['name' => 'Chemistry']);
    $history = Subject::factory()->create(['name' => 'History']);

    $cChapter1 = Chapter::factory()->create(['subject_id' => $chemistry->id]);
    $cChapter2 = Chapter::factory()->create(['subject_id' => $chemistry->id]);
    $hChapter1 = Chapter::factory()->create(['subject_id' => $history->id]);
    $hChapter2 = Chapter::factory()->create(['subject_id' => $history->id]);

    $cQuiz1 = Quiz::factory()->create(['chapter_id' => $cChapter1->id]);
    $cQuiz2 = Quiz::factory()->create(['chapter_id' => $cChapter2->id]);
    $hQuiz1 = Quiz::factory()->create(['chapter_id' => $hChapter1->id]);
    $hQuiz2 = Quiz::factory()->create(['chapter_id' => $hChapter2->id]);

    // Chemistry: 2 quizzes, 98% and 94% average (96.0%) -> Top qualifying subject!
    QuizAttempt::factory()->create(['user_id' => $user->id, 'quiz_id' => $cQuiz1->id, 'score_percent' => 98.0]);
    QuizAttempt::factory()->create(['user_id' => $user->id, 'quiz_id' => $cQuiz2->id, 'score_percent' => 94.0]);

    // History: 2 quizzes, 70% and 72% average (71.0%)
    QuizAttempt::factory()->create(['user_id' => $user->id, 'quiz_id' => $hQuiz1->id, 'score_percent' => 70.0]);
    QuizAttempt::factory()->create(['user_id' => $user->id, 'quiz_id' => $hQuiz2->id, 'score_percent' => 72.0]);

    $historian = CareerProfile::factory()->create([
        'title' => 'Historian',
        'related_subjects' => ['History'],
    ]);
    $chemicalEngineer = CareerProfile::factory()->create([
        'title' => 'Chemical Process Engineer',
        'related_subjects' => ['Chemistry', 'Physics'],
    ]);
    $generalCounselor = CareerProfile::factory()->create([
        'title' => 'Academic Counselor',
        'related_subjects' => ['Social Studies'],
    ]);

    // Gemini returns Historian first, then Chemical Process Engineer, then Academic Counselor
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'recommendations' => [
                                        [
                                            'career_profile_id' => $historian->id,
                                            'match_score' => 82,
                                            'reasoning' => 'Consistent interest in historical events.',
                                        ],
                                        [
                                            'career_profile_id' => $chemicalEngineer->id,
                                            'match_score' => 96,
                                            'reasoning' => 'Exceptional chemistry and problem solving mastery.',
                                        ],
                                        [
                                            'career_profile_id' => $generalCounselor->id,
                                            'match_score' => 65,
                                            'reasoning' => 'General communication skills.',
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/career-pathway/generate');

    $response->assertStatus(201);
    $recommendations = $response->json('pathway.recommendations');

    // Chemical Engineer (matches top subject Chemistry) is moved to 1st place!
    expect($recommendations[0]['career_profile_id'])->toBe($chemicalEngineer->id);
    expect($recommendations[0]['career_title'])->toBe('Chemical Process Engineer');
    expect($recommendations[0]['match_score'])->toBe(96);
    expect($recommendations[0]['reasoning'])->toBe('Exceptional chemistry and problem solving mastery.');

    // Remaining items preserve Gemini original relative order
    expect($recommendations[1]['career_profile_id'])->toBe($historian->id);
    expect($recommendations[1]['match_score'])->toBe(82);

    expect($recommendations[2]['career_profile_id'])->toBe($generalCounselor->id);
    expect($recommendations[2]['match_score'])->toBe(65);
});

test('uses original Gemini ordering unchanged when no subject has 2+ quiz attempts', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);

    $physics = Subject::factory()->create(['name' => 'Physics']);
    $pChapter1 = Chapter::factory()->create(['subject_id' => $physics->id]);
    $pQuiz1 = Quiz::factory()->create(['chapter_id' => $pChapter1->id]);

    // Only 1 quiz attempt in Physics (does not meet 2+ threshold for top_subject)
    QuizAttempt::factory()->create(['user_id' => $user->id, 'quiz_id' => $pQuiz1->id, 'score_percent' => 100.0]);

    $profileA = CareerProfile::factory()->create(['title' => 'Civil Engineer', 'related_subjects' => ['Civil']]);
    $profileB = CareerProfile::factory()->create(['title' => 'Physicist', 'related_subjects' => ['Physics']]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'recommendations' => [
                                        [
                                            'career_profile_id' => $profileA->id,
                                            'match_score' => 88,
                                            'reasoning' => 'First chosen by AI.',
                                        ],
                                        [
                                            'career_profile_id' => $profileB->id,
                                            'match_score' => 85,
                                            'reasoning' => 'Second chosen by AI.',
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/career-pathway/generate');

    $response->assertStatus(201);
    $recommendations = $response->json('pathway.recommendations');

    // Original ordering preserved exactly as returned by Gemini
    expect($recommendations[0]['career_profile_id'])->toBe($profileA->id);
    expect($recommendations[1]['career_profile_id'])->toBe($profileB->id);
});
