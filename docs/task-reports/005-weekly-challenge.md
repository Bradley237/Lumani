# Task 005 — Weekly Challenge System Specification & Report

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication**: Laravel Sanctum Personal Access Tokens (`Bearer <token>`)
- **Key Invariants**:
  - Exactly 1 attempt per student per weekly challenge (`unique(['user_id', 'weekly_challenge_id'])`).
  - Answer security: `correct_choice` is stripped from questions in student-facing endpoints.
  - MCQ-only challenges are auto-graded immediately on submission.
  - Challenges with structural/essay questions remain in `submitted` (pending grading) until an admin grades every structural answer in the Filament grading queue.
  - Reward Tiers (awarded atomically via `CoinService` with type `earned_challenge`):
    - `Score >= 95%`: 100 coins
    - `70% <= Score < 95%`: 50 coins
    - `Score < 70%`: 0 coins

---

## 1. What Was Built

### 1.1 Database Migrations & Schema
1. **`weekly_challenges` Table**:
   - `id`: Auto-increment primary key
   - `subject_id`: Foreign key referencing `subjects.id` (cascade on delete)
   - `exam_subsystem`: Target exam subsystem (`anglophone`, `francophone`, `general`)
   - `level`: Academic level (`O-Level`, `A-Level`, `BEPC`, `Probatoire`, `Baccalaureat`)
   - `title`: Challenge title
   - `time_limit_minutes`: Integer time duration for student attempts (default 30)
   - `week_start_date`, `week_end_date`: Active datetime window
   - `status`: Enum (`draft`, `published`, `closed`, default `draft`)
   - `created_by`: Foreign key referencing admin user in `users.id`
   - `timestamps`
2. **`weekly_challenge_questions` Table**:
   - `id`: Primary key
   - `weekly_challenge_id`: Foreign key referencing `weekly_challenges.id` (cascade on delete)
   - `type`: Enum (`mcq`, `structural`)
   - `question_text`: Text content
   - `options`: JSON map for MCQ choices (e.g. `{"A": "...", "B": "..."}`)
   - `correct_choice`: Key of correct answer (e.g. `A`, `B`, `C`, `D`), nullable
   - `max_points`: Integer points allocated to question (default 10)
   - `order`: Question display order
   - `timestamps`
3. **`user_challenge_attempts` Table**:
   - `id`: Primary key
   - `user_id`: Foreign key referencing `users.id` (cascade on delete)
   - `weekly_challenge_id`: Foreign key referencing `weekly_challenges.id` (cascade on delete)
   - `started_at`: Datetime of attempt commencement
   - `submitted_at`: Datetime of attempt completion (nullable)
   - `status`: Enum (`in_progress`, `submitted`, `graded`)
   - `total_score_percent`: Decimal(5,2) final percentage score (nullable)
   - `reward_coins_awarded`: Integer coins earned (nullable)
   - `timestamps`
   - Unique constraint: `[user_id, weekly_challenge_id]`
4. **`user_challenge_answers` Table**:
   - `id`: Primary key
   - `attempt_id`: Foreign key referencing `user_challenge_attempts.id` (cascade on delete)
   - `question_id`: Foreign key referencing `weekly_challenge_questions.id` (cascade on delete)
   - `selected_choice`: Student choice key (MCQ), nullable
   - `answer_text`: Student essay/open response text (Structural), nullable
   - `points_awarded`: Integer points awarded (auto-graded for MCQ, manual for structural), nullable
   - `timestamps`
   - Unique constraint: `[attempt_id, question_id]`

### 1.2 Enums & Models
- `app/Enums/ChallengeStatus.php` (`draft`, `published`, `closed`)
- `app/Enums/ChallengeQuestionType.php` (`mcq`, `structural`)
- `app/Enums/ChallengeAttemptStatus.php` (`in_progress`, `submitted`, `graded`)
- Updated `app/Enums/CoinTransactionType.php` with `EarnedChallenge = 'earned_challenge'`
- Models: `WeeklyChallenge`, `WeeklyChallengeQuestion`, `UserChallengeAttempt`, `UserChallengeAnswer` with relations and casts.

### 1.3 Service Layer (`app/Services/ChallengeService.php`)
- `startAttempt(User $user, WeeklyChallenge $challenge): UserChallengeAttempt`
- `submitAttempt(User $user, UserChallengeAttempt $attempt, array $answers): array`
- `gradeStructuralAnswer(User $admin, UserChallengeAnswer $answer, int $pointsAwarded): array`
- `calculateCoinsForScore(float $scorePercent): int`
- `getPublishedChallengesForUser(User $user): array`
- `getSanitizedQuestions(WeeklyChallenge $challenge): array`
- `getAttemptResult(User $user, WeeklyChallenge $challenge): array`

### 1.4 Filament Admin Interface
- **Weekly Challenge Resource (`/admin/weekly-challenges`)**: Full CRUD with questions repeater for adding MCQ and structural questions with point allocations and week scheduling.
- **Grading Queue Page (`/admin/grading-queue`)**: Dedicated admin view listing all pending structural answers with student details, question, submitted essay text, point validation input, and one-click submission that auto-finalizes scores and triggers coin grants.

