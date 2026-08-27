<?php

namespace Database\Factories;

use App\Enums\CoinTransactionType;
use App\Models\CoinTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoinTransaction>
 */
class CoinTransactionFactory extends Factory
{
    protected $model = CoinTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => 50,
            'type' => CoinTransactionType::EarnedMission,
            'reference_type' => null,
            'reference_id' => null,
        ];
    }
}
