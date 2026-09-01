# Task Report 017a — Business Settings Store (Part 1: General Tunables)

## 1. Executive Summary
Implemented a dynamic, database-backed **Business Settings Store** to replace previously hardcoded numerical literals across the platform. Configurable settings cover quiz XP rewards, XP-to-coin conversion ratios, rewarded ad caps and cooldowns, daily check-in cooldowns, referral cooldowns, exam session time caps, and weekly challenge reward thresholds.

All business settings are typed (`integer`, `decimal`, `boolean`), cached in-memory at the request level to avoid redundant database reads, seeded with their existing production values to ensure backward compatibility, and editable via a dedicated Filament admin page under **Settings > Business Settings**.

---

## 2. Database Schema & Model Architecture

### 2.1 Table: `business_settings`
Created via migration `2026_09_02_000002_create_business_settings_table.php`:
| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint unsigned` | Primary Key, Auto Increment | Unique record ID |
| `key` | `string` | Unique, Indexed | Unique configuration key |
| `value` | `string` | Non-null | Stored value as string representation |
| `type` | `string` | Backed by `BusinessSettingType` | Data type (`integer`, `decimal`, `boolean`) |
| `group` | `string` | Indexed | Group identifier for Filament UI sections |
| `description` | `text` | Nullable | Explanatory description shown as help text |
| `created_at` | `timestamp` | Nullable | Creation timestamp |
| `updated_at` | `timestamp` | Nullable | Update timestamp |

### 2.2 Model & In-Memory Caching: `App\Models\BusinessSetting`
- **Request-Level In-Memory Cache**:
  - `protected static array $runtimeCache = [];`
  - Prevents repeated database queries when services or middleware query the same setting multiple times within a single HTTP request or queue job lifecycle.
- **Methods**:
  - `BusinessSetting::get(string $key, mixed $default = null): mixed`:
    Checks static `$runtimeCache` first. If missing, loads from database, casts to the defined PHP type, caches, and returns. If not found in DB, returns `$default`.
  - `BusinessSetting::set(string $key, mixed $value): static`:
    Persists new value to DB, updates static runtime cache, and returns model instance.
  - `BusinessSetting::flushRuntimeCache(): void`:
    Resets the in-memory cache.
  - `castValue(?string $value): mixed`:
    - `BusinessSettingType::Integer` -> `(int) $value`
    - `BusinessSettingType::Decimal` -> `(float) $value`
    - `BusinessSettingType::Boolean` -> `filter_var($value, FILTER_VALIDATE_BOOLEAN)`
  - **Model Events**: `saved` and `deleted` hooks automatically keep `$runtimeCache` in sync with database modifications.

---

## 3. Seeded Business Settings Reference

| Key | Group | Type | Seeded Value | Description / Scope |
| :--- | :--- | :--- | :--- | :--- |
| `quiz_xp_per_correct_answer` | `quiz` | `integer` | `10` | XP awarded per correct question in chapter quizzes. |
| `quiz_xp_completion_bonus` | `quiz` | `integer` | `20` | Bonus XP awarded for submitting a completed quiz. |
| `xp_to_coins_ratio_xp` | `xp_economy` | `integer` | `1500` | XP threshold chunk required to convert into coins. |
| `xp_to_coins_ratio_coins` | `xp_economy` | `integer` | `50` | Coins awarded per XP conversion threshold chunk. |
| `watch_ad_daily_cap` | `missions` | `integer` | `5` | Maximum number of rewarded ads a student can watch per reset window. |
| `watch_ad_reset_hours` | `missions` | `integer` | `20` | Rolling window in hours before watched ad count resets. |
| `checkin_reset_hours` | `missions` | `integer` | `20` | Cooldown window in hours before a student can claim the next daily check-in. |
| `referral_cap_hours` | `missions` | `integer` | `24` | Rolling window in hours between eligible referral rewards for a referrer. |
| `exam_time_cap_mcq_minutes` | `exam_sessions` | `integer` | `90` | Maximum allowed time in minutes for MCQ-only past paper exam sessions. |
| `exam_time_cap_structural_minutes` | `exam_sessions` | `integer` | `180` | Maximum allowed time in minutes for structural/essay-only exam sessions. |
| `exam_time_cap_mixed_minutes` | `exam_sessions` | `integer` | `240` | Maximum allowed time in minutes for mixed composition past paper exam sessions. |
| `challenge_reward_high_threshold_percent` | `weekly_challenges` | `decimal` | `95.0` | Score percentage threshold required to earn the highest tier challenge coin reward. |
| `challenge_reward_high_coins` | `weekly_challenges` | `integer` | `100` | Coins awarded for achieving the highest weekly challenge tier. |
| `challenge_reward_mid_threshold_percent` | `weekly_challenges` | `decimal` | `70.0` | Score percentage threshold required to earn the mid-tier challenge coin reward. |
| `challenge_reward_mid_coins` | `weekly_challenges` | `integer` | `50` | Coins awarded for achieving the mid-tier weekly challenge score. |

---

## 4. Service Refactorings

### 4.1 `QuizService` (`app/Services/QuizService.php`)
Replaced hardcoded `($correctCount * 10) + 20` with:
```php
$xpPerCorrect = (int) BusinessSetting::get('quiz_xp_per_correct_answer', 10);
$completionBonus = (int) BusinessSetting::get('quiz_xp_completion_bonus', 20);
$quizXp = ($correctCount * $xpPerCorrect) + $completionBonus;
```

### 4.2 `XpService` (`app/Services/XpService.php`)
Replaced hardcoded `1500` XP and `50` coins conversion chunks with:
```php
$xpRatioThreshold = (int) BusinessSetting::get('xp_to_coins_ratio_xp', 1500);
$coinsRatioReward = (int) BusinessSetting::get('xp_to_coins_ratio_coins', 50);

