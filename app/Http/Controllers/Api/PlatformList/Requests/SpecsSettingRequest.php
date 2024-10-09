<?php

namespace App\Http\Controllers\Api\PlatformList\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class SpecsSettingRequest extends FormRequest
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
            'affiliate_master_id' => ['required', 'integer', 'exists:users,id'],
            'affiliate_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'posting_type' => ['required', 'string', 'max:255'],
            'ping_required_fields' => ['nullable', 'array'],
            'force_pingpost_sell' => ['required', 'boolean'],
            'affid' => ['required', 'string', 'max:255'],
            'payout.model' => ['nullable', 'string', 'max:255'],
            'payout.amount' => ['nullable', 'numeric'],
            'payout.params' => ['nullable', 'string'],
            'buyers' => ['nullable', 'array'],
            'optional_fields' => ['nullable', 'array'],
            'required_fields' => ['nullable', 'array']
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
        return [
            'ping.triggers' => 'Please select at least one ping trigger.',
            'ping.save_data' => 'Please select at least from Ping Response Fields.',
            'ping.payout.params' => 'Please enter Ping Payout Parameter.',
            'curl.url' => 'Please enter a valid endpoint.',
            'curl.method' => 'Please enter a valid HTTP method.',
        ];
    }
}
