<?php

namespace App\Http\Controllers\Api\SavedReport\Requests;

use App\Helpers\Utility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class SavedReportRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255', Rule::unique('saved_reports', 'title')],
            'filters' => ['required', 'array'],
            'filters.end_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'filters.start_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'filters.group_by' => ['required', 'string'],
            'filters.timezone' => ['nullable', 'string'],
            'filters.event_values' => ['nullable', 'array'],
            'filters.filters' => ['nullable', 'json'],
            'page_setting_ids' => ['required', 'array'],
            'page_setting_ids.*' => ['required', 'integer', Rule::exists('page_settings', 'id')]
        ];

        if (request()->isMethod('PUT') && ! empty($this->reportUid)) {
            $rules['title'] = ['required', 'string', 'max:255', Rule::unique('saved_reports', 'title')->ignore($this->reportUid, 'uid')];
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
