<?php

namespace App\Forms;

use App\Automotive\VehicleCatalog;
use App\Blocks\OfficialBlockCatalog;
use App\Models\BlockInstance;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoSeries;
use App\Models\Form;
use App\Models\Page;
use App\Models\Popup;
use App\Models\SeriesMediaSet;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Turns visitor public-ID hints into a trusted Submission context (FORMS_AND_INTEGRATIONS.md
 * §7, §37–§38). Every hint must resolve inside the Form's Site; vehicle, offer and price are
 * read from the database, never from the browser. Visitor-sourced tracking (page URL,
 * referrer, UTMs) is kept apart under `visitor`.
 *
 * @phpstan-type Context array{trusted: array<string, array<string, mixed>>, visitor: array<string, string>}
 */
class SubmissionContextResolver
{
    public const CONTEXT_KEYS = ['page', 'block', 'popup', 'vehicle', 'offer', 'media_set'];

    /** Quiz option IDs (P9-016); valid only together with a quiz `block`. */
    public const ANSWERS_KEY = 'answers';

    public const URL_KEYS = ['page_url', 'referrer'];

    public const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    public function __construct(private VehicleCatalog $catalog) {}

    /**
     * @return Context|null Null when a hint is malformed or does not belong to this Form's Site.
     */
    public function resolve(Form $form, mixed $context, mixed $tracking): ?array
    {
        if (($context !== null && ! is_array($context)) || ($tracking !== null && ! is_array($tracking))) {
            return null;
        }

        $ids = [];

        foreach (self::CONTEXT_KEYS as $key) {
            $value = $context[$key] ?? null;

            if ($value === null) {
                continue;
            }

            if (! is_string($value) || ! Str::isUlid($value)) {
                return null;
            }

            $ids[$key] = strtolower($value);
        }

        $trusted = $this->trusted($form, $ids, $context[self::ANSWERS_KEY] ?? null);

        return $trusted === null ? null : ['trusted' => $trusted, 'visitor' => $this->visitor($tracking ?? [])];
    }

    /**
     * @param  array<string, string>  $ids
     * @return array<string, array<string, mixed>>|null
     */
    private function trusted(Form $form, array $ids, mixed $answers): ?array
    {
        $site = $form->site;
        $trusted = [];
        $page = null;
        $vehicle = null;

        if (isset($ids['page'])) {
            $page = $site->pages()->where('public_id', $ids['page'])->first();

            if (! $page instanceof Page) {
                return null;
            }

            $trusted['page'] = ['public_id' => $page->public_id, 'title' => $page->title];
        }

        if (isset($ids['block'])) {
            $block = BlockInstance::query()
                ->with('version.definition')
                ->where('public_id', $ids['block'])
                ->whereHas('page', fn ($query) => $query->where('site_id', $site->id))
                ->first();

            if ($block === null || ($page !== null && $block->page_id !== $page->id)) {
                return null;
            }

            $trusted['block'] = ['public_id' => $block->public_id, 'name' => $block->version->definition->name];

            if ($answers !== null) {
                $quiz = $block->version->definition->slug === OfficialBlockCatalog::QUIZ_SLUG
                    ? QuizAnswers::resolve($block->state_json, $answers)
                    : null;

                if ($quiz === null) {
                    return null;
                }

                $trusted['quiz'] = ['answers' => $quiz];
            }
        } elseif ($answers !== null) {
            return null;
        }

        if (isset($ids['popup'])) {
            $popup = $site->popups()->active()->where('public_id', $ids['popup'])->first();

            if (! $popup instanceof Popup || $popup->form_id !== $form->id) {
                return null;
            }

            $trusted['popup'] = ['public_id' => $popup->public_id, 'name' => $popup->name];
        }

        if (isset($ids['vehicle'])) {
            $vehicle = $site->vehicles()->where('status', true)->where('public_id', $ids['vehicle'])->first();

            if (! $vehicle instanceof SiteVehicle) {
                return null;
            }
        }

        $offer = null;

        if (isset($ids['offer'])) {
            $offer = SiteOffer::query()
                ->where('public_id', $ids['offer'])
                ->where('status', true)
                ->whereHas('vehicle', fn ($query) => $query->where('site_id', $site->id)->where('status', true))
                ->first();

            if ($offer === null || ($vehicle !== null && $offer->site_vehicle_id !== $vehicle->id)) {
                return null;
            }

            $vehicle ??= $offer->vehicle;
        }

        if ($vehicle !== null) {
            $series = AutoSeries::query()->available()->with('generation.model.mark')
                ->where('public_id', $vehicle->catalog_series_public_id)->first();

            if ($series === null) {
                return null;
            }

            $trusted['vehicle'] = ['public_id' => $vehicle->public_id, ...$this->catalog->seriesTitle($series)];
        }

        if ($offer !== null) {
            $equipment = AutoEquipment::query()->available()->with('modification')
                ->where('public_id', $offer->catalog_equipment_public_id)->first();

            if ($equipment === null) {
                return null;
            }

            $trusted['offer'] = [
                'public_id' => $offer->public_id,
                'modification' => $equipment->modification->name,
                'equipment' => $equipment->name,
                'price_minor' => $offer->price_minor,
                'currency' => $offer->currency,
                'price_label' => Money::format($offer->price_minor, $offer->currency),
            ];
        }

        if (isset($ids['media_set'])) {
            $set = $vehicle?->mediaSets()->active()->where('series_media_sets.public_id', $ids['media_set'])->first();

            if (! $set instanceof SeriesMediaSet) {
                return null;
            }

            $trusted['media_set'] = ['public_id' => $set->public_id, 'name' => $set->name];
        }

        return $trusted;
    }

    /**
     * Visitor-sourced and untrusted: kept only when well-formed, never used for authorization.
     *
     * @param  array<array-key, mixed>  $tracking
     * @return array<string, string>
     */
    public function visitor(array $tracking): array
    {
        $visitor = [];

        foreach (self::URL_KEYS as $key) {
            $value = $tracking[$key] ?? null;

            if (is_string($value) && mb_strlen($value) <= 2048 && preg_match('#^https?://#i', $value) === 1) {
                $visitor[$key] = $value;
            }
        }

        foreach (self::UTM_KEYS as $key) {
            $value = $tracking[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $visitor[$key] = mb_substr(trim($value), 0, 255);
            }
        }

        return $visitor;
    }
}
