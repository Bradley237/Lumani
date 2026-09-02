# LUMANI PRE-CODING BASELINE AUDIT

## 1. Executive Summary
The Lumani Frontend currently consists of a healthy, lint-free UI foundation and a functioning authentication shell that successfully communicates with the Laravel backend. However, it lacks a robust data/domain architecture, relying heavily on raw `Map<String, dynamic>` parsing within UI state controllers (Cubits). While the foundational design system strictly adheres to the requested Material 3 rules and responsive constraints, the app contains several critical P0 defects (such as a missing Internet permission on Android and a destructive offline-launch token wipe) that will cause immediate runtime failures. Before feature development can begin, the architectural gaps (missing Repositories and Data Models) and pubspec dependency bloat must be addressed.

## 2. Verified Current Project State
The project environment was explicitly tested and verified:
- **Linter:** `flutter analyze` executed with 0 issues found.
- **Formatter:** `dart format` executed confirming perfect formatting.
- **Tests:** `flutter test` executed successfully with 15 passing tests (verifying design tokens and smoke rendering).

## 3. Project Structure
- **Root Layout:** Separated into `app/`, `core/`, and `features/` (`auth`, `home`, `splash`, `subsystem`).
- **Deficiency:** The structure relies solely on `presentation` and `cubit` directories. There are absolutely no `data/`, `domain/`, or `repositories/` directories.
- **Evidence:** Inspection of `lib/features/auth/` reveals only `cubit` and `presentation`.

## 4. Flutter/Dart Configuration
- **SDK:** `^3.13.1` is appropriately constrained.
- **Configuration:** The `pubspec.yaml` dictates the configuration but includes massive, currently unused native plugins that inflate build size.

## 5. Dependency Audit

| Package | Used? | Where? | Purpose | Duplicate? | Concern | Recommendation |
|---|---|---|---|---|---|---|
| `flutter_bloc` | Yes | Cubits (`AuthCubit`, etc.) | State Management | No | None | Keep |
| `go_router` | Yes | `app_router.dart` | Navigation | No | Missing Guards | Keep |
| `dio` | Yes | `api_client.dart` | Networking | No | None | Keep |
| `flutter_secure_storage` | Yes | `api_client.dart` | Token persistence | No | None | Keep |
| `shared_preferences` | Yes | `LocaleCubit` | Settings persistence | No | None | Keep |
| `equatable` | Yes | State classes | Object comparison | No | None | Keep |
| `pdfrx` | No | Unused | PDF Rendering | No | Bloats build | Remove later |
| `path_provider` | No | Unused | File storage | No | None | Remove later |
| `google_mobile_ads` | No | Unused | Ad Monetization | No | Massive SDK bloat | Remove later |
| `webview_flutter` | No | Unused | Web rendering | No | Native bloat | Remove later |
| `flutter_markdown` | No | Unused | Text rendering | No | None | Remove later |
| `flutter_math_fork` | No | Unused | LaTeX rendering | No | None | Remove later |
| `speech_to_text` | No | Unused | Voice | No | Permissions risk | Remove later |
| `flutter_tts` | No | Unused | Text to Speech | No | Native bloat | Remove later |

## 6. Architecture Audit
- **Paradigm:** Feature-first presentation with tightly coupled data logic. 
- **Flaw:** Business logic, networking, and JSON parsing all reside inside Cubits (e.g., `AuthCubit.login` calls `apiClient.dio.post` directly).
- **Evidence:** `lib/features/auth/cubit/auth_cubit.dart` handles HTTP requests and state mutation simultaneously.

## 7. State Management Audit
- **Implementation:** `flutter_bloc` handles state safely via immutable classes.
- **Flaw:** `AuthCubit` lacks checks to prevent concurrent duplicate executions (race conditions).
- **Flaw:** Destructive state management exists in `checkAuthStatus()`, wiping tokens on any error.

