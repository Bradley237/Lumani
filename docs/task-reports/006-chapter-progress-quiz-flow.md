# Task 006 — Chapter Progress & Quiz-Taking Flow

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication**: Laravel Sanctum Personal Access Tokens (`Bearer <token>`)
- **Key Invariants**:
  - Chapter progress follows the `ChapterProgressState` enum (`not_started`, `in_progress`, `completed`).
  - Chapter access is gated by `AccessControlService::canAccessChapter`. Locked chapters reject quiz viewing, submission, and touch operations with a `422 Unprocessable Content` response.
  - Quiz grading awards **10 XP** per correct answer plus a flat **20 XP** completion bonus.
  - Chapter `xp_reward` is awarded strictly on the **first time** a chapter reaches `completed` state. Retakes award quiz XP but never double-award chapter XP.
  - All XP awards are routed through `XpService::award()` to guarantee atomic state mutation and automatic conversion of 1,500 XP thresholds (1,500 XP = 50 coins) recorded in `coin_transactions`.
  - Question fetching on `GET /api/quizzes/{id}` strictly omits `correct_choice` and `explanation` to prevent client-side answer leakage.

---

## 1. What Was Built

### 1.1 Database Migrations & Schema
1. **`2026_08_27_000017_create_chapter_progress_table.php`**:
   - `id`: Auto-incrementing primary key
   - `user_id`: Foreign key referencing `users.id` (cascade on delete)
   - `chapter_id`: Foreign key referencing `chapters.id` (cascade on delete)
   - `state`: String/enum (`not_started`, `in_progress`, `completed`, default `not_started`)
   - `last_accessed_at`: Nullable timestamp
   - `completed_at`: Nullable timestamp
   - `timestamps`: `created_at`, `updated_at`
   - Unique composite index on `[user_id, chapter_id]`
2. **`2026_08_27_000018_create_quiz_attempts_table.php`**:
   - `id`: Primary key
   - `user_id`: Foreign key referencing `users.id` (cascade on delete)
   - `quiz_id`: Foreign key referencing `quizzes.id` (cascade on delete)
   - `score_percent`: Decimal (5, 2)
   - `correct_count`: Unsigned integer
   - `total_questions`: Unsigned integer
   - `xp_earned`: Unsigned integer
   - `submitted_at`: Timestamp
   - `timestamps`: `created_at`, `updated_at`
3. **`2026_08_27_000019_create_quiz_attempt_answers_table.php`**:
   - `id`: Primary key
   - `quiz_attempt_id`: Foreign key referencing `quiz_attempts.id` (cascade on delete)
   - `question_id`: Foreign key referencing `questions.id` (cascade on delete)
   - `selected_choice`: Nullable string
   - `is_correct`: Boolean
   - `timestamps`: `created_at`, `updated_at`

### 1.2 Models & Enums
- **`App\Enums\ChapterProgressState`**: Enum with cases `NotStarted ('not_started')`, `InProgress ('in_progress')`, `Completed ('completed')`.
- **`App\Models\ChapterProgress`**: Eloquent model with relationships to `User` and `Chapter`.
- **`App\Models\QuizAttempt`**: Eloquent model with relationships to `User`, `Quiz`, and `QuizAttemptAnswer` (`answers`).
- **`App\Models\QuizAttemptAnswer`**: Eloquent model with relationships to `QuizAttempt` and `Question`.
- Updated **`Chapter`**, **`Quiz`**, and **`User`** models with corresponding `HasMany` relationships (`progress`, `attempts`, `chapterProgress`, `quizAttempts`).

### 1.3 Service Layer (`app/Services`)
- **`XpService`**:
  - `award(User $user, int $amount, ?Model $reference = null)`: Mutates `experience_points` inside a locked database transaction (`$user->lockForUpdate()`), evaluates available unconverted XP against 1,500-unit boundaries, awards 50 coins per 1,500 XP chunk via `CoinService`, and records `CoinTransactionType::EarnedXpConversion`.
- **`QuizService`**:
  - `touchChapter(User $user, Chapter $chapter)`: Checks access control, marks progress as `in_progress` (without reverting `completed`), and updates `last_accessed_at`.
  - `getQuizForStudent(User $user, Quiz $quiz)`: Checks access control, sanitizes question payload (stripping `correct_choice` and `explanation`).
  - `submitQuiz(User $user, Quiz $quiz, array $answers)`: Grades answers, computes score percentage, awards quiz XP (10 XP/correct + 20 XP bonus) and chapter `xp_reward` on first completion, upserts `ChapterProgress`, persists attempt and answer records, and triggers XP award.
  - `getStudentProgress(User $user)`: Computes chapter progress statistics across all subjects and chapters for the student dashboard.

---

## 2. API Endpoints Specification

### Headers Required
```http
Authorization: Bearer <token>
Accept: application/json
```

---

### Endpoint 1: `POST /api/chapters/{id}/touch`
Marks a chapter as `in_progress` and updates `last_accessed_at` when the student views chapter content.

#### Response (`200 OK`)
```json
{
  "message": "Chapter progress updated.",
  "progress": {
    "chapter_id": 1,
    "state": "in_progress",
    "last_accessed_at": "2026-08-27T22:30:00.000000Z",
    "completed_at": null
  }
}
```

#### Error Response (`422 Unprocessable Content` if locked)
```json
{
  "message": "You must unlock this chapter before accessing it.",
  "errors": {
    "chapter": [
      "You must unlock this chapter before accessing it."
    ]
  }
}
```

---

### Endpoint 2: `GET /api/quizzes/{id}`
Returns quiz metadata and questions sanitized for the student (omitting `correct_choice` and `explanation`).

