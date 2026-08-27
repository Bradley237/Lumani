# Task 007 — Exam-Condition Mode (Timed Past Paper Practice)

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication**: Laravel Sanctum Personal Access Tokens (`Bearer <token>`)
- **Key Invariants**:
  - Timed exam sessions are an exclusive **subscription benefit** gated by `AccessControlService::hasActiveSubscription` (with global `free_mode` bypass). Users without an active subscription receive `403 Forbidden`.
  - Max allowed session duration is dynamically calculated from question composition:
    - **90 minutes** for MCQ-only papers
    - **180 minutes** for Structural/Essay-only papers
    - **240 minutes** for Mixed papers
  - Students can choose custom `selected_minutes` up to `max_allowed_minutes`. Exceeding this cap rejects with `422 Unprocessable Content`.
  - Submissions past `started_at + selected_minutes` (+60s tolerance) are rejected with `422 Unprocessable Content`.
  - MCQ questions are automatically graded upon submission.
  - Structural answers automatically trigger the existing `GradingAssistantService::suggestScore()` (Gemini AI) upon submission to pre-populate `suggested_points` and `suggested_justification`.
  - MCQ-only papers finalize immediately (`status: graded`, `total_score_percent` calculated). Papers with structural questions transition to `status: submitted` and appear in the unified Filament Grading Queue.
  - No coin rewards are issued for exam sessions (subscription feature, not an earning loop).
  - Sanitized session start response never leaks `correct_choice` or `marking_scheme`.
  - Graded results expose `feedback` (renamed from `suggested_justification`) for structural answers.

---

## 1. What Was Built

### 1.1 Database Migrations & Schema
1. **`2026_08_27_000020_create_past_paper_questions_table.php`**:
   - `id`: Auto-incrementing primary key
   - `past_paper_id`: Foreign key referencing `past_papers.id` (cascade on delete)
   - `type`: String/enum (`mcq`, `structural`, default `mcq`)
   - `question_text`: Text
   - `options`: Nullable JSON (MCQ choices: `A`, `B`, `C`, `D`)
   - `correct_choice`: Nullable string (MCQ only)
   - `marking_scheme`: Nullable text (Structural model answers / criteria)
   - `max_points`: Unsigned integer (default 10)
   - `order`: Integer (default 0)
   - `timestamps`: `created_at`, `updated_at`
2. **`2026_08_27_000021_create_exam_sessions_table.php`**:
   - `id`: Primary key
   - `user_id`: Foreign key referencing `users.id` (cascade on delete)
   - `past_paper_id`: Foreign key referencing `past_papers.id` (cascade on delete)
   - `max_allowed_minutes`: Unsigned integer
   - `selected_minutes`: Unsigned integer
   - `started_at`: Timestamp
   - `submitted_at`: Nullable timestamp
   - `status`: String/enum (`in_progress`, `submitted`, `graded`, default `in_progress`)
   - `total_score_percent`: Nullable decimal(5, 2)
   - `timestamps`: `created_at`, `updated_at`
3. **`2026_08_27_000022_create_exam_session_answers_table.php`**:
   - `id`: Primary key
   - `exam_session_id`: Foreign key referencing `exam_sessions.id` (cascade on delete)
   - `question_id`: Foreign key referencing `past_paper_questions.id` (cascade on delete)
   - `selected_choice`: Nullable string (MCQ)
   - `answer_text`: Nullable text (Structural)
   - `points_awarded`: Nullable unsigned integer
   - `suggested_points`: Nullable unsigned integer
   - `suggested_justification`: Nullable text
   - `timestamps`: `created_at`, `updated_at`

### 1.2 Models & Enums
- **`App\Enums\PastPaperQuestionType`**: `Mcq ('mcq')`, `Structural ('structural')`.
- **`App\Enums\ExamSessionStatus`**: `InProgress ('in_progress')`, `Submitted ('submitted')`, `Graded ('graded')`.
- **`App\Models\PastPaperQuestion`**, **`App\Models\ExamSession`**, **`App\Models\ExamSessionAnswer`**.
- Updated relationships on **`PastPaper`** (`questions`, `examSessions`) and **`User`** (`examSessions`).

### 1.3 Service Layer
- **`GradingAssistantService`**: Extended `suggestScore()` signature to accept `WeeklyChallengeQuestion|PastPaperQuestion $question`.
- **`ExamSessionService`**:
  - `startSession(User $user, PastPaper $pastPaper, ?int $requestedMinutes = null)`: Enforces active subscription check (403 if missing), determines composition and max minutes, validates student requested duration, creates `ExamSession`, and returns sanitized questions.
  - `submitSession(User $user, ExamSession $session, array $answers)`: Validates ownership, active status, and deadline tolerance. Auto-grades MCQ answers, queries Gemini AI suggestions for structural answers, and transitions session status.
  - `gradeStructuralAnswer(User $admin, ExamSessionAnswer $answer, int $pointsAwarded)`: Records teacher points, checks if all answers are graded, and finalizes score percent.
  - `getSessionResult(User $user, ExamSession $session)`: Compiles result payload with answer breakdown and student feedback.

### 1.4 Filament Admin Enhancements
- **`PastPaperForm`**: Added an **Exam-Condition Questions** Repeater with dynamic visibility for MCQ options / correct choice vs. Structural marking scheme textarea.
- **`GradingQueue`**: Extended unified grading queue page and Blade view to display pending structural answers from both **Weekly Challenges** and **Timed Exam Practice Sessions** in structured sections with AI suggested scores and prefilled inputs.