## 8. Navigation & Routing Audit
- **Implementation:** `go_router` is centralized in `AppRouter`.
- **Flaw:** Zero route guards (`redirect`) are implemented. 
- **Evidence:** `lib/app/router/app_router.dart` relies on imperative UI listeners in `SplashScreen` instead of global router state protection.

## 9. Authentication Audit
- **Implementation:** Login, Register, Logout, and User profiles perfectly map to the backend endpoints. Token interceptors inject `Bearer <token>` accurately.
- **Flaw:** No global 401 interceptor exists to gracefully log users out mid-session.
- **Evidence:** `lib/core/network/api_client.dart` error interceptor merely wraps `DioException` without handling HTTP 401 specifically.

## 10. API / Network Audit
- **Implementation:** Secure. No logging intercepts exist to leak secrets to the console.
- **Flaw:** A hardcoded Android-emulator localhost assumption (`http://10.0.2.2:8000/api`) will crash iOS simulators and physical devices.
- **Evidence:** `api_client.dart` hardcodes `_baseUrl`.

## 11. Backend/Frontend Contract Compatibility

| Endpoint | Backend Contract | Flutter Implementation | Status | Severity |
|---|---|---|---|---|
| `POST /api/register` | Accepts registration details | Implemented | Match | None |
| `POST /api/login` | Accepts email/password | Implemented | Match | None |
| `POST /api/logout` | Revokes current token | Implemented | Match | None |
| `GET /api/user` | Returns authenticated user profile | Implemented | Match | None |
| All Learning Routes | E.g. `/api/quizzes/{id}` | None | Not yet integrated | None |
| All Mission/Coin Routes | E.g. `/api/missions` | None | Not yet integrated | None |
| All Tutor/Challenge Routes | E.g. `/api/challenges` | None | Not yet integrated | None |

## 12. Data Model Compatibility
- **Status:** Critical Risk.
- **Evidence:** Zero data models exist. `HomeScreen` accesses fields via dynamic casting: `user?['first_name']`. A change in the backend response structure will instantly cause `TypeError` crashes.

## 13. Learning & Quiz Integration
- **Status:** Unimplemented. No quizzes, chapters, or grading UI exists.

## 14. Past Papers & Protected Content
- **Status:** Unimplemented. Following the strict offline rule, no offline educational cache exists.

## 15. Coins / XP / Missions / Monetization
- **Status:** Unimplemented. Direct coin purchasing is accurately omitted from the project.

## 16. Subscription Integration
- **Status:** Unimplemented. The UI does not query or enforce premium tiers.

## 17. Weekly Challenge & Exam Mode
- **Status:** Unimplemented. No timed-state representations exist.

## 18. AI Tutor
- **Status:** Unimplemented. No markdown rendering or conversational context handling exists.

## 19. Progress / Revision Plan / Career
- **Status:** Unimplemented. No availability collection forms exist.

## 20. UI / UX / Design System
- **Implementation:** Excellent adherence to the Material 3 standards. Poppins font is actively used. 
- **Flaw:** Contrast failure in `AppBar` light theme (white text on a white background).
- **Evidence:** `lib/core/theme/app_theme.dart` maps `titleTextStyle` to `AppTextStyles.titleLarge` which defaults to `textPrimaryDark`.

## 21. Responsiveness & Accessibility
- **Implementation:** Excellent layout bounds.
- **Evidence:** `lib/core/responsive/responsive_utils.dart` strictly caps wide devices using `AdaptiveConstraintContainer` (max width 720). Touch targets are correctly sized at `48.0`.

## 22. Screen State Coverage
- **Status:** Missing crucial error/empty states.
- **Evidence:** `HomeScreen` is entirely static, containing no Loading, Empty, or Network Error states if user data fails to load dynamically.

## 23. Testing
- **Implementation:** 15 smoke tests explicitly pass. 
- **Flaw:** No API, state management, or integration tests exist.

## 24. Security
- **Implementation:** `FlutterSecureStorage` securely handles token persistence. The backend is correctly assumed as the absolute authority. No client-side trust vectors or screenshot behaviors were detected.

