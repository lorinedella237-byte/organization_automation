<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class NewPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],

            // Twelve characters matches the minimum docs/backend-setup.md already
            // requires of a hosted demo administrator password. Length is the
            // only composition rule: forcing symbol classes pushes people toward
            // predictable substitutions without adding real entropy.
            'password' => ['required', 'string', 'max:4096', 'confirmed', Password::min(12)],
        ];
    }
}
