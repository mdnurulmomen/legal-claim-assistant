<?php

namespace App\Http\Controllers\Api\Public\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class LeadDetailsRequest extends FormRequest
{
    /**
     * Determine if the Admin Role is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Merge the `platform_key` header into the request data
        $this->merge([
            'platform_key' => $this->header('platform_key'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'platform_key' => ['required', 'string'],
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
            response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422)
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
            'platform_key.required' => 'Header key is required',
        ];
    }
}
