# Lumani Mobile API Reference

A living, comprehensive reference manual for frontend developers (Flutter/Mobile) and API consumers integrating with the Lumani backend.

---

## Global Conventions

- **Base URL**: `http://<host>/api` (or `https://<domain>/api` in production)
- **Headers**:
  - `Accept: application/json` (Required on all requests)
  - `Content-Type: application/json` (Required on POST/PUT requests)
  - `Authorization: Bearer <sanctum_token>` (Required for all authenticated routes)
- **Standard Error Responses**:
  - `401 Unauthorized`: Missing or invalid Bearer token.
    ```json
    {
      "message": "Unauthenticated."
    }
    ```
  - `403 Forbidden`: Subscription required or unauthorized access to another user's resources.
    ```json
    {
      "message": "An active subscription is required to access this feature."
    }
    ```
  - `422 Unprocessable Entity`: Validation failure or business rule violation.
    ```json
    {
      "message": "The given data was invalid.",
      "errors": {
        "email": ["The email has already been taken."]
      }
    }
    ```

---

## 1. Authentication & User Profile

### 1.1 Register Student
Creates a new student account, optionally credits the referrer if a referral code is provided, dispatches a welcome email, and issues a personal access token.

- **Method**: `POST`
- **URL**: `/api/register`
- **Route Name**: `api.register`
- **Auth**: Public
- **Gating**: None
- **Rate Limit**: `throttle:auth` (10 requests / min / IP)

#### Request Body
| Field | Type | Required | Rules | Description |
|---|---|---|---|---|
| `first_name` | `string` | Yes | max: 255 | Student's first name |
| `last_name` | `string` | Yes | max: 255 | Student's last name |
| `email` | `string` | Yes | email, max: 255, unique:users | Email address |
| `password` | `string` | Yes | min: 8, confirmed | Account password |
| `password_confirmation` | `string` | Yes | matches `password` | Password confirmation |
| `preferred_language` | `string` | No | `en` or `fr` (default: `en`) | UI language preference |
| `phone_number` | `string` | No | max: 50 | Mobile phone number |
| `referral_code` | `string` | No | exists:users,referral_code | Valid referral code of another user |
| `exam_system` | `string` | No | `in:gce,obc` | Educational subsystem (`gce` or `obc`) |
| `level` | `string` | No | Valid level for `exam_system` | Academic level (`ordinary_level`, `advanced_level` for GCE; `bepc`, `probatoire`, `bac` for OBC) |
| `exam_date` | `string` | No | date (`YYYY-MM-DD`) | Target examination date |

```json
{
  "first_name": "Samuel",
  "last_name": "Eto'o",
  "email": "samuel@example.com",
  "password": "SecretPassword123!",
  "password_confirmation": "SecretPassword123!",
  "preferred_language": "en",
  "phone_number": "+237670000000",
  "referral_code": "REF872A1B",
  "exam_system": "gce",
  "level": "ordinary_level",
  "exam_date": "2027-05-15"
}
```

#### Response Body (`201 Created`)
```json
{
  "message": "User registered successfully.",
  "token": "1|qWeRtYuIoP1234567890abcdef...",
  "token_type": "Bearer",
  "user": {
    "id": 42,
    "first_name": "Samuel",
    "last_name": "Eto'o",
    "email": "samuel@example.com",
    "role": "student",
    "preferred_language": "en",
    "phone_number": "+237670000000",
    "coin_balance": 0,
    "experience_points": 0,
    "xp_converted_total": 0,
    "day_streak": 0,
    "referral_code": "SAMU429B",
    "referred_by_user_id": 12,
    "created_at": "2026-08-30T10:00:00.000000Z",
    "updated_at": "2026-08-30T10:00:00.000000Z"
  }
}
```

---

### 1.2 Login
Authenticates an existing student and returns an API token.

- **Method**: `POST`
- **URL**: `/api/login`
- **Route Name**: `api.login`
- **Auth**: Public
- **Gating**: None
- **Rate Limit**: `throttle:auth` (10 requests / min / IP)

#### Request Body
| Field | Type | Required | Rules | Description |
|---|---|---|---|---|
| `email` | `string` | Yes | email | Account email |
| `password` | `string` | Yes | string | Account password |

```json
{
  "email": "samuel@example.com",
  "password": "SecretPassword123!"
}
```

#### Response Body (`200 OK`)
```json
{
  "message": "Logged in successfully.",
  "token": "2|yUiOpAsDfG0987654321fedcba...",
  "token_type": "Bearer",
  "user": {
    "id": 42,
    "first_name": "Samuel",
    "last_name": "Eto'o",
    "email": "samuel@example.com",
    "role": "student",
    "preferred_language": "en",
    "coin_balance": 15,
    "experience_points": 350,
    "day_streak": 3
  }
}
```

#### Error Response (`401 Unauthorized`)
```json
{
  "message": "Invalid credentials."
}
```

---

### 1.3 Logout
Revokes the student's current access token.

- **Method**: `POST`
- **URL**: `/api/logout`
- **Route Name**: `api.logout`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Request Body
None.

#### Response Body (`200 OK`)
```json
{
  "message": "Logged out successfully."
}
```

---

### 1.4 Get Authenticated User Profile
Retrieves the full profile of the currently authenticated student.

