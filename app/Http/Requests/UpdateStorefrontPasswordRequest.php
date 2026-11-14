<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateStorefrontPasswordRequest extends FormRequest
{
    protected $errorBag = 'password';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => __('frontend.profile.current_password_required'),
            'current_password.current_password' => __('frontend.profile.current_password_invalid'),
            'password.required' => __('frontend.profile.new_password_required'),
            'password.confirmed' => __('frontend.profile.password_confirmation_invalid'),
            'password.different' => __('frontend.profile.password_reused'),
            'password.min' => __('frontend.profile.password_strength'),
            'password.letters' => __('frontend.profile.password_strength'),
            'password.mixed' => __('frontend.profile.password_strength'),
            'password.numbers' => __('frontend.profile.password_strength'),
            'password.symbols' => __('frontend.profile.password_strength'),
        ];
    }
}
