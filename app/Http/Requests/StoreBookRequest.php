<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Book::class) ?? false;
    }

    /**
     * Shared with Livewire components, which cannot inject form requests.
     *
     * @return array<string, list<string>>
     */
    public static function baseRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:102400'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }
}
