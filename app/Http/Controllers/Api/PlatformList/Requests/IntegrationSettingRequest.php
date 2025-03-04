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
            'name' => ['required', 'string'],
            'internal_buyer' => ['nullable', 'boolean'],
            'buyer_profile' => ['required', 'integer'],
            'alias' => ['nullable', 'string'],
            'buyer_type' => ['required', 'string'],
            'curl.url' => ['required', 'string'],
            'curl.method' => ['required', 'string'],
            'auth' => ['nullable'],
            'phone_format' => ['required', 'string'],
            'lead_id_key' => ['nullable', 'string', 'max:255'],
            'save_data' => ['required', 'array'],
            'custom_params' => ['nullable', 'array'],
            'ping' => ['nullable', 'array'],
            'ping.required' => ['sometimes', 'boolean'],
            'ping.triggers' => ['nullable', 'array'],
            'ping.save_data' => ['nullable', 'array'],
            'ping.payout.params' => ['nullable', 'string'],
            'maps' => ['required', 'array'],
            'custom_maps' => ['nullable', 'array'],
            'filter' => ['nullable', 'array'],
            'convert_maps' => ['nullable', 'array'],
            'payout' => ['required', 'array'],
            'buyer_payout_by_affid' => ['nullable', 'array'],
            'caps' => ['nullable', 'array'],
            'cv_trigger' => ['nullable', 'array'],
        ];

        if(array_key_exists('brand_data', $this->all())) {
            $rules['brand_data'] = ['required', 'array'];
            $rules['brand_data.sort_by'] = ['nullable', 'string'];
            $rules['brand_data.save_data'] = ['required', 'array'];
            $rules['brand_data.save_data.*.save_as'] = ['required', 'string'];
            $rules['brand_data.save_data.*.buyer_key'] = ['required', 'string'];
        }

        if($this->ping && $this->ping['required']) {
            $rules['ping.triggers'] = ['required', 'array'];
            $rules['ping.payout.params'] = ['required', 'string'];
            $rules['ping.save_data'] = ['required', 'array'];
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
            'brand_data.sort_by' => 'Please enter a valid sort by value.',
            'brand_data.save_data' => 'Please select at least one from Brand Save Data.',
            'brand_data.save_data.*.save_as' => 'Please enter a valid save as value.',
            'brand_data.save_data.*.buyer_key' => 'Please enter a valid buyer key.',
        ];
    }
}
