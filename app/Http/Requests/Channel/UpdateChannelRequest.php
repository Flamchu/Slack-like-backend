<?php

namespace App\Http\Requests\Channel;

use App\Enums\ChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChannelRequest extends FormRequest
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
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-_]+$/',
                Rule::unique('channels', 'name')
                    ->where(function ($query) {
                        return $query->where('team_id', $this->route('teamId'));
                    })
                    ->ignore($this->route('id'))
            ],
            'description' => 'sometimes|nullable|string|max:1000',
            'type' => ['sometimes', 'required', Rule::enum(ChannelType::class)],
            'is_private' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
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
        if ($this->has('name')) {
            $this->merge([
                'name' => strtolower(str_replace(' ', '-', $this->name ?? '')),
            ]);
        }

        if ($this->has('is_private')) {
            $this->merge([
                'is_private' => $this->boolean('is_private'),
            ]);
        }

        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }
}