$availableXp = max(0, $lockedUser->experience_points - $lockedUser->xp_converted_total);
$chunks = $xpRatioThreshold > 0 ? intdiv($availableXp, $xpRatioThreshold) : 0;
if ($chunks > 0) {
    $xpToConvert = $chunks * $xpRatioThreshold;
    $coinsEarned = $chunks * $coinsRatioReward;
    ...
}
```

### 4.3 `MissionService` (`app/Services/MissionService.php`)
- **Daily Check-in**: Replaced hardcoded `20` hours cooldown and `40` hours skip reset with `BusinessSetting::get('checkin_reset_hours', 20)`.
- **Referral Rewards**: Replaced hardcoded `24` hours limit with `BusinessSetting::get('referral_cap_hours', 24)`.
- **XP Conversion**: Replaced hardcoded `1500` XP and `50` coins with `BusinessSetting::get('xp_to_coins_ratio_xp', 1500)` and `BusinessSetting::get('xp_to_coins_ratio_coins', 50)`.
- **Mission Progress Screen**: Uses `watch_ad_reset_hours`, `watch_ad_daily_cap`, and `checkin_reset_hours` to calculate `remaining_ads`, `can_watch_ad`, and `next_checkin_at`.

### 4.4 `AdRewardService` (`app/Services/AdRewardService.php`)
Replaced hardcoded `20` hours and `5` ad limits with:
```php
$resetHours = (int) BusinessSetting::get('watch_ad_reset_hours', 20);
$dailyCap = (int) BusinessSetting::get('watch_ad_daily_cap', 5);

