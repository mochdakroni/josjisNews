<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ArticleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'tag' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'sort' => ['sometimes', 'string', 'in:latest,popular,oldest'],
        ];
    }
}
