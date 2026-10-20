<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TrendingArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours' => ['sometimes', 'integer', 'in:24,48'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
