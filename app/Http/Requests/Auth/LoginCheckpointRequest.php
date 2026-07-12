<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginCheckpointRequest extends FormRequest
{
    /**
     * Determine if the request is authorized.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules to apply to the request.
     */
    public function rules(): array
    {
        return [
            'confirmation_token' => 'required|string',
            'authentication_code' => [
                'nullable',
                'numeric',
                Rule::requiredIf(function () {
                    return empty($this->input('recovery_token'));
                }),
            ],
            'recovery_token' => [
                'nullable',
                'string',
                Rule::requiredIf(function () {
                    return empty($this->input('authentication_code'));
                }),
            ],
        ];
    }
}
