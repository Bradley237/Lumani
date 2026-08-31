<?php

namespace App\Http\Requests\Api\User;

use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use App\Rules\ValidExamPair;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'preferred_language' => ['nullable', 'string', 'in:en,fr'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'exam_system' => ['nullable', new Enum(ExamSubsystem::class)],
            'level' => ['nullable', new Enum(ExamLevel::class), new ValidExamPair('exam_system')],
            'exam_date' => ['nullable', 'date'],
        ];
    }
}
