<?php

namespace Database\Factories;

use App\Models\Popup;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Popup>
 */
class PopupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => 'Обратный звонок',
            'status' => true,
            'title' => 'Перезвоним за 5 минут',
            'text' => null,
            'size' => 'medium',
            'animation' => 'fade',
            'close_on_overlay' => true,
            'close_on_escape' => true,
            'show_close_button' => true,
            'mobile_fullscreen' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }
}
