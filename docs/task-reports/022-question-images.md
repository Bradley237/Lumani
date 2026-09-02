# Task Report 022 — Question Images

**Date:** 2026-09-02  
**Task:** Support one optional image per question across all four question types, with automatic
resize/compress on upload, admin form support, full API exposure, and safe publish-flow carry-over.

---

## What Was Built

### 1. Database — four new migrations

| Migration | Table | Column added |
|---|---|---|
| `2026_09_02_000003` | `questions` | `image_path VARCHAR NULL` |
| `2026_09_02_000004` | `weekly_challenge_questions` | `image_path VARCHAR NULL` |
| `2026_09_02_000005` | `past_paper_questions` | `image_path VARCHAR NULL` |
| `2026_09_02_000006` | `submitted_questions` | `image_path VARCHAR NULL` |

Run `php artisan migrate` to apply.

---

### 2. ImageProcessingService — `app/Services/ImageProcessingService.php`

Encapsulates all resize/compress logic using **PHP's built-in GD extension** (no additional composer
package required):

- Decodes the upload with `imagecreatefromstring()` — accepts JPEG, PNG, GIF, WebP.
- **Downscales** to max **1200 px wide** (proportional height) using bicubic resampling. Images that
  are already ≤ 1200 px wide are stored as-is (no upscaling).
- Re-encodes as **JPEG at quality 80** — typically 3–5× smaller than a raw PNG or high-quality camera
  JPEG.
- Stores on the **`public` disk** under `question-images/{uuid}.jpg`.
- Returns the stored relative path.

> **Setup note:** Run `php artisan storage:link` once to symlink `storage/app/public` → `public/storage`
> so that `Storage::disk('public')->url(...)` produces a valid web-accessible URL.

---

### 3. Admin Panel — Filament image upload fields

Image upload was added to all four admin forms:

| Resource / Form | Location of new field |
|---|---|
| **QuestionResource** → `QuestionForm` | After the Explanation textarea |
| **WeeklyChallengeResource** → `WeeklyChallengeForm` | Inside the Questions Repeater |
| **PastPaperResource** → `PastPaperForm` | Inside the Exam-Condition Questions Repeater |
| **SubmittedQuestionResource** → `SubmittedQuestionForm` | After the Explanation textarea |

Each field:
- Accepts `image/jpeg`, `image/png`, `image/webp`, `image/gif` — max **20 MB** raw upload.
- Shows an inline image preview (160 px high for standalone forms, 120 px in Repeaters).
- Routes through `saveUploadedFileUsing` → `ImageProcessingService::processAndStore()` → returns
  the final processed path. The raw file is never stored; only the compressed JPEG reaches the disk.

---

### 4. publish() — image_path carry-over

`ContentReviewService::publish()` now includes `image_path` in the `Question::create([...])` call.
A submitted question with an image will produce a live `Question` record with the same `image_path`.

---

### 5. API — `image_url` field

#### Field shape the frontend should expect

```json
{
  "id": 42,
  "question_text": "Which diagram shows the correct force vector?",
  "answer_choices": { "A": "Diagram 1", "B": "Diagram 2" },
  "image_url": "https://yourdomain.com/storage/question-images/550e8400-e29b-41d4-a716-446655440000.jpg"
}
```

```json
{
  "id": 43,
  "question_text": "What is 2 + 2?",
  "answer_choices": { "A": "3", "B": "4" },
  "image_url": null
}
```

#### Endpoints that now include `image_url`

| Endpoint | Service method |
|---|---|
| `GET /api/quizzes/{id}` | `QuizService::getQuizForStudent()` |
| `POST /api/challenges/{id}/start` | `ChallengeService::getSanitizedQuestions()` |
| `POST /api/exam-sessions/{id}/start` | `ExamSessionService::startSession()` |

`image_url` is always present in the question object — either a full HTTPS URL or `null`. The
frontend should treat a `null` value as "no image to render" without throwing an error.

---

### 6. Feature Tests — `tests/Feature/Api/QuestionImageTest.php`

Seven test cases:

| # | Test | Assertion |
|---|---|---|
| 1 | Oversized image (3000 × 2000 px PNG) → resized ≤ 1200 px wide, stored as JPEG | `imagesx() ≤ 1200`, path ends `.jpg` |
| 2 | Small image (800 × 600 px) → NOT upscaled | `imagesx() ≤ 800` |
| 3 | Quiz API — question with image → `image_url` = full storage URL | `assertJsonPath` |
| 4 | Quiz API — question without image → `image_url: null` | `assertJsonPath('...', null)` |
| 5 | Weekly challenge start → `image_url` present | `assertJsonPath` |
| 6 | Exam session start → `image_url` present | `assertJsonPath` |
| 7 | `publish()` → `image_path` survives into live `Question` | `assertDatabaseHas` |

Run: `php artisan test --filter=QuestionImageTest`

---

## Open Questions / Potential Follow-ups

| Topic | Notes |
|---|---|
| **WebP output** | Currently outputs JPEG q80 for universal compatibility. Switch to WebP for better compression once frontend confirms browser support. |
| **Old image cleanup** | If an admin replaces an image, the old file is orphaned on disk. A scheduled job or Filament observer to delete stale images is a follow-up task. |
| **Signed URLs** | Images are on the public disk and served directly. If images ever need access control (e.g. premium-only), switch to the `local` disk with signed/expiring URLs — change `Storage::disk('public')->url()` to `Storage::disk('local')->temporaryUrl()`. |
| **Student image upload** | Submitted questions are admin-created only for now. Student submission via API (Task 11) does not yet support image attachment — separate API endpoint + upload pipeline needed if required. |
