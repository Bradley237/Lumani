<?php

use App\Enums\ChallengeAttemptStatus;
use App\Enums\ChallengeQuestionType;
use App\Enums\ChapterProgressState;
use App\Enums\CoinTransactionType;
use App\Enums\ExamSessionStatus;
use App\Enums\PastPaperQuestionType;
use App\Enums\ReviewStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Filament\Pages\GradingQueue;
use App\Filament\Resources\SubmittedQuestions\SubmittedQuestionResource;
use App\Filament\Widgets\DashboardStatsOverviewWidget;
use App\Filament\Widgets\NeedsYourAttentionWidget;
use App\Filament\Widgets\PopularSubjectsWidget;
use App\Filament\Widgets\StudentRegistrationsChartWidget;
use App\Models\AiTutorConversation;
use App\Models\Chapter;
use App\Models\ChapterProgress;
use App\Models\CoinTransaction;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\PastPaper;
use App\Models\PastPaperQuestion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\SubmittedQuestion;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserChallengeAnswer;
use App\Models\UserChallengeAttempt;
use App\Models\UserChapterUnlock;
use App\Models\WeeklyChallenge;
use App\Models\WeeklyChallengeQuestion;
use Livewire\Livewire;

test('admin can access dashboard and see all analytics widgets', function () {
    $admin = User::factory()->admin()->create(['coin_balance' => 0]);

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSeeLivewire(NeedsYourAttentionWidget::class);
    $response->assertSeeLivewire(DashboardStatsOverviewWidget::class);
    $response->assertSeeLivewire(StudentRegistrationsChartWidget::class);
    $response->assertSeeLivewire(PopularSubjectsWidget::class);
});

test('dashboard stats overview computes accurate live platform statistics', function () {
    $admin = User::factory()->admin()->create(['coin_balance' => 0]);

    // 1. Students: 3 created this week, 1 created last week
    $student1 = User::factory()->student()->create(['coin_balance' => 300, 'created_at' => now()->startOfWeek()]);
    $student2 = User::factory()->student()->create(['coin_balance' => 200, 'created_at' => now()]);
    $student3 = User::factory()->student()->create(['coin_balance' => 500, 'created_at' => now()]);
    $oldStudent = User::factory()->student()->create(['coin_balance' => 100, 'created_at' => now()->subWeeks(2)]);

    // 2. Subscriptions: 2 active 2k, 1 active 5k, 1 expired
    Subscription::factory()->create([
        'user_id' => $student1->id,
        'tier' => SubscriptionTier::Tier2000,
        'status' => SubscriptionStatus::Active,
        'end_date' => now()->addDays(20),
    ]);
    Subscription::factory()->create([
        'user_id' => $student2->id,
        'tier' => SubscriptionTier::Tier2000,
        'status' => SubscriptionStatus::Active,
        'end_date' => now()->addDays(15),
    ]);
    Subscription::factory()->create([
        'user_id' => $student3->id,
        'tier' => SubscriptionTier::Tier5000,
        'status' => SubscriptionStatus::Active,
        'end_date' => now()->addDays(25),
    ]);
    Subscription::factory()->create([
        'user_id' => $oldStudent->id,
        'tier' => SubscriptionTier::Tier2000,
        'status' => SubscriptionStatus::Expired,
        'end_date' => now()->subDays(5),
    ]);

    // 3. Coin transactions: spent this week (negative amount) and old spent
    CoinTransaction::factory()->create([
        'user_id' => $student1->id,
        'amount' => -150,
        'type' => CoinTransactionType::SpentUnlock,
        'created_at' => now(),
    ]);
    CoinTransaction::factory()->create([
        'user_id' => $student2->id,
        'amount' => -50,
        'type' => CoinTransactionType::SpentAiTutor,
        'created_at' => now()->startOfWeek(),
    ]);
    CoinTransaction::factory()->create([
        'user_id' => $oldStudent->id,
        'amount' => -100,
        'type' => CoinTransactionType::SpentUnlock,
        'created_at' => now()->subWeeks(2),
    ]);

    // 4. Chapters completed this week
    $chapter = Chapter::factory()->create();
    ChapterProgress::factory()->create([
        'user_id' => $student1->id,
        'chapter_id' => $chapter->id,
        'state' => ChapterProgressState::Completed,
        'completed_at' => now(),
    ]);
    ChapterProgress::factory()->create([
        'user_id' => $student2->id,
        'chapter_id' => $chapter->id,
        'state' => ChapterProgressState::Completed,
        'completed_at' => now()->subWeeks(2),
    ]);

    // 5. Quiz attempts this week
    $quiz = Quiz::factory()->create(['chapter_id' => $chapter->id]);
    QuizAttempt::factory()->create([
        'user_id' => $student1->id,
        'quiz_id' => $quiz->id,
        'score_percent' => 80.0,
        'created_at' => now(),
    ]);
    QuizAttempt::factory()->create([
        'user_id' => $student2->id,
        'quiz_id' => $quiz->id,
        'score_percent' => 90.0,
        'created_at' => now(),
    ]);
    QuizAttempt::factory()->create([
        'user_id' => $oldStudent->id,
        'quiz_id' => $quiz->id,
        'score_percent' => 40.0,
        'created_at' => now()->subWeeks(2),
    ]);

    // 6. AI Tutor conversations started this week
    AiTutorConversation::factory()->create([
        'user_id' => $student1->id,
        'created_at' => now(),
    ]);
    AiTutorConversation::factory()->create([
        'user_id' => $student2->id,
        'created_at' => now()->startOfWeek(),
    ]);
    AiTutorConversation::factory()->create([
        'user_id' => $oldStudent->id,
        'created_at' => now()->subWeeks(2),
    ]);

    $this->actingAs($admin);

    $component = Livewire::test(DashboardStatsOverviewWidget::class);

    $component->assertSuccessful();

    // Total students: 4 students, 3 this week
    $component->assertSee('Total Students');
    $component->assertSee('4');
    $component->assertSee('+3 registered this week');

    // Active subscriptions: 3 active (2k: 2, 5k: 1)
    $component->assertSee('Active Subscriptions');
    $component->assertSee('3');
    $component->assertSee('2k FCFA: 2 | 5k FCFA: 1');

    // Coins in circulation: 300+200+500+100 = 1,100; spent this week: 150 + 50 = 200
    $component->assertSee('Coins in Circulation');
    $component->assertSee('1,100');
    $component->assertSee('200 spent this week');

    // Chapters completed this week: 1
    $component->assertSee('Chapters Completed');
    $component->assertSee('1');

    // Average quiz score: (80 + 90) / 2 = 85.0%
    $component->assertSee('Avg Quiz Score');
    $component->assertSee('85%');

    // AI Tutor conversations: 2 this week
    $component->assertSee('AI Tutor Conversations');
    $component->assertSee('2');
});

