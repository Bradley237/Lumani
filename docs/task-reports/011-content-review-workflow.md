# Task 011 — Admin Content-Review Workflow (Submitted Questions)

## Overview & Status
- **Status**: Completed & Fully Verified
- **Scope**: Admin review pipeline for drafted/submitted questions before making them active in student quizzes.
- **Key Invariants**:
  - Review status workflow follows a strict state transition: `pending` → `approved` or `rejected` → `published` (only from `approved`).
  - Cannot publish unapproved, rejected, or already-published content.
  - Rejection requires explanatory notes stored in `review_notes`.
  - Publishing creates a live `Question` in the chapter's `Quiz` (auto-initializes Quiz if not already present) and prevents duplicate publishing.
  - Admin reviewers and submitters are audited via `submitted_by` and `reviewed_by` foreign keys.

---

## 1. What Was Built

### 1.1 Database Migration & Schema
- **`database/migrations/2026_08_28_000001_create_submitted_questions_table.php`**:
  - `id`: Auto-incrementing primary key.
  - `submitted_by`: Nullable foreign key referencing `users.id` (null on delete).
  - `chapter_id`: Foreign key referencing `chapters.id` (cascade on delete).
  - `question_text`: Text.
  - `answer_choices`: JSON array/map of choices (e.g. `{"A": "...", "B": "..."}`).
  - `correct_choice`: Text key of the correct option (e.g., `'A'`).
  - `explanation`: Nullable text explanation.
  - `review_status`: String defaulting to `'pending'` (cast to `ReviewStatus` enum).
  - `reviewed_by`: Nullable foreign key referencing `users.id` (null on delete).
  - `review_notes`: Nullable text notes capturing feedback or reasons for rejection.
  - `timestamps`: `created_at`, `updated_at`.

### 1.2 Enums & Models
- **`App\Enums\ReviewStatus`**:
  - Cases: `Pending` (`'pending'`), `Approved` (`'approved'`), `Rejected` (`'rejected'`), `Published` (`'published'`).
  - Provides `label()` and color mapping `color()` (`warning`, `info`, `danger`, `success`).
- **`App\Models\SubmittedQuestion`**:
  - Casts: `answer_choices` => `array`, `review_status` => `ReviewStatus::class`.
  - Relations: `submitter()` (`BelongsTo<User>`), `reviewer()` (`BelongsTo<User>`), `chapter()` (`BelongsTo<Chapter>`).
- **`App\Models\User`**:
  - Added `submittedQuestions()` (`HasMany`) and `reviewedQuestions()` (`HasMany`).
- **`App\Models\Chapter`**:
  - Added `submittedQuestions()` (`HasMany`).
- **`Database\Factories\SubmittedQuestionFactory`**:
  - Supports states: `pending()`, `approved(?User)`, `rejected(?User, string)`, and `published(?User)`.

### 1.3 Service Layer (`ContentReviewService`)
- **`approve(User $admin, SubmittedQuestion $submittedQuestion): SubmittedQuestion`**:
  - Sets `review_status = ReviewStatus::Approved` and `reviewed_by = $admin->id`.
- **`reject(User $admin, SubmittedQuestion $submittedQuestion, string $notes): SubmittedQuestion`**:
  - Enforces non-empty rejection notes.
  - Sets `review_status = ReviewStatus::Rejected`, `reviewed_by = $admin->id`, and `review_notes = $notes`.
- **`publish(User $admin, SubmittedQuestion $submittedQuestion): Question`**:
  - Enforces `review_status === ReviewStatus::Approved`.
  - Finds or creates a `Quiz` for the given `chapter_id` (`passing_score = 70` default).
  - Creates the live `Question` record linked to the Quiz.
  - Sets `review_status = ReviewStatus::Published` and `reviewed_by = $admin->id`.
  - Executed inside a database transaction.

### 1.4 Filament Admin Resource (`SubmittedQuestionResource`)
- **Resource URL**: `/admin/submitted-questions`
- **Schemas**:
  - **`SubmittedQuestionForm`**:
    - Chapter selector with searchable preloaded chapters and parent subject labels.
    - Question text (Textarea, required).
    - Answer choices (KeyValue component for key/value pairs, e.g. A/B/C/D).
    - Correct choice key (TextInput, required).
    - Explanation (Textarea, nullable).
    - Read-only review status, submitter, reviewer, and review notes on edit/view mode.
  - **`SubmittedQuestionsTable`**:
    - Filter by `review_status` via `SelectFilter`.
    - Columns: ID, Chapter title, Question text preview, Correct choice badge, Review status badge with color coding, Submitter name, Reviewer name, Created at.
    - **One-Click Record Actions**:
      - **Approve**: Visible for `pending` and `rejected` submissions. Prompts confirmation and approves question.
      - **Reject**: Visible for non-published, non-rejected submissions. Opens a modal with a required `review_notes` textarea and transitions status to `rejected`.
      - **Publish**: Visible only for `approved` submissions. Prompts confirmation and publishes directly to the live `questions` table.
      - **Edit / Delete**: Standard CRUD operations.
  - **Pages**:
    - `ListSubmittedQuestions`: Displays table, create action button, and status filter.
    - `CreateSubmittedQuestion`: Auto-populates `submitted_by = auth()->id()` and sets `review_status = pending`.
    - `EditSubmittedQuestion`: Includes Approve, Reject (with modal notes), Publish, and Delete header actions for fast detail-view reviews.

