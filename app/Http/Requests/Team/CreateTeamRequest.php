<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTeamRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('teams', 'slug')
            ],
            'description' => 'nullable|string|max:1000',
            'avatar' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Team name is required',
            'name.max' => 'Team name must not exceed 255 characters',
            'slug.regex' => 'Team slug must contain only lowercase letters, numbers, and hyphens',
            'slug.unique' => 'This team slug is already taken',
            'description.max' => 'Description must not exceed 1000 characters',
        ];
    }
}