- **Method**: `GET`
- **URL**: `/api/user`
- **Route Name**: `api.user`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Response Body (`200 OK`)
```json
{
  "user": {
    "id": 42,
    "first_name": "Samuel",
    "last_name": "Eto'o",
    "email": "samuel@example.com",
    "role": "student",
    "preferred_language": "en",
    "phone_number": "+237670000000",
    "coin_balance": 25,
    "experience_points": 1250,
    "xp_converted_total": 0,
    "day_streak": 4,
    "referral_code": "SAMU429B",
    "created_at": "2026-08-30T10:00:00.000000Z",
    "updated_at": "2026-08-30T10:00:00.000000Z"
  }
}
```

---

### 1.5 Get Referral Code & Referral Stats
Retrieves the student's personal referral code, count of invited friends, and total coins earned through referrals.

- **Method**: `GET`
- **URL**: `/api/user/referral-code`
- **Route Name**: `api.user.referral-code`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Response Body (`200 OK`)
```json
{
  "referral_code": "SAMU429B",
  "total_referrals": 3,
  "coins_earned_from_referrals": 150
}
```

---

### 1.6 Get Exam Options (Subsystem & Level Mapping)
Retrieves the complete valid mapping of Cameroon secondary education exam subsystems (`gce`, `obc`) to their allowed academic levels, allowing frontend clients to render dynamic cascading dropdowns without hardcoding pairings.

- **Method**: `GET`
- **URL**: `/api/exam-options`
- **Route Name**: `api.exam-options`
- **Auth**: Public
- **Gating**: None

#### Response Body (`200 OK`)
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

---

### 1.7 Update Authenticated User Profile
Updates the authenticated student's personal details, UI language, exam subsystem, academic level, or target exam date. Rejects any invalid or mismatched subsystem + level combination with `422 Unprocessable Entity`.

- **Method**: `PUT` or `PATCH`
- **URL**: `/api/user`
- **Route Name**: `api.user.update`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Request Body
| Field | Type | Required | Rules | Description |
|---|---|---|---|---|
| `first_name` | `string` | No | string, max: 255 | Student first name |
| `last_name` | `string` | No | string, max: 255 | Student last name |
| `preferred_language` | `string` | No | `in:en,fr` | Language code |
| `phone_number` | `string` | No | max: 50 | Mobile phone number |
| `exam_system` | `string` | No | `in:gce,obc` | Educational subsystem |
| `level` | `string` | No | Valid level for chosen `exam_system` | Academic level |
| `exam_date` | `string` | No | date (`YYYY-MM-DD`) | Target examination date |

```json
{
  "first_name": "Samuel",
  "last_name": "Eto'o Fils",
  "preferred_language": "fr",
  "exam_system": "obc",
  "level": "bac",
  "exam_date": "2027-06-20"
}
```

#### Response Body (`200 OK`)
```json
{
  "message": "Profile updated successfully.",
  "user": {
    "id": 42,
    "first_name": "Samuel",
    "last_name": "Eto'o Fils",
    "email": "samuel@example.com",
    "role": "student",
    "preferred_language": "fr",
    "phone_number": "+237670000000",
    "coin_balance": 25,
    "experience_points": 1250,
    "xp_converted_total": 0,
    "day_streak": 4,
    "exam_system": "obc",
    "level": "bac",
    "exam_date": "2027-06-20",
    "referral_code": "SAMU429B",
    "created_at": "2026-08-30T10:00:00.000000Z",
    "updated_at": "2026-08-31T21:00:00.000000Z"
  }
}
```

#### Error Response (`422 Unprocessable Entity`)
```json
{
  "message": "The selected level 'bac' is not valid for the 'gce' exam subsystem.",
  "errors": {
    "level": [
      "The selected level 'bac' is not valid for the 'gce' exam subsystem."
    ]
  }
}
```

---

## 2. Content, Chapters & Past Papers

### 2.1 Get Overall Student Progress
Fetches complete syllabus progress grouped by subject, including unlock status, coin prices, XP rewards, and completion status.

- **Method**: `GET`
- **URL**: `/api/progress`
- **Route Name**: `api.progress`
- **Auth**: Required (Sanctum)
- **Gating**: None (Free mode unlocks all chapters)

#### Response Body (`200 OK`)
```json
{
  "total_chapters": 24,
  "completed_chapters": 6,
  "in_progress_chapters": 2,
  "overall_progress_percent": 25.0,
  "experience_points": 850,
  "coin_balance": 30,
  "subjects": [
    {
      "id": 1,
      "name": "Mathematics",
      "total_chapters": 10,
      "completed_chapters": 3,
      "chapters": [
        {
          "id": 101,
          "title": "Algebraic Fractions",
          "order": 1,
          "is_free": true,
          "is_unlocked": true,
          "coin_price": 0,
          "xp_reward": 50,
          "state": "completed",
          "last_accessed_at": "2026-08-29T14:20:00.000000Z",
          "completed_at": "2026-08-29T15:10:00.000000Z"
        },
        {
          "id": 102,
          "title": "Quadratic Equations",
          "order": 2,
          "is_free": false,
          "is_unlocked": true,
          "coin_price": 20,
          "xp_reward": 75,
          "state": "in_progress",
          "last_accessed_at": "2026-08-30T08:00:00.000000Z",
          "completed_at": null
        },
        {
          "id": 103,
          "title": "Matrices & Determinants",
          "order": 3,
          "is_free": false,
          "is_unlocked": false,
          "coin_price": 25,
          "xp_reward": 100,
          "state": "not_started",
          "last_accessed_at": null,
          "completed_at": null
        }
      ]
    }
  ]
}
```

---

### 2.2 Touch Chapter (Track Access / In-Progress)
Marks a chapter as `in_progress` and updates its `last_accessed_at` timestamp. Requires the chapter to be unlocked.

