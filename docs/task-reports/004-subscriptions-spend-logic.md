# Task 004 — Subscriptions, Spend-Side Coin Logic, and Admin Overrides Specification & Report

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication Mechanism**: Laravel Sanctum Personal Access Tokens (`Bearer <token>`)
- **Access Control & Invariants**:
  - Global `free_mode_enabled` bypasses all coin checks and grants universal access across chapters, past papers, and subscription tiers.
  - Chapter and past paper unlocks are permanently idempotent (never double-charges).
  - All coin deductions atomically create `coin_transactions` entries of type `spent_unlock` with polymorphic references.

---

## 1. What Was Built

### 1.1 Database Migrations & Schema
1. **`app_settings` (Singleton Table)**:
   - `id`: Primary key (singleton ID `1`)
   - `free_mode_enabled`: Boolean flag (default `false`)
   - Pre-seeded with the single global switch row upon migration.
2. **`chapters` Table Updates**:
   - `coin_price`: Integer (default `50`)
   - `xp_reward`: Integer (explicit admin-set reward upon chapter completion)
   - `is_free`: Boolean (default `false`)
3. **`past_papers` Table**:
   - `id`: Auto-incrementing primary key
   - `subject_id`: Foreign key referencing `subjects.id` (cascade on delete)
   - `exam_subsystem`: Subsystem identifier (`anglophone`, `francophone`, `general`)
   - `level`: Educational level (`O-Level`, `A-Level`, `BEPC`, `Probatoire`, `Baccalaureat`)
   - `year`: Exam year (integer)
   - `title`: Paper title
   - `file_path`: Storage path/URL for the question paper PDF
   - `coin_price`: Integer price for questions (default `15`)
   - `solution_file_path`: Storage path/URL for solutions PDF
   - `solution_coin_price`: Integer price for solution (default `20`)
4. **`subscriptions` Table**:
   - `id`: Primary key
   - `user_id`: Foreign key referencing `users.id`
   - `tier`: Subscription tier enum (`tier_2000`, `tier_5000`)
   - `coin_allotment`: Integer (500 for `tier_2000`, 1500 for `tier_5000`)
   - `amount_fcfa`: Integer (2,000 or 5,000 FCFA)
   - `start_date`, `end_date`: Datetime range
   - `status`: Enum (`active`, `expired`, `cancelled`, default `active`)
5. **Unlock Ledger Tables**:
   - `user_chapter_unlocks`: `[user_id, chapter_id]` unique pair with `unlocked_at` timestamp.
   - `user_past_paper_unlocks`: `[user_id, past_paper_id]` unique pair with independent `paper_unlocked_at` and `solution_unlocked_at` timestamps.

### 1.2 Seed Data
- **`FreeChapterSeeder`**: Iterates through all existing subjects and automatically sets `is_free = true` for the first 2 chapters (ordered by `order`).

### 1.3 Service Layer (`app/Services/AccessControlService.php`)
- `canAccessChapter(User $user, Chapter $chapter): bool`: Returns `true` if `free_mode_enabled` is active, OR `chapter.is_free` is true, OR an unlock record exists.
- `unlockChapter(User $user, Chapter $chapter): array`: Idempotent unlock checking sufficient coin balance, debiting via `CoinService->spend()` with `CoinTransactionType::SpentUnlock`, and creating the `user_chapter_unlocks` record.
- `canAccessPastPaper()` & `canAccessPastPaperSolution()`: Independent permission checks for question paper and solution documents.
- `unlockPastPaper()` & `unlockPastPaperSolution()`: Debits 15 or 20 coins respectively and sets `paper_unlocked_at` / `solution_unlocked_at`.
- `hasActiveSubscription(User $user): bool`: Checks if user has an active subscription record (`status = 'active'` and `end_date >= now()`) or `free_mode_enabled` is true.
- `getSubscriptionStatus(User $user): array`: Returns comprehensive subscription and override metadata.

### 1.4 Filament Admin Interface
- **App Settings Page (`/admin/settings`)**: Settings page displaying current global override state and toggle for `free_mode_enabled`.
- **Chapter Resource (`/admin/chapters`)**: Updated form and table with `coin_price`, `xp_reward`, and `is_free`.
- **Past Paper Resource (`/admin/past-papers`)**: Full CRUD resource for past exam papers and separate paper/solution coin pricing.
- **Subscription Resource (`/admin/subscriptions`)**: Manual subscription manager for granting testing tiers and allotments to students.

