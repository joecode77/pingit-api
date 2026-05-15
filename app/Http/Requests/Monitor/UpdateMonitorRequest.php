<?php

// app/Http/Requests/Monitor/UpdateMonitorRequest.php

namespace App\Http\Requests\Monitor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMonitorRequest extends FormRequest
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
        return [
            'name'                       => ['nullable', 'string', 'max:255'],
            'check_interval'             => ['nullable', 'integer', 'min:1', 'max:60'],
            'threshold'                  => ['nullable', 'integer', 'min:1'],
            'response_time_threshold_ms' => ['nullable', 'integer', 'min:1'],
            'http_method'                => ['nullable', 'string', 'in:GET,HEAD'],
            'follow_redirects'           => ['nullable', 'boolean'],
            'custom_headers'             => ['nullable', 'array'],
            'custom_headers.*'           => ['string'],
        ];
    }
}