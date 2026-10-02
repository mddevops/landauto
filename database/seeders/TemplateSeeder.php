<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = Template::query()->updateOrCreate(
            ['slug' => 'blank'],
            ['name' => 'Пустой шаблон'],
        );

        $template->forceFill(['is_official' => true])->save();
        $template->versions()->firstOrCreate(['version' => '1.0.0']);
    }
}
