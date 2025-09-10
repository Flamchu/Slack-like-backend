<?php

namespace App\Http\Requests\Channel;

use App\Enums\ChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateChannelRequest extends FormRequest
{
    /**
     * check if user authorized
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * validation rules
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-_]+$/',
                Rule::unique('channels', 'name')->where(function ($query) {
                    return $query->where('team_id', $this->route('teamId'));
                })
            ],
            'description' => 'nullable|string|max:1000',
            'type' => ['required', Rule::enum(ChannelType::class)],
            'is_private' => 'boolean',
        ];
    }

    /**
     * custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Channel name is required',
            'name.regex' => 'Channel name must contain only lowercase letters, numbers, hyphens, and underscores',
            'name.unique' => 'A channel with this name already exists in this team',
            'name.max' => 'Channel name must not exceed 255 characters',
            'description.max' => 'Description must not exceed 1000 characters',
            'type.required' => 'Channel type is required',
            'type.enum' => 'Invalid channel type',
        ];
    }

    /**
     * prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strtolower(str_replace(' ', '-', $this->name ?? '')),
            'is_private' => $this->boolean('is_private', false),
        ]);
    }
}
