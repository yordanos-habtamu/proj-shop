<?php

namespace App\Http\Requests\Review;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class ApproveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()->can('approve', $project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
