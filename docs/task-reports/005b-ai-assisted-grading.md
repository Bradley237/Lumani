# Task 005b — Marking Scheme & AI-Assisted Grading for Weekly Challenges

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication**: Laravel Sanctum Personal Access Tokens (`Bearer <token>`)
- **Key Invariants**:
  - `marking_scheme` (nullable text) is stored on `weekly_challenge_questions` to provide reference model answers/criteria for structural questions.
  - `suggested_points` and `suggested_justification` (nullable) are stored on `user_challenge_answers` as non-destructive AI guidance.
  - `GradingAssistantService` automatically evaluates student structural submissions against the question text, marking scheme, and maximum points using Gemini API (`gemini-2.5-flash`).
  - AI grading is resilient: API failure/timeouts will never block student submission or teacher grading.
  - Teachers retain 100% authority: the Filament Grading Queue pre-fills with the AI suggestion, but the teacher must submit/confirm the grade (or override it). Overrides keep the original AI justification for student feedback.
  - `GET /api/challenges/{id}/result` exposes the AI justification as `feedback` for structural answers, omitting it cleanly if no suggestion was generated.

---

## 1. What Was Built

### 1.1 Database Migrations & Schema
1. **`2026_08_27_000015_add_marking_scheme_to_weekly_challenge_questions_table.php`**:
   - Added `marking_scheme` (`text`, nullable) to `weekly_challenge_questions`.
2. **`2026_08_27_000016_add_suggested_columns_to_user_challenge_answers_table.php`**:
   - Added `suggested_points` (`integer`, nullable) and `suggested_justification` (`text`, nullable) to `user_challenge_answers`.

### 1.2 Model & Configuration Updates
- **`WeeklyChallengeQuestion`**: Added `marking_scheme` to `$fillable` and PHPDoc.
- **`UserChallengeAnswer`**: Added `suggested_points`, `suggested_justification` to `$fillable`, `casts()`, and PHPDoc.
- **`config/services.php`**: Added `gemini` config (`api_key`, `model: gemini-2.5-flash`).
- **`.env` / `.env.example`**: Declared `GEMINI_API_KEY` and `GEMINI_MODEL`.

### 1.3 AI Service Layer (`App\Services\GradingAssistantService`)
- Implemented `suggestScore(WeeklyChallengeQuestion $question, string $studentAnswer): ?array`:
  - Constructs structured prompt with question text, marking scheme, max points, and student answer.
  - Requests JSON output: `{"suggested_points": int, "justification": string}`.
  - Sanitizes and bounds suggested score between `0` and `$question->max_points`.
  - Comprehensive error handling: catches timeouts, invalid responses, network errors, and returns `null` without throwing exceptions.

### 1.4 Challenge Service Integration (`App\Services\ChallengeService`)
- **`submitAttempt()`**: Automatically queries `GradingAssistantService::suggestScore()` upon student submission for structural questions with non-empty text, persisting `suggested_points` and `suggested_justification`.
- **`gradeStructuralAnswer()`**: Updates `points_awarded` with admin input while preserving `suggested_justification` and `suggested_points`.
- **`getAttemptResult()`**: Appends `'feedback' => $ans->suggested_justification` for structural answers when available.

### 1.5 Filament Admin Form & Grading Queue
- **`WeeklyChallengeForm`**: Added `marking_scheme` Textarea in the question repeater, visible for structural questions.
- **`GradingQueue`**:
  - Displays Question text, Marking Scheme (when defined), Student Answer, and AI Suggestion box (suggested points & justification).
  - Pre-fills the points input field with `suggested_points` for one-click approval or easy override.

---

## 2. API Response Specification Updates

### `GET /api/challenges/{id}/result`
For graded attempts with structural questions, returns `feedback` alongside `points_awarded`:

```json
{
  "has_attempted": true,
  "status": "graded",
  "attempt": {
    "id": 5,
    "started_at": "2026-08-27T03:20:00.000000Z",
    "submitted_at": "2026-08-27T03:35:00.000000Z",
    "status": "graded",
    "total_score_percent": 90.0,
    "reward_coins_awarded": 50,
    "answers": [
      {
        "question_id": 10,
        "type": "mcq",
        "question_text": "What is the SI unit of momentum?",
        "selected_choice": "A",
        "answer_text": null,
        "points_awarded": 10,
        "max_points": 10
      },
      {
        "question_id": 11,
        "type": "structural",
        "question_text": "State Newton's second law of motion and derive F = ma.",
        "selected_choice": null,
        "answer_text": "Force is proportional to the rate of change of momentum...",
        "points_awarded": 18,
        "max_points": 20,
        "feedback": "Accurate derivation of F=ma from rate of change of momentum; minor step omitted in constant of proportionality."
      }
    ]
  }
}
```

If no AI suggestion was generated (API unavailable or timeout), `feedback` is omitted from the JSON answer object without breaking client parsing.

---

## 3. Why This Approach

1. **Pre-computed Asynchronous Evaluation**:
   Evaluating the submission at student submission time guarantees that suggestions are already cached in the database when an admin opens the grading queue. The admin experiences zero latency while reviewing.
2. **Human-in-the-Loop Safeguard**:
   The AI suggestion is strictly advisory. Points are never committed to student grades automatically; an admin must explicitly submit the grade.
3. **Decoupled Fallback**:
   If Gemini API rate limits or errors occur, `suggested_points` is left as `null`. The admin can simply grade manually, and the system continues normal operations without failing student submissions.
4. **Student-Friendly Feedback**:
   Internal field names like `suggested_justification` are mapped to `feedback` in student-facing APIs.

---

## 4. Verification & Test Results

- **Automated Tests**: 93 passing feature and unit tests (533 assertions) across the suite:
  - `tests/Feature/Api/ChallengeAiGradingTest.php`:
    - Structural answer submission triggers Gemini API call and stores suggested points & justification.
    - Graceful fallback when Gemini API fails.
    - Filament grading queue displays suggestion and pre-fills score.
    - Admin override updates final points while preserving original justification.
    - `/result` endpoint returns `feedback` and handles missing suggestions cleanly.
- **Static Analysis**: PHPStan passing at level configured with 0 errors (`phpstan analyse --memory-limit=512M`).
- **Code Style**: 100% formatted via Laravel Pint.

---

## 5. Open Questions & Blockers
- None.