## 25. Technical Debt
- **Status:** Pubspec bloat (11 completely unused native packages) and tight coupling (Cubits handling `Dio` directly).

## 26. Contradictions / Risks
- **Subsystem Mismatch:** The frontend uses `'anglophone'` / `'francophone'` (in `SubsystemCubit`), but the backend explicitly expects `'gce'` / `'obc'`. This is a severe contradiction that will break API calls.

## 27. What Is Already Good
- Linter and formatter health.
- GoRouter foundation.
- Responsive container limits protecting against stretched tablet UI.
- Strict Material 3 design system adherence.

## 28. P0 Findings
- **Missing Internet Permission:** `android.permission.INTERNET` is missing from `AndroidManifest.xml` (will crash Release builds).
- **Destructive Offline Launch:** `AuthCubit.checkAuthStatus` aggressively wipes tokens on network exceptions, breaking user sessions.

## 29. P1 Findings
- **Subsystem Enum Mismatch:** Breaking backend validation logic.
- **Hardcoded Localhost:** Restricts development solely to Android emulators.
- **AppBar Contrast:** Invisible titles in Light Mode.

## 30. P2 Findings
- **No Global 401 Handling:** Expired tokens mid-session won't force a redirect.
- **Data Model Absence:** Lack of type-safety everywhere.
- **Pubspec Bloat:** Slower builds, larger footprints.

## 31. Top 15 Findings
1. Missing Internet Permission
2. Destructive Offline Token Wipe
3. Subsystem API Value Mismatch
4. Hardcoded API Base URL
5. AppBar Contrast Issue
6. Zero Data Models/DTOs implemented
7. No Repositories (Tight Dio Coupling in Cubits)
8. Massive Pubspec Bloat
9. No GoRouter Redirect Guards
10. No Global 401 Handling
11. Lack of Empty/Error States in HomeScreen
12. Lack of Concurrency Checks on Auth Forms
13. No Error Distinction (Offline vs 500)
14. Missing LocalizationsDelegates
15. Excellent Responsive Constraints established

## 32. What Must Be Fixed Before Feature Development
1. Fix P0/P1 defects (Internet Perm, Token Wipe, Hardcoded URL, Subsystem Mismatch, Contrast).
2. Implement robust Data Models (`json_serializable` or `freezed`).
3. Abstract `Dio` into a formal Repository layer.

## 33. What Can Wait
- Feature integration (Exams, AI Tutor, Monetization).
- Complete cleanup of pubspec bloat (can happen before release).

## 34. What Is NOT a Problem
- The lack of an offline cache (perfectly adheres to the "no offline mode" product rule).
- Missing coin purchasing logic (perfectly adheres to the monetization rule).

## 35. Recommended Development Order
1. Defect resolution (P0 and P1).
2. Architectural refactor (Models & Repositories).
3. Routing & State hardening (GoRouter guards & 401 handling).
4. Feature sprint (starting with Dashboard & Profile).

## 36. Recommended Next Audit / Implementation Task
- **Immediate Task:** Execute fixes for P0 and P1 defects directly.

---

## PRODUCT ARCHITECT DECISION REQUIRED

**1. Data Model Generation Strategy**
- **Decision Needed:** Choose the library for creating strongly-typed models to replace the `Map<String, dynamic>` usage.
- **Options:** `json_serializable` vs `freezed`.
- **Recommendation:** `freezed`.
- **Why:** The codebase is using `flutter_bloc` and `equatable`. Freezed provides immutable state and deep copying out of the box, which pairs perfectly with Bloc architectures for entities like `User` and `Progress`.

**2. Network Timeout UX Policy**
- **Decision Needed:** Define the universal behavior when a network request times out on a loaded screen (e.g., HomeScreen).
- **Options:** Silently fail, Display SnackBar, or Full-screen Error State.
- **Recommendation:** Display an actionable SnackBar ("Connection lost. Retry.") without destroying the currently loaded UI.
- **Why:** Since offline modes are explicitly prohibited, timeouts must be explicit, but full-screen errors degrade the UX when navigating cached tabs.