$watchedInWindow = $this->getAdsWatchedInWindow($user, $resetHours);
if ($watchedInWindow >= $dailyCap) { ... }
```

### 4.5 `ExamSessionService` (`app/Services/ExamSessionService.php`)
Replaced hardcoded `90`, `180`, and `240` minutes duration caps with:
```php
if ($hasMcq && ! $hasStructural) {
    $maxAllowedMinutes = (int) BusinessSetting::get('exam_time_cap_mcq_minutes', 90);
} elseif (! $hasMcq && $hasStructural) {
    $maxAllowedMinutes = (int) BusinessSetting::get('exam_time_cap_structural_minutes', 180);
} else {
    $maxAllowedMinutes = (int) BusinessSetting::get('exam_time_cap_mixed_minutes', 240);
}
```

### 4.6 `ChallengeService` (`app/Services/ChallengeService.php`)
In `calculateCoinsForScore(float $scorePercent): int`, replaced hardcoded cutoffs:
```php
$highThreshold = (float) BusinessSetting::get('challenge_reward_high_threshold_percent', 95.0);
$highCoins = (int) BusinessSetting::get('challenge_reward_high_coins', 100);
$midThreshold = (float) BusinessSetting::get('challenge_reward_mid_threshold_percent', 70.0);
$midCoins = (int) BusinessSetting::get('challenge_reward_mid_coins', 50);

if ($scorePercent >= $highThreshold) {
    return $highCoins;
}

if ($scorePercent >= $midThreshold) {
    return $midCoins;
}

return 0;
```

---

## 5. Filament Admin Panel Integration
- **Page**: `App\Filament\Pages\ManageBusinessSettings`
- **Route**: `/admin/business-settings`
- **Navigation**: Placed under **Settings** group with `Heroicon::OutlinedAdjustmentsHorizontal` icon.
- **Sections**:
  1. **Quiz Settings** (`quiz_xp_per_correct_answer`, `quiz_xp_completion_bonus`)
  2. **XP & Coins Economy** (`xp_to_coins_ratio_xp`, `xp_to_coins_ratio_coins`)
  3. **Missions & Cooldowns** (`watch_ad_daily_cap`, `watch_ad_reset_hours`, `checkin_reset_hours`, `referral_cap_hours`)
  4. **Exam Session Time Limits** (`exam_time_cap_mcq_minutes`, `exam_time_cap_structural_minutes`, `exam_time_cap_mixed_minutes`)
  5. **Weekly Challenge Rewards** (`challenge_reward_high_threshold_percent`, `challenge_reward_high_coins`, `challenge_reward_mid_threshold_percent`, `challenge_reward_mid_coins`)
- **Descriptions**: Rendered automatically as helper text under each input.
- **Form Submission**: Iterates through state, updates `BusinessSetting` database rows, invokes `BusinessSetting::flushRuntimeCache()`, and sends Filament success notification.

---

## 6. Test Verification & Code Quality

Created feature tests in `tests/Feature/BusinessSettingsStoreTest.php` covering:
1. `BusinessSetting::get reads, type-casts, and caches values in memory` — Passed.
2. `QuizService respects custom quiz XP settings from BusinessSetting` — Passed.
3. `XpService respects custom XP to coins conversion settings from BusinessSetting` — Passed.
4. `MissionService convertXp respects custom ratio settings from BusinessSetting` — Passed.
5. `AdRewardService respects custom watch ad daily cap and reset hours from BusinessSetting` — Passed.
6. `MissionService checkin respects custom checkin reset hours from BusinessSetting` — Passed.
7. `MissionService referral respects custom referral cap hours from BusinessSetting` — Passed.
8. `ExamSessionService respects custom duration caps from BusinessSetting` — Passed.
9. `ChallengeService calculateCoinsForScore respects custom threshold and reward settings from BusinessSetting` — Passed.
10. `admin can update business settings via Filament ManageBusinessSettings page` — Passed.

### Test Results
- `tests/Feature/BusinessSettingsStoreTest.php`: **10 passed (35 assertions)**
- `tests/Feature/Api`: **107 passed (736 assertions)**
- Code Style: **Laravel Pint 100% passed (0 errors)**

---

## 7. Open Questions & Blockers
- **None**: Implementation is complete, fully tested, and ready for production deployment.
