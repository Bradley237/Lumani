# Task 009 — AI Tutor "Lumani" with Per-Chapter Conversation Memory

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication**: Laravel Sanctum Personal Access Tokens (`Bearer <token>`)
- **Key Invariants**:
  - Chatting with AI Tutor Lumani is **subscription-gated** via `AccessControlService::hasActiveSubscription` (`403 Forbidden` if not subscribed, bypassed by `free_mode`).
  - Starting a conversation with a `chapter_id` automatically reuses the student's most recent conversation for that specific chapter, maintaining contextual chapter memory.
  - Starting a conversation without a `chapter_id` always creates a new general conversation (`chapter_id: null`).
  - Context sent to Gemini AI (`gemini-2.5-flash`) includes:
    1. The specialized Lumani Cameroonian GCE/Baccalauréat persona.
    2. The chapter's subject and title (if chapter-linked).
    3. Exactly the **last 15 messages** of the conversation in chronological order (bounding token cost and keeping latency low).
  - If the Gemini API call fails (network timeout, rate limit, invalid response), **no broken assistant message is saved** to the database, and a clear `422 Unprocessable Content` error is returned to allow the student to retry.
  - Conversations and messages are strictly isolated per student.

---

## 1. What Was Built

### 1.1 Database Migrations & Schema
1. **`2026_08_27_000025_create_ai_tutor_conversations_table.php`**:
   - `id`: Auto-incrementing primary key
   - `user_id`: Foreign key referencing `users.id` (cascade on delete)
   - `chapter_id`: Nullable foreign key referencing `chapters.id` (null on delete)
   - `title`: Nullable string (auto-titled from initial message or topic)
   - `last_message_at`: Timestamp (tracks most recent message)
   - `timestamps`: `created_at`, `updated_at`
2. **`2026_08_27_000026_create_ai_tutor_messages_table.php`**:
   - `id`: Primary key
   - `conversation_id`: Foreign key referencing `ai_tutor_conversations.id` (cascade on delete)
   - `role`: String/enum (`user`, `assistant`)
   - `content`: Text (message body)
   - `timestamps`: `created_at`, `updated_at`

### 1.2 Enums & Eloquent Models
- **`App\Enums\AiTutorMessageRole`**: `User ('user')`, `Assistant ('assistant')`.
- **`App\Models\AiTutorConversation`**: Has `user`, `chapter`, and `messages` relationships.
- **`App\Models\AiTutorMessage`**: Belongs to `conversation`.
- Updated **`App\Models\User`**: Added `tutorConversations(): HasMany`.
- Updated **`App\Models\Chapter`**: Added `tutorConversations(): HasMany`.

### 1.3 Service Layer (`TutorService`)
- `startOrGetConversation(User $user, ?int $chapterId = null)`: Reuses existing per-chapter thread or creates a new one / new general conversation.
- `sendMessage(User $user, AiTutorConversation $conversation, string $messageText)`: Enforces subscription, saves user message, formats last 15 messages with Lumani's persona and chapter context, queries Gemini, and saves assistant response upon success.
- `getUserConversations(User $user)`: Lists conversations sorted by `last_message_at DESC`.
- `getConversationMessages(User $user, AiTutorConversation $conversation)`: Returns full chronological message history with access control.

---

## 2. API Endpoints Specification

### Headers Required
```http
Authorization: Bearer <token>
Accept: application/json
```

---

### Endpoint 1: `GET /api/tutor/conversations`
Lists the student's conversations, ordered by most recent activity first.

#### Response (`200 OK`)
```json
{
  "conversations": [
    {
      "id": 5,
      "chapter_id": 12,
      "chapter_title": "Thermodynamics",
      "subject_name": "Physics",
      "title": "Physics: Thermodynamics",
      "last_message_at": "2026-08-27T23:45:00.000000Z",
      "created_at": "2026-08-27T23:30:00.000000Z"
    },
    {
      "id": 4,
      "chapter_id": null,
      "chapter_title": null,
      "subject_name": null,
      "title": "Study plan for national exam",
      "last_message_at": "2026-08-27T23:10:00.000000Z",
      "created_at": "2026-08-27T23:05:00.000000Z"
    }
  ]
}
```

---

### Endpoint 2: `POST /api/tutor/conversations`
Initializes a new conversation or retrieves an existing per-chapter thread.

#### Request Body (Optional)
```json
{
  "chapter_id": 12
}
```

#### Response (`201 Created`)
```json
{
  "message": "Conversation initialized successfully.",
  "conversation": {
    "id": 5,
    "chapter_id": 12,
    "chapter_title": "Thermodynamics",
    "subject_name": "Physics",
    "title": "Physics: Thermodynamics",
    "last_message_at": "2026-08-27T23:45:00.000000Z",
    "created_at": "2026-08-27T23:30:00.000000Z"
  }
}
```

---

### Endpoint 3: `GET /api/tutor/conversations/{id}/messages`
Retrieves full chronological message history for a conversation.

#### Response (`200 OK`)
```json
{
  "conversation_id": 5,
  "messages": [
    {
      "id": 101,
      "role": "user",
      "content": "Can you explain the First Law of Thermodynamics?",
      "created_at": "2026-08-27T23:31:00.000000Z"
    },
    {
      "id": 102,
      "role": "assistant",
      "content": "Certainly! The First Law states that energy cannot be created or destroyed, only transformed (ΔU = Q - W)...",
      "created_at": "2026-08-27T23:31:02.000000Z"
    }
  ]
}
```

---

### Endpoint 4: `POST /api/tutor/conversations/{id}/messages`
Sends a message to Lumani AI tutor and receives the response.

#### Request Body
```json
{
  "message": "What is the difference between an isothermal and an adiabatic process?"
}
```

#### Response (`200 OK`)
```json
{
  "message": "Reply received from Lumani.",
  "conversation_id": 5,
  "user_message": {
    "id": 103,
    "role": "user",
    "content": "What is the difference between an isothermal and an adiabatic process?",
    "created_at": "2026-08-27T23:46:00.000000Z"
  },
  "assistant_message": {
    "id": 104,
    "role": "assistant",
    "content": "Great question! In an isothermal process, the temperature remains constant (ΔT = 0), so heat exchange occurs slowly. In an adiabatic process, no heat enters or leaves the system (Q = 0)...",
    "created_at": "2026-08-27T23:46:02.000000Z"
  }
}
```

#### Error Response (`403 Forbidden` if not subscribed)
```json
{
  "message": "An active subscription is required to chat with AI Tutor Lumani."
}
```

---

## 3. Why This Approach

1. **Per-Chapter Conversation Memory**:
   Linking conversations to chapters allows students to resume their study threads without losing context or explanation history when revisiting specific topics.
2. **Context Window Bound (15 Messages)**:
   Restricting Gemini context to the last 15 messages provides sufficient conversational continuity while strictly bounding API token consumption and response latency.
3. **Resilient Failure Handling**:
   Discarding failed attempts and never persisting broken assistant messages prevents corrupted chat state, ensuring students can cleanly retry their prompt.

---

## 4. Verification & Test Results
- **Automated Tests**: 127 tests passing (719 assertions) across the entire backend suite.
  - `tests/Feature/Api/AiTutorTest.php`: 8 feature tests verifying subscription gating (403), free mode bypass, per-chapter thread reuse, general conversation creation, 15-message context bounding, error handling on Gemini failure, and strict user isolation.
- **Static Analysis**: PHPStan passing at 0 errors (`phpstan analyse --memory-limit=512M`).
- **Code Style**: 100% compliant with Laravel Pint.

---

## 5. Open Questions & Blockers
- None.
