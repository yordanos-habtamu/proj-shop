<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Rules\SafeProjectZip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'currency' => ['nullable', 'string', 'in:USD'],
            'completeness' => ['required', Rule::in(['concept', 'starter', 'mvp', 'complete'])],
            'tech_stack' => ['nullable', 'array', 'max:10'],
            'tech_stack.*' => ['string', 'min:1', 'max:24'],
            'zip' => [
                'required',
                'file',
                'max:51200',
                'mimetypes:application/zip,application/x-zip-compressed,application/x-zip,application/octet-stream',
                new SafeProjectZip,
            ],
            'cover_image' => ['nullable', 'file', 'image', 'max:4096'],
            'save' => ['sometimes', 'string', 'in:draft,submit'],
        ];
    }
}