---

## 2. API Endpoints Specification

### Headers Required
```http
Authorization: Bearer <token>
Accept: application/json
```

---

### Endpoint 1: `POST /api/chapters/{id}/unlock`
Unlocks a locked chapter using student coins.

#### Successful Unlock Response (`200 OK`)
```json
{
  "success": true,
  "message": "Chapter 'Linear Equations' unlocked successfully.",
  "already_unlocked": false,
  "coins_spent": 50,
  "coin_balance": 150
}
```

#### Idempotent Re-Unlock Response (`200 OK`)
```json
{
  "success": true,
  "message": "Chapter is already accessible.",
  "already_unlocked": true,
  "coins_spent": 0,
  "coin_balance": 150
}
```

#### Insufficient Coins Rejection (`422 Unprocessable Content`)
```json
{
  "message": "Insufficient coin balance. Unlocking this chapter requires 50 coins.",
  "errors": {
    "coins": [
      "Insufficient coin balance. Unlocking this chapter requires 50 coins."
    ]
  }
}
```

---

### Endpoint 2: `POST /api/past-papers/{id}/unlock-paper`
Unlocks a past paper questions document (default 15 coins).

#### Successful Unlock Response (`200 OK`)
```json
{
  "success": true,
  "message": "Past paper 'GCE O-Level Mathematics 2024 Past Paper' unlocked successfully.",
  "already_unlocked": false,
  "coins_spent": 15,
  "coin_balance": 135
}
```

---

### Endpoint 3: `POST /api/past-papers/{id}/unlock-solution`
Unlocks a past paper solution document (default 20 coins).

#### Successful Unlock Response (`200 OK`)
```json
{
  "success": true,
  "message": "Solution for 'GCE O-Level Mathematics 2024 Past Paper' unlocked successfully.",
  "already_unlocked": false,
  "coins_spent": 20,
  "coin_balance": 115
}
```

---

### Endpoint 4: `GET /api/subscription`
Fetches current student subscription status, plan details, and active override flags.

#### Response with Active Subscription (`200 OK`)
```json
{
  "has_active_subscription": true,
  "free_mode_enabled": false,
  "subscription": {
    "id": 1,
    "tier": "tier_2000",
    "tier_label": "Standard Plan (2,000 FCFA)",
    "status": "active",
    "coin_allotment": 500,
    "amount_fcfa": 2000,
    "start_date": "2026-08-27T00:00:00.000000Z",
    "end_date": "2026-09-27T00:00:00.000000Z"
  }
}
```

#### Response with No Subscription (`200 OK`)
```json
{
  "has_active_subscription": false,
  "free_mode_enabled": false,
  "subscription": null
}
```

#### Response with Global Free Mode Override Active (`200 OK`)
```json
{
  "has_active_subscription": true,
  "free_mode_enabled": true,
  "subscription": null
}
```

---

## 3. Why This Approach

1. **Independent Paper & Solution Unlocks**:
   Students frequently only need to review the questions, or already have the paper and only want worked step-by-step solutions. Modeling both prices and unlock timestamps on a single `past_papers` and `user_past_paper_unlocks` record avoids table bloat while offering students flexible pricing.
2. **Global Override Architecture**:
   During marketing campaigns, server maintenance, or open-access promo periods, switching `free_mode_enabled` instantly enables frictionless access across the entire app without altering individual student balances or user records.
3. **Strict Idempotency in Coin Transactions**:
   Network retries or double taps from mobile clients could cause accidental double-spending. The `AccessControlService` checks permissions prior to transactions and guarantees that already accessible content returns success with 0 coins deducted.

---

## 4. Verification & Test Results
- **Pest Test Suite**: 78 tests passing (423 assertions)
- **Static Analysis**: PHPStan passing at 0 errors (`--memory-limit=512M`)
- **Code Style**: Laravel Pint formatted

---

## 5. Open Questions & Blockers
- None. Payment gateway integration (Mobile Money / Orange Money / MTN MoMo) is prepared to plug into `Subscription` models during the subsequent billing task.
