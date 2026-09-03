# Lumani Screen 2: 4-Act Image-Driven Onboarding Implementation Report

**Version:** 1.0.0  
**Date:** September 3, 2026  
**Target Environment:** Android & iOS (GCE & OBC Examination Tracks)  
**Status:** COMPLETE & VERIFIED (54/54 Tests Passing, 0 Analyzer Issues)

---

## 1. Executive Summary

This report documents the implementation of **Screen 2: 4-Act Story Onboarding Experience** for the Lumani mobile application. The onboarding journey takes Cameroonian secondary and high-school students through an emotional and aspirational arc: from the isolation and difficulty of exam preparation, to structured curriculum alignment (GCE & OBC), 24/7 AI tutor assistance, and future university and career opportunities.

Key Technical Accomplishments:
1. **Asset Management & Pre-caching**: Verified and synchronized 4 high-resolution photography assets (`onboarding1.jpg` through `onboarding4.jpg`) into `assets/images/branding/`. Implemented asset pre-caching via `precacheImage` in `OnboardingScreen.didChangeDependencies()` to eliminate swipe hitching, white flashes, and frame drops.
2. **Multi-Layered Scrim System**: Engineered a 4-layer composition stack per page with a top status-bar protection gradient and a bottom Deep Obsidian (`#090D16`) gradient starting at 42% screen height, ensuring contrast and readability for typography across any screen brightness.
3. **Bilingual Narrative Engine**: Fully localized all 4 Acts, titles, bodies, and control actions into English and French without hardcoding, utilizing the type-safe `AppLocalizations` architecture.
4. **Adaptive UI & Micro-interactions**:
   - 4-segment animated progress indicator (4dp height, 2dp radius) with 250ms smooth transition in Lumani Gold (`#FFB800`).
   - Contextual Skip action visible on Acts 1–3, smoothly hidden on Act 4 via `AnimatedOpacity`.
   - Constrained layout (`maxWidth: 440dp`) ensuring readability across phones, foldables, and tablets.
   - `AnimatedCrossFade` bottom controller transitioning smoothly on Act 4 to a full-width Lumani Gold "Get Started" CTA with haptic feedback (`HapticFeedback.lightImpact()`).
5. **State Persistence & Router Hand-off**: Integrated `has_seen_onboarding` in `SharedPreferences`. First-time unauthenticated users are seamlessly routed from `/splash` to `/onboarding`. Returning users bypass onboarding directly to `/auth` (or `/home` if authenticated).
6. **Testing & Code Quality**: Added 7 targeted unit and widget tests covering localization, rendering, swipe transitions, and persistence. Verified with `flutter analyze` (0 issues) and `flutter test` (54/54 tests passing).

---

## 2. File Modification & Creation Log

