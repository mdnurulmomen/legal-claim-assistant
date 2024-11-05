<?php

namespace App\Http\Controllers\Api\PlatformList\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class PlatformSettingRequest extends FormRequest
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
            'platform_id' => ['required', 'integer', 'exists:platform_lists,id'],
            'name' => ['required', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'additional_source' => ['nullable', 'array'],
            'skip_duplicate' => ['sometimes', 'boolean'],
            'is_high_level' => ['sometimes', 'boolean'],
            'lead_distribution' => ['nullable', 'string', 'max:255'],
            'min_ping_price' => ['nullable', 'numeric'],
            'min_affiliate_ping_prices' => ['nullable', 'array'],
            'global_postback' => ['nullable', 'array'],
            'dynamic_margin' => ['nullable', 'array'],
            'buyer_revshare' => ['nullable', 'array'],
            'lead_posting' => ['nullable', 'array'],
            'hidden_values' => ['nullable', 'array'],
            'lead_headers' => ['nullable', 'array'],
        ];

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
