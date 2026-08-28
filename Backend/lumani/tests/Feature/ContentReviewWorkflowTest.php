<?php

use App\Enums\ReviewStatus;
use App\Filament\Resources\SubmittedQuestions\Pages\CreateSubmittedQuestion;
use App\Filament\Resources\SubmittedQuestions\Pages\ListSubmittedQuestions;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\SubmittedQuestion;
use App\Models\User;
use App\Services\ContentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can approve a submitted question', function () {
    $admin = User::factory()->admin()->create();
    $submission = SubmittedQuestion::factory()->pending()->create();

    $service = app(ContentReviewService::class);
    $result = $service->approve($admin, $submission);

    expect($result->review_status)->toBe(ReviewStatus::Approved);
    expect($result->reviewed_by)->toBe($admin->id);

    $fresh = $submission->fresh();
    expect($fresh->review_status)->toBe(ReviewStatus::Approved);
    expect($fresh->reviewed_by)->toBe($admin->id);
});

test('admin can reject a submitted question with required review notes', function () {
    $admin = User::factory()->admin()->create();
    $submission = SubmittedQuestion::factory()->pending()->create();

    $service = app(ContentReviewService::class);
    $result = $service->reject($admin, $submission, 'Option C is ambiguous and does not follow curriculum standard.');

    expect($result->review_status)->toBe(ReviewStatus::Rejected);
    expect($result->reviewed_by)->toBe($admin->id);
    expect($result->review_notes)->toBe('Option C is ambiguous and does not follow curriculum standard.');

    $fresh = $submission->fresh();
    expect($fresh->review_status)->toBe(ReviewStatus::Rejected);
    expect($fresh->reviewed_by)->toBe($admin->id);
    expect($fresh->review_notes)->toBe('Option C is ambiguous and does not follow curriculum standard.');
});

test('rejecting a submitted question requires non-empty review notes', function () {
    $admin = User::factory()->admin()->create();
    $submission = SubmittedQuestion::factory()->pending()->create();

    $service = app(ContentReviewService::class);

    expect(fn () => $service->reject($admin, $submission, '   '))
        ->toThrow(ValidationException::class);

    expect($submission->fresh()->review_status)->toBe(ReviewStatus::Pending);
});

test('cannot publish a pending submitted question', function () {
    $admin = User::factory()->admin()->create();
    $submission = SubmittedQuestion::factory()->pending()->create();

    $service = app(ContentReviewService::class);

    expect(fn () => $service->publish($admin, $submission))
        ->toThrow(ValidationException::class);

    expect(Question::count())->toBe(0);
    expect($submission->fresh()->review_status)->toBe(ReviewStatus::Pending);
});

test('cannot publish a rejected submitted question', function () {
    $admin = User::factory()->admin()->create();
    $submission = SubmittedQuestion::factory()->rejected($admin)->create();

    $service = app(ContentReviewService::class);

    expect(fn () => $service->publish($admin, $submission))
        ->toThrow(ValidationException::class);

    expect(Question::count())->toBe(0);
    expect($submission->fresh()->review_status)->toBe(ReviewStatus::Rejected);
});

test('publishing an approved submitted question creates live question record with matching data', function () {
    $admin = User::factory()->admin()->create();
    $chapter = Chapter::factory()->create();
    $submission = SubmittedQuestion::factory()->approved($admin)->create([
        'chapter_id' => $chapter->id,
        'question_text' => 'What is the powerhouse of the cell?',
        'answer_choices' => [
            'A' => 'Nucleus',
            'B' => 'Mitochondria',
            'C' => 'Ribosome',
            'D' => 'Endoplasmic Reticulum',
        ],
        'correct_choice' => 'B',
        'explanation' => 'Mitochondria generate most of the chemical energy needed to power the cell.',
    ]);

    $service = app(ContentReviewService::class);
    $question = $service->publish($admin, $submission);

    expect($question)->toBeInstanceOf(Question::class);
    expect($question->question_text)->toBe('What is the powerhouse of the cell?');
    expect($question->answer_choices)->toBe([
        'A' => 'Nucleus',
        'B' => 'Mitochondria',
        'C' => 'Ribosome',
        'D' => 'Endoplasmic Reticulum',
    ]);
    expect($question->correct_choice)->toBe('B');
    expect($question->explanation)->toBe('Mitochondria generate most of the chemical energy needed to power the cell.');

    $quiz = Quiz::where('chapter_id', $chapter->id)->first();
    expect($quiz)->not->toBeNull();
    expect($question->quiz_id)->toBe($quiz->id);

    $freshSubmission = $submission->fresh();
    expect($freshSubmission->review_status)->toBe(ReviewStatus::Published);
    expect($freshSubmission->reviewed_by)->toBe($admin->id);
});

