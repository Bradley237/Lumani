# Task Report 016 — Exam Subsystem & Level Validation

## 1. Overview
Enforced strict Cameroon secondary education subsystem (`gce` and `obc`) and academic level pairing across student registration, profile management, content models (`Subject`, `Chapter`, `PastPaper`, `WeeklyChallenge`), public options API, and Filament admin resources.

---

## 2. Exact Enum Values Reference

API clients (mobile app, web app, AI agents, ChatGPT) MUST use these exact lowercase string backing values:

### ExamSubsystem (`app/Enums/ExamSubsystem.php`)
| Enum Case | String Value | Display Label | Valid Levels |
| :--- | :--- | :--- | :--- |
| `ExamSubsystem::Gce` | `"gce"` | GCE (Anglophone Subsystem) | `ordinary_level`, `advanced_level` |
| `ExamSubsystem::Obc` | `"obc"` | OBC (Francophone Subsystem) | `bepc`, `probatoire`, `bac` |

### ExamLevel (`app/Enums/ExamLevel.php`)
| Enum Case | String Value | Display Label | Allowed Subsystem |
| :--- | :--- | :--- | :--- |
| `ExamLevel::OrdinaryLevel` | `"ordinary_level"` | Ordinary Level (O-Level) | `"gce"` |
| `ExamLevel::AdvancedLevel` | `"advanced_level"` | Advanced Level (A-Level) | `"gce"` |
| `ExamLevel::Bepc` | `"bepc"` | BEPC | `"obc"` |
| `ExamLevel::Probatoire` | `"probatoire"` | Probatoire | `"obc"` |
| `ExamLevel::Bac` | `"bac"` | Baccalauréat (BAC) | `"obc"` |

### Valid Pair Mapping Matrix
```json
{
  "gce": [
    "ordinary_level",
    "advanced_level"
  ],
  "obc": [
    "bepc",
    "probatoire",
    "bac"
  ]
}
```

Any mismatched pairing (e.g., `{"exam_system": "gce", "level": "bac"}` or `{"exam_system": "obc", "level": "ordinary_level"}`) is rejected with HTTP **`422 Unprocessable Entity`**.

---

## 3. What Was Built & Changed

### 1. PHP Enums
- **`App\Enums\ExamSubsystem`**: Backed string enum (`gce`, `obc`) with `label()`, `validLevels(): array`, and `static mapping(): array`.
- **`App\Enums\ExamLevel`**: Backed string enum (`ordinary_level`, `advanced_level`, `bepc`, `probatoire`, `bac`) with `label()`, `subsystem(): ExamSubsystem`, and `static forSubsystem($subsystem): array`.

### 2. Database Migration & Schema Harmonization
- Migration: `2026_08_31_000001_update_exam_subsystem_and_level_columns.php`
  - Added nullable `level` column to `subjects` table.
  - Added nullable `exam_subsystem` and `level` columns to `chapters` table.
  - Normalized existing data in `users`, `subjects`, `past_papers`, and `weekly_challenges`:
    - `anglophone` -> `gce`
    - `francophone` -> `obc`
    - `general` -> `NULL` (indicating universal content across subsystems)
    - `O-Level` / `o_level` -> `ordinary_level`
    - `A-Level` / `a_level` -> `advanced_level`
    - `BEPC` / `bepc` -> `bepc`
    - `Probatoire` / `probatoire` -> `probatoire`
    - `Baccalaureat` / `Baccalauréat` -> `bac`

### 3. Model Casts & Factories
- Updated Eloquent model casts on `User`, `Subject`, `Chapter`, `PastPaper`, and `WeeklyChallenge` to use `ExamSubsystem::class` and `ExamLevel::class`.
- Updated `UserFactory`, `SubjectFactory`, `PastPaperFactory`, and `WeeklyChallengeFactory` to generate valid enum combinations.

### 4. Custom Validation Rule
- **`App\Rules\ValidExamPair`**:
  - Implements `ValidationRule` and `DataAwareRule`.
  - Dynamically extracts the subsystem from payload (checking either `exam_system` or `exam_subsystem`, or falling back to the authenticated user's profile if updating only level).
  - Validates that the provided level belongs to the selected subsystem.
  - Applied in:
    - `App\Http\Requests\Api\Auth\RegisterRequest`
    - `App\Http\Requests\Api\User\UpdateProfileRequest`
    - `App\Concerns\ProfileValidationRules` (web settings profile updates)

### 5. API Endpoints
- **`GET /api/exam-options`** (Public):
  - Returns the complete valid mapping object:
    ```json
    {
      "gce": ["ordinary_level", "advanced_level"],
      "obc": ["bepc", "probatoire", "bac"]
    }
    ```
- **`POST /api/register`**:
  - Accepts optional `exam_system` and `level` fields.
  - Persists both fields on user creation.
- **`PUT /api/user` & `PATCH /api/user`** (Authenticated):
  - Updates student profile, enforcing subsystem and level pairing.

### 6. Content Filtering & Query Safety
- Updated `ChallengeService`:
  - Extracts string values from `ExamSubsystem` and `ExamLevel` enum instances safely.
  - Queries `exam_subsystem` matches: `whereNull('exam_subsystem')->orWhere('exam_subsystem', 'general')->orWhere('exam_subsystem', $userSubsystem)`.
  - Queries `level` matches: `whereNull('level')->orWhere('level', $userLevel)`.

### 7. Filament Admin Forms
- **`SubjectForm`**:
  - Added `exam_subsystem` Select using `ExamSubsystem::cases()`.
  - Added cascading `level` Select using `->options(fn (Get $get) => ...)` and `->disabled(fn (Get $get) => blank($get('exam_subsystem')))`.
- **`ChapterForm`**:
  - Added `exam_subsystem` and cascading `level` Select components.
- **`PastPaperForm`**:
  - Replaced legacy free-text options with `ExamSubsystem` and cascading `ExamLevel` dropdowns.
- **`WeeklyChallengeForm`**:
  - Replaced legacy free-text options with `ExamSubsystem` and cascading `ExamLevel` dropdowns.
- **`SubjectsTable` & `ChaptersTable`**:
  - Added badge columns for `exam_subsystem` and `level`.

---

## 4. Test Coverage & Verification

Created `tests/Feature/Api/ExamSubsystemLevelValidationTest.php` with 18 automated tests:
1. `GET /api/exam-options returns valid subsystem and level mapping` -> Passed.
2. `student can register with valid GCE pairs (ordinary_level, advanced_level)` -> Passed.
3. `student can register with valid OBC pairs (bepc, probatoire, bac)` -> Passed.
4. `student registration rejects mismatched subsystem and level pairs with 422 (gce+bac, gce+bepc, gce+probatoire, obc+ordinary_level, obc+advanced_level)` -> Passed.
5. `student registration rejects invalid enum strings` -> Passed.
6. `student registration rejects level when exam_system is missing` -> Passed.
7. `student can update profile via API with valid exam pair` -> Passed.
8. `student profile update via API rejects mismatched pair with 422` -> Passed.
9. `content filtering by subsystem and level matches student correctly` -> Passed.
10. `web settings profile update validates exam pair` -> Passed.
11. `filament forms for Subject, Chapter, and PastPaper configure exam_subsystem and cascading level selects` -> Passed.

All feature test suites (`AuthTest`, `ChallengeTest`, `ExamSubsystemLevelValidationTest`) pass with 0 errors.

---

## 5. Open Questions & Blockers
- **None**: Implementation is complete, tested, formatted with Laravel Pint, and fully aligned with the requirements.
