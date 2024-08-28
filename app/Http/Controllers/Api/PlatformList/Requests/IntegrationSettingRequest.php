<?php

namespace App\Http\Controllers\Api\PlatformList\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class IntegrationSettingRequest extends FormRequest
{
    /**
     * Determine if the Admin Role is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'internal_buyer' => ['nullable', 'boolean'],
            'buyer_profile' => ['required', 'integer'],
            'alias' => ['nullable', 'string'],
            'buyer_type' => ['required', 'string'],
            'curl.url' => ['required', 'string'],
            'curl.method' => ['required', 'string'],
            'auth' => ['nullable'],
            'phone_format' => ['required', 'string'],
            'save_data' => ['required', 'array'],
            'custom_params' => ['nullable', 'array'],
            'ping' => ['required', 'array'],
            'ping.required' => ['sometimes', 'boolean'],
            'ping.triggers' => ['nullable', 'array'],
            'ping.payout.params' => ['nullable', 'string', 'max:255'],
            'maps' => ['required', 'array'],
            'custom_maps' => ['nullable', 'array'],
            'filter' => ['nullable', 'array'],
            'convert_maps' => ['nullable', 'array'],
            'payout' => ['required', 'array'],
            'buyer_payout_by_affid' => ['nullable', 'array'],
            'caps' => ['nullable', 'array'],
            'cv_trigger' => ['nullable', 'array']
        ];

        if($this->ping['required']) {
            $rules['ping.triggers'] = ['required', 'array'];
            $rules['ping.payout.params'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    public function getRulesByType($settingType): array
    {
        $rules = match ($settingType) {
            'configuration' => [
                'internal_buyer' => ['nullable', 'boolean'],
                'buyer_profile' => ['nullable', 'integer'],
                'alias' => ['nullable', 'string'],
                'buyer_type' => ['nullable', 'string'],
                'curl.url' => ['nullable', 'string'],
                'curl.method' => ['nullable', 'string'],
                'auth' => ['nullable', 'boolean'],
                'phone_format' => ['nullable', 'string'],
                'save_data' => ['nullable', 'array'],
                'custom_params' => ['nullable', 'array'],
            ],
            'mapping' => [
                'maps' => ['required', 'array'],
            ],
            'static_fields' => [
                'custom_maps' => ['required', 'array'],
            ],
            'filters' => [
                'filter' => ['required', 'array'],
            ],
            'converted_filters' => [
                'convert_maps' => ['required', 'array'],
            ],
            'payout_settings' => [
                'payout' => ['required', 'array'],
                'buyer_payout_by_affid' => ['required', 'array'],
            ],
            'caps_controller' => [
                'caps' => ['required', 'array']
            ],

            default => []
        };

        return $rules;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     * @return void
     */
    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            withValidationError($validator->errors())
        );
    }

    /**
     * Returns an array of validation error messages.
     *
     * @return array<string, string> An empty array.
     */
    public function messages(): array
    {
        return [
            'ping.triggers' => 'Please select at least one ping trigger.',
            'ping.payout.params' => 'Please enter Ping Payout Parameter.',
            'curl.url' => 'Please enter a valid endpoint.',
            'curl.method' => 'Please enter a valid HTTP method.',
        ];
    }
}