- **Method**: `POST`
- **URL**: `/api/chapters/{id}/touch`
- **Route Name**: `api.chapters.touch`
- **Auth**: Required (Sanctum)
- **Gating**: Unlocked chapter required

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Chapter ID |

#### Response Body (`200 OK`)
```json
{
  "message": "Chapter progress updated.",
  "progress": {
    "chapter_id": 102,
    "state": "in_progress",
    "last_accessed_at": "2026-08-30T10:15:00.000000Z",
    "completed_at": null
  }
}
```

---

### 2.3 Unlock Chapter
Spends coins to permanently unlock access to a paid chapter.

- **Method**: `POST`
- **URL**: `/api/chapters/{id}/unlock`
- **Route Name**: `api.chapters.unlock`
- **Auth**: Required (Sanctum)
- **Gating**: Coin-gated (`chapter.coin_price`)

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Chapter ID |

#### Response Body (`200 OK`)
```json
{
  "success": true,
  "message": "Chapter 'Matrices & Determinants' unlocked successfully.",
  "already_unlocked": false,
  "coins_spent": 25,
  "coin_balance": 5
}
```

---

### 2.4 Unlock Past Paper (Questions)
Spends coins to unlock the question sheet of an official past examination paper.

- **Method**: `POST`
- **URL**: `/api/past-papers/{id}/unlock-paper`
- **Route Name**: `api.past-papers.unlock-paper`
- **Auth**: Required (Sanctum)
- **Gating**: Coin-gated (`past_paper.paper_coin_price`)

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Past Paper ID |

#### Response Body (`200 OK`)
```json
{
  "success": true,
  "message": "Past paper 'GCE A-Level Mathematics 2024 Paper 1' unlocked successfully.",
  "already_unlocked": false,
  "coins_spent": 15,
  "coin_balance": 10
}
```

---

### 2.5 Unlock Past Paper Solution
Spends coins to unlock the detailed answer key and marking guide for a past paper.

- **Method**: `POST`
- **URL**: `/api/past-papers/{id}/unlock-solution`
- **Route Name**: `api.past-papers.unlock-solution`
- **Auth**: Required (Sanctum)
- **Gating**: Coin-gated (`past_paper.solution_coin_price`)

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Past Paper ID |

#### Response Body (`200 OK`)
```json
{
  "success": true,
  "message": "Solution for 'GCE A-Level Mathematics 2024 Paper 1' unlocked successfully.",
  "already_unlocked": false,
  "coins_spent": 20,
  "coin_balance": 5
}
```

---

### 2.6 Get Quiz Questions (Sanitized)
Fetches quiz questions for an unlocked chapter. Correct choices and explanations are strictly stripped to prevent answer leaking.

- **Method**: `GET`
- **URL**: `/api/quizzes/{id}`
- **Route Name**: `api.quizzes.show`
- **Auth**: Required (Sanctum)
- **Gating**: Chapter unlock required

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Quiz ID |

#### Response Body (`200 OK`)
```json
{
  "id": 5,
  "chapter_id": 101,
  "passing_score": 70,
  "total_questions": 3,
  "questions": [
    {
      "id": 501,
      "question_text": "Simplify (x^2 - 4) / (x - 2):",
      "answer_choices": {
        "A": "x - 2",
        "B": "x + 2",
        "C": "x^2 + 2",
        "D": "x + 4"
      }
    },
    {
      "id": 502,
      "question_text": "What is the common denominator for 1/2x and 1/3x?",
      "answer_choices": {
        "A": "5x",
        "B": "6x",
        "C": "6x^2",
        "D": "x"
      }
    }
  ]
}
```

---

### 2.7 Submit Quiz Answers
Submits student answers for a quiz. Grades MCQ responses instantly, marks chapter as `completed`, awards XP (and automatically triggers 50 coins conversion per 1,500 accumulated XP), and returns question-by-question explanations.

- **Method**: `POST`
- **URL**: `/api/quizzes/{id}/submit`
- **Route Name**: `api.quizzes.submit`
- **Auth**: Required (Sanctum)
- **Gating**: Chapter unlock required

#### Request Body
| Field | Type | Required | Description |
|---|---|---|---|
| `answers` | `array` | Yes | List of student answers |
| `answers.*.question_id` | `integer` | Yes | Question ID |
| `answers.*.selected_choice` | `string` | No | Selected choice key (e.g. `"A"`, `"B"`, `"C"`, `"D"`) |

```json
{
  "answers": [
    { "question_id": 501, "selected_choice": "B" },
    { "question_id": 502, "selected_choice": "B" }
  ]
}
```

#### Response Body (`200 OK`)
```json
{
  "message": "Quiz submitted successfully.",
  "attempt_id": 89,
  "score_percent": 100.0,
  "correct_count": 2,
  "total_questions": 2,
  "quiz_xp_earned": 20,
  "chapter_xp_reward": 50,
  "total_xp_earned": 70,
  "is_first_completion": true,
  "coins_earned_from_xp": 0,
  "experience_points": 420,
  "coin_balance": 15,
  "chapter_progress": {
    "chapter_id": 101,
    "state": "completed",
    "completed_at": "2026-08-30T10:30:00.000000Z",
    "last_accessed_at": "2026-08-30T10:30:00.000000Z"
  },
  "answers": [
    {
      "question_id": 501,
      "question_text": "Simplify (x^2 - 4) / (x - 2):",
      "selected_choice": "B",
      "correct_choice": "B",
      "is_correct": true,
      "explanation": "(x^2 - 4) factors into (x - 2)(x + 2). Canceling (x - 2) leaves (x + 2)."
    }
  ]
}
```

