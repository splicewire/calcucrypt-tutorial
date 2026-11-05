<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The settings wire is camel (beam-inertia's security form); the legacy snake keys are refused, not read.
            'currentPassword' => $this->currentPasswordRules(),
            'password' => $this->passwordRules('passwordConfirmation'),
            'current_password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ];
    }
}
