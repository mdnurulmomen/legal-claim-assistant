<?php

namespace App\Http\Controllers\Api\Lead\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreLeadReportRequest extends FormRequest
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
        return [
            'affiliate_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'lead_id' => ['required', 'integer', Rule::exists('platform_datas', 'id')],
            'list_id' => ['required', 'integer', Rule::exists('platform_lists', 'id')],
            'buyer_id' => ['nullable', 'integer', Rule::exists('buyers', 'id')],
            'affid' => ['nullable', 'string', 'max:255'],
            'buyer_integration_id' => ['nullable', 'integer', Rule::exists('integrations', 'id')],
            'affiliate_specs_id' => ['nullable', 'integer'],
            'sold_type' => ['nullable', 'string'],
            'is_retainer' => ['nullable', 'boolean'],
            'is_paid' => ['nullable', 'boolean'],
            'is_internal' => ['nullable', 'boolean'],
            'lead_revenue' => ['nullable', 'numeric'],
            'affiliate_payout' => ['nullable', 'numeric'],
            'lead_profit' => ['nullable', 'numeric'],
            'affiliate_margin' => ['nullable', 'numeric'],
            'profit_margin' => ['nullable', 'numeric'],
            'is_posted' => ['nullable', 'boolean'],
            'page_source' => ['nullable', 'string', 'max:255'],
            'affm_source_id' => ['nullable', 'integer']
        ];
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
