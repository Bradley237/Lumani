# Task 008 — Career Content: Profiles + Personalized Pathway

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication**: Laravel Sanctum Personal Access Tokens (`Bearer <token>`)
- **Key Invariants**:
  - `GET /api/career-profiles` is **free** for any authenticated student (supports open exploration of career options).
  - `POST /api/career-pathway/generate` is **subscription-gated** via `AccessControlService::hasActiveSubscription` (`403 Forbidden` if not subscribed, bypassed by `free_mode`).
  - Personalized pathway generation analyzes real student performance data:
    - Chapter progress completion percentage per subject.
    - Average quiz score percentage per subject across all attempts.
  - Performance data plus existing database career profiles are provided to Gemini AI (`gemini-2.5-flash`) to rank best-fit careers with a `match_score` (0–100) and personalized `reasoning`.
  - Strict validation guarantees that only **existing database `career_profile_id`s** are accepted and persisted in recommendations (hallucinated IDs are discarded).
  - If Gemini API fails or returns invalid responses, the request fails gracefully with a `422 Unprocessable Content` validation exception rather than corrupting user data with empty or broken pathways.
  - `GET /api/career-pathway` returns the student's latest generated pathway enriched with career profile details, or a clear `{ "has_pathway": false, "pathway": null }` response when none has been generated.

---

## 1. What Was Built

### 1.1 Database Migrations & Schema
1. **`2026_08_27_000023_create_career_profiles_table.php`**:
   - `id`: Auto-incrementing primary key
   - `title`: String
   - `description`: Text
   - `average_salary`: Nullable string (e.g. `600,000 - 1,500,000 FCFA/mo`)
   - `job_demand`: String/enum (`low`, `moderate`, `high`, `very_high`, default `moderate`)
   - `related_subjects`: Nullable JSON array of strings (e.g. `["Mathematics", "Physics", "Computer Science"]`)
   - `timestamps`: `created_at`, `updated_at`
2. **`2026_08_27_000024_create_career_pathways_table.php`**:
   - `id`: Primary key
   - `user_id`: Foreign key referencing `users.id` (cascade on delete)
   - `generated_at`: Timestamp
   - `recommendations`: JSON array of `{ career_profile_id, match_score, reasoning }`
   - `timestamps`: `created_at`, `updated_at`

### 1.2 Enums & Eloquent Models
- **`App\Enums\JobDemand`**: `Low ('low')`, `Moderate ('moderate')`, `High ('high')`, `VeryHigh ('very_high')`.
- **`App\Models\CareerProfile`**: Represents industry/career options.
- **`App\Models\CareerPathway`**: Represents a student's AI-generated career recommendations snapshot.
- Updated **`App\Models\User`**: Added `careerPathways(): HasMany`.

### 1.3 Filament Admin Panel (CRUD)
- Created **`CareerProfileResource`** under the **Academic Management** navigation group:
  - **`CareerProfileForm`**: Title, Job Demand dropdown, Average Salary input, TagsInput for Related Subjects, and rich Description textarea.
  - **`CareerProfilesTable`**: Searchable title, color-coded job demand badge, salary, related subjects tags, and standard record actions.
  - **Pages**: `ListCareerProfiles`, `CreateCareerProfile`, `EditCareerProfile`.

### 1.4 Service Layer (`CareerPathwayService`)
- `generate(User $user)`:
  - Validates active subscription (403 if absent).
  - Aggregates student subject completion rates and quiz score averages.
  - Sends structured prompt to Gemini API with available career options.
  - Validates and sanitizes returned recommendations strictly against database IDs.
  - Creates and returns a new `CareerPathway` record.
- `buildStudentPerformanceSummary(User $user)`: Generates subject-by-subject statistics.
- `formatPathway(?CareerPathway $pathway)`: Enriches stored recommendations with full career profile metadata for API responses.

---

## 2. API Endpoints Specification

### Headers Required
```http
Authorization: Bearer <token>
Accept: application/json
```

---

### Endpoint 1: `GET /api/career-profiles`
Lists all career profiles in the system (free for all authenticated users).