---

## 2. API Endpoints Specification

### Headers Required
```http
Authorization: Bearer <token>
Accept: application/json
```

---

### Endpoint 1: `GET /api/challenges`
Lists current active published weekly challenges matching the student's subsystem and level with attempt status.

#### Sample Response (`200 OK`)
```json
{
  "challenges": [
    {
      "id": 1,
      "title": "Weekly Challenge: Physics Mechanics Round 1",
      "subject": {
        "id": 2,
        "name": "Physics"
      },
      "exam_subsystem": "anglophone",
      "level": "A-Level",
      "time_limit_minutes": 30,
      "week_start_date": "2026-08-25T00:00:00.000000Z",
      "week_end_date": "2026-08-31T23:59:59.000000Z",
      "has_attempted": false,
      "attempt_status": null,
      "attempt_id": null
    }
  ]
}
```

---

### Endpoint 2: `POST /api/challenges/{id}/start`
Starts a timed challenge attempt and returns challenge metadata and questions without leaking correct answers.

#### Sample Response (`201 Created`)
```json
{
  "message": "Weekly challenge started.",
  "attempt": {
    "id": 5,
    "started_at": "2026-08-27T03:20:00.000000Z",
    "status": "in_progress"
  },
  "challenge": {
    "id": 1,
    "title": "Weekly Challenge: Physics Mechanics Round 1",
    "time_limit_minutes": 30,
    "questions": [
      {
        "id": 10,
        "type": "mcq",
        "question_text": "What is the SI unit of momentum?",
        "options": {
          "A": "kg·m/s",
          "B": "N·m",
          "C": "J/s",
          "D": "kg/s"
        },
        "max_points": 10,
        "order": 1
      },
      {
        "id": 11,
        "type": "structural",
        "question_text": "State Newton's second law of motion and derive F = ma.",
        "options": null,
        "max_points": 20,
        "order": 2
      }
    ]
  }
}
```

---

### Endpoint 3: `POST /api/challenges/{id}/submit`
Submits student answers before the time limit expires.

#### Request Body
```json
{
  "answers": [
    {
      "question_id": 10,
      "selected_choice": "A"
    },
    {
      "question_id": 11,
      "answer_text": "Force is the rate of change of momentum..."
    }
  ]
}
```

#### Response for MCQ-Only Challenge (`200 OK`)
```json
{
  "status": "graded",
  "message": "Challenge submitted and graded successfully.",
  "total_score_percent": 100.0,
  "reward_coins_awarded": 100,
  "coin_balance": 150
}
```

#### Response for Challenge with Structural Questions (`200 OK`)
```json
{
  "status": "submitted",
  "message": "Challenge submitted successfully. Results will be available after teacher grading.",
  "total_score_percent": null,
  "reward_coins_awarded": null
}
```

---

### Endpoint 4: `GET /api/challenges/{id}/result`
Checks status and scores of an attempt (used by student app for polling or reviewing graded attempts).

#### Sample Response for Graded Attempt (`200 OK`)
```json
{
  "has_attempted": true,
  "status": "graded",
  "attempt": {
    "id": 5,
    "started_at": "2026-08-27T03:20:00.000000Z",
    "submitted_at": "2026-08-27T03:35:00.000000Z",
    "status": "graded",
    "total_score_percent": 95.0,
    "reward_coins_awarded": 100,
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
        "answer_text": "Force is the rate of change of momentum...",
        "points_awarded": 18,
        "max_points": 20
      }
    ]
  }
}
```

---

## 3. Why This Approach

1. **Strict Client Answer Protection**:
   `WeeklyChallengeQuestion` stores `correct_choice` for automated backend grading, but the service method `getSanitizedQuestions()` maps questions to guarantee that `correct_choice` is never leaked in API responses.
2. **Hybrid Instant & Asynchronous Manual Grading**:
   Students get immediate gratification (instant score & coin credit) for standard MCQ quizzes, while retaining complete support for open-ended essay and calculation questions via the Filament grading queue.
3. **Strict Time Limit Enforcement**:
   Submission checks elapsed time against `started_at + time_limit_minutes` with a small 60s tolerance for network latency.
4. **Guaranteed Idempotent Grading & Coin Grants**:
   Points entered across multiple teacher reviews accumulate; once every answer is marked, the attempt transitions to `graded` and credits student coins in a single atomic database transaction.

---

## 4. Verification & Test Results
- **Automated Tests**: 86 passing tests (493 assertions) across `ChallengeTest.php`, `SpendAndAccessTest.php`, `MissionsAndStreakTest.php`, `AuthAndContentTest.php`, `ResourceLoadingTest.php`.
- **Static Analysis**: PHPStan passing at 0 errors (`--memory-limit=512M`).
- **Code Style**: 100% formatted via Laravel Pint.

---

## 5. Open Questions & Blockers
- None.