| File | Exact Path | Type | Fixes & Enhancements Applied |
|---|---|---|---|
| `onboarding[1-4].jpg` | `Frontend/assets/images/branding/` | Copied | Copied approved branding assets from `assets/images/` into `assets/images/branding/` to strictly match the asset path specification. |
| `app_localizations.dart` | `Frontend/lib/core/localization/app_localizations.dart` | Modified | Added getters for `onboardingAct1Title`, `onboardingAct1Body`, `onboardingAct2Title`, `onboardingAct2Body`, `onboardingAct3Title`, `onboardingAct3Body`, `onboardingAct4Title`, `onboardingAct4Body`, `onboardingSkip`, `onboardingNext`, and `onboardingGetStarted`. |
| `app_en.dart` | `Frontend/lib/core/localization/app_en.dart` | Modified | Implemented exact English string overrides for all 4 acts, Skip, Next, and Get Started. |
| `app_fr.dart` | `Frontend/lib/core/localization/app_fr.dart` | Modified | Implemented exact French string overrides for all 4 acts, Passer, Suivant, and Commencer. |
| `onboarding_screen.dart` | `Frontend/lib/features/onboarding/presentation/onboarding_screen.dart` | Created | Built `OnboardingScreen` with asset pre-caching, 4-layer scrim stack, 4-segment progress bar, typography hierarchy, `AnimatedCrossFade` CTA, and `has_seen_onboarding` persistence. |
| `app_router.dart` | `Frontend/lib/app/router/app_router.dart` | Modified | Added `/onboarding` `GoRoute` and added `/onboarding` to `_authOnlyRoutes` so authenticated users cannot be stranded on onboarding. |
| `splash_screen.dart` | `Frontend/lib/features/splash/presentation/splash_screen.dart` | Modified | Updated `_navigateBasedOnState` to check `has_seen_onboarding` via `SharedPreferences`. First-time unauthenticated users route to `/onboarding`; returning users route to `/auth`. |
| `splash_screen_test.dart` | `Frontend/test/features/splash/splash_screen_test.dart` | Modified | Updated transition tests to verify first-time user transition to `/onboarding` and returning user transition to `/auth`. |
| `onboarding_screen_test.dart` | `Frontend/test/features/onboarding/onboarding_screen_test.dart` | Created | Created comprehensive test suite verifying English & French localization, rendering, Act advancement to Act 4 CTA, Skip persistence, and Get Started persistence. |

---

## 3. Localization Strings Matrix

| Key | English (`en`) | French (`fr`) | Purpose |
|---|---|---|---|
| `onboardingAct1Title` | "Learning shouldn't feel this difficult." | "Apprendre ne devrait pas être si difficile." | Act 1: Problem awareness (isolation, fragmented notes) |
| `onboardingAct1Body` | "National exams are demanding, but fragmented notes, missing past papers, and studying in isolation make preparation harder than it has to be." | "Les examens nationaux sont exigeants, mais les cours dispersés, le manque d'épreuves et l'isolement compliquent inutilement votre préparation." | Act 1 description |
| `onboardingAct2Title` | "There's a better way to master your syllabus." | "Une méthode claire pour maîtriser le programme." | Act 2: Solution & curriculum alignment |
| `onboardingAct2Body` | "Access structured, curriculum-aligned lessons tailored precisely to the official GCE Board and OBC examination standards." | "Accédez à des cours structurés et conformes aux exigences officielles de l'Office du Baccalauréat et du GCE Board." | Act 2 description |
| `onboardingAct3Title` | "Ask whenever you are stuck." | "Posez vos questions à tout moment." | Act 3: Lumani AI Tutor capability |
| `onboardingAct3Body` | "Get 24/7 step-by-step academic explanations from Lumani AI Tutor. Break down complex math formulas, science concepts, and essay frameworks instantly." | "Profitez d'explications détaillées 24h/24 avec le tuteur Lumani IA. Maîtrisez les formules complexes et les dissertations sans attendre." | Act 3 description |
| `onboardingAct4Title` | "Your education is leading somewhere." | "Votre parcours vous mène plus loin." | Act 4: Real-world mastery & future opportunities |
| `onboardingAct4Body` | "Turn exam success into real-world opportunities. Unlock university tracks, career roadmaps, and build verified academic mastery." | "Transformez votre réussite scolaire en opportunités réelles : préparez vos filières universitaires et bâtissez votre avenir." | Act 4 description |
| `onboardingSkip` | "Skip" | "Passer" | Top-right skip button on Acts 1–3 |
| `onboardingNext` | "Next" | "Suivant" | Tooltip / accessibility for circular next icon |
| `onboardingGetStarted` | "Get Started" | "Commencer" | Act 4 primary full-width CTA |

---

## 4. UI Layout & Scrim Architecture

Each page of the onboarding screen is constructed using a 4-layer composition stack inside `PageView.builder`:

```
+-------------------------------------------------------------+
| Layer 2: Top Gradient Scrim (Black 60% -> Transparent, 140dp) |
| [ ========================== ] Segmented Progress Bar       |
|                                        [ Skip ] Action      |
|-------------------------------------------------------------|
|                                                             |
| Layer 1: Background Photography Image                       |
| (BoxFit.cover, Alignment.center, Pre-cached)                |
|                                                             |
|-------------------------------------------------------------|
| Layer 3: Bottom Gradient Scrim                              |
| (Starts at 42% height -> Deep Obsidian #090D16 at 100%)    |
|                                                             |
| Layer 4: Foreground Narrative Text (maxWidth: 440dp)        |
| - Title: Poppins Bold 24-28sp, White, height 1.25          |
| - Body:  Poppins Regular 15sp, #CBD5E1, height 1.45         |
|                                                             |
| Layer 4: Bottom Controls (Fixed SafeArea, maxWidth: 440dp)  |
| Acts 1-3: [ ● ○ ○ ○ ]                   [ ( -> ) Next Button ] |
| Act 4:    [ ============= Get Started -> ============= ]    |
+-------------------------------------------------------------+
```

### Scrim Design Rationale
- **Top Scrim**: Spans the top `140dp` from `Colors.black.withValues(alpha: 0.60)` to `Colors.transparent`. This shields the device status bar icons (clock, battery, Wi-Fi), the 4-segment progress bar, and the "Skip" action regardless of background photography lightness.
- **Bottom Scrim**: Spans from 42% screen height down to the bottom with 4 color stops: `[0.0, 0.28, 0.65, 1.0]`, transitioning from transparent into `AppColors.backgroundDark` (Deep Obsidian `#090D16`). This creates an optical contrast ratio exceeding 12:1 for white title and slate body text without occluding the focal point of the subject's face in the upper half of the photograph.

---

## 5. Verification & Quality Gates

### Static Analysis
```
flutter analyze
Analyzing Frontend...
No issues found! (ran in 8.3s)
```

### Code Formatting
```
dart format --set-exit-if-changed .
Formatted 35 files (0 changed) in 0.44 seconds.
```

### Automated Test Suite
```
flutter test
00:23 +54: All tests passed!
```

Breakdown of Passing Tests:
- `test/features/onboarding/onboarding_screen_test.dart`: 7 tests passing (Localization, PageView, French/English render, Act 1–4 transitions, Skip persistence, Get Started persistence).
- `test/features/splash/splash_screen_test.dart`: 9 tests passing (Including first-time `/onboarding` and returning `/auth` routing).
- `test/sprint1_hardening_test.dart`: 21 tests passing (Session resilience, UserModel, ExamLevel, Locale lock).
- `test/design_system_test.dart`: 6 tests passing (M3 tokens, responsive utilities).
- `test/core/theme_test.dart`: 6 tests passing (M3 ThemeData, system brightness).
- `test/features/auth/auth_flow_test.dart`: 4 tests passing.
- `test/widget_test.dart`: 1 smoke test passing.
- **Total:** **54 / 54 tests passed (100%)**.

---

## 6. Route & Hand-off Verification Checklist

- [x] Pre-caching verified: all 4 onboarding background assets pre-cached on entry.
- [x] Segmented progress bar: animates color/width smoothly across 4 segments.
- [x] Skip button: visible and operational on Acts 1–3, hidden on Act 4.
- [x] Act 4 CTA: AnimatedCrossFade displays full-width "Get Started" button in Lumani Gold with haptic feedback.
- [x] SharedPreferences persistence: `has_seen_onboarding` set to `true` upon tapping Skip or Get Started.
- [x] Splash routing: First-time users navigate to `/onboarding`; returning users navigate to `/auth`.
- [x] AppRouter: `/onboarding` route registered and protected by `_authOnlyRoutes` guard.
- [x] Bilingual parity: All 11 strings verified in English and French.
- [x] 0 linter warnings or errors.
- [x] 100% test pass rate (54/54 tests).

**Status:** **GREEN LIGHT** — Lumani Screen 2 (4-Act Image-Driven Onboarding) is complete, hardened, and ready for production deployment.