#### Response (`200 OK`)
```json
{
  "career_profiles": [
    {
      "id": 1,
      "title": "Data Scientist",
      "description": "Analyzes complex data to help organizations make better decisions.",
      "average_salary": "800,000 - 2,000,000 FCFA/mo",
      "job_demand": "very_high",
      "job_demand_label": "Very High Demand",
      "related_subjects": ["Mathematics", "Computer Science", "Physics"]
    },
    {
      "id": 2,
      "title": "Civil Engineer",
      "description": "Designs, builds, and supervises infrastructure projects.",
      "average_salary": "600,000 - 1,500,000 FCFA/mo",
      "job_demand": "moderate",
      "job_demand_label": "Moderate Demand",
      "related_subjects": ["Physics", "Mathematics"]
    }
  ]
}
```

---

### Endpoint 2: `POST /api/career-pathway/generate`
Generates a personalized AI career pathway for the student (subscription-gated).

#### Response (`201 Created`)
```json
{
  "message": "Personalized career pathway generated successfully.",
  "pathway": {
    "id": 1,
    "generated_at": "2026-08-27T23:25:00.000000Z",
    "recommendations": [
      {
        "career_profile_id": 1,
        "career_title": "Data Scientist",
        "description": "Analyzes complex data to help organizations make better decisions.",
        "average_salary": "800,000 - 2,000,000 FCFA/mo",
        "job_demand": "very_high",
        "related_subjects": ["Mathematics", "Computer Science", "Physics"],
        "match_score": 94,
        "reasoning": "Outstanding 95% score in Mathematics quizzes demonstrates strong quantitative and analytical aptitude."
      },
      {
        "career_profile_id": 2,
        "career_title": "Civil Engineer",
        "description": "Designs, builds, and supervises infrastructure projects.",
        "average_salary": "600,000 - 1,500,000 FCFA/mo",
        "job_demand": "moderate",
        "related_subjects": ["Physics", "Mathematics"],
        "match_score": 78,
        "reasoning": "Solid chapter progress in Physics provides a good engineering foundation."
      }
    ]
  }
}
```

#### Error Response (`403 Forbidden` if not subscribed)
```json
{
  "message": "An active subscription is required to generate a personalized career pathway."
}
```

---

### Endpoint 3: `GET /api/career-pathway`
Returns the student's most recently generated pathway.

#### Response (`200 OK` — Pathway exists)
```json
{
  "has_pathway": true,
  "pathway": {
    "id": 1,
    "generated_at": "2026-08-27T23:25:00.000000Z",
    "recommendations": [
      {
        "career_profile_id": 1,
        "career_title": "Data Scientist",
        "description": "Analyzes complex data to help organizations make better decisions.",
        "average_salary": "800,000 - 2,000,000 FCFA/mo",
        "job_demand": "very_high",
        "related_subjects": ["Mathematics", "Computer Science", "Physics"],
        "match_score": 94,
        "reasoning": "Outstanding 95% score in Mathematics quizzes demonstrates strong quantitative and analytical aptitude."
      }
    ]
  }
}
```

#### Response (`200 OK` — None generated yet)
```json
{
  "has_pathway": false,
  "message": "No career pathway has been generated yet for this student.",
  "pathway": null
}
```

---

## 3. Why This Approach

1. **Freemium Career Model**:
   Allowing all students to browse generic career profiles (`/api/career-profiles`) provides immediate educational value and aspiration, while gating personalized AI guidance (`/api/career-pathway/generate`) behind subscriptions creates a strong monetization incentive.
2. **Grounding AI in Real Database Records**:
   By feeding existing career profiles into the prompt and filtering the response strictly by database IDs, we ensure students receive recommendations only for careers with actionable local salary, demand, and subject data.
3. **Resilient Error Handling**:
   Failing with a 422 error on Gemini timeouts or non-200 responses protects the integrity of the student's profile and avoids creating corrupted or empty pathway rows.

---

## 4. Verification & Test Results
- **Automated Tests**: 117 tests passing (676 assertions) across the entire test suite.
  - `tests/Feature/Api/CareerPathwayTest.php`: 6 feature tests verifying open access to career profiles, subscription enforcement (403), free mode bypass, performance data compilation, database ID sanitization, Gemini failure handling (422), and retrieval of latest pathways.
- **Static Analysis**: PHPStan passing at 0 errors (`phpstan analyse --memory-limit=512M`).
- **Code Style**: 100% compliant with Laravel Pint.

---

## 5. Open Questions & Blockers
- None.