test('publishing an approved question attaches to existing quiz if one already exists', function () {
    $admin = User::factory()->admin()->create();
    $chapter = Chapter::factory()->create();
    $existingQuiz = Quiz::factory()->create(['chapter_id' => $chapter->id, 'passing_score' => 80]);

    $submission = SubmittedQuestion::factory()->approved($admin)->create([
        'chapter_id' => $chapter->id,
    ]);

    $service = app(ContentReviewService::class);
    $question = $service->publish($admin, $submission);

    expect($question->quiz_id)->toBe($existingQuiz->id);
    expect(Quiz::where('chapter_id', $chapter->id)->count())->toBe(1);
});

test('publishing twice fails and does not create duplicate live questions', function () {
    $admin = User::factory()->admin()->create();
    $submission = SubmittedQuestion::factory()->approved($admin)->create();

    $service = app(ContentReviewService::class);
    $service->publish($admin, $submission);

    expect(Question::count())->toBe(1);
    expect($submission->fresh()->review_status)->toBe(ReviewStatus::Published);

    expect(fn () => $service->publish($admin, $submission))
        ->toThrow(ValidationException::class);

    expect(Question::count())->toBe(1);
});

test('review status transitions strictly follow pending -> approved -> published and pending -> rejected -> approved -> published', function () {
    $admin = User::factory()->admin()->create();
    $submission = SubmittedQuestion::factory()->pending()->create();

    $service = app(ContentReviewService::class);

    // Initial state
    expect($submission->review_status)->toBe(ReviewStatus::Pending);

    // 1. Pending -> Rejected
    $service->reject($admin, $submission, 'Needs improvement');
    expect($submission->fresh()->review_status)->toBe(ReviewStatus::Rejected);

    // Cannot publish from rejected
    expect(fn () => $service->publish($admin, $submission))->toThrow(ValidationException::class);

    // 2. Rejected -> Approved
    $service->approve($admin, $submission);
    expect($submission->fresh()->review_status)->toBe(ReviewStatus::Approved);

    // 3. Approved -> Published
    $question = $service->publish($admin, $submission);
    expect($submission->fresh()->review_status)->toBe(ReviewStatus::Published);
    expect($question->exists)->toBeTrue();
});

test('admin can render submitted questions list and filter by review status', function () {
    $admin = User::factory()->admin()->create();
    $pending = SubmittedQuestion::factory()->pending()->create(['question_text' => 'Pending Question Text']);
    $published = SubmittedQuestion::factory()->published($admin)->create(['question_text' => 'Published Question Text']);

    $this->actingAs($admin);

    Livewire::test(ListSubmittedQuestions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$pending, $published])
        ->filterTable('review_status', ReviewStatus::Pending->value)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$published]);
});

test('admin can create a submitted question in filament', function () {
    $admin = User::factory()->admin()->create();
    $chapter = Chapter::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreateSubmittedQuestion::class)
        ->assertSuccessful()
        ->fillForm([
            'chapter_id' => $chapter->id,
            'question_text' => 'What is 10 + 10?',
            'answer_choices' => ['A' => '10', 'B' => '20', 'C' => '30'],
            'correct_choice' => 'B',
            'explanation' => '10 plus 10 is equal to 20.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = SubmittedQuestion::where('question_text', 'What is 10 + 10?')->first();
    expect($created)->not->toBeNull();
    expect($created->submitted_by)->toBe($admin->id);
    expect($created->review_status)->toBe(ReviewStatus::Pending);
});

test('admin can approve a question via filament table action', function () {
    $admin = User::factory()->admin()->create();
    $submission = SubmittedQuestion::factory()->pending()->create();

    $this->actingAs($admin);

    Livewire::test(ListSubmittedQuestions::class)
        ->callTableAction('approve', $submission)
        ->assertHasNoTableActionErrors();

    expect($submission->fresh()->review_status)->toBe(ReviewStatus::Approved);
    expect($submission->fresh()->reviewed_by)->toBe($admin->id);
});

test('admin can reject a question via filament table action with notes', function () {
    $admin = User::factory()->admin()->create();
    $submission = SubmittedQuestion::factory()->pending()->create();

    $this->actingAs($admin);

    Livewire::test(ListSubmittedQuestions::class)
        ->callTableAction('reject', $submission, data: [
            'review_notes' => 'Explanation does not provide adequate rationale.',
        ])
        ->assertHasNoTableActionErrors();

    $fresh = $submission->fresh();
    expect($fresh->review_status)->toBe(ReviewStatus::Rejected);
    expect($fresh->reviewed_by)->toBe($admin->id);
    expect($fresh->review_notes)->toBe('Explanation does not provide adequate rationale.');
});

test('admin can publish an approved question via filament table action', function () {
    $admin = User::factory()->admin()->create();
    $chapter = Chapter::factory()->create();
    $submission = SubmittedQuestion::factory()->approved($admin)->create([
        'chapter_id' => $chapter->id,
        'question_text' => 'Publishable question',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListSubmittedQuestions::class)
        ->callTableAction('publish', $submission)
        ->assertHasNoTableActionErrors();

    expect($submission->fresh()->review_status)->toBe(ReviewStatus::Published);
    expect(Question::where('question_text', 'Publishable question')->exists())->toBeTrue();
});
