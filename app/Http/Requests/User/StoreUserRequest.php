<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Manual user creation is disabled (see UserController::store) — users are now
        // auto-provisioned from bpms.users on first login. No fields are validated here.
        return [];
    }
}
