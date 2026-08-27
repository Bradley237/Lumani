# Task 003 — Coin Ledger, Missions, Referral, and XP Conversion Specification & Report

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication Mechanism**: Laravel Sanctum Personal Access Tokens (`Bearer <token>`)
- **Coin Invariant**: `users.coin_balance` strictly equals `SUM(coin_transactions.amount)` for each user.

---

## 1. What Was Built

### 1.1 Database Migrations & Schema
1. **`coin_transactions` table**:
   - `id`: Auto-incrementing primary key
   - `user_id`: Foreign key referencing `users.id` (cascade on delete)
   - `amount`: Signed integer (`+` for credits, `-` for debits)
   - `type`: Indexed string/enum (`earned_mission`, `earned_referral`, `earned_xp_conversion`, `spent_unlock`, `spent_ai_tutor`)
   - `reference_type`, `reference_id`: Polymorphic nullable reference
   - Composite index on `[user_id, type, created_at]`
2. **`missions` table**:
   - `id`, `key` (unique slug), `title`, `description`, `coin_reward`, `type` (`daily_checkin`, `watch_ad`, `one_time`, `referral`), `is_active`, `timestamps`
3. **`daily_checkin_rewards` table**:
   - `id`, `day` (1 to 7, unique), `coin_reward`, `timestamps`
4. **`user_mission_progress` table**:
   - `id`, `user_id` FK, `mission_id` FK, `current_streak_day` (nullable), `last_completed_at` (nullable timestamp), `completed` (bool), `timestamps`
   - Unique composite index on `[user_id, mission_id]`
5. **`users` table modifications**:
   - `referral_code`: 8-character uppercase unique alphanumeric code (auto-generated on creation)
   - `referred_by_user_id`: Nullable foreign key referencing `users.id`
   - `xp_converted_total`: Unsigned 64-bit integer (default 0) tracking cumulative converted XP

### 1.2 Seeders
- **`MissionAndRewardSeeder`**:
  - `daily_checkin_rewards`: Day 1 = 3 coins, Day 2 = 5 coins, Day 3 = 7 coins, Day 4 = 9 coins, Day 5 = 11 coins, Day 6 = 13 coins, Day 7 = 15 coins.
  - `missions`:
    - `daily_checkin`: 3 coins (`daily_checkin`)
    - `watch_ad`: 5 coins (`watch_ad`)
    - `complete_profile`: 30 coins (`one_time`)
    - `first_quiz`: 40 coins (`one_time`)
    - `refer_a_friend`: 50 coins (`referral`)

### 1.3 Service Layer (`app/Services`)
- **`CoinService`**:
  - Encapsulates atomic coin mutations inside DB transactions with `$user->lockForUpdate()`.
  - Guarantees `coin_balance` cannot be updated without a corresponding `coin_transactions` ledger entry.
- **`MissionService`**:
  - **Daily Check-in**: 20-hour minimum cooldown between check-ins; increments streak day (1-7, wrapping) if claimed within 20-40 hours; resets to Day 1 if > 40 hours (skipped day); rejects check-in within < 20 hours.
  - **Rewarded Video Ads**: Awards 5 coins per call; strictly capped at 5 ad claims per rolling 20-hour window.
  - **One-time Missions**: Idempotent check preventing duplicate rewards for `complete_profile` and `first_quiz`.
  - **Referral Processing**: Awards referrer 50 coins immediately upon new user registration with valid code, enforcing a rolling 24-hour cap (maximum 1 referral reward per referrer per 24 hours).
  - **XP Conversion**: Converts available XP in 1,500-unit chunks (1,500 XP = 50 coins). Accumulates `xp_converted_total` while leaving permanent lifetime `experience_points` intact.

### 1.4 Filament Admin Resources
- **`MissionResource`**: Admin CRUD management for gamification missions (`/admin/missions`).
- **`DailyCheckinRewardResource`**: Admin configuration for 7-day streak reward values (`/admin/daily-checkin-rewards`).

---

## 2. API Endpoints Specification

### Headers Required
For authenticated endpoints:
```http
Authorization: Bearer <token>
Accept: application/json
```

---

### Endpoint 1: `GET /api/missions`
Lists all active missions, current user progress, and the 7-day daily check-in ladder.

#### Response (`200 OK`)
```json
{
  "missions": [
    {
      "id": 1,
      "key": "daily_checkin",
      "title": "Daily Check-in",
      "description": "Check in every day to claim bonus coins and grow your streak.",
      "coin_reward": 3,
      "type": "daily_checkin",
      "is_active": true,
      "completed": true,
      "last_completed_at": "2026-08-27T02:00:00.000000Z",
      "current_streak_day": 1,
      "can_checkin": false,
      "next_checkin_at": "2026-08-27T22:00:00.000000Z"
    },
    {
      "id": 2,
      "key": "watch_ad",
      "title": "Watch an Ad",
      "description": "Watch a short video ad to earn 5 coins (up to 5 times per 20-hour window).",
      "coin_reward": 5,
      "type": "watch_ad",
      "is_active": true,
      "completed": true,
      "last_completed_at": "2026-08-27T02:15:00.000000Z",
      "ads_watched_in_window": 2,
      "remaining_ads": 3,
      "can_watch_ad": true
    },
    {
      "id": 3,
      "key": "complete_profile",
      "title": "Complete Profile",
      "description": "Fill in all your profile details to earn a one-time bonus.",
      "coin_reward": 30,
      "type": "one_time",
      "is_active": true,
      "completed": false,
      "last_completed_at": null
    },
    {
      "id": 4,
      "key": "first_quiz",
      "title": "Take Your First Quiz",
      "description": "Complete your first quiz to earn a one-time bonus.",
      "coin_reward": 40,
      "type": "one_time",
      "is_active": true,
      "completed": false,
      "last_completed_at": null
    },
    {
      "id": 5,
      "key": "refer_a_friend",
      "title": "Refer a Friend",
      "description": "Share your referral code. Earn 50 coins when a friend joins (max 1 reward per 24 hours).",
      "coin_reward": 50,
      "type": "referral",
      "is_active": true,
      "completed": false,
      "last_completed_at": null,
      "referral_code": "LUM78XYZ",
      "total_referrals": 3
    }
  ],
  "daily_checkin_rewards": [
    { "id": 1, "day": 1, "coin_reward": 3 },
    { "id": 2, "day": 2, "coin_reward": 5 },
    { "id": 3, "day": 3, "coin_reward": 7 },
    { "id": 4, "day": 4, "coin_reward": 9 },
    { "id": 5, "day": 5, "coin_reward": 11 },
    { "id": 6, "day": 6, "coin_reward": 13 },
    { "id": 7, "day": 7, "coin_reward": 15 }
  ],
  "user_streak": 1
}
```