---

## 2. Exact Filament Workflow

1. **Creating a Question Submission / Draft**:
   - An administrator (or future content contributor) navigates to **Submitted Questions** (`/admin/submitted-questions`) and clicks **New submitted question**.
   - Selects the target **Chapter**, enters the **Question text**, specifies **Answer choices** (`A`, `B`, `C`, `D`), marks the **Correct choice**, and optionally adds an **Explanation**.
   - Upon saving, `submitted_by` is recorded as the authenticated admin and `review_status` defaults to `Pending`.

2. **Reviewing in List or Edit View**:
   - The admin table displays all submissions with status filters (Pending, Approved, Rejected, Published).
   - For any `Pending` submission:
     - **Approve**: Clicking **Approve** marks the question as `Approved` and assigns `reviewed_by` to the current admin.
     - **Reject**: Clicking **Reject** opens a modal asking for `Rejection Notes`. Submitting marks status as `Rejected` and records the feedback.
     - **Edit/Revise**: If rejected, an author or admin can edit the question text or options to resolve feedback, then re-approve.

3. **Publishing to Live Quizzes**:
   - Once a submission has status `Approved`, the **Publish** action button appears.
   - Clicking **Publish** invokes `ContentReviewService::publish(...)`:
     - Checks if a quiz exists for the chapter (or automatically initializes one).
     - Inserts the question record into the live `questions` table.
     - Transitions the submitted question's status to `Published`.
   - The question is immediately active for students taking quizzes in that chapter.
   - The `Publish` button disappears and cannot be triggered again, ensuring duplicate questions are never generated.

---

## 3. Why This Approach

- **Staging / Separation of Concerns**: Isolating drafts in `submitted_questions` prevents draft errors, incomplete options, or unvetted content from affecting live student quiz attempts, streaks, XP, or grading metrics.
- **Clear Two-Phase Publication**: Allows dual-admin workflows (one drafts, another reviews) or single-admin draft-then-publish workflows with built-in auditability (`submitted_by`, `reviewed_by`, `review_notes`).
- **Encapsulated Service Layer**: `ContentReviewService` centralizes business rules and invariants, ensuring Filament actions, CLI commands, or future APIs (e.g. educator portal or community question submissions) strictly respect the same validation and transition constraints.
- **Fail-Safe Publishing**: Enforcing `review_status === ReviewStatus::Approved` at the service transaction boundary prevents race conditions, stale draft publishing, or accidental duplicate insertions.

---

## 4. Test Coverage & Verification

- **Feature Tests** (`tests/Feature/ContentReviewWorkflowTest.php`):
  - `admin can approve a submitted question` (verified status & reviewer updated)
  - `admin can reject a submitted question with required review notes` (verified notes & status)
  - `rejecting a submitted question requires non-empty review notes` (validation exception on blank notes)
  - `cannot publish a pending submitted question` (prevented with validation error)
  - `cannot publish a rejected submitted question` (prevented with validation error)
  - `publishing an approved submitted question creates live question record with matching data` (matches quiz, chapter, question text, choices, correct choice, explanation)
  - `publishing an approved question attaches to existing quiz if one already exists` (reuses quiz without creating redundant quizzes)
  - `publishing twice fails and does not create duplicate live questions` (prevents duplication)
  - `review status transitions strictly follow valid sequence` (`pending` → `rejected` → `approved` → `published`)
  - `admin can render submitted questions list and filter by review status`
  - `admin can create a submitted question in filament` (auto-populates submitter)
  - `admin can approve a question via filament table action`
  - `admin can reject a question via filament table action with notes`
  - `admin can publish an approved question via filament table action`
- **Resource Loading Tests** (`tests/Feature/Filament/ResourceLoadingTest.php`):
  - Verified index, create, and edit routes for `/admin/submitted-questions`.
- **Full Test Suite**: 149 tests, 819 assertions passed.
- **Code Style**: 100% formatted and verified via Laravel Pint.

---

## 5. Open Questions & Next Steps

- **User-Submitted Questions API**: When community / teacher question submissions are opened via mobile or web client, a Sanctum-authenticated API endpoint can reuse `SubmittedQuestion` and `ContentReviewService` directly without modification.
- **Email / In-App Notifications**: If desired, an event can be dispatched when a question is rejected or published to notify the original submitter.