#### Response (`200 OK`)
```json
{
  "id": 1,
  "chapter_id": 2,
  "passing_score": 75,
  "total_questions": 2,
  "questions": [
    {
      "id": 10,
      "question_text": "What is the speed of light in vacuum?",
      "answer_choices": {
        "A": "3.0 x 10^8 m/s",
        "B": "3.0 x 10^6 m/s",
        "C": "3.0 x 10^5 m/s",
        "D": "3.0 x 10^7 m/s"
      }
    },
    {
      "id": 11,
      "question_text": "What is Newton's first law also known as?",
      "answer_choices": {
        "A": "Law of Action-Reaction",
        "B": "Law of Inertia",
        "C": "Law of Gravitation",
        "D": "Law of Acceleration"
      }
    }
  ]
}
```

---

### Endpoint 3: `POST /api/quizzes/{id}/submit`
Submits student answers, computes grade and score percentage, awards XP, and updates chapter progress.

#### Request Body
```json
{
  "answers": [
    { "question_id": 10, "selected_choice": "A" },
    { "question_id": 11, "selected_choice": "B" }
  ]
}
```

#### Response (`200 OK`)
```json
{
  "message": "Quiz submitted successfully.",
  "attempt_id": 4,
  "score_percent": 100.0,
  "correct_count": 2,
  "total_questions": 2,
  "quiz_xp_earned": 40,
  "chapter_xp_reward": 50,
  "total_xp_earned": 90,
  "is_first_completion": true,
  "coins_earned_from_xp": 0,
  "experience_points": 190,
  "coin_balance": 50,
  "chapter_progress": {
    "chapter_id": 2,
    "state": "completed",
    "completed_at": "2026-08-27T22:35:00.000000Z",
    "last_accessed_at": "2026-08-27T22:35:00.000000Z"
  },
  "answers": [
    {
      "question_id": 10,
      "selected_choice": "A",
      "is_correct": true,
      "correct_choice": "A",
      "explanation": "Light travels at approximately 300,000,000 m/s in vacuum."
    },
    {
      "question_id": 11,
      "selected_choice": "B",
      "is_correct": true,
      "correct_choice": "B",
      "explanation": "Newton's first law describes inertia."
    }
  ]
}
```

---

### Endpoint 4: `GET /api/progress`
Returns comprehensive progress statistics and chapter states across all subjects and chapters for the dashboard.

#### Response (`200 OK`)
```json
{
  "total_chapters": 4,
  "completed_chapters": 1,
  "in_progress_chapters": 1,
  "overall_progress_percent": 25.0,
  "experience_points": 250,
  "coin_balance": 30,
  "subjects": [
    {
      "id": 1,
      "name": "Mathematics",
      "total_chapters": 3,
      "completed_chapters": 1,
      "chapters": [
        {
          "id": 1,
          "title": "Algebra",
          "order": 1,
          "is_free": true,
          "is_unlocked": true,
          "coin_price": 0,
          "xp_reward": 50,
          "state": "completed",
          "last_accessed_at": "2026-08-26T14:00:00.000000Z",
          "completed_at": "2026-08-26T14:15:00.000000Z"
        },
        {
          "id": 2,
          "title": "Calculus",
          "order": 2,
          "is_free": false,
          "is_unlocked": true,
          "coin_price": 50,
          "xp_reward": 60,
          "state": "in_progress",
          "last_accessed_at": "2026-08-27T10:00:00.000000Z",
          "completed_at": null
        },
        {
          "id": 3,
          "title": "Trigonometry",
          "order": 3,
          "is_free": false,
          "is_unlocked": false,
          "coin_price": 50,
          "xp_reward": 50,
          "state": "not_started",
          "last_accessed_at": null,
          "completed_at": null
        }
      ]
    },
    {
      "id": 2,
      "name": "Physics",
      "total_chapters": 1,
      "completed_chapters": 0,
      "chapters": [
        {
          "id": 4,
          "title": "Mechanics",
          "order": 1,
          "is_free": true,
          "is_unlocked": true,
          "coin_price": 0,
          "xp_reward": 50,
          "state": "not_started",
          "last_accessed_at": null,
          "completed_at": null
        }
      ]
    }
  ]
}
```

---

## 3. Why This Approach

1. **Centralized XP Service Pattern**:
   Mirroring `CoinService`, `XpService` encapsulates all mutations to `experience_points` in database transactions with row-level locking (`$user->lockForUpdate()`). This prevents concurrent race conditions and ensures automatic conversion to coins whenever a 1,500 XP milestone is reached.
2. **First-Completion Idempotency**:
   Students are encouraged to retake quizzes for mastery and practice without inflating chapter rewards. Checking existing completion status guarantees chapter `xp_reward` is granted only on initial success while still rewarding quiz practice XP on subsequent attempts.
3. **Information Leakage Prevention**:
   Sanitizing question payloads before sending to the client prevents inspection of answers in network requests or memory dumps before submission.
4. **Lightweight State Machine**:
   Using `POST /api/chapters/{id}/touch` allows tracking real-time student study activity without requiring heavy video playback tracking overhead.

---

## 4. Verification & Test Results
- **Automated Tests**: 101 tests passing (592 assertions) across the entire test suite.
  - `tests/Feature/Api/ChapterProgressAndQuizTest.php`: 8 dedicated feature tests validating locked chapter rejection, correct choice protection, grading accuracy, XP calculation, retake rules, XP-to-coin threshold conversions, and multi-chapter progress tracking.
- **Static Analysis**: PHPStan passing at 0 errors.
- **Code Style**: Formatted cleanly with Laravel Pint.

---

## 5. Open Questions & Blockers
- None.
