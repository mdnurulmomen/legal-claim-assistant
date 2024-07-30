<?php

namespace App\Http\Controllers\Api\Affiliates\Requests;

use App\Helpers\Utility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class CreateOrUpdateAffiliateRequest extends FormRequest
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
            'email' => [
                        'required', 'string', 'email', 'max:255',
                        Rule::unique('users', 'email')->when(! empty($this->id), function ($query) {
                            return $query->ignore($this->id);
                        })
                    ],
            'username' => [
                        'required', 'string', 'max:255',
                        Rule::unique('users', 'username')->when(! empty($this->id), function ($query) {
                            return $query->ignore($this->id);
                        })
                    ],
            'password' => ['nullable', Rule::requiredIf(! $this->id), 'string', 'min:6'],
            'password_confirmation' => ['nullable', 'required_with:password', 'same:password'],
            'phone' => [
                        'nullable', 'string', 'max:255',
                        Rule::unique('users', 'phone')->when(! empty($this->id), function ($query) {
                            return $query->ignore($this->id);
                        })
                    ],
            'workspace' => ['nullable', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'zip' => ['required', 'string', 'max:255'],
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
