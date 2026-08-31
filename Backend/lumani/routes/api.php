<?php

use App\Http\Controllers\Api\AiTutorController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CareerPathwayController;
use App\Http\Controllers\Api\CareerProfileController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\ChapterProgressController;
use App\Http\Controllers\Api\ChapterUnlockController;
use App\Http\Controllers\Api\ExamOptionController;
use App\Http\Controllers\Api\ExamSessionController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\PastPaperController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\RevisionPlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\XpConversionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('api.login');
Route::get('/exam-options', [ExamOptionController::class, 'index'])->name('api.exam-options');
Route::post('/payments/callback', [PaymentController::class, 'callback'])->name('api.payments.callback');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/user', [AuthController::class, 'user'])->name('api.user');
    Route::put('/user', [UserController::class, 'update'])->name('api.user.update');
    Route::patch('/user', [UserController::class, 'update']);
    Route::get('/user/referral-code', [UserController::class, 'referralCode'])->name('api.user.referral-code');

    // Subscriptions
    Route::get('/subscription', [SubscriptionController::class, 'status'])->name('api.subscription.status');
    Route::post('/subscriptions/purchase', [SubscriptionController::class, 'purchase'])->name('api.subscriptions.purchase');
    Route::post('/subscription/purchase', [SubscriptionController::class, 'purchase'])->name('api.subscription.purchase');

    // Missions
    Route::get('/missions', [MissionController::class, 'index'])->name('api.missions.index');
    Route::post('/missions/checkin', [MissionController::class, 'checkin'])->name('api.missions.checkin');
    Route::post('/missions/watch-ad', [MissionController::class, 'watchAd'])->middleware('throttle:watch-ad')->name('api.missions.watch-ad');
    Route::post('/missions/complete/{missionKey}', [MissionController::class, 'complete'])->name('api.missions.complete');

    // XP Conversion
    Route::post('/xp/convert', [XpConversionController::class, 'convert'])->name('api.xp.convert');

    // Chapter Progress & Dashboard
    Route::post('/chapters/{id}/touch', [ChapterProgressController::class, 'touch'])->name('api.chapters.touch');
    Route::get('/progress', [ChapterProgressController::class, 'progress'])->name('api.progress');

    // Career Profiles & Pathway
    Route::get('/career-profiles', [CareerProfileController::class, 'index'])->name('api.career-profiles.index');
    Route::post('/career-pathway/generate', [CareerPathwayController::class, 'generate'])->name('api.career-pathway.generate');
    Route::get('/career-pathway', [CareerPathwayController::class, 'show'])->name('api.career-pathway.show');

    // Revision Plan
    Route::post('/revision-plan/generate', [RevisionPlanController::class, 'generate'])->name('api.revision-plan.generate');
    Route::get('/revision-plan', [RevisionPlanController::class, 'show'])->name('api.revision-plan.show');

    // AI Tutor "Lumani"
    Route::get('/tutor/conversations', [AiTutorController::class, 'index'])->name('api.tutor.conversations.index');
    Route::post('/tutor/conversations', [AiTutorController::class, 'store'])->name('api.tutor.conversations.store');
    Route::get('/tutor/conversations/{id}/messages', [AiTutorController::class, 'messages'])->name('api.tutor.conversations.messages');
    Route::post('/tutor/conversations/{id}/messages', [AiTutorController::class, 'sendMessage'])->middleware('throttle:tutor-messages')->name('api.tutor.conversations.send-message');

    // Chapter and Past Paper Unlocks
    Route::post('/chapters/{id}/unlock', [ChapterUnlockController::class, 'unlock'])->name('api.chapters.unlock');
    Route::post('/past-papers/{id}/unlock-paper', [PastPaperController::class, 'unlockPaper'])->name('api.past-papers.unlock-paper');
    Route::post('/past-papers/{id}/unlock-solution', [PastPaperController::class, 'unlockSolution'])->name('api.past-papers.unlock-solution');

    // Exam Sessions (Timed Practice)
    Route::post('/past-papers/{id}/exam-session/start', [ExamSessionController::class, 'start'])->name('api.past-papers.exam-session.start');
    Route::post('/exam-sessions/{id}/submit', [ExamSessionController::class, 'submit'])->name('api.exam-sessions.submit');
    Route::get('/exam-sessions/{id}/result', [ExamSessionController::class, 'result'])->name('api.exam-sessions.result');

    // Quizzes
    Route::get('/quizzes/{id}', [QuizController::class, 'show'])->name('api.quizzes.show');
    Route::post('/quizzes/{id}/submit', [QuizController::class, 'submit'])->name('api.quizzes.submit');

    // Weekly Challenges
    Route::get('/challenges', [ChallengeController::class, 'index'])->name('api.challenges.index');
    Route::post('/challenges/{id}/start', [ChallengeController::class, 'start'])->name('api.challenges.start');
    Route::post('/challenges/{id}/submit', [ChallengeController::class, 'submit'])->name('api.challenges.submit');
    Route::get('/challenges/{id}/result', [ChallengeController::class, 'result'])->name('api.challenges.result');
});
