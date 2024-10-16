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
            'platform_id' => ['required', 'integer', 'exists:platform_lists,id'],
            'internal_affiliate' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->when(! empty($this->specsId), function($query) {
                        return $query->ignore($this->affiliate_id);
                    })
            ],
            'posting_type' => ['required', 'string', 'max:255'],
            'ping_required_fields' => ['nullable', 'array'],
            'force_pingpost_sell' => ['required', 'boolean'],
            'affid' => ['required', 'string', 'max:255'],
            'payout.model' => ['nullable', 'string', 'max:255'],
            'payout.amount' => ['nullable', 'numeric'],
            'payout.percentage' => ['nullable', 'numeric'],
            'buyers' => ['nullable', 'array'],
            'optional_fields' => ['nullable', 'array'],
            'required_fields' => ['nullable', 'array']
        ];

        if(! empty($this->specsId)){
            $rules['platform_id'] = ['nullable', 'integer', 'exists:platform_lists,id'];
        }

        if(empty($this->specsId)){
            $rules['affiliate_id'] = ['nullable', 'integer', 'exists:users,id'];
        }

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
