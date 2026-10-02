<?php

namespace Tests\Feature\Templates;

use App\Models\Template;
use App\Models\TemplateVersion;
use Database\Seeders\TemplateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class TemplateFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_schema_contains_only_the_foundation_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('templates', [
            'id', 'public_id', 'name', 'slug', 'is_official', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('template_versions', [
            'id', 'template_id', 'version', 'created_at', 'updated_at',
        ]));

        foreach ([
            'developer_id',
            'category_id',
            'status',
            'pricing_type',
            'price',
            'manifest_json',
            'preview_metadata',
            'vehicle_data',
            'offers',
            'integrations',
            'domain',
            'publishing_state',
        ] as $prematureColumn) {
            $this->assertFalse(Schema::hasColumn('templates', $prematureColumn));
            $this->assertFalse(Schema::hasColumn('template_versions', $prematureColumn));
        }
    }

    public function test_template_receives_an_immutable_public_id_used_for_route_binding(): void
    {
        Route::middleware('web')->get(
            '/_template-binding/{template}',
            fn (Template $template) => response()->json(['public_id' => $template->public_id]),
        );
        $template = Template::factory()->create();

        $this->get("/_template-binding/{$template->public_id}")
            ->assertOk()
            ->assertJson(['public_id' => $template->public_id]);
        $this->get("/_template-binding/{$template->id}")->assertNotFound();
        $this->assertTrue(Str::isUlid($template->public_id));

        $template->public_id = (string) Str::ulid();

        $this->expectException(LogicException::class);
        $template->save();
    }

    public function test_template_slug_is_unique(): void
    {
        Template::factory()->create(['slug' => 'official']);

        $this->expectException(QueryException::class);
        Template::factory()->create(['slug' => 'official']);
    }

    public function test_template_public_id_is_unique(): void
    {
        $publicId = (string) Str::ulid();
        Template::factory()->create(['public_id' => $publicId]);

        $this->expectException(QueryException::class);
        Template::factory()->create(['public_id' => $publicId]);
    }

    public function test_template_versions_are_owned_by_one_template_and_unique_per_template(): void
    {
        $template = Template::factory()->create();
        $version = TemplateVersion::factory()->for($template)->create(['version' => '1.0.0']);

        $this->assertTrue($version->template->is($template));
        $this->assertTrue($template->versions->contains($version));

        $this->expectException(QueryException::class);
        TemplateVersion::factory()->for($template)->create(['version' => '1.0.0']);
    }

    public function test_blank_official_template_is_seeded_idempotently(): void
    {
        $this->seed(TemplateSeeder::class);
        $this->seed(TemplateSeeder::class);

        $blank = Template::query()->where('slug', 'blank')->sole();

        $this->assertSame('Пустой шаблон', $blank->name);
        $this->assertTrue($blank->is_official);
        $this->assertSame(['1.0.0'], $blank->versions()->pluck('version')->all());
        $this->assertSame(1, Template::query()->where('slug', 'blank')->count());
    }

    public function test_serialization_does_not_expose_numeric_identifiers(): void
    {
        $template = Template::factory()->create();
        $version = TemplateVersion::factory()->for($template)->create();

        $this->assertArrayNotHasKey('id', $template->toArray());
        $this->assertSame($template->public_id, $template->toArray()['public_id']);
        $this->assertArrayNotHasKey('id', $version->toArray());
        $this->assertArrayNotHasKey('template_id', $version->toArray());
    }
}
