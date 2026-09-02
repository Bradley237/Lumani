# Task Report 021 — Admin Navigation Reorganization

**Date:** 2026-09-02  
**Task:** Reorganize every Filament resource/page under semantic navigation groups, assign distinct
Heroicons, set sort orders, create `UserResource`, and confirm Dashboard is the landing page.

---

## Before — Sidebar Structure

The sidebar had no consistent grouping. Resources were scattered across mismatched groups or ungrouped
entirely, and several resources shared the same default icon (`OutlinedRectangleStack`).

| Resource / Page | Old Group | Old Icon |
|---|---|---|
| SubjectResource | *(ungrouped)* | `OutlinedRectangleStack` |
| ChapterResource | *(ungrouped)* | `OutlinedRectangleStack` |
| QuizResource | *(ungrouped)* | `OutlinedRectangleStack` |
| QuestionResource | *(ungrouped)* | `OutlinedRectangleStack` |
| PastPaperResource | Curriculum | `OutlinedDocumentText` |
| CareerProfileResource | Academic Management | `OutlinedBriefcase` |
| MissionResource | Gamification | `OutlinedSparkles` |
| DailyCheckinRewardResource | Gamification | `OutlinedCalendarDays` |
| SubscriptionResource | Gamification | `OutlinedCreditCard` |
| WeeklyChallengeResource | Gamification | `OutlinedTrophy` |
| GradingQueue (Page) | Gamification | `OutlinedClipboardDocumentCheck` |
| SubmittedQuestionResource | *(ungrouped)* | `OutlinedClipboardDocumentList` |
| ManageBusinessSettings (Page) | Settings | `OutlinedAdjustmentsHorizontal` |
| ManageAppSettings (Page) | Settings | `OutlinedCog6Tooth` |
| UserResource | *did not exist* | — |

---

## After — Sidebar Structure

The sidebar is now organized into six clear semantic groups:

### Content (sort 1–6)
Reflects the learning content hierarchy — from broad subjects down to individual questions.

| Sort | Resource | Icon |
|---|---|---|
| 1 | SubjectResource | `OutlinedAcademicCap` |
| 2 | ChapterResource | `OutlinedBookOpen` |
| 3 | QuizResource | `OutlinedClipboardDocumentCheck` |
| 4 | QuestionResource | `OutlinedQuestionMarkCircle` |
| 5 | PastPaperResource | `OutlinedDocumentText` |
| 6 | CareerProfileResource | `OutlinedBriefcase` |

### Economy (sort 1–4)
Everything that drives the in-app economy: missions, daily rewards, business config, and subscriptions.

| Sort | Resource / Page | Icon |
|---|---|---|
| 1 | MissionResource | `OutlinedSparkles` |
| 2 | DailyCheckinRewardResource | `OutlinedCalendarDays` |
| 3 | ManageBusinessSettings (Page) | `OutlinedAdjustmentsHorizontal` |
| 4 | SubscriptionResource | `OutlinedCreditCard` |

### Engagement (sort 1–2)
Tools for managing time-bound competitive features and manual grading.

| Sort | Resource / Page | Icon |
|---|---|---|
| 1 | WeeklyChallengeResource | `OutlinedTrophy` |
| 2 | GradingQueue (Page) | `OutlinedClipboardDocumentList` |

### Moderation (sort 1)
Student-submitted content that requires admin review.

| Sort | Resource | Icon |
|---|---|---|
| 1 | SubmittedQuestionResource | `OutlinedFlag` |

### Users (sort 1)
Read-only user directory — newly created.

| Sort | Resource | Icon |
|---|---|---|
| 1 | UserResource *(new)* | `OutlinedUsers` |

### Settings (sort 1)
Platform-wide feature flags and overrides.

| Sort | Page | Icon |
|---|---|---|
| 1 | ManageAppSettings | `OutlinedCog6Tooth` |

---

## New Files Created

| File | Purpose |
|---|---|
| `app/Filament/Resources/Users/UserResource.php` | Root resource — read-only (no create/edit) |
| `app/Filament/Resources/Users/Pages/ListUsers.php` | Paginated user list with filters |
| `app/Filament/Resources/Users/Pages/ViewUser.php` | Detail view: personal info, economy, exam profile |
| `app/Filament/Resources/Users/Tables/UsersTable.php` | Table columns, filters, view action |

`UserResource` is intentionally read-only (`canCreate()` returns `false`, no edit/delete actions) to
protect production data. Columns: name, email, role badge, coins, XP, streak, exam system, level,
registered date. Filters: role, exam system.

---

## Dashboard Landing Page

No changes were required. `AdminPanelProvider` already registers `Dashboard::class` in its `pages([])`
array and uses `->default()`, which makes the Filament dashboard the landing page at `/admin` after login.

---

## Notes on `SubscriptionTierResource`

The task brief listed `SubscriptionTierResource` under Economy. This resource does not exist and cannot
be created because `SubscriptionTier` is a PHP-backed `enum` (two hard-coded values: `tier_2000`,
`tier_5000`) — it has no corresponding database table or Eloquent model to CRUD against. The existing
`SubscriptionResource` (which manages actual `subscriptions` table rows) is the correct Economy entry.

---

## Files Modified

| File | Change |
|---|---|
| `SubjectResource.php` | Group→Content, icon→AcademicCap, sort→1 |
| `ChapterResource.php` | Group→Content, icon→BookOpen, sort→2 |
| `QuizResource.php` | Group→Content, icon→ClipboardDocumentCheck, sort→3 |
| `QuestionResource.php` | Group→Content, icon→QuestionMarkCircle, sort→4 |
| `PastPaperResource.php` | Group Curriculum→Content, sort→5 |
| `CareerProfileResource.php` | Group AcademicManagement→Content, sort→6 |
| `MissionResource.php` | Group Gamification→Economy, sort→1 |
| `DailyCheckinRewardResource.php` | Group Gamification→Economy, sort→2 |
| `ManageBusinessSettings.php` | Group Settings→Economy, sort→3 |
| `SubscriptionResource.php` | Group Gamification→Economy, sort→4 |
| `WeeklyChallengeResource.php` | Group Gamification→Engagement, sort→1 |
| `GradingQueue.php` | Group Gamification→Engagement, icon changed, sort→2 |
| `SubmittedQuestionResource.php` | Group→Moderation, icon→Flag, sort→1 |
| `ManageAppSettings.php` | sort→1 (group Settings unchanged) |
