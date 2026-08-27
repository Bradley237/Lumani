# Task 002 — Mobile Auth API Specification & Verification Report

## Overview & Status
- **Status**: Completed & Fully Verified
- **API Base URL**: `/api`
- **Authentication Mechanism**: Laravel Sanctum Personal Access Tokens (Bearer Token)
- **Token Lifespan**: Non-expiring (`config/sanctum.php` -> `'expiration' => null`)

---

## 1. API Endpoints Specification

All requests must supply the header:
```http
Accept: application/json
```
For requests with a body (POST), supply:
```http
Content-Type: application/json
```

---

### Endpoint 1: `POST /api/register`

Registers a new student user and returns an authentication token along with the user profile.

#### Headers
```http
Content-Type: application/json
Accept: application/json
```

#### Request Payload
| Field | Type | Required | Validation Rules | Description |
| :--- | :--- | :--- | :--- | :--- |
| `first_name` | `string` | **Yes** | `required`, `string`, `max:255` | User's first name |
| `last_name` | `string` | **Yes** | `required`, `string`, `max:255` | User's last name |
| `email` | `string` | **Yes** | `required`, `string`, `email`, `max:255`, `unique:users,email` | Unique email address |
| `password` | `string` | **Yes** | `required`, `string`, `min:8`, `confirmed` | Account password |
| `password_confirmation` | `string` | **Yes** | `required` (must match `password`) | Password confirmation |
| `preferred_language` | `string` | No | `nullable`, `in:en,fr` | Preferred UI language (`en` default) |
| `phone_number` | `string` | No | `nullable`, `string`, `max:50` | Optional phone number |

```json
{
  "first_name": "Ambe",
  "last_name": "Nfor",
  "email": "student@lumani.test",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!",
  "preferred_language": "en",
  "phone_number": "+237670112233"
}
```

#### Successful Response (`201 Created`)
```json
{
  "message": "User registered successfully.",
  "token": "1|GeJQc8cHnd8of8wxJuQXImxplAuze6Ae3PLpPQUH3eea2b35",
  "token_type": "Bearer",
  "user": {
    "id": 2,
    "first_name": "Ambe",
    "last_name": "Nfor",
    "email": "student@lumani.test",
    "role": "student",
    "preferred_language": "en",
    "phone_number": "+237670112233",
    "coin_balance": 0,
    "experience_points": 0,
    "day_streak": 0,
    "created_at": "2026-08-26T23:50:36.000000Z",
    "updated_at": "2026-08-26T23:50:36.000000Z",
    "name": "Ambe Nfor"
  }
}
```

#### Error Responses
- **`422 Unprocessable Content`** (Validation failed or duplicate email):
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

---

### Endpoint 2: `POST /api/login`

Authenticates an existing user and issues a new Sanctum token.

#### Headers
```http
Content-Type: application/json
Accept: application/json
```

#### Request Payload
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `email` | `string` | **Yes** | Registered email address |
| `password` | `string` | **Yes** | Account password |

```json
{
  "email": "student@lumani.test",
  "password": "SecurePassword123!"
}
```

#### Successful Response (`200 OK`)
```json
{
  "message": "Logged in successfully.",
  "token": "2|0ZBjZffYD3KcDNw7SHRr9qDoAKGFvStJgDjltntZdde185a5",
  "token_type": "Bearer",
  "user": {
    "id": 2,
    "first_name": "Ambe",
    "last_name": "Nfor",
    "email": "student@lumani.test",
    "email_verified_at": null,
    "role": "student",
    "preferred_language": "en",
    "phone_number": "+237670112233",
    "coin_balance": 0,
    "experience_points": 0,
    "day_streak": 0,
    "exam_system": null,
    "level": null,
    "exam_date": null,
    "created_at": "2026-08-26T23:50:36.000000Z",
    "updated_at": "2026-08-26T23:50:36.000000Z",
    "two_factor_confirmed_at": null,
    "name": "Ambe Nfor"
  }
}
```

#### Error Responses
- **`401 Unauthorized`** (Invalid credentials or non-existent email):
```json
{
  "message": "Invalid credentials."
}
```
- **`422 Unprocessable Content`** (Missing email or password):
```json
{
  "message": "The email field is required. (and 1 more error)",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."]
  }
}
```

---

### Endpoint 3: `GET /api/user`

Retrieves the currently authenticated user's profile.

#### Headers
```http
Accept: application/json
Authorization: Bearer <sanctum_token>
```

#### Request Payload
None.

