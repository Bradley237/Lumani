<?php

namespace App\Models;

use App\Enums\JobDemand;
use Database\Factories\CareerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string|null $average_salary
 * @property JobDemand $job_demand
 * @property array<int, string>|null $related_subjects
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CareerProfile extends Model
{
    /** @use HasFactory<CareerProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'average_salary',
        'job_demand',
        'related_subjects',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_demand' => JobDemand::class,
            'related_subjects' => 'array',
        ];
    }
}
