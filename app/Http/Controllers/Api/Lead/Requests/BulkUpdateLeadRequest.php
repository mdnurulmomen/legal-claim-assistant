<?php

namespace App\Http\Controllers\Api\Lead\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class BulkUpdateLeadRequest extends FormRequest
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
            'upload_type' => ['required', 'string', 'max:255'],
            'leads' => ['required', 'array'],
            'leads.*.id' => ['required', 'integer']
        ];

        if($this->upload_type == 'retainer_upload') {
            $rules['leads.*.retained_date'] = ['required', 'date_format:Y-m-d'];
            $rules['leads.*.revenue'] = ['nullable', 'numeric'];
            $rules['leads.*.affiliate_payout'] = ['nullable', 'numeric'];
            $rules['leads.*.is_show_portal'] = ['nullable', 'in:0,1,2'];
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
        return [];
    }
}
