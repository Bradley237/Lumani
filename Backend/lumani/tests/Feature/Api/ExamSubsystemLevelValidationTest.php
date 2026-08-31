<?php

use App\Enums\ChallengeStatus;
use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use App\Filament\Resources\Chapters\Schemas\ChapterForm;
use App\Filament\Resources\PastPapers\Schemas\PastPaperForm;
use App\Filament\Resources\Subjects\Schemas\SubjectForm;
use App\Models\Subject;
use App\Models\User;
use App\Models\WeeklyChallenge;
use Database\Seeders\MissionAndRewardSeeder;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
});

test('GET /api/exam-options returns valid subsystem and level mapping', function () {
    $response = $this->getJson(route('api.exam-options'));

    $response->assertOk()
        ->assertExactJson([
            'gce' => [
                'ordinary_level',
                'advanced_level',
            ],
            'obc' => [
                'bepc',
                'probatoire',
                'bac',
            ],
        ]);
});

test('student can register with valid GCE pairs', function (string $level) {
    $payload = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.'.uniqid().'@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'exam_system' => 'gce',
        'level' => $level,
    ];

    $response = $this->postJson(route('api.register'), $payload);

    $response->assertCreated();

    $user = User::where('email', $payload['email'])->first();
    expect($user)->not->toBeNull();
    expect($user->exam_system)->toBe(ExamSubsystem::Gce);
    expect($user->level)->toBe(ExamLevel::from($level));
})->with([
    'ordinary_level',
    'advanced_level',
]);

test('student can register with valid OBC pairs', function (string $level) {
    $payload = [
        'first_name' => 'Paul',
        'last_name' => 'Biya',
        'email' => 'paul.'.uniqid().'@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'exam_system' => 'obc',
        'level' => $level,
    ];

    $response = $this->postJson(route('api.register'), $payload);

    $response->assertCreated();

    $user = User::where('email', $payload['email'])->first();
    expect($user)->not->toBeNull();
    expect($user->exam_system)->toBe(ExamSubsystem::Obc);
    expect($user->level)->toBe(ExamLevel::from($level));
})->with([
    'bepc',
    'probatoire',
    'bac',
]);

test('student registration rejects mismatched subsystem and level pairs with 422', function (string $subsystem, string $level) {
    $payload = [
        'first_name' => 'Invalid',
        'last_name' => 'Pair',
        'email' => 'invalid.'.uniqid().'@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'exam_system' => $subsystem,
        'level' => $level,
    ];

    $response = $this->postJson(route('api.register'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['level']);
})->with([
    ['gce', 'bac'],
    ['gce', 'bepc'],
    ['gce', 'probatoire'],
    ['obc', 'ordinary_level'],
    ['obc', 'advanced_level'],
]);

test('student registration rejects invalid enum strings', function () {
    $response = $this->postJson(route('api.register'), [
        'first_name' => 'Invalid',
        'last_name' => 'Enums',
        'email' => 'invalid.enums@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'exam_system' => 'cambridge',
        'level' => 'phd',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['exam_system', 'level']);
});

test('student registration rejects level when exam_system is missing', function () {
    $response = $this->postJson(route('api.register'), [
        'first_name' => 'Missing',
        'last_name' => 'Subsystem',
        'email' => 'missing.subsystem@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'exam_system' => null,
        'level' => 'ordinary_level',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['level']);
});

test('student can update profile via API with valid exam pair', function () {
    $user = User::factory()->create([
        'exam_system' => ExamSubsystem::Gce,
        'level' => ExamLevel::OrdinaryLevel,
    ]);

    $response = $this->actingAs($user, 'sanctum')->putJson(route('api.user.update'), [
        'exam_system' => 'obc',
        'level' => 'bac',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Profile updated successfully.')
        ->assertJsonPath('user.exam_system', 'obc')
        ->assertJsonPath('user.level', 'bac');

    $user->refresh();
    expect($user->exam_system)->toBe(ExamSubsystem::Obc);
    expect($user->level)->toBe(ExamLevel::Bac);
});

test('student profile update via API rejects mismatched pair with 422', function () {
    $user = User::factory()->create([
        'exam_system' => ExamSubsystem::Gce,
        'level' => ExamLevel::OrdinaryLevel,
    ]);

    $response = $this->actingAs($user, 'sanctum')->putJson(route('api.user.update'), [
        'exam_system' => 'gce',
        'level' => 'bac',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['level']);
});

test('content filtering by subsystem and level matches student correctly', function () {
    $student = User::factory()->create([
        'exam_system' => ExamSubsystem::Gce,
        'level' => ExamLevel::OrdinaryLevel,
    ]);

    $subject = Subject::factory()->create([
        'exam_subsystem' => ExamSubsystem::Gce,
        'level' => ExamLevel::OrdinaryLevel,
    ]);

    // 1. Matching GCE Ordinary Level challenge
    $matchingChallenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'title' => 'GCE O-Level Maths Challenge',
        'exam_subsystem' => ExamSubsystem::Gce,
        'level' => ExamLevel::OrdinaryLevel,
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
    ]);

    // 2. GCE Advanced Level challenge (should NOT be visible to Ordinary Level student)
    $gceALevelChallenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'title' => 'GCE A-Level Maths Challenge',
        'exam_subsystem' => ExamSubsystem::Gce,
        'level' => ExamLevel::AdvancedLevel,
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
    ]);

    // 3. OBC Bac challenge (should NOT be visible to GCE student)
    $obcChallenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'title' => 'OBC Bac Challenge',
        'exam_subsystem' => ExamSubsystem::Obc,
        'level' => ExamLevel::Bac,
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
    ]);

    // 4. General challenge (null subsystem and null level, should be visible to all)
    $generalChallenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'title' => 'Universal Cameroon Trivia Challenge',
        'exam_subsystem' => null,
        'level' => null,
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
    ]);

    $response = $this->actingAs($student, 'sanctum')->getJson('/api/challenges');

    $response->assertOk();
    $challengeIds = collect($response->json('challenges'))->pluck('id')->all();

    expect($challengeIds)->toContain($matchingChallenge->id);
    expect($challengeIds)->toContain($generalChallenge->id);
    expect($challengeIds)->not->toContain($gceALevelChallenge->id);
    expect($challengeIds)->not->toContain($obcChallenge->id);
});

