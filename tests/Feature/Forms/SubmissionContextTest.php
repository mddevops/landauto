<?php

namespace Tests\Feature\Forms;

use App\Enums\FormFieldType;
use App\Models\BlockInstance;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoSeries;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Page;
use App\Models\Popup;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class SubmissionContextTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Site $site;

    private Form $form;

    private Popup $popup;

    private Page $page;

    private BlockInstance $block;

    private AutoSeries $series;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create();
        $this->form = Form::factory()->for($this->site)->withLeadFields()->create();
        $this->popup = Popup::factory()->for($this->site)->create(['name' => 'Получить предложение']);
        $this->popup->form()->associate($this->form)->save();
        $this->page = Page::factory()->for($this->site)->create(['title' => 'Модели']);
        $this->block = BlockInstance::factory()->for($this->page)->create();

        $mark = AutoMark::factory()->create(['name' => 'Changan', 'url' => 'changan']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'UNI-K', 'url' => 'uni-k']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'I', 'url' => 'i']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Кроссовер', 'url' => 'suv']);
    }

    public function test_same_popup_records_different_trusted_context_per_vehicle_with_server_price(): void
    {
        [$vehicleA, $offerA] = $this->vehicleWithOffer(389_000_000, mainSeries: true);
        [$vehicleB, $offerB] = $this->vehicleWithOffer(415_000_000);
        $black = SeriesMediaSet::factory()->forSeries($this->series)->create(['name' => 'Чёрный']);
        $vehicleA->selectMediaSets([$black->public_id]);

        $this->submit($this->context(['vehicle' => $vehicleA->public_id, 'offer' => $offerA->public_id, 'media_set' => $black->public_id]))->assertCreated();
        $this->submit($this->context(['vehicle' => $vehicleB->public_id, 'offer' => $offerB->public_id]), fields: ['phone' => '+7 (999) 222-33-44'])->assertCreated();

        [$first, $second] = Submission::query()->orderBy('id')->get()->all();
        $trustedA = $first->context['trusted'] ?? [];
        $trustedB = $second->context['trusted'] ?? [];

        $this->assertSame(['public_id' => $this->page->public_id, 'title' => 'Модели'], $trustedA['page']);
        $this->assertSame($this->block->public_id, $trustedA['block']['public_id']);
        $this->assertSame(['public_id' => $this->popup->public_id, 'name' => 'Получить предложение'], $trustedA['popup']);
        $this->assertSame([$vehicleA->public_id, 'Changan UNI-K', 'Кроссовер'], [$trustedA['vehicle']['public_id'], $trustedA['vehicle']['title'], $trustedA['vehicle']['series']]);
        $this->assertSame([$offerA->public_id, 389_000_000, 'RUB'], [$trustedA['offer']['public_id'], $trustedA['offer']['price_minor'], $trustedA['offer']['currency']]);
        $this->assertSame(AutoEquipment::query()->where('public_id', $offerA->catalog_equipment_public_id)->value('name'), $trustedA['offer']['equipment']);
        $this->assertSame(['public_id' => $black->public_id, 'name' => 'Чёрный'], $trustedA['media_set']);

        $this->assertSame([$vehicleB->public_id, 'Лифтбек', 415_000_000], [$trustedB['vehicle']['public_id'], $trustedB['vehicle']['series'], $trustedB['offer']['price_minor']]);
        $this->assertArrayNotHasKey('media_set', $trustedB);
    }

    public function test_visitor_price_is_ignored_and_offer_alone_resolves_its_vehicle(): void
    {
        [$vehicle, $offer] = $this->vehicleWithOffer(389_000_000);
        FormField::factory()->for($this->form)->create(['key' => 'price', 'type' => FormFieldType::Hidden, 'required' => false]);

        $this->submit(
            ['offer' => $offer->public_id, 'price' => 1, 'price_minor' => 1],
            fields: ['price' => '1'],
        )->assertCreated();

        $submission = Submission::query()->sole();
        $this->assertSame(389_000_000, $submission->context['trusted']['offer']['price_minor'] ?? null);
        $this->assertSame($vehicle->public_id, $submission->context['trusted']['vehicle']['public_id'] ?? null);
        $this->assertArrayNotHasKey('price', $submission->context['trusted'] ?? []);
        $this->assertSame('1', collect($submission->payload)->firstWhere('key', 'price')['value'] ?? null);

        $this->submit(['offer' => $offer->public_id], extra: ['price' => 1])->assertUnprocessable();
        $this->assertSame(1, Submission::query()->count());
    }

    public function test_foreign_mismatched_inactive_and_malformed_hints_are_rejected(): void
    {
        [$vehicle, $offer] = $this->vehicleWithOffer(389_000_000, mainSeries: true);
        [$otherVehicle] = $this->vehicleWithOffer(415_000_000);
        $inactiveOffer = SiteOffer::factory()->for($vehicle, 'vehicle')->create(['status' => false]);
        $foreignSite = Site::factory()->create();
        $foreignVehicle = SiteVehicle::factory()->for($foreignSite)->forSeries($this->series)->create();
        $foreignOffer = SiteOffer::factory()->for($foreignVehicle, 'vehicle')->create();
        $otherPage = Page::factory()->for($this->site)->create();
        $otherForm = Form::factory()->for($this->site)->create();
        $otherPopup = Popup::factory()->for($this->site)->create();
        $otherPopup->form()->associate($otherForm)->save();
        $foreignSet = SeriesMediaSet::factory()->forSeries($this->series)->create();

        $cases = [
            'foreign vehicle' => ['vehicle' => $foreignVehicle->public_id],
            'foreign offer' => ['offer' => $foreignOffer->public_id],
            'offer of another vehicle' => ['vehicle' => $otherVehicle->public_id, 'offer' => $offer->public_id],
            'inactive offer' => ['offer' => $inactiveOffer->public_id],
            'foreign page' => ['page' => Page::factory()->for($foreignSite)->create()->public_id],
            'block of another page' => ['page' => $otherPage->public_id, 'block' => $this->block->public_id],
            'popup of another form' => ['popup' => $otherPopup->public_id],
            'unselected media set' => ['vehicle' => $vehicle->public_id, 'media_set' => $foreignSet->public_id],
            'media set without vehicle' => ['media_set' => $foreignSet->public_id],
            'numeric id' => ['vehicle' => (string) $vehicle->id],
            'non string' => ['vehicle' => ['x']],
        ];

        foreach ($cases as $case => $context) {
            $this->submit($context)
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Данные страницы устарели. Обновите страницу и отправьте заявку снова.');
            $this->assertSame(0, Submission::query()->count(), $case);
        }

        $vehicle->update(['status' => false]);
        $this->submit(['vehicle' => $vehicle->public_id])->assertUnprocessable();
    }

    public function test_page_url_referrer_and_utms_are_stored_separately_as_visitor_data(): void
    {
        $this->submit($this->context(), tracking: [
            'page_url' => 'https://dealer.example.ru/models?utm_source=yandex',
            'referrer' => 'javascript:alert(1)',
            'utm_source' => ' yandex ',
            'utm_campaign' => str_repeat('к', 300),
            'utm_term' => '',
            'gclid' => 'ignored',
        ])->assertCreated();

        $context = Submission::query()->sole()->context;

        $this->assertSame([
            'page_url' => 'https://dealer.example.ru/models?utm_source=yandex',
            'utm_source' => 'yandex',
            'utm_campaign' => str_repeat('к', 255),
        ], $context['visitor'] ?? null);
        $this->assertArrayNotHasKey('utm_source', $context['trusted'] ?? []);
    }

    /**
     * @return array{SiteVehicle, SiteOffer}
     */
    private function vehicleWithOffer(int $price, bool $mainSeries = false): array
    {
        $series = $mainSeries
            ? $this->series
            : AutoSeries::factory()->for($this->series->generation, 'generation')->create(['name' => 'Лифтбек', 'url' => 'lift-'.$price]);
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($series)->create();

        return [$vehicle, SiteOffer::factory()->for($vehicle, 'vehicle')->create(['price_minor' => $price])];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function context(array $overrides = []): array
    {
        return ['page' => $this->page->public_id, 'block' => $this->block->public_id, 'popup' => $this->popup->public_id, ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $tracking
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $extra
     */
    private function submit(array $context, array $tracking = [], array $fields = [], array $extra = []): TestResponse
    {
        return $this->postJson(route('forms.submissions.store', $this->form->public_id), [
            'fields' => ['name' => 'Иван', 'phone' => '+7 (999) 111-22-33', 'consent' => true, ...$fields],
            'context' => $context,
            'tracking' => $tracking,
            ...$extra,
        ]);
    }
}
