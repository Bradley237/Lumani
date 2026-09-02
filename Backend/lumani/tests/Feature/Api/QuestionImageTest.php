<?php

use App\Enums\ChallengeStatus;
use App\Enums\ReviewStatus;
use App\Models\AppSetting;
use App\Models\Chapter;
use App\Models\PastPaper;
use App\Models\PastPaperQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\SubmittedQuestion;
use App\Models\User;
use App\Models\WeeklyChallenge;
use App\Models\WeeklyChallengeQuestion;
use App\Services\ContentReviewService;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Database\Seeders\MissionAndRewardSeeder;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper — create a minimal in-memory JPEG (GD) without touching the filesystem
// ---------------------------------------------------------------------------
function makeTestJpeg(int $width = 100, int $height = 80): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    $colour = imagecolorallocate($image, 100, 149, 237); // cornflower blue
    imagefill($image, 0, 0, $colour);

    ob_start();
    imagejpeg($image, null, 90);
    $data = ob_get_clean();
    imagedestroy($image);

    $tmpPath = sys_get_temp_dir() . '/test_' . uniqid() . '.jpg';
    file_put_contents($tmpPath, $data);

    return new UploadedFile($tmpPath, 'test.jpg', 'image/jpeg', null, true);
}

function makeTestPng(int $width, int $height): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    $colour = imagecolorallocate($image, 255, 165, 0); // orange
    imagefill($image, 0, 0, $colour);

    ob_start();
    imagepng($image, null, 6);
    $data = ob_get_clean();
    imagedestroy($image);

    $tmpPath = sys_get_temp_dir() . '/test_' . uniqid() . '.png';
    file_put_contents($tmpPath, $data);

    return new UploadedFile($tmpPath, 'test.png', 'image/png', null, true);
}

// ---------------------------------------------------------------------------
// Test 1 — ImageProcessingService: oversized image is resized + stored as JPEG
// ---------------------------------------------------------------------------
test('ImageProcessingService resizes an oversized image to max 1200 px wide and stores JPEG', function () {
    Storage::fake('public');

    $file = makeTestPng(3000, 2000); // 3000 px wide — well over the 1200 px cap

    /** @var ImageProcessingService $service */
    $service = app(ImageProcessingService::class);
    $storedPath = $service->processAndStore($file);

    // File must exist on the public disk
    Storage::disk('public')->assertExists($storedPath);

    // Stored file must be a JPEG and ≤ 1200 px wide
    $content = Storage::disk('public')->get($storedPath);
    $image   = imagecreatefromstring($content);

    expect($image)->not->toBeFalse();
    expect(imagesx($image))->toBeLessThanOrEqual(1200);
    expect(str_ends_with($storedPath, '.jpg'))->toBeTrue();

    imagedestroy($image);
});

// ---------------------------------------------------------------------------
// Test 2 — ImageProcessingService: image already within limit is NOT upscaled
// ---------------------------------------------------------------------------
test('ImageProcessingService does not upscale an image narrower than 1200 px', function () {
    Storage::fake('public');

    $file = makeTestJpeg(800, 600); // already within limit

    $service = app(ImageProcessingService::class);
    $storedPath = $service->processAndStore($file);

    $content = Storage::disk('public')->get($storedPath);
    $image   = imagecreatefromstring($content);

    // Width must NOT be stretched beyond original
    expect(imagesx($image))->toBeLessThanOrEqual(800);

    imagedestroy($image);
});

