<?php

namespace App\Http\Requests\Menu;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMenuRequest extends FormRequest
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
            $menu = $this->route('menu');
            $parentId = $this->input('parent_id');

            if (! $parentId) {
                return;
            }

            if ((int) $parentId === $menu->id) {
                $validator->errors()->add('parent_id', 'Menu tidak boleh menjadi parent dirinya sendiri.');

                return;
            }

            $parent = Menu::find($parentId);

            if ($parent && $this->isDescendantOf($parent, $menu)) {
                $validator->errors()->add('parent_id', 'Menu tidak boleh dipindah ke bawah sub-menunya sendiri.');

                return;
            }

            if ($parent && $parent->depth() >= 3) {
                $validator->errors()->add('parent_id', 'Kedalaman menu maksimal 3 level.');
            }
        });
    }

    private function isDescendantOf(Menu $candidateParent, Menu $menu): bool
    {
        $node = $candidateParent;

        while ($node->parent_id) {
            if ($node->parent_id === $menu->id) {
                return true;
            }

            $node = $node->parent()->first();
        }

        return false;
    }
}
