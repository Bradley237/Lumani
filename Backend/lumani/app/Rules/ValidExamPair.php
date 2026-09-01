<?php

namespace App\Rules;

use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidExamPair implements DataAwareRule, ValidationRule
{
    /**
     * All data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Create a new rule instance.
     *
     * @param  string  $subsystemField  The name of the subsystem field in the payload.
     */
    public function __construct(
        protected string $subsystemField = 'exam_system'
    ) {}

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        // Subsystem may be provided in payload under the given key or fallback to 'exam_subsystem'
        $subsystemRaw = $this->data[$this->subsystemField] ?? ($this->data['exam_subsystem'] ?? null);

        // If not in payload, check if user is authenticated and has exam_system
        if (blank($subsystemRaw) && Auth::check()) {
            /** @var User|null $user */
            $user = Auth::user();
            $subsystemRaw = $user?->exam_system instanceof ExamSubsystem
                ? $user->exam_system->value
                : $user?->exam_system;
        }

        if (blank($subsystemRaw)) {
            $fail("The {$attribute} field requires a valid exam subsystem to be selected.");

            return;
        }

        $subsystem = $subsystemRaw instanceof ExamSubsystem
            ? $subsystemRaw
            : ExamSubsystem::tryFrom((string) $subsystemRaw);

        $level = $value instanceof ExamLevel
            ? $value
            : ExamLevel::tryFrom((string) $value);

        if (! $subsystem || ! $level) {
            $fail("The selected {$attribute} or exam subsystem is invalid.");

            return;
        }

        if ($level->subsystem() !== $subsystem) {
            $fail("The selected {$attribute} '{$level->value}' is not valid for the '{$subsystem->value}' exam subsystem.");
        }
    }
}
