<?php

namespace App\Http\Requests\Menu;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'path' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'permission_name' => ['nullable', 'string', 'exists:permissions,name'],
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentId = $this->input('parent_id');

            if (! $parentId) {
                return;
            }

            $parent = Menu::find($parentId);

            if ($parent && $parent->depth() >= 3) {
                $validator->errors()->add('parent_id', 'Kedalaman menu maksimal 3 level.');
            }
        });
    }
}
