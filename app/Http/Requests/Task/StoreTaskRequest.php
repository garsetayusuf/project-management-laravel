<?php

namespace App\Http\Requests\Task;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'sometimes|in:pending,in_progress,done',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'The project ID is required.',
            'project_id.exists' => 'The selected project does not exist.',
            'title.required' => 'The task title is required.',
            'title.string' => 'The task title must be a string.',
            'title.max' => 'The task title may not be greater than 255 characters.',
            'description.required' => 'The description field is required.',
            'description.string' => 'The description must be a string.',
            'status.in' => 'The status must be one of: pending, in_progress, done.',
            'priority.in' => 'The priority must be one of: low, medium, high, urgent.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after_or_equal' => 'The due date must be today or in the future.',
        ];
    }
}
