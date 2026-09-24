<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorefrontQueryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'completeness' => ['nullable', Rule::in(['concept', 'starter', 'mvp', 'complete'])],
            'stack' => ['nullable', 'string', 'max:40'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', 'gte:min_price'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'sales'])],
        ];
    }
}
