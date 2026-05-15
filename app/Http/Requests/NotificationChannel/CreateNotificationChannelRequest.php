<?php

// app/Http/Requests/NotificationChannel/CreateNotificationChannelRequest.php

namespace App\Http\Requests\NotificationChannel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateNotificationChannelRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type'               => ['required', 'string', 'in:email,webhook'],
            'value'              => [
                'required',
                'string',
                $type === 'email' ? 'email' : 'url',
            ],
            'notify_on_down'     => ['nullable', 'boolean'],
            'notify_on_recovery' => ['nullable', 'boolean'],
            'notify_on_degraded' => ['nullable', 'boolean'],
        ];
    }
}