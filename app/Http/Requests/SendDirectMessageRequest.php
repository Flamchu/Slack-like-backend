<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendDirectMessageRequest extends FormRequest
{
    /**
     * determine if user is authorized to make this request
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * get validation rules
     */
    public function rules(): array
    {
        return [
            'receiver_id' => 'required|integer|exists:users,id|different:' . auth()->id(),
            'content' => 'sometimes|string|max:4000|required_without:file',
            'file' => 'sometimes|file|max:20480',
            'type' => 'sometimes|string|in:text,file,image',
        ];
    }

    /**
     * custom validation messages
     */
    public function messages(): array
    {
        return [
            'receiver_id.different' => 'cannot send message to yourself',
            'receiver_id.exists' => 'receiver not found',
            'content.required' => 'message content is required',
            'content.max' => 'message content cannot exceed 4000 characters',
        ];
    }
}
