<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\User;
use App\Models\UserChapterUnlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserChapterUnlock>
 */
class UserChapterUnlockFactory extends Factory
{
    protected $model = UserChapterUnlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'chapter_id' => Chapter::factory(),
            'unlocked_at' => now(),
        ];
    }
}
