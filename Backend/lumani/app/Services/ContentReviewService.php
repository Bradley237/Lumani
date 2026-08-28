<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\SubmittedQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentReviewService
{
    /**
     * Approve a submitted question.
     */
    public function approve(User $admin, SubmittedQuestion $submittedQuestion): SubmittedQuestion
    {
        $submittedQuestion->review_status = ReviewStatus::Approved;
        $submittedQuestion->reviewed_by = $admin->id;
        $submittedQuestion->save();

        return $submittedQuestion;
    }

    /**
     * Reject a submitted question with required notes.
     */
    public function reject(User $admin, SubmittedQuestion $submittedQuestion, string $notes): SubmittedQuestion
    {
        $trimmedNotes = trim($notes);

        if ($trimmedNotes === '') {
            throw ValidationException::withMessages([
                'review_notes' => 'Rejection notes are required when rejecting a submitted question.',
            ]);
        }

        $submittedQuestion->review_status = ReviewStatus::Rejected;
        $submittedQuestion->reviewed_by = $admin->id;
        $submittedQuestion->review_notes = $trimmedNotes;
        $submittedQuestion->save();

        return $submittedQuestion;
    }

    /**
     * Publish an approved submitted question to the live quizzes/questions table.
     */
    public function publish(User $admin, SubmittedQuestion $submittedQuestion): Question
    {
        if ($submittedQuestion->review_status !== ReviewStatus::Approved) {
            throw ValidationException::withMessages([
                'review_status' => 'Only approved questions can be published.',
            ]);
        }

        return DB::transaction(function () use ($admin, $submittedQuestion): Question {
            /** @var Quiz $quiz */
            $quiz = Quiz::firstOrCreate(
                ['chapter_id' => $submittedQuestion->chapter_id],
                ['passing_score' => 70]
            );

            /** @var Question $question */
            $question = Question::create([
                'quiz_id' => $quiz->id,
                'question_text' => $submittedQuestion->question_text,
                'answer_choices' => $submittedQuestion->answer_choices,
                'correct_choice' => $submittedQuestion->correct_choice,
                'explanation' => $submittedQuestion->explanation,
            ]);

            $submittedQuestion->review_status = ReviewStatus::Published;
            $submittedQuestion->reviewed_by = $admin->id;
            $submittedQuestion->save();

            return $question;
        });
    }
}
