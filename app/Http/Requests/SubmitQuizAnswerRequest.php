<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuizAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formRules(): array
    {
        return [
            'option_id' => ['nullable', 'integer', 'required_without:answer_text', 'exists:quiz_options,id'],
            'answer_text' => ['nullable', 'string', 'max:4000', 'required_without:option_id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::formRules();
    }
}
