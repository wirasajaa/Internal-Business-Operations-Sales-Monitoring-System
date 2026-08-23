<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'label' => $this->faker->words(2, true),
            'path' => '/'.$this->faker->slug(2),
            'icon' => null,
            'permission_name' => null,
            'order' => 0,
            'is_active' => true,
        ];
    }
}
