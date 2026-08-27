<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string $preferred_language
 * @property string|null $phone_number
 * @property string|null $referral_code
 * @property int|null $referred_by_user_id
 * @property int $coin_balance
 * @property int $experience_points
 * @property int $xp_converted_total
 * @property int $day_streak
 * @property string|null $exam_system
 * @property string|null $level
 * @property Carbon|null $exam_date
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $referrer
 * @property-read Collection<int, User> $referrals
 * @property-read Collection<int, CoinTransaction> $coinTransactions
 * @property-read Collection<int, UserMissionProgress> $missionProgress
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read Collection<int, UserChapterUnlock> $chapterUnlocks
 * @property-read Collection<int, UserPastPaperUnlock> $pastPaperUnlocks
 * @property-read Collection<int, UserChallengeAttempt> $challengeAttempts
 * @property-read Collection<int, ChapterProgress> $chapterProgress
 * @property-read Collection<int, QuizAttempt> $quizAttempts
 * @property-read Collection<int, ExamSession> $examSessions
 * @property-read Collection<int, CareerPathway> $careerPathways
 */
class User extends Authenticatable implements FilamentUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'preferred_language',
        'phone_number',
        'referral_code',
        'referred_by_user_id',
        'coin_balance',
        'experience_points',
        'xp_converted_total',
        'day_streak',
        'exam_system',
        'level',
        'exam_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->referral_code)) {
                $user->referral_code = static::generateUniqueReferralCode();
            }
        });
    }

    /**
     * Generate a unique uppercase alphanumeric referral code.
     */
    public static function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'referred_by_user_id' => 'integer',
            'coin_balance' => 'integer',
            'experience_points' => 'integer',
            'xp_converted_total' => 'integer',
            'day_streak' => 'integer',
            'exam_date' => 'date',
        ];
    }

    /**
     * Determine if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Accessor and mutator for user full name.
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => trim(
                (($attributes['first_name'] ?? '').' '.($attributes['last_name'] ?? ''))
            ),
            set: function (string $value): array {
                $parts = explode(' ', trim($value), 2);

                return [
                    'first_name' => $parts[0],
                    'last_name' => $parts[1] ?? '',
                ];
            }
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_user_id');
    }

    /**
     * @return HasMany<CoinTransaction, $this>
     */
    public function coinTransactions(): HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }

    /**
     * @return HasMany<UserMissionProgress, $this>
     */
    public function missionProgress(): HasMany
    {
        return $this->hasMany(UserMissionProgress::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<UserChapterUnlock, $this>
     */
    public function chapterUnlocks(): HasMany
    {
        return $this->hasMany(UserChapterUnlock::class);
    }

    /**
     * @return HasMany<UserPastPaperUnlock, $this>
     */
    public function pastPaperUnlocks(): HasMany
    {
        return $this->hasMany(UserPastPaperUnlock::class);
    }

    /**
     * @return HasMany<UserChallengeAttempt, $this>
     */
    public function challengeAttempts(): HasMany
    {
        return $this->hasMany(UserChallengeAttempt::class);
    }

    /**
     * @return HasMany<ChapterProgress, $this>
     */
    public function chapterProgress(): HasMany
    {
        return $this->hasMany(ChapterProgress::class);
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * @return HasMany<ExamSession, $this>
     */
    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    /**
     * @return HasMany<CareerPathway, $this>
     */
    public function careerPathways(): HasMany
    {
        return $this->hasMany(CareerPathway::class);
    }
}
