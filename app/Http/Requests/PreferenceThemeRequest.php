<?php

namespace App\Http\Requests;

use App\Enums\PreferenceTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreferenceThemeRequest extends FormRequest
{
    /** Chacun modifie uniquement sa propre préférence : aucun droit particulier requis. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'preference_theme' => ['required', Rule::enum(PreferenceTheme::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'preference_theme.required' => 'Le thème est obligatoire.',
            'preference_theme.enum' => 'Le thème doit être « clair », « sombre » ou « auto ».',
        ];
    }
}