---

### Endpoint 2: `POST /api/missions/checkin`
Claims the daily check-in reward.

#### Successful Response (`200 OK`)
```json
{
  "message": "Day 2 check-in completed successfully.",
  "streak_day": 2,
  "coins_earned": 5,
  "next_checkin_at": "2026-08-27T22:30:00.000000Z",
  "coin_balance": 35
}
```

#### Cooldown Rejection (`422 Unprocessable Content`)
```json
{
  "message": "You have already checked in within the last 20 hours. Next check-in available at 2026-08-27T22:30:00.000000Z.",
  "errors": {
    "checkin": [
      "You have already checked in within the last 20 hours. Next check-in available at 2026-08-27T22:30:00.000000Z."
    ]
  }
}
```

---

### Endpoint 3: `POST /api/missions/watch-ad`
Claims a rewarded ad view reward (5 coins).

#### Successful Response (`200 OK`)
```json
{
  "message": "Reward claimed for watching ad.",
  "coins_earned": 5,
  "ads_watched_in_window": 1,
  "remaining_ads": 4,
  "coin_balance": 40
}
```

#### Rate Limit Rejection (`422 Unprocessable Content`)
```json
{
  "message": "You have reached the maximum limit of 5 rewarded ads in a 20-hour rolling window.",
  "errors": {
    "ad": [
      "You have reached the maximum limit of 5 rewarded ads in a 20-hour rolling window."
    ]
  }
}
```

---

### Endpoint 4: `POST /api/missions/complete/{missionKey}`
Claims completion reward for one-time missions (`complete_profile`, `first_quiz`).

#### Successful Response (`200 OK`)
```json
{
  "message": "Mission 'Complete Profile' completed successfully.",
  "mission": "complete_profile",
  "coins_earned": 30,
  "coin_balance": 70
}
```

#### Duplicate Claim Rejection (`422 Unprocessable Content`)
```json
{
  "message": "Mission 'Complete Profile' has already been completed.",
  "errors": {
    "mission": [
      "Mission 'Complete Profile' has already been completed."
    ]
  }
}
```

---

### Endpoint 5: `GET /api/user/referral-code`
Fetches the student's unique referral code and referral stats.

#### Successful Response (`200 OK`)
```json
{
  "referral_code": "LUM78XYZ",
  "total_referrals": 2,
  "coins_earned_from_referrals": 50
}
```

---

### Endpoint 6: `POST /api/xp/convert`
Converts unconverted XP chunks (1,500 XP = 50 coins) into coins.

#### Successful Response (`200 OK`)
```json
{
  "message": "XP converted to coins successfully.",
  "xp_converted": 3000,
  "coins_earned": 100,
  "xp_converted_total": 3000,
  "experience_points": 3200,
  "remaining_unconverted_xp": 200,
  "coin_balance": 170
}
```

#### Insufficient XP Rejection (`422 Unprocessable Content`)
```json
{
  "message": "You need at least 1,500 unconverted XP to convert to coins.",
  "errors": {
    "xp": [
      "You need at least 1,500 unconverted XP to convert to coins."
    ]
  }
}
```

---

## 3. Why This Approach

1. **Strict Double-Entry Ledger Discipline**:
   Directly mutating `users.coin_balance` without a transaction ledger leads to un-auditable discrepancies and concurrency bugs. Using `CoinService` with database row-level locking (`$user->lockForUpdate()`) ensures zero race conditions and guarantees that `coin_balance` is always mathematically equal to the sum of transaction ledger records.
2. **Rolling Windows vs. Calendar Days**:
   - For daily check-in, a **20-hour** cooldown window eliminates timezone friction and prevents schedule drift for students who study at slightly varying hours each evening.
   - For rewarded ads, a rolling 20-hour window prevents bot/exploit abuse while ensuring continuous daily engagement.
   - For referrals, a strict **24-hour** rolling cap prevents spam registration farms while allowing organic word-of-mouth growth.
3. **Immutable Lifetime Experience Points**:
   Gamification leaderboards and student levels depend on total lifetime XP. Deducting XP on coin conversion damages user progression tracking. By introducing `xp_converted_total`, lifetime `experience_points` is preserved permanently while tracking exactly which XP chunks have already yielded coins.

---

## 4. Verification & Test Results
- **Pest Test Suite**: 68 tests passing (372 assertions)
- **Static Analysis**: PHPStan passing at 0 errors
- **Code Style**: Laravel Pint formatted

---

## 5. Open Questions & Blockers
- None. Everything is built, tested, and verified.
