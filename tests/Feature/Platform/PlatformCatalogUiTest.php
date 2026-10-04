<?php

namespace Tests\Feature\Platform;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoCharacteristic;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoOption;
use App\Models\Catalog\AutoSeries;
use App\Models\PlatformRoleAssignment;
use App\Models\SeriesMediaSet;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class PlatformCatalogUiTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_customers_cannot_open_or_mutate_the_platform_catalog(): void
    {
        $owner = User::factory()->create();
        Workspace::factory()->create()->addMember($owner, WorkspaceRole::Owner);
        $mark = AutoMark::factory()->create();
        $series = AutoSeries::factory()->create();

        $this->get(route('platform.catalog.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('platform.catalog.index'))->assertForbidden();
        $this->actingAs($owner)
            ->post(route('platform.catalog.entries.store', 'marks'), ['name' => 'X', 'url' => 'x', 'status' => 1, 'sort_order' => 0])
            ->assertForbidden();
        $this->actingAs($owner)
            ->patch(route('platform.catalog.entries.update', ['marks', $mark->public_id]), ['name' => 'X', 'url' => 'x', 'status' => 0, 'sort_order' => 0])
            ->assertForbidden();
        $this->actingAs($owner)->get(route('platform.catalog.media.show', $series->public_id))->assertForbidden();
        $this->actingAs($owner)
            ->post(route('platform.catalog.media.sets.store', $series->public_id), ['name' => 'Белый', 'status' => 1, 'sort_order' => 0])
            ->assertForbidden();

        $this->assertSame(0, AutoMark::query()->where('url', 'x')->count());
        $this->assertSame([$mark->name, true], [$mark->fresh()?->name, $mark->fresh()?->status]);
        $this->assertSame(0, SeriesMediaSet::query()->count());
    }

    public function test_cascading_browser_drops_lower_selection_that_does_not_belong_to_the_parent(): void
    {
        $manager = $this->platformUser(PlatformRole::CatalogManager);
        $kia = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $lada = AutoMark::factory()->create(['name' => 'Lada', 'url' => 'lada', 'sort_order' => 1]);
        $rio = AutoModel::factory()->for($kia, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $vesta = AutoModel::factory()->for($lada, 'mark')->create(['name' => 'Vesta', 'url' => 'vesta']);
        AutoGeneration::factory()->for($rio, 'model')->create(['name' => 'IV']);

        $this->actingAs($manager)
            ->get(route('platform.catalog.index', ['mark' => $kia->public_id, 'model' => $rio->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/catalog/index')
                ->has('levels', 3)
                ->where('levels.0.selected', $kia->public_id)
                ->where('levels.1.parent', $kia->public_id)
                ->where('levels.1.items.0.name', 'Rio')
                ->where('levels.1.selected', $rio->public_id)
                ->where('levels.2.items.0.name', 'IV')
                ->missing('levels.0.items.0.id')
                ->where('can.edit', true)
                ->where('can.manageMedia', true));

        $this->actingAs($manager)
            ->get(route('platform.catalog.index', ['mark' => $kia->public_id, 'model' => $vesta->public_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('levels', 2)
                ->has('levels.1.items', 1)
                ->where('levels.1.selected', null));
    }

    public function test_manager_creates_and_updates_hierarchy_entries_with_server_side_validation(): void
    {
        $manager = $this->platformUser(PlatformRole::SuperAdmin);
        $store = fn (string $level, array $data) => $this->actingAs($manager)
            ->post(route('platform.catalog.entries.store', $level), [...$data, 'status' => '1', 'sort_order' => '0']);

        $store('marks', ['name' => 'Kia', 'url' => 'kia'])->assertSessionHasNoErrors()->assertRedirect();
        $kia = AutoMark::query()->where('url', 'kia')->sole();

        $store('marks', ['name' => 'Kia 2', 'url' => 'kia'])->assertSessionHasErrors('url');
        $store('marks', ['name' => 'Bad', 'url' => 'Bad Url'])->assertSessionHasErrors('url');
        $store('models', ['name' => 'Rio', 'url' => 'rio'])->assertSessionHasErrors('parent');
        $store('generations', ['name' => 'IV', 'url' => 'iv', 'parent' => $kia->public_id])->assertSessionHasErrors('parent');

        $store('models', ['name' => 'Rio', 'url' => 'rio', 'parent' => $kia->public_id, 'year_from' => '2017', 'year_to' => '2011'])
            ->assertSessionHasErrors('year_to');
        $store('models', ['name' => 'Rio', 'url' => 'rio', 'parent' => $kia->public_id])->assertSessionHasNoErrors();
        $rio = AutoModel::query()->where('url', 'rio')->sole();
        $this->assertSame($kia->id, $rio->mark_id);

        $series = AutoSeries::factory()->create();
        $store('modifications', [
            'name' => '1.6 AT',
            'parent' => $series->public_id,
            'engine_volume' => '1591',
            'engine_power' => '123,5',
            'engine' => 'petrol',
            'transmission' => 'automatic',
            'drive' => 'fwd',
            'consumption_100_km' => '',
        ])->assertSessionHasNoErrors();
        $modification = AutoModification::query()->where('series_id', $series->id)->sole();
        $this->assertSame('123.50', $modification->engine_power);
        $this->assertNull($modification->consumption_100_km);

        $store('modifications', ['name' => 'X', 'parent' => $series->public_id, 'engine' => 'steam'])->assertSessionHasErrors('engine');

        $this->actingAs($manager)
            ->patch(route('platform.catalog.entries.update', ['marks', $kia->public_id]), [
                'name' => 'KIA', 'url' => 'kia', 'status' => false, 'sort_order' => 5,
            ])
            ->assertSessionHasNoErrors();
        $kia->refresh();
        $this->assertSame(['KIA', false, 5], [$kia->name, $kia->status, $kia->sort_order]);
        $this->assertSame(1, AutoMark::query()->where('url', 'kia')->count());
    }

    public function test_model_group_must_be_a_model_of_the_same_mark(): void
    {
        $manager = $this->platformUser(PlatformRole::CatalogManager);
        $kia = AutoMark::factory()->create();
        $rio = AutoModel::factory()->for($kia, 'mark')->create(['url' => 'rio']);
        $foreign = AutoModel::factory()->create(['url' => 'vesta']);
        $update = fn (?string $group) => $this->actingAs($manager)
            ->patch(route('platform.catalog.entries.update', ['models', $rio->public_id]), [
                'name' => 'Rio', 'url' => 'rio', 'status' => 1, 'sort_order' => 0, 'group' => $group,
            ]);

        $update($foreign->public_id)->assertSessionHasErrors('group');
        $this->assertNull($rio->fresh()?->parent_id);

        $family = AutoModel::factory()->for($kia, 'mark')->create(['url' => 'rio-family']);
        $update($family->public_id)->assertSessionHasNoErrors();
        $this->assertSame($family->id, $rio->fresh()?->parent_id);
    }

    public function test_equipment_page_saves_characteristics_and_options(): void
    {
        $manager = $this->platformUser(PlatformRole::CatalogManager);
        $equipment = AutoEquipment::factory()->create(['name' => 'Comfort']);
        $group = AutoCharacteristic::factory()->create(['name' => 'Размеры']);
        $length = AutoCharacteristic::factory()->parameter($group, 'мм')->create(['name' => 'Длина']);
        $optionGroup = AutoOption::factory()->create();
        $climate = AutoOption::factory()->option($optionGroup)->create(['name' => 'Климат-контроль']);
        $heated = AutoOption::factory()->option($optionGroup)->create(['name' => 'Подогрев руля']);

        $this->actingAs($manager)
            ->put(route('platform.catalog.equipments.characteristics', $equipment->public_id), [
                'values' => [$length->public_id => '4400'],
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)
            ->put(route('platform.catalog.equipments.options', $equipment->public_id), [
                'values' => [$climate->public_id => 'standard', $heated->public_id => 'unknown'],
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)
            ->put(route('platform.catalog.equipments.options', $equipment->public_id), [
                'values' => [$climate->public_id => 'maybe'],
            ])
            ->assertSessionHasErrors('values.'.$climate->public_id);

        $this->actingAs($manager)
            ->get(route('platform.catalog.equipments.show', $equipment->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/catalog/equipment')
                ->where('equipment.name', 'Comfort')
                ->where('chain.modification.public_id', $equipment->modification->public_id)
                ->where('characteristicGroups.0.items.0.value', '4400')
                ->where('characteristicGroups.0.items.0.unit', 'мм')
                ->where('optionGroups.0.items.0.availability', 'standard')
                ->where('optionGroups.0.items.1.availability', 'unknown'));

        $this->assertSame(1, $equipment->optionValues()->count(), 'unknown options are not stored');
    }

    public function test_dictionary_entries_are_created_under_root_groups_only(): void
    {
        $manager = $this->platformUser(PlatformRole::CatalogManager);
        $store = fn (string $dictionary, array $data) => $this->actingAs($manager)
            ->post(route('platform.catalog.dictionaries.store', $dictionary), [...$data, 'sort_order' => 0]);

        $store('characteristics', ['code' => 'dimensions', 'name' => 'Размеры'])->assertSessionHasNoErrors();
        $group = AutoCharacteristic::query()->where('code', 'dimensions')->sole();
        $store('characteristics', ['code' => 'length', 'name' => 'Длина', 'unit' => 'мм', 'group' => $group->public_id])->assertSessionHasNoErrors();
        $length = AutoCharacteristic::query()->where('code', 'length')->sole();
        $this->assertSame($group->id, $length->parent_id);

        $store('characteristics', ['code' => 'width', 'name' => 'Ширина', 'group' => $length->public_id])->assertSessionHasErrors('group');
        $store('characteristics', ['code' => 'dimensions', 'name' => 'Дубль'])->assertSessionHasErrors('code');
        $store('characteristics', ['code' => 'engine_power', 'name' => 'Мощность', 'group' => $group->public_id])->assertSessionHasErrors('code');
        $store('options', ['code' => 'Bad-Code', 'name' => 'X'])->assertSessionHasErrors('code');

        $this->actingAs($manager)
            ->patch(route('platform.catalog.dictionaries.update', ['characteristics', $length->public_id]), [
                'code' => 'changed', 'name' => 'Длина кузова', 'unit' => 'мм', 'sort_order' => 2,
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(['length', 'Длина кузова', 2], [$length->fresh()?->code, $length->fresh()?->name, $length->fresh()?->sort_order]);

        $this->actingAs($manager)
            ->get(route('platform.catalog.dictionaries.show', 'characteristics'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/catalog/dictionary')
                ->where('dictionary.hasUnit', true)
                ->where('groups.0.code', 'dimensions')
                ->where('groups.0.items.0.code', 'length'));
    }

    public function test_series_media_sets_are_managed_from_the_series_page(): void
    {
        $manager = $this->platformUser(PlatformRole::CatalogManager);
        $series = AutoSeries::factory()->create(['name' => 'Седан']);

        $this->actingAs($manager)
            ->post(route('platform.catalog.media.sets.store', $series->public_id), [
                'name' => 'Белый', 'swatch_hex' => '#FFFFFF', 'status' => 1, 'sort_order' => 0,
            ])
            ->assertSessionHasNoErrors();
        $set = SeriesMediaSet::query()->sole();
        $this->assertSame(['#ffffff', $series->public_id], [$set->swatch_hex, $set->catalog_series_public_id]);

        $this->actingAs($manager)
            ->post(route('platform.catalog.media.sets.store', $series->public_id), [
                'name' => 'Белый', 'swatch_hex' => 'white', 'status' => 1, 'sort_order' => 0,
            ])
            ->assertSessionHasErrors(['name', 'swatch_hex']);

        $this->actingAs($manager)
            ->patch(route('platform.catalog.media.sets.update', $set), ['name' => 'Белый перламутр', 'swatch_hex' => '', 'status' => 0, 'sort_order' => 3])
            ->assertSessionHasNoErrors();
        $set->refresh();
        $this->assertSame(['Белый перламутр', null, false, 3], [$set->name, $set->swatch_hex, $set->status, $set->sort_order]);

        $this->actingAs($manager)
            ->get(route('platform.catalog.media.show', $series->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/catalog/media')
                ->where('series.name', 'Седан')
                ->where('sets.0.name', 'Белый перламутр')
                ->has('angles', 6)
                ->missing('sets.0.id'));
    }

    private function platformUser(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user;
    }
}
