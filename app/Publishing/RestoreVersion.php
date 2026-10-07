<?php

namespace App\Publishing;

use App\Catalog\CatalogReferences;
use App\Enums\PublishedVersionStatus;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Page;
use App\Models\Popup;
use App\Models\PublishedVersion;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\SiteVersionRestore;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Copies a historical version's private draft snapshot into the current Draft in one transaction
 * (ADR-006 §8). Production, Published Versions, Submissions, blacklists, form security, the
 * subdomain, Site Assets, the catalog and platform media are never touched. Public IDs are kept;
 * Forms are deactivated rather than deleted because Submissions reference them. Vehicles or
 * Offers whose catalog entries no longer exist are skipped and counted. Every successful restore
 * leaves a `SiteVersionRestore` audit record in the same transaction.
 *
 * @phpstan-type RestoreSummary array{skipped_vehicles: int, skipped_offers: int}
 */
final class RestoreVersion
{
    private int $skippedVehicles = 0;

    private int $skippedOffers = 0;

    public function __construct(private CatalogReferences $catalog) {}

    /**
     * @return RestoreSummary
     */
    public function handle(Site $site, PublishedVersion $version, User $actor): array
    {
        if ($version->site_id !== $site->id || $version->status !== PublishedVersionStatus::Ready) {
            throw new RestoreFailed('Эту версию нельзя восстановить.');
        }

        $snapshot = $version->draft_snapshot_json;

        if (($snapshot['schema'] ?? null) !== PublishedSnapshotBuilder::SCHEMA) {
            throw new RestoreFailed('Формат этой версии не поддерживается для восстановления.');
        }

        $this->skippedVehicles = 0;
        $this->skippedOffers = 0;

        DB::transaction(function () use ($site, $version, $actor, $snapshot): void {
            $site = Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();

            $this->restoreSite($site, self::map($snapshot['site'] ?? []));
            $this->restoreForms($site, self::list($snapshot['forms'] ?? []));
            $this->restorePopups($site, self::list($snapshot['popups'] ?? []));
            $this->restoreVehicles($site, self::list($snapshot['vehicles'] ?? []));
            $this->restorePages($site, self::list($snapshot['pages'] ?? []));

            SiteVersionRestore::query()->create([
                'site_id' => $site->id,
                'published_version_id' => $version->id,
                'actor_user_id' => $actor->id,
                'restored_at' => now(),
            ]);
        });

        return ['skipped_vehicles' => $this->skippedVehicles, 'skipped_offers' => $this->skippedOffers];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function restoreSite(Site $site, array $data): void
    {
        $site->forceFill([
            'name' => is_string($data['name'] ?? null) ? $data['name'] : $site->name,
            'design_tokens' => is_array($data['design_tokens'] ?? null) ? $data['design_tokens'] : null,
        ])->save();
    }

    /**
     * @param  list<array<string, mixed>>  $forms
     */
    private function restoreForms(Site $site, array $forms): void
    {
        $keep = array_map(fn (array $form): string => (string) $form['public_id'], $forms);
        $site->forms()->whereNotIn('public_id', $keep)->update(['status' => false]);

        foreach ($forms as $data) {
            $form = $this->ownRow(Form::class, $site, (string) $data['public_id']) ?? new Form;
            $form->forceFill([
                'public_id' => $data['public_id'],
                'site_id' => $site->id,
                'name' => $data['name'],
                'status' => (bool) $data['status'],
                'submit_label' => $data['submit_label'],
                'success_message' => $data['success_message'],
            ])->save();

            $fields = self::list($data['fields'] ?? []);
            $byKey = [];

            foreach ($fields as $field) {
                $byKey[(string) $field['key']] = $field;
            }

            foreach ($form->fields()->get() as $existing) {
                if (! isset($byKey[$existing->key]) || $byKey[$existing->key]['type'] !== $existing->type->value) {
                    $existing->delete();
                }
            }

            foreach ($fields as $fieldData) {
                $field = $form->fields()->where('key', $fieldData['key'])->first() ?? new FormField;
                $field->forceFill([
                    'form_id' => $form->id,
                    'key' => $fieldData['key'],
                    'type' => $fieldData['type'],
                    'label' => $fieldData['label'],
                    'placeholder' => $fieldData['placeholder'] ?? null,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'required' => (bool) $fieldData['required'],
                    'validation' => $fieldData['validation'] ?? null,
                    'options' => $fieldData['options'] ?? null,
                    'sort_order' => (int) $fieldData['sort_order'],
                ])->save();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $popups
     */
    private function restorePopups(Site $site, array $popups): void
    {
        $keep = array_map(fn (array $popup): string => (string) $popup['public_id'], $popups);
        $site->popups()->whereNotIn('public_id', $keep)->delete();

        foreach ($popups as $data) {
            $popup = $this->ownRow(Popup::class, $site, (string) $data['public_id']) ?? new Popup;
            $formId = is_string($data['form'] ?? null)
                ? $site->forms()->where('public_id', $data['form'])->value('id')
                : null;

            $popup->forceFill([
                'public_id' => $data['public_id'],
                'site_id' => $site->id,
                'form_id' => $formId,
                'name' => $data['name'],
                'status' => (bool) $data['status'],
                'title' => $data['title'] ?? null,
                'text' => $data['text'] ?? null,
                'size' => $data['size'],
                'animation' => $data['animation'],
                'close_on_overlay' => (bool) $data['close_on_overlay'],
                'close_on_escape' => (bool) $data['close_on_escape'],
                'show_close_button' => (bool) $data['show_close_button'],
                'mobile_fullscreen' => (bool) $data['mobile_fullscreen'],
            ])->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $vehicles
     */
    private function restoreVehicles(Site $site, array $vehicles): void
    {
        $keep = array_map(fn (array $vehicle): string => (string) $vehicle['public_id'], $vehicles);
        $site->vehicles()->whereNotIn('public_id', $keep)->get()->each(fn (SiteVehicle $vehicle) => $vehicle->delete());

        foreach ($vehicles as $data) {
            $vehicle = $this->ownRow(SiteVehicle::class, $site, (string) $data['public_id']);

            if ($vehicle === null) {
                if ($this->catalog->series((string) $data['catalog_series_public_id']) === null) {
                    $this->skippedVehicles++;
                    $this->skippedOffers += count(self::list($data['offers'] ?? []));

                    continue;
                }

                $vehicle = new SiteVehicle;
                $vehicle->forceFill([
                    'public_id' => $data['public_id'],
                    'site_id' => $site->id,
                    'catalog_series_public_id' => $data['catalog_series_public_id'],
                ]);
            }

            $vehicle->forceFill(['status' => (bool) $data['status'], 'sort_order' => (int) $data['sort_order']]);

            // Snapshots from before P8-005 have no custom text keys; the current values are kept then.
            foreach (['custom_name', 'custom_description'] as $key) {
                if (array_key_exists($key, $data)) {
                    $vehicle->setAttribute($key, is_string($data[$key]) ? $data[$key] : null);
                }
            }

            $vehicle->save();

            $available = SeriesMediaSet::query()
                ->where('catalog_series_public_id', $vehicle->catalog_series_public_id)
                ->active()
                ->whereIn('public_id', self::strings($data['media_sets'] ?? []))
                ->pluck('public_id')
                ->all();
            $vehicle->selectMediaSets(array_values(array_filter(self::strings($data['media_sets'] ?? []), fn (string $id): bool => in_array($id, $available, true))));

            $this->restoreOffers($vehicle, self::list($data['offers'] ?? []));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    private function restoreOffers(SiteVehicle $vehicle, array $offers): void
    {
        $keep = array_map(fn (array $offer): string => (string) $offer['public_id'], $offers);
        $vehicle->offers()->whereNotIn('public_id', $keep)->get()->each(fn (SiteOffer $offer) => $offer->delete());

        foreach ($offers as $data) {
            $offer = SiteOffer::query()->where('public_id', $data['public_id'])->first();

            if ($offer !== null && $offer->site_vehicle_id !== $vehicle->id) {
                if ($offer->vehicle->site_id !== $vehicle->site_id) {
                    throw new RestoreFailed('Версия ссылается на данные другого сайта.');
                }

                $offer->delete();
                $offer = null;
            }

            if ($offer === null) {
                $equipment = $this->catalog->equipment((string) $data['catalog_equipment_public_id']);

                if ($equipment === null || ! $this->catalog->equipmentBelongsToSeries($equipment, $vehicle->catalog_series_public_id)) {
                    $this->skippedOffers++;

                    continue;
                }

                $offer = new SiteOffer;
                $offer->forceFill(['public_id' => $data['public_id'], 'site_vehicle_id' => $vehicle->id]);
                $offer->setRelation('vehicle', $vehicle);
            }

            $offer->forceFill([
                'catalog_equipment_public_id' => $data['catalog_equipment_public_id'],
                'price_minor' => (int) $data['price_minor'],
                'rrp_minor' => isset($data['rrp_minor']) ? (int) $data['rrp_minor'] : null,
                'currency' => $data['currency'],
                'availability' => $data['availability'] ?? null,
                'badge' => $data['badge'] ?? null,
                'status' => (bool) $data['status'],
                'sort_order' => (int) $data['sort_order'],
            ])->save();

            $offer->benefits()->delete();

            foreach (self::list($data['benefits'] ?? []) as $position => $benefit) {
                $offer->benefits()->create([
                    'type' => $benefit['type'],
                    'amount_minor' => (int) $benefit['amount_minor'],
                    'label' => $benefit['label'] ?? null,
                    'sort_order' => $position,
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    private function restorePages(Site $site, array $pages): void
    {
        $keepPages = array_map(fn (array $page): string => (string) $page['public_id'], $pages);
        $targets = [];

        foreach ($pages as $page) {
            foreach (self::list($page['blocks'] ?? []) as $block) {
                $targets[(string) $block['public_id']] = [
                    'page' => (string) $page['public_id'],
                    'version' => $this->blockVersion((string) $block['definition'], (string) $block['version']),
                ];
            }
        }

        // Blocks that leave the Draft or would change their (immutable) Page or Block Version go first.
        BlockInstance::query()
            ->whereIn('page_id', $site->pages()->select('id'))
            ->with('page')
            ->get()
            ->each(function (BlockInstance $block) use ($targets): void {
                $target = $targets[$block->public_id] ?? null;

                if ($target === null || $target['page'] !== $block->page->public_id || $target['version']->id !== $block->block_version_id) {
                    $block->delete();
                }
            });

        $site->pages()->whereNotIn('public_id', $keepPages)->delete();

        // Free the per-Site slug and home slots before the snapshot values are written.
        foreach ($site->pages()->get() as $existing) {
            $existing->forceFill(['slug' => 'restore-'.$existing->id, 'is_home' => false])->save();
        }

        foreach ($pages as $data) {
            $page = $this->ownRow(Page::class, $site, (string) $data['public_id']) ?? new Page;
            $seo = self::map($data['seo'] ?? []);
            $page->forceFill([
                'public_id' => $data['public_id'],
                'site_id' => $site->id,
                'title' => $data['title'],
                'slug' => $data['slug'],
                'sort_order' => (int) $data['sort_order'],
                'is_home' => (bool) $data['is_home'],
                'seo_title' => $seo['title'] ?? null,
                'seo_description' => $seo['description'] ?? null,
                'seo_noindex' => (bool) ($seo['noindex'] ?? false),
            ])->save();

            foreach (self::list($data['blocks'] ?? []) as $blockData) {
                $block = BlockInstance::query()->where('public_id', $blockData['public_id'])->first();

                if ($block !== null && $block->page_id !== $page->id) {
                    throw new RestoreFailed('Версия ссылается на данные другого сайта.');
                }

                $block ??= new BlockInstance;
                $block->forceFill([
                    'public_id' => $blockData['public_id'],
                    'page_id' => $page->id,
                    'block_version_id' => $targets[(string) $blockData['public_id']]['version']->id,
                    'sort_order' => (int) $blockData['sort_order'],
                    'is_hidden' => (bool) $blockData['is_hidden'],
                    'state_json' => is_array($blockData['state'] ?? null) ? $blockData['state'] : [],
                ]);
                // The state passed full validation when this version was published; references that
                // went stale since are reported by the designer and block the next Publish.
                $block->saveQuietly();
            }
        }
    }

    private function blockVersion(string $definition, string $version): BlockVersion
    {
        $found = BlockVersion::query()
            ->where('version', $version)
            ->whereIn('block_definition_id', BlockDefinition::query()->inCatalog()->where('slug', $definition)->select('id'))
            ->first();

        if ($found === null) {
            throw new RestoreFailed('Один из блоков этой версии больше недоступен.');
        }

        return $found;
    }

    /**
     * The Site's own row with this public ID, or null when it does not exist yet. A public ID owned
     * by another Site aborts the restore.
     *
     * @template TModel of Page|Form|Popup|SiteVehicle
     *
     * @param  class-string<TModel>  $model
     * @return TModel|null
     */
    private function ownRow(string $model, Site $site, string $publicId): ?Model
    {
        $row = $model::query()->where('public_id', $publicId)->first();

        if (! $row instanceof $model) {
            return null;
        }

        if ($row->getAttribute('site_id') !== $site->id) {
            throw new RestoreFailed('Версия ссылается на данные другого сайта.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