// ---------------------------------------------------------------------------
// Test 3 — Quiz API: image_url present and correct when question has an image
// ---------------------------------------------------------------------------
test('GET /api/quizzes/{id} includes image_url for questions with an image', function () {
    Storage::fake('public');
    $this->seed(MissionAndRewardSeeder::class);

    $user    = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create(['subject_id' => $subject->id, 'is_free' => true]);
    $quiz    = Quiz::factory()->create(['chapter_id' => $chapter->id]);

    // Store a fake processed image path
    $imagePath = 'question-images/test-uuid.jpg';
    Storage::disk('public')->put($imagePath, 'fake-jpeg-data');

    Question::factory()->create([
        'quiz_id'      => $quiz->id,
        'question_text' => 'What is the capital of France?',
        'answer_choices' => ['A' => 'London', 'B' => 'Paris'],
        'correct_choice' => 'B',
        'image_path'   => $imagePath,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/quizzes/{$quiz->id}");

    $response->assertOk()
        ->assertJsonPath('questions.0.image_url', Storage::disk('public')->url($imagePath));
});

// ---------------------------------------------------------------------------
// Test 4 — Quiz API: question without image returns image_url: null cleanly
// ---------------------------------------------------------------------------
test('GET /api/quizzes/{id} returns image_url null when question has no image', function () {
    $this->seed(MissionAndRewardSeeder::class);

    $user    = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create(['subject_id' => $subject->id, 'is_free' => true]);
    $quiz    = Quiz::factory()->create(['chapter_id' => $chapter->id]);

    Question::factory()->create([
        'quiz_id'       => $quiz->id,
        'question_text' => 'No image question',
        'answer_choices' => ['A' => 'Yes', 'B' => 'No'],
        'correct_choice' => 'A',
        'image_path'    => null,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/quizzes/{$quiz->id}");

    $response->assertOk()
        ->assertJsonPath('questions.0.image_url', null);
});

// ---------------------------------------------------------------------------
// Test 5 — Weekly Challenge start: image_url present in question response
// ---------------------------------------------------------------------------
test('Weekly challenge start includes image_url for questions with an image', function () {
    Storage::fake('public');
    $this->seed(MissionAndRewardSeeder::class);

    $user    = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $imagePath = 'question-images/challenge-uuid.jpg';
    Storage::disk('public')->put($imagePath, 'fake-jpeg-data');

    /** @var WeeklyChallenge $challenge */
    $challenge = WeeklyChallenge::factory()->create([
        'subject_id'      => $subject->id,
        'status'          => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date'   => now()->addDays(6),
    ]);

    WeeklyChallengeQuestion::factory()->create([
        'weekly_challenge_id' => $challenge->id,
        'image_path'          => $imagePath,
        'order'               => 1,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/challenges/{$challenge->id}/start");

    $response->assertStatus(201)
        ->assertJsonPath('challenge.questions.0.image_url', Storage::disk('public')->url($imagePath));
});

// ---------------------------------------------------------------------------
// Test 6 — Exam session start: image_url present in question response
// ---------------------------------------------------------------------------
test('Exam session start includes image_url for past paper questions with an image', function () {
    Storage::fake('public');
    $this->seed(MissionAndRewardSeeder::class);

    // Use free_mode so we skip the subscription check
    $setting = AppSetting::current();
    $setting->free_mode_enabled = true;
    $setting->save();

    $user    = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $pastPaper = PastPaper::factory()->create(['subject_id' => $subject->id]);

    $imagePath = 'question-images/exam-uuid.jpg';
    Storage::disk('public')->put($imagePath, 'fake-jpeg-data');

    PastPaperQuestion::factory()->create([
        'past_paper_id' => $pastPaper->id,
        'image_path'    => $imagePath,
        'order'         => 1,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/past-papers/{$pastPaper->id}/exam-session/start");

    $response->assertStatus(201)
        ->assertJsonPath('questions.0.image_url', Storage::disk('public')->url($imagePath));
});

// ---------------------------------------------------------------------------
// Test 7 — publish() flow: image_path carries over to live Question record
// ---------------------------------------------------------------------------
test('image_path is preserved when a submitted question is published', function () {
    Storage::fake('public');

    $imagePath = 'question-images/submitted-uuid.jpg';
    Storage::disk('public')->put($imagePath, 'fake-jpeg-data');

    $admin      = User::factory()->admin()->create();
    $chapter    = Chapter::factory()->create();

    /** @var SubmittedQuestion $submitted */
    $submitted = SubmittedQuestion::factory()->create([
        'chapter_id'    => $chapter->id,
        'review_status' => ReviewStatus::Approved,
        'image_path'    => $imagePath,
    ]);

    /** @var ContentReviewService $service */
    $service = app(ContentReviewService::class);
    $published = $service->publish($admin, $submitted);

    expect($published->image_path)->toBe($imagePath);
    $this->assertDatabaseHas('questions', [
        'id'         => $published->id,
        'image_path' => $imagePath,
    ]);
});
