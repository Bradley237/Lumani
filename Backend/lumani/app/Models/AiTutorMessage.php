<?php

namespace App\Models;

use App\Enums\AiTutorMessageRole;
use Database\Factories\AiTutorMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property AiTutorMessageRole $role
 * @property string $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AiTutorConversation $conversation
 */
class AiTutorMessage extends Model
{
    /** @use HasFactory<AiTutorMessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'role' => AiTutorMessageRole::class,
        ];
    }

    /**
     * @return BelongsTo<AiTutorConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiTutorConversation::class, 'conversation_id');
    }
}
