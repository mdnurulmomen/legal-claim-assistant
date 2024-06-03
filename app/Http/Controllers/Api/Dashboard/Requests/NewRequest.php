<?php

namespace App\Http\Controllers\Api\Dashboard\Requests;

use Illuminate\Foundation\Http\FormRequest;
class NewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function newRequest(): bool
    {
        return true;
    }

}