test('needs your attention widget surfaces pending submitted questions and unified grading queue items', function () {
    $admin = User::factory()->admin()->create(['coin_balance' => 0]);
    $student = User::factory()->student()->create();
    $chapter = Chapter::factory()->create();

    // 1. Pending submitted questions: 2 pending, 1 approved
    SubmittedQuestion::factory()->create([
        'submitted_by' => $student->id,
        'chapter_id' => $chapter->id,
        'review_status' => ReviewStatus::Pending,
    ]);
    SubmittedQuestion::factory()->create([
        'submitted_by' => $student->id,
        'chapter_id' => $chapter->id,
        'review_status' => ReviewStatus::Pending,
    ]);
    SubmittedQuestion::factory()->create([
        'submitted_by' => $student->id,
        'chapter_id' => $chapter->id,
        'review_status' => ReviewStatus::Approved,
    ]);

    // 2. Weekly challenge ungraded structural answers: 1 submitted attempt with ungraded structural answer
    $challenge = WeeklyChallenge::factory()->create();
    $challengeQuestion = WeeklyChallengeQuestion::factory()->create([
        'weekly_challenge_id' => $challenge->id,
        'type' => ChallengeQuestionType::Structural->value,
    ]);
    $attempt = UserChallengeAttempt::factory()->create([
        'user_id' => $student->id,
        'weekly_challenge_id' => $challenge->id,
        'status' => ChallengeAttemptStatus::Submitted->value,
    ]);
    UserChallengeAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $challengeQuestion->id,
        'points_awarded' => null,
    ]);

    // 3. Exam session ungraded structural answers: 1 submitted session with ungraded structural answer
    $pastPaper = PastPaper::factory()->create(['subject_id' => $chapter->subject_id]);
    $examQuestion = PastPaperQuestion::factory()->create([
        'past_paper_id' => $pastPaper->id,
        'type' => PastPaperQuestionType::Structural->value,
    ]);
    $examSession = ExamSession::factory()->create([
        'user_id' => $student->id,
        'past_paper_id' => $pastPaper->id,
        'status' => ExamSessionStatus::Submitted->value,
    ]);
    ExamSessionAnswer::factory()->create([
        'exam_session_id' => $examSession->id,
        'question_id' => $examQuestion->id,
        'points_awarded' => null,
    ]);

    $this->actingAs($admin);

    $component = Livewire::test(NeedsYourAttentionWidget::class);

    $component->assertSuccessful();

    // 2 pending questions
    $component->assertSee('Pending Questions Review');
    $component->assertSee('2');
    $component->assertSee(SubmittedQuestionResource::getUrl('index'));

    // 2 total ungraded structural answers (1 challenge + 1 exam)
    $component->assertSee('Ungraded Structural Answers');
    $component->assertSee('2');
    $component->assertSee('Weekly Challenges: 1 | Exams: 1');
    $component->assertSee(GradingQueue::getUrl());
});

