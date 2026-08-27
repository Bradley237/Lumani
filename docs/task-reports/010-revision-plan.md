# Task 010 — Revision Plan Generation

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication**: Laravel Sanctum Personal Access Tokens (`Bearer <token>`)
- **Key Invariants**:
  - Revision plan generation is **completely free** for all authenticated students (no subscription required).
  - Purely algorithmic scheduling without external LLM latency or failure modes.
  - Computes a dynamic "weakness score" per subject:
    - Average `quiz_attempts` `score_percent` across the subject's chapters.
    - Inverse weighting: Lower score = weaker performance = higher weakness weight = proportionally more study time.
    - **Unattempted subjects** ("not yet assessed") receive top priority weighting (`weight: 100.0`), ensuring students are guided to assess neglected subjects rather than skipping them.
  - Allocates total `weekly_available_minutes` across the student's chosen `available_days` (integers 0–6).
  - Total allocated minutes across the plan strictly equals and never exceeds `weekly_available_minutes`.
  - Recommends the specific weakest or incomplete chapter per subject:
    1. First incomplete or unstarted chapter (`not_started` or `in_progress`).
    2. If all completed, the chapter with the lowest average quiz score.
  - Preserves full generation history in `revision_plans`, with `GET /api/revision-plan` returning the student's most recent plan.
  - Sensible fallback plan generated when no accessible chapters/subjects exist yet.

---

## 1. What Was Built

### 1.1 Database Migrations & Schema
1. **`2026_08_27_000027_create_revision_plans_table.php`**:
   - `id`: Auto-incrementing primary key
   - `user_id`: Foreign key referencing `users.id` (cascade on delete)
   - `weekly_available_minutes`: Unsigned integer
   - `available_days`: JSON array of weekday integers (`0` = Sunday .. `6` = Saturday)
   - `generated_at`: Timestamp
   - `plan_data`: JSON array of `{ day, subject_id, subject_name, chapter_id, chapter_title, duration_minutes }`
   - `timestamps`: `created_at`, `updated_at`

### 1.2 Eloquent Model & Relationships
- **`App\Models\RevisionPlan`**: Model with casts for `available_days` (array), `plan_data` (array), `generated_at` (datetime), and `user` relationship.
- Updated **`App\Models\User`**: Added `revisionPlans(): HasMany`.

### 1.3 Service Layer (`RevisionPlanService`)
- `generate(User $user, int $weeklyAvailableMinutes, array $availableDays)`:
  - Validates and sanitizes available days (0–6).
  - Computes inverse weakness weights across all subjects.
  - Identifies priority chapters per subject.
  - Allocates weekly minutes proportionally across available days with exact balance adjustment on the final day.
  - Creates a new `RevisionPlan` record.
- `determinePriorityChapter()`: Finds the first incomplete chapter or chapter with lowest quiz score.
- `getLatestPlan(User $user)`: Fetches the student's latest generated plan.

---

## 2. API Endpoints Specification

### Headers Required
```http
Authorization: Bearer <token>
Accept: application/json
```

---

### Endpoint 1: `POST /api/revision-plan/generate`
Generates a new personalized revision plan.

#### Request Body
```json
{
  "weekly_available_minutes": 300,
  "available_days": [1, 3, 5]
}
```

#### Response (`201 Created`)
```json
{
  "message": "Revision plan generated successfully.",
  "plan": {
    "id": 1,
    "weekly_available_minutes": 300,
    "available_days": [1, 3, 5],
    "generated_at": "2026-08-27T23:58:00.000000Z",
    "plan_data": [
      {
        "day": 1,
        "subject_id": 2,
        "subject_name": "Physics",
        "chapter_id": 4,
        "chapter_title": "Kinematics & Dynamics",
        "duration_minutes": 150
      },
      {
        "day": 3,
        "subject_id": 1,
        "subject_name": "Mathematics",
        "chapter_id": 2,
        "chapter_title": "Calculus Foundations",
        "duration_minutes": 90
      },
      {
        "day": 5,
        "subject_id": 3,
        "subject_name": "Chemistry",
        "chapter_id": 7,
        "chapter_title": "Chemical Bonding",
        "duration_minutes": 60
      }
    ]
  }
}
```

---

### Endpoint 2: `GET /api/revision-plan`
Fetches the student's latest generated revision plan.

#### Response (`200 OK` — Plan exists)
```json
{
  "has_plan": true,
  "plan": {
    "id": 1,
    "weekly_available_minutes": 300,
    "available_days": [1, 3, 5],
    "generated_at": "2026-08-27T23:58:00.000000Z",
    "plan_data": [
      {
        "day": 1,
        "subject_id": 2,
        "subject_name": "Physics",
        "chapter_id": 4,
        "chapter_title": "Kinematics & Dynamics",
        "duration_minutes": 150
      },
      {
        "day": 3,
        "subject_id": 1,
        "subject_name": "Mathematics",
        "chapter_id": 2,
        "chapter_title": "Calculus Foundations",
        "duration_minutes": 90
      },
      {
        "day": 5,
        "subject_id": 3,
        "subject_name": "Chemistry",
        "chapter_id": 7,
        "chapter_title": "Chemical Bonding",
        "duration_minutes": 60
      }
    ]
  }
}
```

#### Response (`200 OK` — None generated yet)
```json
{
  "has_plan": false,
  "message": "No revision plan has been generated yet for this student.",
  "plan": null
}
```

---

## 3. Why This Approach

1. **Zero External Dependency / Pure Algorithmic**:
   Revision planning requires instant responsiveness and deterministic consistency. Calculating weights directly from real quiz attempts and progress states ensures zero API costs and instant generation.
2. **Prioritization of Unassessed Subjects**:
   Treating unattempted subjects with top priority (`weight: 100.0`) encourages students to complete diagnostic quizzes and explore new curriculum areas.
3. **Exact Minute Balance**:
   The schedule distribution uses fractional proportional weights and rounds out the balance on the final day, guaranteeing that the sum of daily study blocks precisely matches the student's declared availability.

---

## 4. Verification & Test Results
- **Automated Tests**: 134 tests passing (744 assertions) across the test suite.
  - `tests/Feature/Api/RevisionPlanTest.php`: 7 feature tests verifying subscription-free access, proportional time weighting for weak subjects, unassessed subject prioritization, strict duration cap enforcement, row history retention with latest retrieval, empty state handling, and fallback schedules.
- **Static Analysis**: PHPStan passing at 0 errors (`phpstan analyse --memory-limit=512M`).
- **Code Style**: 100% compliant with Laravel Pint.

---

## 5. Open Questions & Blockers
- None.
