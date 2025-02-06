<?php

namespace App\Http\Controllers\Api\CaseStudy\Requests;

use App\Helpers\Utility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class CreateOrUpdateCaseStudyCategoryRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [ 'exists:case_study_categories,id', 'nullable'],
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
