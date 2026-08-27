# Task 008b — Guarantee Top-Performing Subject Leads Career Pathway Results

## Overview & Status
- **Status**: Completed & Fully Verified
- **Key Invariants**:
  - `CareerPathwayService::buildStudentPerformanceSummary()` identifies the student's single top-performing subject (highest average quiz `score_percent`) among subjects with **at least 2 quiz attempts** (preventing a single lucky quiz attempt from skewing recommendations). If no subject meets the 2+ attempt threshold, `top_subject` is `null`.
  - The `top_subject` is explicitly passed in the Gemini AI prompt to bias ranking towards relevant fields.
  - In code, post-validation re-sorting enforces that all recommended `career_profiles` whose `related_subjects` include `top_subject` are placed at the front of the recommendations list.
  - The re-sorting uses a stable partition: matching-subject careers appear first in their original Gemini relative order, followed by remaining careers in their original Gemini relative order.
  - Every recommendation preserves its AI-generated `match_score` and `reasoning` untouched; only the presentation order changes.
  - When `top_subject` is `null` (insufficient data), Gemini's ranking is preserved as-is.

---

## 1. What Was Built

### 1.1 `CareerPathwayService::buildStudentPerformanceSummary()`
- Aggregates subject stats and tracks `quiz_attempts_count` alongside average score percentages.
- Filters subjects where `attempt_count >= 2`.
- Sorts qualifying subjects descending by average score to select `top_subject` (or `null` if none qualify).
- Returns structured payload:
  ```php
  array{
      subjects: array<int, array<string, mixed>>,
      top_subject: string|null
  }
  ```

### 1.2 `CareerPathwayService::generate()`
- Includes top-performing subject highlight in the prompt to Gemini.
- Receives and validates Gemini recommendations.
- Applies programmatic re-sorting:
  ```php
  if ($topSubject !== null) {
      $profilesMap = $careerProfiles->keyBy('id');
      $matching = [];
      $nonMatching = [];

      foreach ($recommendations as $rec) {
          $profile = $profilesMap->get($rec['career_profile_id']);
          $related = $profile !== null ? $profile->related_subjects : null;

          $hasTopSubject = false;
          if ($related !== null) {
              foreach ($related as $subj) {
                  if (strcasecmp(trim($subj), trim($topSubject)) === 0) {
                      $hasTopSubject = true;
                      break;
                  }
              }
          }

          if ($hasTopSubject) {
              $matching[] = $rec;
          } else {
              $nonMatching[] = $rec;
          }
      }

      $recommendations = array_merge($matching, $nonMatching);
  }
  ```

---

## 2. Why This Approach

1. **Threshold Protection (2+ Quiz Attempts)**:
   A student taking a single short quiz and scoring 100% might not represent sustained academic strength. Enforcing a minimum of 2 quiz attempts ensures statistically meaningful top-subject identification.
2. **Dual-Layer Bias (Prompt Guidance + Deterministic Re-sort)**:
   While Gemini is prompted with the top subject to craft relevant qualitative reasoning, LLMs can occasionally return sub-optimal rankings. Deterministically re-sorting the final list in backend code provides a 100% guarantee that matching careers lead the user experience.
3. **Score & Reasoning Integrity**:
   The re-sort only reorganizes array elements without mutating `match_score` or `reasoning`, maintaining transparent and coherent explanations for every career.

---

## 3. Verification & Test Results
- **Automated Tests**: 119 tests passing (688 assertions) across the entire backend suite.
  - `tests/Feature/Api/CareerPathwayTest.php`:
    - `test('guarantees top-performing subject leads career pathway results and preserves scores')`: Verifies that with 2+ quiz attempts, a matching career is moved to index 0, retaining its score and reasoning while non-matching items preserve relative order.
    - `test('uses original Gemini ordering unchanged when no subject has 2+ quiz attempts')`: Verifies that single-attempt subjects do not trigger re-sorting and preserve exact Gemini ordering.
- **Static Analysis**: PHPStan passing at 0 errors (`phpstan analyse --memory-limit=512M`).
- **Code Style**: 100% compliant with Laravel Pint.

---

## 4. Open Questions & Blockers
- None.
