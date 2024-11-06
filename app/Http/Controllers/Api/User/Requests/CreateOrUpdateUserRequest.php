<?php

namespace App\Http\Controllers\Api\User\Requests;

use App\Helpers\Utility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class CreateOrUpdateUserRequest extends FormRequest
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
                        Rule::unique('users', 'email')->when(! empty($this->userId), function ($query) {
                            return $query->ignore($this->userId);
                        })
                    ],
            'username' => [
                        'required', 'string', 'max:255',
                        Rule::unique('users', 'username')->when(! empty($this->userId), function ($query) {
                            return $query->ignore($this->userId);
                        })
                    ],
            'password' => ['nullable', Rule::requiredIf(! $this->userId), 'string', 'min:6'],
            'password_confirmation' => ['nullable', 'required_with:password', 'same:password'],
            'phone' => [
                        'nullable', 'string', 'max:255',
                        Rule::unique('users', 'phone')->when(! empty($this->userId), function ($query) {
                            return $query->ignore($this->userId);
                        })
                    ],
            'workspace' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(array_keys(Utility::$userRoles))],
            'admin_role_id' => ['required', 'integer', Rule::exists('admin_roles', 'id')],
            'manager' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['nullable', 'boolean', Rule::in(array_keys(Utility::$userStatus))]
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