test('web settings profile update validates exam pair', function () {
    $user = User::factory()->create([
        'exam_system' => ExamSubsystem::Gce,
        'level' => ExamLevel::OrdinaryLevel,
    ]);

    // Mismatched pair fails validation
    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Valid Name',
        'email' => $user->email,
        'exam_system' => 'gce',
        'level' => 'bac',
    ]);

    $response->assertSessionHasErrors(['level']);

    // Valid pair succeeds
    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Updated Name',
        'email' => $user->email,
        'exam_system' => 'obc',
        'level' => 'bac',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->exam_system)->toBe(ExamSubsystem::Obc);
    expect($user->level)->toBe(ExamLevel::Bac);
});

test('filament forms for Subject, Chapter, and PastPaper configure exam_subsystem and cascading level selects', function () {
    $livewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;

        public function render()
        {
            return '<div></div>';
        }
    };

    $subjectSchema = SubjectForm::configure(Schema::make($livewire));
    $components = collect($subjectSchema->getComponents());

    $subsystemSelect = $components->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'exam_subsystem');
    $levelSelect = $components->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'level');

    expect($subsystemSelect)->not->toBeNull();
    expect($levelSelect)->not->toBeNull();

    $subsystemOptions = $subsystemSelect->getOptions();
    expect($subsystemOptions)->toHaveKeys(['gce', 'obc']);
    expect($subsystemOptions)->not->toHaveKey('anglophone');

    // Chapter form
    $chapterSchema = ChapterForm::configure(Schema::make($livewire));
    $chapterComponents = collect($chapterSchema->getComponents());
    expect($chapterComponents->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'exam_subsystem'))->not->toBeNull();
    expect($chapterComponents->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'level'))->not->toBeNull();

    // PastPaper form
    $pastPaperSchema = PastPaperForm::configure(Schema::make($livewire));
    // PastPaperForm wraps fields inside a Section
    $pastPaperSection = collect($pastPaperSchema->getComponents())->first();
    $pastPaperComponents = collect($pastPaperSection->getChildComponents());
    $paperSubsystem = $pastPaperComponents->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'exam_subsystem');
    $paperLevel = $pastPaperComponents->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'level');

    expect($paperSubsystem)->not->toBeNull();
    expect($paperLevel)->not->toBeNull();
    expect($paperSubsystem->getOptions())->toHaveKeys(['gce', 'obc']);
});
