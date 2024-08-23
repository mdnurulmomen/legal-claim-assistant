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
        $settingType = $this->route('settingType');

        return $this->getRulesByType($settingType);
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
        return [];
    }
}
