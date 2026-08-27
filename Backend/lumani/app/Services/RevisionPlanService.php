<?php

namespace App\Services;

use App\Enums\ChapterProgressState;
use App\Models\Chapter;
use App\Models\ChapterProgress;
use App\Models\QuizAttempt;
use App\Models\RevisionPlan;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class RevisionPlanService
{
    /**
     * Generate a personalized algorithmic revision plan for a student.
     *
     * @param  array<int, int>  $availableDays
     */
    public function generate(User $user, int $weeklyAvailableMinutes, array $availableDays): RevisionPlan
    {
        if ($weeklyAvailableMinutes <= 0) {
            throw ValidationException::withMessages([
                'weekly_available_minutes' => 'Weekly available minutes must be greater than 0.',
            ]);
        }

        // Sanitize and validate available days (0-6)
        $cleanDays = collect($availableDays)
            ->map(fn ($d) => (int) $d)
            ->filter(fn ($d) => $d >= 0 && $d <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (empty($cleanDays)) {
            throw ValidationException::withMessages([
                'available_days' => 'Please provide at least one valid study day (0-6).',
            ]);
        }

        /** @var Collection<int, Subject> $subjects */
        $subjects = Subject::with(['chapters.quizzes'])->get();

        // Edge case: No subjects in system
        if ($subjects->isEmpty()) {
            return RevisionPlan::create([
                'user_id' => $user->id,
                'weekly_available_minutes' => $weeklyAvailableMinutes,
                'available_days' => $cleanDays,
                'generated_at' => now(),
                'plan_data' => [
                    [
                        'day' => $cleanDays[0],
                        'subject_id' => 0,
                        'subject_name' => 'General Exploration',
                        'chapter_id' => null,
                        'chapter_title' => 'Explore your first free chapters',
                        'duration_minutes' => $weeklyAvailableMinutes,
                    ],
                ],
            ]);
        }

        // Fetch user performance data
        $userChapterProgress = ChapterProgress::where('user_id', $user->id)->get()->keyBy('chapter_id');
        $userQuizAttempts = QuizAttempt::where('user_id', $user->id)->with('quiz.chapter')->get();

        // 1. Compute Weakness Score and Priority Chapter per Subject
        $subjectAnalysis = [];

        foreach ($subjects as $subject) {
            $chapterIds = $subject->chapters->pluck('id')->all();
            $attemptsForSubject = $userQuizAttempts->filter(fn (QuizAttempt $att) => in_array($att->quiz->chapter_id, $chapterIds, true));

            $hasAttempts = $attemptsForSubject->isNotEmpty();
            $avgScore = $hasAttempts ? (float) $attemptsForSubject->avg('score_percent') : null;

            // Weakness weight:
            // Unattempted ("not yet assessed") = 100 (highest priority)
            // Attempted: (100 - avgScore) with a floor of 10
            if (! $hasAttempts || $avgScore === null) {
                $weaknessWeight = 100.0;
                $isAssessed = false;
            } else {
                $weaknessWeight = max(10.0, 100.0 - $avgScore);
                $isAssessed = true;
            }

            // Find recommended chapter for this subject
            $recommendedChapter = $this->determinePriorityChapter($subject, $userChapterProgress, $userQuizAttempts);

            $subjectAnalysis[] = [
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
                'weakness_weight' => $weaknessWeight,
                'average_score' => $avgScore,
                'is_assessed' => $isAssessed,
                'chapter_id' => $recommendedChapter !== null ? $recommendedChapter->id : null,
                'chapter_title' => $recommendedChapter !== null ? $recommendedChapter->title : 'Foundational Chapter',
            ];
        }

        // Sort subjects by weakness weight DESC (weakest and unattempted first)
        usort($subjectAnalysis, fn (array $a, array $b): int => $b['weakness_weight'] <=> $a['weakness_weight']);

        // 2. Allocate time across student available days
        $planData = $this->buildSchedule(
            $cleanDays,
            $subjectAnalysis,
            $weeklyAvailableMinutes
        );

        /** @var RevisionPlan $plan */
        $plan = RevisionPlan::create([
            'user_id' => $user->id,
            'weekly_available_minutes' => $weeklyAvailableMinutes,
            'available_days' => $cleanDays,
            'generated_at' => now(),
            'plan_data' => $planData,
        ]);

        return $plan;
    }

    /**
     * Determine the priority chapter to focus on for a subject.
     *
     * @param  \Illuminate\Support\Collection<int, ChapterProgress>  $userProgress
     * @param  \Illuminate\Support\Collection<int, QuizAttempt>  $userQuizAttempts
     */
    protected function determinePriorityChapter(
        Subject $subject,
        $userProgress,
        $userQuizAttempts
    ): ?Chapter {
        $chapters = $subject->chapters->sortBy('order');

        if ($chapters->isEmpty()) {
            return null;
        }

        // 1. Incomplete / Not started chapter
        foreach ($chapters as $chapter) {
            $prog = $userProgress->get($chapter->id);
            if (! $prog || $prog->state !== ChapterProgressState::Completed) {
                return $chapter;
            }
        }

        // 2. If all completed, chapter with lowest quiz score
        $chapterScores = [];
        foreach ($chapters as $chapter) {
            $quizIds = $chapter->quizzes->pluck('id')->all();
            $attempts = $userQuizAttempts->filter(fn (QuizAttempt $att) => in_array($att->quiz_id, $quizIds, true));
            if ($attempts->isNotEmpty()) {
                $chapterScores[$chapter->id] = (float) $attempts->avg('score_percent');
            }
        }

        if (! empty($chapterScores)) {
            asort($chapterScores);
            $lowestChapterId = (int) array_key_first($chapterScores);

            return $chapters->firstWhere('id', $lowestChapterId);
        }

        // 3. Fallback to first chapter
        return $chapters->first();
    }

    /**
     * Build the daily schedule distributing minutes according to weakness weights.
     *
     * @param  array<int, int>  $days
     * @param  array<int, array<string, mixed>>  $subjects
     * @return array<int, array{day: int, subject_id: int, subject_name: string, chapter_id: int|null, chapter_title: string|null, duration_minutes: int}>
     */
    protected function buildSchedule(array $days, array $subjects, int $totalMinutes): array
    {
        $numDays = count($days);
        $numSubjects = count($subjects);

        // Assign a subject to each day (cyclically over sorted weakness list)
        $dayAssignments = [];
        $totalAssignedWeight = 0.0;

        for ($i = 0; $i < $numDays; $i++) {
            $subj = $subjects[$i % $numSubjects];
            $dayAssignments[] = [
                'day' => $days[$i],
                'subject_id' => $subj['subject_id'],
                'subject_name' => $subj['subject_name'],
                'chapter_id' => $subj['chapter_id'],
                'chapter_title' => $subj['chapter_title'],
                'weight' => (float) $subj['weakness_weight'],
            ];
            $totalAssignedWeight += (float) $subj['weakness_weight'];
        }

        // Compute proportional minutes
        $allocatedMinutes = 0;
        $schedule = [];

        foreach ($dayAssignments as $index => $item) {
            if ($index === $numDays - 1) {
                // Last day takes the exact remaining balance so total equals totalMinutes
                $duration = max(1, $totalMinutes - $allocatedMinutes);
            } else {
                $fraction = $totalAssignedWeight > 0 ? ($item['weight'] / $totalAssignedWeight) : (1 / $numDays);
                $duration = (int) round($fraction * $totalMinutes);
                $duration = max(1, $duration);
                $allocatedMinutes += $duration;
            }

            $schedule[] = [
                'day' => $item['day'],
                'subject_id' => (int) $item['subject_id'],
                'subject_name' => (string) $item['subject_name'],
                'chapter_id' => $item['chapter_id'] !== null ? (int) $item['chapter_id'] : null,
                'chapter_title' => $item['chapter_title'] !== null ? (string) $item['chapter_title'] : null,
                'duration_minutes' => $duration,
            ];
        }

        return $schedule;
    }

    /**
     * Get the student's latest revision plan.
     */
    public function getLatestPlan(User $user): ?RevisionPlan
    {
        return RevisionPlan::where('user_id', $user->id)
            ->latest('id')
            ->first();
    }
}
