<?php

namespace App\Http\Controllers\Api\PortalOffers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CreateOrUpdatePortalOffersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'string'],
            'img' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif'],
            'payout_range_cpl_min' => ['nullable', 'numeric'],
            'payout_range_cpl_max' => ['nullable', 'numeric'],
            'payout_range_cpa_min' => ['nullable', 'numeric'],
            'payout_range_cpa_max' => ['nullable', 'numeric'],
            'preview_link' => ['nullable', 'url'],
            'criteria' => ['nullable', 'string'],
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            withValidationError($validator->errors())
        );
    }

    public function messages(): array
    {
        return [];
    }
} 