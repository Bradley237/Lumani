# Task 014 — Admin Dashboard: Live Stats & Analytics Widgets

## 1. Executive Summary

- **Task Goal**: Build an executive Filament admin dashboard with live statistical widgets, urgent action queues, student registration trend visualization, and subject unlock popularity rankings.
- **Test Suite Results**: **170 tests passed (935 assertions), 0 failures, 0 skipped** (5 new feature tests covering all 4 widgets and their real-time queries).
- **Code Quality**: 100% compliant with Laravel Pint standards and Larastan Level 5 static analysis (0 errors).

---

## 2. Components Built & Architecture

### 2.1 "Needs Your Attention" Stat & Action Widget (`NeedsYourAttentionWidget`)
- **Sort Order**: 1 (Surfaced at the top of `/admin` for immediate actionability).
- **Metrics & Actions**:
  - **Pending Question Submissions**: Live count of student-submitted questions with `review_status = 'pending'`, linking directly to the Submitted Questions index (`/admin/submitted-questions`). Displays warning icon & yellow accent when pending items exist, or a green checkmark when clear.
  - **Unified Ungraded Structural Answers**: Real-time combined count of ungraded structural/essay answers across **Weekly Challenges** (`UserChallengeAnswer` with `points_awarded = null` on submitted attempts) and **Exam Mode Sessions** (`ExamSessionAnswer` with `points_awarded = null` on submitted sessions). Links directly to the Unified Grading Queue (`/admin/grading-queue`). Displays danger red badge when grading items are waiting.

### 2.2 Live Platform Overview Widget (`DashboardStatsOverviewWidget`)
- **Sort Order**: 2.
- **6 Real-Time Database Metrics**:
  1. **Total Students**: Total student user count, accompanied by a dynamic sub-label indicating `+N registered this week`.
  2. **Active Subscriptions**: Total active subscriptions (`status = active` and `end_date > now()`), with a sub-label breaking down standard vs. premium tiers (`2k FCFA: X | 5k FCFA: Y`).
  3. **Coins in Circulation & Weekly Spend**: Total platform coin volume (sum of all `User.coin_balance`), plus total coins consumed this week (absolute sum of negative `CoinTransaction` rows created since `startOfWeek()`).
  4. **Chapters Completed**: Total chapters marked completed in `chapter_progress` this week.
  5. **Average Quiz Score**: Average `score_percent` across all `quiz_attempts` submitted this week (formatted as percentage or `N/A`).
  6. **AI Tutor Conversations**: Total Lumani AI Tutor chat threads initiated since `startOfWeek()`.

### 2.3 30-Day Registration Trend Chart (`StudentRegistrationsChartWidget`)
- **Sort Order**: 3 (Line chart widget).
- **Functionality**: Chronological rolling 30-day timeline charting daily student signups. Points are grouped by `created_at` timestamp with smooth bezier curves and Amber theme fill.

### 2.4 Popular Subjects Ranking Widget (`PopularSubjectsWidget`)
- **Sort Order**: 4 (Table widget).
- **Query & Relationship**: Leverages the new `Subject->chapterUnlocks()` `HasManyThrough` relationship to rank subjects by total `UserChapterUnlock` coin purchases completed within the current calendar month (`created_at >= startOfMonth()`).
- **Columns**: Subject Name (bold), Exam Subsystem (general/technical badge), Monthly Unlocks (primary badge), and Total Chapters count.

### 2.5 Filament Admin Panel Integration (`AdminPanelProvider`)
- Registered the four widgets and removed default demo scaffolding widgets (`AccountWidget`, `FilamentInfoWidget`), turning `/admin` into an insightful command center for administrators.

---

## 3. Visual Layout & UI Structure Description

```
+----------------------------------------------------------------------------------------------------+
|  Lumani Administration Panel                                                         [Kum Bradley] |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  [!] NEEDS YOUR ATTENTION                                                                          |
|  +-----------------------------------------------+ +-----------------------------------------------+ |
|  | Pending Questions Review                      | | Ungraded Structural Answers                   | |
|  | 2                                             | | 4                                             | |
|  | [!] Questions submitted awaiting review       | | [!] Weekly Challenges: 2 | Exams: 2          | |
|  | -> Link: /admin/submitted-questions          | | -> Link: /admin/grading-queue                 | |
|  +-----------------------------------------------+ +-----------------------------------------------+ |
|                                                                                                    |
|  [*] PLATFORM OVERVIEW                                                                             |
|  +-----------------------+ +-----------------------+ +-----------------------+                    |
|  | Total Students        | | Active Subscriptions  | | Coins in Circulation  |                    |
|  | 1,420                 | | 85                    | | 245,000               |                    |
|  | +42 registered week   | | 2k: 60 | 5k: 25       | | 12,400 spent week     |                    |
|  +-----------------------+ +-----------------------+ +-----------------------+                    |
|  +-----------------------+ +-----------------------+ +-----------------------+                    |
|  | Chapters Completed    | | Avg Quiz Score        | | AI Tutor Convs        |                    |
|  | 318                   | | 76.4%                 | | 142                   |                    |
|  | Completed this week   | | Across attempts week  | | Started this week     |                    |
|  +-----------------------+ +-----------------------+ +-----------------------+                    |
|                                                                                                    |
|  [#] TRENDS & ENGAGEMENT                                                                           |
|  +-----------------------------------------------+ +-----------------------------------------------+ |
|  | Student Registrations (Last 30 Days)          | | Popular Subjects (Chapter Unlocks This Month) | |
|  | ~~~/\_/\_/\___ (30-day Line Chart)            | | 1. Mathematics (GCE O-Level)  | 142 unlocks   | |
|  |                                               | | 2. Physics (GCE A-Level)      | 98 unlocks    | |
|  |                                               | | 3. Chemistry (GCE O-Level)    | 74 unlocks    | |
|  +-----------------------------------------------+ +-----------------------------------------------+ |
+----------------------------------------------------------------------------------------------------+
```

---

## 4. Verification & Testing

- `tests/Feature/Filament/DashboardWidgetsTest.php`:
  1. `admin can access dashboard and see all analytics widgets`: Verifies 200 OK and presence of all 4 Livewire widgets on `/admin`.
  2. `dashboard stats overview computes accurate live platform statistics`: Tests exact computation of student totals, week additions, tier breakdowns (2,000 vs 5,000 FCFA), coin circulation sum, weekly spent sum, completed chapters, average score calculations, and AI conversations.
  3. `needs your attention widget surfaces pending submitted questions and unified grading queue items`: Verifies count accuracy and direct navigation URLs for pending submitted questions and structural answers awaiting teacher grading.
  4. `student registrations chart widget outputs accurate 30-day timeline`: Verifies dataset structure and date bucketing across 30 days.
  5. `popular subjects widget ranks subjects by chapter unlocks this month`: Tests month-scoped unlock aggregation and descending ranking.

---

## 5. Open Questions & Blockers

- **Blockers**: None. All requirements delivered and verified.
- **Future Enhancements**: Optional date-range filter for the overview widget (e.g. "This Month", "Last 30 Days", "All Time") as platform dataset scales.