---

## 2. API Endpoints Specification

### Headers Required
```http
Authorization: Bearer <token>
Accept: application/json
```

---

### Endpoint 1: `POST /api/past-papers/{id}/exam-session/start`
Starts a timed exam session for a past paper.

#### Request Body (Optional)
```json
{
  "requested_minutes": 120
}
```

#### Response (`201 Created`)
```json
{
  "message": "Exam session started successfully.",
  "session": {
    "id": 1,
    "past_paper_id": 4,
    "max_allowed_minutes": 240,
    "selected_minutes": 120,
    "started_at": "2026-08-27T23:00:00.000000Z",
    "status": "in_progress"
  },
  "questions": [
    {
      "id": 10,
      "type": "mcq",
      "question_text": "What is the SI unit of electric current?",
      "options": {
        "A": "Ampere",
        "B": "Volt",
        "C": "Ohm",
        "D": "Coulomb"
      },
      "max_points": 10,
      "order": 1
    },
    {
      "id": 11,
      "type": "structural",
      "question_text": "State Kirchhoff's first and second laws.",
      "options": null,
      "max_points": 20,
      "order": 2
    }
  ]
}
```

#### Error Response (`403 Forbidden` if not subscribed)
```json
{
  "message": "An active subscription is required to access timed exam sessions."
}
```

#### Error Response (`422 Unprocessable Content` if duration exceeds max cap)
```json
{
  "message": "Requested duration must be between 1 and 90 minutes.",
  "errors": {
    "requested_minutes": [
      "Requested duration must be between 1 and 90 minutes."
    ]
  }
}
```

---

### Endpoint 2: `POST /api/exam-sessions/{id}/submit`
Submits student answers for an active exam session.

#### Request Body
```json
{
  "answers": [
    {
      "question_id": 10,
      "selected_choice": "A",
      "answer_text": null
    },
    {
      "question_id": 11,
      "selected_choice": null,
      "answer_text": "Kirchhoff's junction law states that total current entering a junction equals total current leaving..."
    }
  ]
}
```

#### Response (`200 OK` — Structural / Mixed pending grading)
```json
{
  "status": "submitted",
  "message": "Exam session submitted successfully. Results will be available after teacher grading.",
  "session_id": 1,
  "total_score_percent": null
}
```

#### Response (`200 OK` — MCQ-Only auto-graded)
```json
{
  "status": "graded",
  "message": "Exam session submitted and graded successfully.",
  "session_id": 1,
  "total_score_percent": 85.0
}
```

---

### Endpoint 3: `GET /api/exam-sessions/{id}/result`
Fetches exam session score and student feedback.

#### Response (`200 OK`)
```json
{
  "status": "graded",
  "result": {
    "id": 1,
    "past_paper_id": 4,
    "past_paper_title": "GCE A-Level Physics 2024 Paper 2",
    "subject_name": "Physics",
    "selected_minutes": 120,
    "started_at": "2026-08-27T23:00:00.000000Z",
    "submitted_at": "2026-08-27T23:55:00.000000Z",
    "status": "graded",
    "total_score_percent": 90.0,
    "answers": [
      {
        "question_id": 10,
        "type": "mcq",
        "question_text": "What is the SI unit of electric current?",
        "selected_choice": "A",
        "answer_text": null,
        "points_awarded": 10,
        "max_points": 10
      },
      {
        "question_id": 11,
        "type": "structural",
        "question_text": "State Kirchhoff's first and second laws.",
        "selected_choice": null,
        "answer_text": "Kirchhoff's junction law states that total current entering a junction...",
        "points_awarded": 18,
        "max_points": 20,
        "feedback": "Correct statement of junction and loop rules with clear conservation justifications."
      }
    ]
  }
}
```

---

## 3. Why This Approach

1. **Subscription Gating**:
   Exam-condition practice represents premium exam prep functionality. Gating through `AccessControlService::hasActiveSubscription()` reinforces the monetization model while keeping global `free_mode` support intact for tests and trial deployments.
2. **Composition-Based Dynamic Time Limits**:
   Automating the maximum duration (90 min MCQ, 180 min Structural, 240 min Mixed) prevents unrealistic exam session lengths while allowing students flexibility to choose shorter practice runs (e.g. 45 min sprint).
3. **Asynchronous AI Suggestions**:
   Computing Gemini AI score suggestions at submission time keeps the Filament grading queue fast and responsive for teachers, requiring zero API waiting during active grading.
4. **Unified Grading Queue**:
   Combining Weekly Challenge and Timed Exam Session submissions into a single Filament page streamlines administrative workload, preventing teachers from needing to monitor multiple disparate queues.

---

## 4. Verification & Test Results
- **Automated Tests**: 111 tests passing (645 assertions) across the entire backend suite.
  - `tests/Feature/Api/ExamSessionTest.php`: 10 feature tests verifying subscription rejection (403), free mode bypass, dynamic time calculation, duration caps (422), question sanitization, timeout enforcement, MCQ auto-grading, Gemini AI suggestion population, Filament grading queue overrides, and result feedback exposure.
- **Static Analysis**: PHPStan passing at 0 errors (`phpstan analyse --memory-limit=512M`).
- **Code Style**: 100% compliant with Laravel Pint.

---

## 5. Open Questions & Blockers
- None.