#### Successful Response (`200 OK`)
```json
{
  "user": {
    "id": 2,
    "first_name": "Ambe",
    "last_name": "Nfor",
    "email": "student@lumani.test",
    "email_verified_at": null,
    "role": "student",
    "preferred_language": "en",
    "phone_number": "+237670112233",
    "coin_balance": 0,
    "experience_points": 0,
    "day_streak": 0,
    "exam_system": null,
    "level": null,
    "exam_date": null,
    "created_at": "2026-08-26T23:50:36.000000Z",
    "updated_at": "2026-08-26T23:50:36.000000Z",
    "two_factor_confirmed_at": null,
    "name": "Ambe Nfor"
  }
}
```

#### Error Responses
- **`401 Unauthorized`** (Missing or invalid/revoked token):
```json
{
  "message": "Unauthenticated."
}
```

---

### Endpoint 4: `POST /api/logout`

Revokes the current Sanctum personal access token being used to make the request.

#### Headers
```http
Accept: application/json
Authorization: Bearer <sanctum_token>
```

#### Request Payload
None.

#### Successful Response (`200 OK`)
```json
{
  "message": "Logged out successfully."
}
```

#### Error Responses
- **`401 Unauthorized`** (Token missing or already revoked):
```json
{
  "message": "Unauthenticated."
}
```

---

## 2. Configuration Details

### Sanctum Token Lifespan
In `Backend/lumani/config/sanctum.php`:
```php
'expiration' => null,
```
Tokens do not automatically expire after a set time window, allowing persistent mobile app sessions until explicitly logged out via `POST /api/logout`.

---

## 3. Files Created & Modified

| File | Type | Purpose |
| :--- | :--- | :--- |
| `Backend/lumani/app/Http/Controllers/Api/AuthController.php` | Controller | Handles `register`, `login`, `logout` (token revocation), and `user` profile retrieval. |
| `Backend/lumani/app/Http/Requests/Api/Auth/RegisterRequest.php` | Form Request | Validates registration inputs (required fields, email format & uniqueness, password min length & confirmation, language, phone). |
| `Backend/lumani/app/Http/Requests/Api/Auth/LoginRequest.php` | Form Request | Validates login inputs (email and password requirements). |
| `Backend/lumani/routes/api.php` | Routes | Defines public routes (`POST /register`, `POST /login`) and protected `auth:sanctum` routes (`POST /logout`, `GET /user`). |
| `Backend/lumani/config/sanctum.php` | Configuration | Configures Sanctum settings and non-expiring tokens (`expiration => null`). |
| `Backend/lumani/tests/Feature/Api/AuthTest.php` | Feature Test | Pest test suite covering all 6 authentication scenarios. |
| `docs/task-reports/002-mobile-auth-api.md` | Documentation | Hand-off API documentation for frontend/mobile engineering team. |

---

## 4. Test Verification Results

### Automated Test Suite (`pest`)
All 54 project tests (214 assertions) passed cleanly, including all 8 dedicated mobile auth feature tests:
```
✓ user can register successfully via mobile api and receives sanctum token
✓ registration rejects duplicate email
✓ registration validates required fields and password confirmation
✓ user can log in successfully with valid credentials
✓ login rejects incorrect password
✓ login rejects non-existent email
✓ logout revokes current sanctum token
✓ api user endpoint requires authentication

Tests:    8 passed (62 assertions)
Duration: 0.91s
```

### Real Live HTTP Verification (Curl against PostgreSQL)
Live HTTP requests were executed against a running server with PostgreSQL persistence:
1. `POST /api/register` -> `HTTP 201 Created` with valid Bearer token.
2. `GET /api/user` with Bearer token -> `HTTP 200 OK` returning profile data.
3. `POST /api/register` with duplicate email -> `HTTP 422 Unprocessable Content`.
4. `POST /api/login` with correct credentials -> `HTTP 200 OK` with valid Bearer token.
5. `POST /api/login` with incorrect password -> `HTTP 401 Unauthorized`.
6. `GET /api/user` without token -> `HTTP 401 Unauthorized`.
7. `POST /api/logout` with Bearer token -> `HTTP 200 OK` (token revoked).
8. `GET /api/user` with revoked token -> `HTTP 401 Unauthorized`.

---

## 5. Open Questions & Blockers
- **Blockers**: None. All backend routes and database configurations are active and functioning.
- **Frontend Integration Note**: Mobile client can store the received `token` securely (e.g. `flutter_secure_storage`) and attach it as `Authorization: Bearer <token>` for all subsequent authenticated API calls.