---

## 3. Coins, Missions & XP Conversion

### 3.1 List Missions & User Progress
Lists all daily and one-time missions, the 7-day daily check-in reward ladder, and current progress.

- **Method**: `GET`
- **URL**: `/api/missions`
- **Route Name**: `api.missions.index`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Response Body (`200 OK`)
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
      "last_completed_at": "2026-08-30T09:00:00.000000Z",
      "current_streak_day": 2,
      "can_checkin": false,
      "next_checkin_at": "2026-08-31T05:00:00.000000Z"
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
      "last_completed_at": "2026-08-30T08:30:00.000000Z",
      "ads_watched_in_window": 2,
      "remaining_ads": 3,
      "can_watch_ad": true
    },
    {
      "id": 3,
      "key": "refer_a_friend",
      "title": "Refer a Friend",
      "description": "Share your referral code. Earn 50 coins when a friend registers.",
      "coin_reward": 50,
      "type": "referral",
      "is_active": true,
      "completed": false,
      "last_completed_at": null,
      "referral_code": "SAMU429B",
      "total_referrals": 1
    },
    {
      "id": 4,
      "key": "complete_profile",
      "title": "Complete Your Profile",
      "description": "Fill in your profile information to earn 30 bonus coins.",
      "coin_reward": 30,
      "type": "one_time",
      "is_active": true,
      "completed": false,
      "last_completed_at": null
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
  "user_streak": 2
}
```

---

### 3.2 Claim Daily Check-in
Claims the daily bonus coins according to the current streak. Enforces a 20-hour window between check-ins. If more than 40 hours have passed since the previous check-in, the streak resets to Day 1.

- **Method**: `POST`
- **URL**: `/api/missions/checkin`
- **Route Name**: `api.missions.checkin`
- **Auth**: Required (Sanctum)
- **Gating**: Rolling 20-hour interval

#### Request Body
None.

#### Response Body (`200 OK`)
```json
{
  "message": "Day 2 check-in completed successfully.",
  "streak_day": 2,
  "coins_earned": 5,
  "next_checkin_at": "2026-08-31T06:00:00.000000Z",
  "coin_balance": 35
}
```

#### Error Response (`422 Unprocessable Entity`)
```json
{
  "message": "You have already checked in within the last 20 hours. Next check-in available at 2026-08-31T06:00:00+00:00.",
  "errors": {
    "checkin": ["You have already checked in within the last 20 hours. Next check-in available at 2026-08-31T06:00:00+00:00."]
  }
}
```

---

### 3.3 Claim Watched Ad Reward
Awards 5 coins for completing a rewarded video ad. Enforces a strict cap of 5 rewarded ads per rolling 20-hour window.

- **Method**: `POST`
- **URL**: `/api/missions/watch-ad`
- **Route Name**: `api.missions.watch-ad`
- **Auth**: Required (Sanctum)
- **Gating**: Max 5 ads per rolling 20 hours
- **Rate Limit**: `throttle:watch-ad` (6 hits / min / user)

#### Request Body
None.

#### Response Body (`200 OK`)
```json
{
  "message": "Reward claimed for watching ad.",
  "coins_earned": 5,
  "ads_watched_in_window": 3,
  "remaining_ads": 2,
  "coin_balance": 40
}
```

#### Error Response (`422 Unprocessable Entity`)
```json
{
  "message": "You have reached the maximum limit of 5 rewarded ads in a 20-hour rolling window.",
  "errors": {
    "ad": ["You have reached the maximum limit of 5 rewarded ads in a 20-hour rolling window."]
  }
}
```

---

### 3.4 Complete One-Time Mission
Completes a one-time onboarding or milestone mission and awards its coin bounty. Cannot be claimed more than once.

- **Method**: `POST`
- **URL**: `/api/missions/complete/{missionKey}`
- **Route Name**: `api.missions.complete`
- **Auth**: Required (Sanctum)
- **Gating**: One-time only

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `missionKey` | `string` | Mission identifier (e.g. `complete_profile`, `first_quiz`) |

#### Response Body (`200 OK`)
```json
{
  "message": "Mission 'Complete Your Profile' completed successfully.",
  "mission": "complete_profile",
  "coins_earned": 30,
  "coin_balance": 70
}
```

---

### 3.5 Convert XP to Coins
Converts accumulated experience points into coins. Conversion rate is strictly **1,500 XP = 50 coins** in discrete integer chunks.

- **Method**: `POST`
- **URL**: `/api/xp/convert`
- **Route Name**: `api.xp.convert`
- **Auth**: Required (Sanctum)
- **Gating**: Min 1,500 unconverted XP

#### Request Body
None.

#### Response Body (`200 OK`)
```json
{
  "message": "XP converted to coins successfully.",
  "xp_converted": 1500,
  "coins_earned": 50,
  "xp_converted_total": 1500,
  "experience_points": 1820,
  "remaining_unconverted_xp": 320,
  "coin_balance": 120
}
```

#### Error Response (`422 Unprocessable Entity`)
```json
{
  "message": "You need at least 1,500 unconverted XP to convert to coins.",
  "errors": {
    "xp": ["You need at least 1,500 unconverted XP to convert to coins."]
  }
}
```

---

## 4. Subscriptions

### 4.1 Get Subscription Status
Fetches current subscription details, remaining validity, tier allotments, and global free mode override state.

- **Method**: `GET`
- **URL**: `/api/subscription`
- **Route Name**: `api.subscription.status`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Response Body (`200 OK`)
```json
{
  "has_active_subscription": true,
  "free_mode_enabled": false,
  "subscription": {
    "id": 14,
    "tier": "tier_2000",
    "tier_label": "Standard (2,000 FCFA / month)",
    "status": "active",
    "coin_allotment": 100,
    "amount_fcfa": 2000,
    "start_date": "2026-08-30T10:00:00.000000Z",
    "end_date": "2026-09-29T10:00:00.000000Z"
  }
}
```

---

### 4.2 Initiate Subscription Purchase
Initiates payment checkout for a monthly subscription tier.

- **Method**: `POST`
- **URL**: `/api/subscriptions/purchase` *(or alias `/api/subscription/purchase`)*
- **Route Name**: `api.subscriptions.purchase`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Request Body
| Field | Type | Required | Rules | Description |
|---|---|---|---|---|
| `tier` | `string` | Yes | `tier_2000` or `tier_5000` | Subscription plan |

- `tier_2000`: 2,000 FCFA / month (+100 monthly bonus coins)
- `tier_5000`: 5,000 FCFA / month (+300 monthly bonus coins)

```json
{
  "tier": "tier_2000"
}
```

#### Response Body (`200 OK`)
```json
{
  "message": "Subscription purchase initiated successfully.",
  "data": {
    "payment_reference": "mock_pay_9a8b7c6d5e4f3a2b",
    "checkout_url": "http://localhost:8000/api/mock-checkout/mock_pay_9a8b7c6d5e4f3a2b",
    "tier": "tier_2000",
    "amount_fcfa": 2000,
    "coin_allotment": 100,
    "status": "pending"
  }
}
```

---

## 5. Weekly Challenge

### 5.1 List Active Weekly Challenges
Fetches all published challenges for the active week window matching the student's education level.

- **Method**: `GET`
- **URL**: `/api/challenges`
- **Route Name**: `api.challenges.index`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Response Body (`200 OK`)
```json
{
  "challenges": [
    {
      "id": 3,
      "title": "Week 34 Physics Olympiad Drill",
      "subject": {
        "id": 2,
        "name": "Physics"
      },
      "exam_subsystem": "cameroon_gce_al",
      "level": "form_5",
      "time_limit_minutes": 45,
      "week_start_date": "2026-08-25T00:00:00.000000Z",
      "week_end_date": "2026-08-31T23:59:59.000000Z",
      "has_attempted": false,
      "attempt_status": null,
      "attempt_id": null
    }
  ]
}
```

---

### 5.2 Start Challenge Attempt
Starts a timed challenge attempt and delivers questions. Questions exclude `correct_choice`.

- **Method**: `POST`
- **URL**: `/api/challenges/{id}/start`
- **Route Name**: `api.challenges.start`
- **Auth**: Required (Sanctum)
- **Gating**: Published challenge within active week window; single attempt per challenge

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Weekly Challenge ID |

#### Response Body (`201 Created`)
```json
{
  "message": "Weekly challenge started.",
  "attempt": {
    "id": 19,
    "started_at": "2026-08-30T10:45:00.000000Z",
    "status": "in_progress"
  },
  "challenge": {
    "id": 3,
    "title": "Week 34 Physics Olympiad Drill",
    "time_limit_minutes": 45,
    "questions": [
      {
        "id": 301,
        "type": "mcq",
        "question_text": "What is the SI unit of electric capacitance?",
        "options": {
          "A": "Henry",
          "B": "Farad",
          "C": "Weber",
          "D": "Tesla"
        },
        "max_points": 2,
        "order": 1
      },
      {
        "id": 302,
        "type": "structural",
        "question_text": "State Lenz's law of electromagnetic induction.",
        "options": null,
        "max_points": 5,
        "order": 2
      }
    ]
  }
}
```

---

### 5.3 Submit Challenge Attempt
Submits answers for a challenge attempt. If the challenge is purely MCQ, it is graded instantly. If it contains structural questions, it enters `submitted` status pending teacher grading.

- **Method**: `POST`
- **URL**: `/api/challenges/{id}/submit`
- **Route Name**: `api.challenges.submit`
- **Auth**: Required (Sanctum)
- **Gating**: Within `started_at + time_limit_minutes + 60s` grace period

#### Request Body
| Field | Type | Required | Description |
|---|---|---|---|
| `answers` | `array` | Yes | List of answers |
| `answers.*.question_id` | `integer` | Yes | Question ID |
| `answers.*.selected_choice` | `string` | No | Selected choice key (for MCQ) |
| `answers.*.answer_text` | `string` | No | Student's written answer (for structural) |

```json
{
  "answers": [
    { "question_id": 301, "selected_choice": "B" },
    { "question_id": 302, "answer_text": "The direction of an induced current is such that it opposes the change that produces it." }
  ]
}
```

#### Response Body (Instant Graded / Pure MCQ) (`200 OK`)
```json
{
  "status": "graded",
  "message": "Challenge submitted and graded successfully.",
  "total_score_percent": 100.0,
  "reward_coins_awarded": 100,
  "coin_balance": 140
}
```

#### Response Body (Structural Questions Pending Grading) (`200 OK`)
```json
{
  "status": "submitted",
  "message": "Challenge submitted successfully. Results will be available after teacher grading.",
  "total_score_percent": null,
  "reward_coins_awarded": null
}
```

---

### 5.4 Get Challenge Result
Retrieves student attempt score, coins awarded, and teacher/AI feedback once graded.

- **Method**: `GET`
- **URL**: `/api/challenges/{id}/result`
- **Route Name**: `api.challenges.result`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Weekly Challenge ID |

#### Response Body (`200 OK`)
```json
{
  "has_attempted": true,
  "status": "graded",
  "attempt": {
    "id": 19,
    "started_at": "2026-08-30T10:45:00.000000Z",
    "submitted_at": "2026-08-30T11:15:00.000000Z",
    "status": "graded",
    "total_score_percent": 85.7,
    "reward_coins_awarded": 50,
    "answers": [
      {
        "question_id": 301,
        "type": "mcq",
        "question_text": "What is the SI unit of electric capacitance?",
        "selected_choice": "B",
        "answer_text": null,
        "points_awarded": 2,
        "max_points": 2
      },
      {
        "question_id": 302,
        "type": "structural",
        "question_text": "State Lenz's law of electromagnetic induction.",
        "selected_choice": null,
        "answer_text": "The direction of an induced current is such that it opposes the change that produces it.",
        "points_awarded": 4,
        "max_points": 5,
        "feedback": "Correct definition; clearly notes opposition to the magnetic flux change."
      }
    ]
  }
}
```

---

## 6. Exam Mode (Timed Practice)

### 6.1 Start Timed Exam Session
Starts a realistic, timed exam session for an official past paper. Calculates maximum allowed duration dynamically (MCQ: 90m, Structural: 180m, Mixed: 240m) or accepts a student-customized duration.

- **Method**: `POST`
- **URL**: `/api/past-papers/{id}/exam-session/start`
- **Route Name**: `api.past-papers.exam-session.start`
- **Auth**: Required (Sanctum)
- **Gating**: Active subscription required (or free mode enabled)

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Past Paper ID |

#### Request Body
| Field | Type | Required | Rules | Description |
|---|---|---|---|---|
| `requested_minutes` | `integer` | No | min: 1, max: paper ceiling | Optional custom timer duration |

```json
{
  "requested_minutes": 60
}
```

#### Response Body (`201 Created`)
```json
{
  "message": "Exam session started successfully.",
  "session": {
    "id": 7,
    "past_paper_id": 12,
    "max_allowed_minutes": 90,
    "selected_minutes": 60,
    "started_at": "2026-08-30T11:00:00.000000Z",
    "status": "in_progress"
  },
  "questions": [
    {
      "id": 1201,
      "type": "mcq",
      "question_text": "The gradient of y = 3x^2 - 4x at x = 2 is:",
      "options": {
        "A": "8",
        "B": "12",
        "C": "4",
        "D": "16"
      },
      "max_points": 1,
      "order": 1
    }
  ]
}
```

---

### 6.2 Submit Exam Session
Submits answers for a timed exam session. Enforces session duration plus a 60-second submission grace buffer.

- **Method**: `POST`
- **URL**: `/api/exam-sessions/{id}/submit`
- **Route Name**: `api.exam-sessions.submit`
- **Auth**: Required (Sanctum)
- **Gating**: Within `started_at + selected_minutes + 60s` grace buffer

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Exam Session ID |

#### Request Body
| Field | Type | Required | Description |
|---|---|---|---|
| `answers` | `array` | Yes | List of answers |
| `answers.*.question_id` | `integer` | Yes | Past paper question ID |
| `answers.*.selected_choice` | `string` | No | Selected option for MCQ |
| `answers.*.answer_text` | `string` | No | Student response for structural question |

```json
{
  "answers": [
    { "question_id": 1201, "selected_choice": "A" }
  ]
}
```

#### Response Body (`200 OK`)
```json
{
  "status": "graded",
  "message": "Exam session submitted and graded successfully.",
  "session_id": 7,
  "total_score_percent": 100.0
}
```

---

### 6.3 Get Exam Session Result
Retrieves detailed results, breakdown, and question feedback for a submitted exam session.

- **Method**: `GET`
- **URL**: `/api/exam-sessions/{id}/result`
- **Route Name**: `api.exam-sessions.result`
- **Auth**: Required (Sanctum)
- **Gating**: Session owner only

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Exam Session ID |

#### Response Body (`200 OK`)
```json
{
  "status": "graded",
  "result": {
    "id": 7,
    "past_paper_id": 12,
    "past_paper_title": "Pure Mathematics Paper 1 (2024)",
    "subject_name": "Mathematics",
    "selected_minutes": 60,
    "started_at": "2026-08-30T11:00:00.000000Z",
    "submitted_at": "2026-08-30T11:45:20.000000Z",
    "status": "graded",
    "total_score_percent": 100.0,
    "answers": [
      {
        "question_id": 1201,
        "type": "mcq",
        "question_text": "The gradient of y = 3x^2 - 4x at x = 2 is:",
        "selected_choice": "A",
        "answer_text": null,
        "points_awarded": 1,
        "max_points": 1
      }
    ]
  }
}
```

---

## 7. Career Pathways & Profiles

### 7.1 List All Career Profiles
Retrieves catalog of high-demand careers in Cameroon and Central Africa with salary benchmarks and related subjects.

- **Method**: `GET`
- **URL**: `/api/career-profiles`
- **Route Name**: `api.career-profiles.index`
- **Auth**: Required (Sanctum)
- **Gating**: None (Free for all authenticated students)

#### Response Body (`200 OK`)
```json
{
  "career_profiles": [
    {
      "id": 1,
      "title": "Software Engineer",
      "description": "Design and build web, mobile, and cloud software systems.",
      "average_salary": "6,000,000 - 18,000,000 FCFA / year",
      "job_demand": "very_high",
      "job_demand_label": "Very High Demand",
      "related_subjects": ["Mathematics", "Computer Science", "Physics"]
    },
    {
      "id": 2,
      "title": "Civil Engineer",
      "description": "Plan, design, and supervise infrastructure construction.",
      "average_salary": "4,500,000 - 15,000,000 FCFA / year",
      "job_demand": "high",
      "job_demand_label": "High Demand",
      "related_subjects": ["Mathematics", "Physics"]
    }
  ]
}
```

---

### 7.2 Generate Personalized Career Pathway
Analyzes student quiz scores, identifies top qualifying academic subjects, and prompts Gemini AI to recommend tailored career paths.

- **Method**: `POST`
- **URL**: `/api/career-pathway/generate`
- **Route Name**: `api.career-pathway.generate`
- **Auth**: Required (Sanctum)
- **Gating**: Active subscription required (or free mode enabled)

#### Request Body
None.

#### Response Body (`201 Created`)
```json
{
  "message": "Personalized career pathway generated successfully.",
  "pathway": {
    "id": 15,
    "generated_at": "2026-08-30T11:50:00.000000Z",
    "recommendations": [
      {
        "career_profile_id": 1,
        "career_title": "Software Engineer",
        "description": "Design and build web, mobile, and cloud software systems.",
        "average_salary": "6,000,000 - 18,000,000 FCFA / year",
        "job_demand": "very_high",
        "related_subjects": ["Mathematics", "Computer Science", "Physics"],
        "match_score": 92,
        "reasoning": "Strong problem-solving foundation indicated by 90%+ scores in Advanced Mathematics."
      }
    ]
  }
}
```

---

### 7.3 Get Current Career Pathway
Retrieves the student's most recently generated career pathway.

- **Method**: `GET`
- **URL**: `/api/career-pathway`
- **Route Name**: `api.career-pathway.show`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Response Body (`200 OK`)
```json
{
  "has_pathway": true,
  "pathway": {
    "id": 15,
    "generated_at": "2026-08-30T11:50:00.000000Z",
    "recommendations": [
      {
        "career_profile_id": 1,
        "career_title": "Software Engineer",
        "description": "Design and build web, mobile, and cloud software systems.",
        "average_salary": "6,000,000 - 18,000,000 FCFA / year",
        "job_demand": "very_high",
        "related_subjects": ["Mathematics", "Computer Science", "Physics"],
        "match_score": 92,
        "reasoning": "Strong problem-solving foundation indicated by 90%+ scores in Advanced Mathematics."
      }
    ]
  }
}
```

---

## 8. AI Tutor "Lumani"

### 8.1 List Tutor Conversations
Fetches all AI Tutor conversation threads started by the student, ordered by most recent activity.

- **Method**: `GET`
- **URL**: `/api/tutor/conversations`
- **Route Name**: `api.tutor.conversations.index`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Response Body (`200 OK`)
```json
{
  "conversations": [
    {
      "id": 8,
      "chapter_id": 101,
      "chapter_title": "Algebraic Fractions",
      "subject_name": "Mathematics",
      "title": "Mathematics: Algebraic Fractions",
      "last_message_at": "2026-08-30T11:55:00.000000Z",
      "created_at": "2026-08-30T11:50:00.000000Z"
    },
    {
      "id": 9,
      "chapter_id": null,
      "chapter_title": null,
      "subject_name": null,
      "title": "General Discussion",
      "last_message_at": "2026-08-28T09:00:00.000000Z",
      "created_at": "2026-08-28T08:30:00.000000Z"
    }
  ]
}
```

---

### 8.2 Start / Retrieve Conversation Thread
Initializes a new discussion thread or retrieves the existing per-chapter thread for this student.

- **Method**: `POST`
- **URL**: `/api/tutor/conversations`
- **Route Name**: `api.tutor.conversations.store`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Request Body
| Field | Type | Required | Rules | Description |
|---|---|---|---|---|
| `chapter_id` | `integer` | No | exists:chapters,id | Context chapter ID (null for general discussion) |

```json
{
  "chapter_id": 101
}
```

#### Response Body (`201 Created`)
```json
{
  "message": "Conversation initialized successfully.",
  "conversation": {
    "id": 8,
    "chapter_id": 101,
    "chapter_title": "Algebraic Fractions",
    "subject_name": "Mathematics",
    "title": "Mathematics: Algebraic Fractions",
    "last_message_at": "2026-08-30T11:55:00.000000Z",
    "created_at": "2026-08-30T11:50:00.000000Z"
  }
}
```

---

### 8.3 Get Conversation Message History
Retrieves chronological message history for a conversation thread.

- **Method**: `GET`
- **URL**: `/api/tutor/conversations/{id}/messages`
- **Route Name**: `api.tutor.conversations.messages`
- **Auth**: Required (Sanctum)
- **Gating**: Conversation owner only

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Conversation ID |

#### Response Body (`200 OK`)
```json
{
  "conversation_id": 8,
  "messages": [
    {
      "id": 101,
      "role": "user",
      "content": "Why do we need to factor numerator and denominator before canceling?",
      "created_at": "2026-08-30T11:51:00.000000Z"
    },
    {
      "id": 102,
      "role": "assistant",
      "content": "Great question! Canceling is division. You can only divide entire factors (multiplication), not separate terms (addition/subtraction). Factoring reveals the common multipliers you can safely cancel.",
      "created_at": "2026-08-30T11:51:04.000000Z"
    }
  ]
}
```

---

### 8.4 Send Message to AI Tutor Lumani
Sends a message to the AI tutor and returns the AI assistant's pedagogical response.

- **Method**: `POST`
- **URL**: `/api/tutor/conversations/{id}/messages`
- **Route Name**: `api.tutor.conversations.send-message`
- **Auth**: Required (Sanctum)
- **Gating**: Active subscription required (or free mode enabled); thread owner only
- **Rate Limit**: `throttle:tutor-messages` (20 messages / min / user)

#### Path Parameters
| Parameter | Type | Description |
|---|---|---|
| `id` | `integer` | Conversation ID |

#### Request Body
| Field | Type | Required | Rules | Description |
|---|---|---|---|---|
| `message` | `string` | Yes | max: 2000 | Student's message / question |

```json
{
  "message": "Can you give me another example with fractions?"
}
```

#### Response Body (`200 OK`)
```json
{
  "message": "Reply received from Lumani.",
  "conversation_id": 8,
  "user_message": {
    "id": 103,
    "role": "user",
    "content": "Can you give me another example with fractions?",
    "created_at": "2026-08-30T11:55:00.000000Z"
  },
  "assistant_message": {
    "id": 104,
    "role": "assistant",
    "content": "Certainly! Consider (2x + 4) / (4x + 8). Factor 2 out of the numerator: 2(x + 2). Factor 4 out of the denominator: 4(x + 2). The (x + 2) factors cancel, leaving 2/4 = 1/2.",
    "created_at": "2026-08-30T11:55:03.000000Z"
  }
}
```

---

## 9. Revision Plan

### 9.1 Generate Algorithmic Revision Plan
Calculates subject weakness weights from past quiz scores and proportionally distributes available study time across chosen study days.

- **Method**: `POST`
- **URL**: `/api/revision-plan/generate`
- **Route Name**: `api.revision-plan.generate`
- **Auth**: Required (Sanctum)
- **Gating**: None (Free for all authenticated students)

#### Request Body
| Field | Type | Required | Rules | Description |
|---|---|---|---|---|
| `weekly_available_minutes` | `integer` | Yes | min: 15, max: 10080 | Total weekly study budget in minutes |
| `available_days` | `array` | Yes | min: 1 | Array of days student can study (`0` = Sunday, `1` = Monday, ..., `6` = Saturday) |
| `available_days.*` | `integer` | Yes | between: 0, 6 | Day index |

```json
{
  "weekly_available_minutes": 300,
  "available_days": [1, 3, 5]
}
```

#### Response Body (`201 Created`)
```json
{
  "message": "Revision plan generated successfully.",
  "plan": {
    "id": 4,
    "weekly_available_minutes": 300,
    "available_days": [1, 3, 5],
    "generated_at": "2026-08-30T12:00:00.000000Z",
    "plan_data": [
      {
        "day": 1,
        "subject_id": 2,
        "subject_name": "Physics",
        "chapter_id": 204,
        "chapter_title": "Thermodynamics",
        "duration_minutes": 120
      },
      {
        "day": 3,
        "subject_id": 1,
        "subject_name": "Mathematics",
        "chapter_id": 103,
        "chapter_title": "Matrices & Determinants",
        "duration_minutes": 100
      },
      {
        "day": 5,
        "subject_id": 3,
        "subject_name": "Chemistry",
        "chapter_id": 301,
        "chapter_title": "Organic Synthesis",
        "duration_minutes": 80
      }
    ]
  }
}
```

---

### 9.2 Get Latest Revision Plan
Fetches the student's most recently generated revision study timetable.

- **Method**: `GET`
- **URL**: `/api/revision-plan`
- **Route Name**: `api.revision-plan.show`
- **Auth**: Required (Sanctum)
- **Gating**: None

#### Response Body (`200 OK`)
```json
{
  "has_plan": true,
  "plan": {
    "id": 4,
    "weekly_available_minutes": 300,
    "available_days": [1, 3, 5],
    "generated_at": "2026-08-30T12:00:00.000000Z",
    "plan_data": [
      {
        "day": 1,
        "subject_id": 2,
        "subject_name": "Physics",
        "chapter_id": 204,
        "chapter_title": "Thermodynamics",
        "duration_minutes": 120
      },
      {
        "day": 3,
        "subject_id": 1,
        "subject_name": "Mathematics",
        "chapter_id": 103,
        "chapter_title": "Matrices & Determinants",
        "duration_minutes": 100
      },
      {
        "day": 5,
        "subject_id": 3,
        "subject_name": "Chemistry",
        "chapter_id": 301,
        "chapter_title": "Organic Synthesis",
        "duration_minutes": 80
      }
    ]
  }
}
```

---

## 10. Payment Webhook

### 10.1 Payment Gateway Webhook / Callback
Processes external Mobile Money or Card payment notifications. Automatically activates the purchased subscription tier and credits tier coin allotments.

- **Method**: `POST`
- **URL**: `/api/payments/callback`
- **Route Name**: `api.payments.callback`
- **Auth**: Public (Webhook signature verified in production)
- **Gating**: None

#### Request Body
| Field | Type | Required | Description |
|---|---|---|---|
| `payment_reference` | `string` | Yes | Payment transaction reference identifier |
| `status` | `string` | Yes | Payment outcome status (`success`, `failed`, `cancelled`) |
| `user_id` | `integer` | Optional | User ID (if not cached under reference) |
| `tier` | `string` | Optional | Subscription tier (`tier_2000`, `tier_5000`) |

```json
{
  "payment_reference": "mock_pay_9a8b7c6d5e4f3a2b",
  "status": "success"
}
```

#### Response Body (`200 OK`)
```json
{
  "success": true,
  "message": "Payment processed successfully."
}
```

#### Error Response (`400 Bad Request`)
```json
{
  "success": false,
  "message": "Payment processing failed or invalid callback payload."
}
```
