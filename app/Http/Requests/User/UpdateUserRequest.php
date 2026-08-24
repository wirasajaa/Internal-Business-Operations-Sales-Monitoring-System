<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Identity (id/username) and credentials are sourced from bpms.users and never
        // editable here — only the local display name and role assignment can change.
        return [
            'name' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}