test('student registrations chart widget outputs accurate 30-day timeline', function () {
    $admin = User::factory()->admin()->create(['coin_balance' => 0]);

    // Create students across different dates
    User::factory()->student()->create(['created_at' => now()]);
    User::factory()->student()->create(['created_at' => now()]);
    User::factory()->student()->create(['created_at' => now()->subDays(5)]);
    User::factory()->student()->create(['created_at' => now()->subDays(40)]); // Outside 30 days window

    $this->actingAs($admin);

    $component = Livewire::test(StudentRegistrationsChartWidget::class);

    $component->assertSuccessful();
    $component->assertSee('Student Registrations (Last 30 Days)');

    $chartData = $component->instance()->getData();
    expect($chartData)->toHaveKeys(['datasets', 'labels']);
    expect($chartData['labels'])->toHaveCount(30);
    expect($chartData['datasets'][0]['data'])->toHaveCount(30);

    // Last index (today) should have count 2
    $lastIndex = count($chartData['datasets'][0]['data']) - 1;
    expect($chartData['datasets'][0]['data'][$lastIndex])->toBe(2);
});

test('popular subjects widget ranks subjects by chapter unlocks this month', function () {
    $admin = User::factory()->admin()->create(['coin_balance' => 0]);
    $student1 = User::factory()->student()->create();
    $student2 = User::factory()->student()->create();

    $subjectA = Subject::factory()->create(['name' => 'Mathematics GCE']);
    $subjectB = Subject::factory()->create(['name' => 'Physics GCE']);
    $subjectC = Subject::factory()->create(['name' => 'Chemistry GCE']);

    $chapterA1 = Chapter::factory()->create(['subject_id' => $subjectA->id]);
    $chapterA2 = Chapter::factory()->create(['subject_id' => $subjectA->id]);
    $chapterB1 = Chapter::factory()->create(['subject_id' => $subjectB->id]);
    $chapterC1 = Chapter::factory()->create(['subject_id' => $subjectC->id]);

    // Subject A: 3 unlocks this month
    UserChapterUnlock::factory()->create([
        'user_id' => $student1->id,
        'chapter_id' => $chapterA1->id,
        'created_at' => now(),
    ]);
    UserChapterUnlock::factory()->create([
        'user_id' => $student1->id,
        'chapter_id' => $chapterA2->id,
        'created_at' => now(),
    ]);
    UserChapterUnlock::factory()->create([
        'user_id' => $student2->id,
        'chapter_id' => $chapterA1->id,
        'created_at' => now()->startOfMonth(),
    ]);

    // Subject B: 1 unlock this month
    UserChapterUnlock::factory()->create([
        'user_id' => $student1->id,
        'chapter_id' => $chapterB1->id,
        'created_at' => now(),
    ]);

    // Subject C: 1 unlock 2 months ago (should not count for this month)
    UserChapterUnlock::factory()->create([
        'user_id' => $student1->id,
        'chapter_id' => $chapterC1->id,
        'created_at' => now()->subMonths(2),
    ]);

    $this->actingAs($admin);

    $component = Livewire::test(PopularSubjectsWidget::class);

    $component->assertSuccessful();
    $component->assertSee('Popular Subjects (Chapter Unlocks This Month)');
    $component->assertSee('Mathematics GCE');
    $component->assertSee('Physics GCE');
    $component->assertSee('Chemistry GCE');
});
