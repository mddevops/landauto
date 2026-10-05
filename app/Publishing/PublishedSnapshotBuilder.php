<?php

namespace App\Publishing;

use App\Automotive\VehicleBindings;
use App\Blocks\BlockStateValidator;
use App\Enums\Entitlement;
use App\Enums\PublishedAssetKind;
use App\Forms\SiteSecurityPolicy;
use App\Models\BlockInstance;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Page;
use App\Models\Popup;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteOfferBenefit;
use App\Models\SiteVehicle;
use App\Popups\PopupRuntime;
use App\Support\SiteDesignTokens;
use App\Support\WorkspaceEntitlements;
use Illuminate\Support\Facades\DB;

/**
 * Builds the two immutable representations of a Publish from the current Draft (ADR-006 §1).
 * The public manifest holds only display-ready, sanitized data; the draft snapshot holds the
 * editable state needed for restore. Neither contains Submissions, blacklists, CAPTCHA keys,
 * credentials, audit logs or numeric IDs. Same Draft → same manifest and hash.
 */
final class PublishedSnapshotBuilder
{
    public const SCHEMA = 1;

    public function __construct(
        private VehicleBindings $vehicles,
        private PopupRuntime $popups,
        private BlockStateValidator $states,
        private WorkspaceEntitlements $entitlements,
    ) {}

    public function build(Site $site): PublishedSnapshot
    {
        // One read transaction gives a consistent view of the Draft rows on the main database.
        return DB::transaction(function () use ($site): PublishedSnapshot {
            $site = Site::query()->with('workspace')->findOrFail($site->id);
            $pages = $site->pages()->orderBy('sort_order')->orderBy('id')->with('blocks.version.definition')->get();
            $vehicles = $this->vehicles->forPublication($site);
            $popups = $site->popups()->active()->with('form.fields')->orderBy('name')->orderBy('id')->get();
            $manifest = $this->manifest($site, array_values($pages->all()), $vehicles['vehicles'], $vehicles['offers'], array_values($popups->all()));

            return new PublishedSnapshot(
                $manifest,
                $this->draftSnapshot($site, array_values($pages->all())),
                self::hash($manifest),
                $this->assetReferences($manifest),
            );
        });
    }

    /**
     * SHA-256 of the canonical JSON (object keys sorted, list order kept).
     *
     * @param  array<string, mixed>  $manifest
     */
    public static function hash(array $manifest): string
    {
        return hash('sha256', json_encode(self::canonical($manifest), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }

    /**
     * @param  list<Page>  $pages
     * @param  list<array<string, mixed>>  $vehicles
     * @param  array<string, array{price_minor: int, currency: string}>  $money
     * @param  list<Popup>  $popups
     * @return array<string, mixed>
     */
    private function manifest(Site $site, array $pages, array $vehicles, array $money, array $popups): array
    {
        $assets = [];
        $manifestPages = [];

        foreach ($pages as $page) {
            $blocks = [];

            foreach ($page->blocks->reject(fn (BlockInstance $block): bool => $block->is_hidden) as $block) {
                foreach ($this->states->references($block->version->schema_json, $block->state_json)['assets'] as $assetId) {
                    $assets[$assetId] = true;
                }

                $blocks[] = [
                    'public_id' => $block->public_id,
                    'definition' => $block->version->definition->slug,
                    'version' => $block->version->version,
                    'state' => $block->state_json,
                ];
            }

            $manifestPages[] = [
                'public_id' => $page->public_id,
                'slug' => $page->slug,
                'is_home' => $page->is_home,
                'title' => $page->title,
                'sort_order' => $page->sort_order,
                'seo' => [
                    'title' => $page->seo_title ?? $page->title,
                    'description' => $page->seo_description,
                    'indexable' => ! $page->seo_noindex,
                ],
                'blocks' => $blocks,
            ];
        }

        $forms = [];
        $manifestPopups = [];

        foreach ($popups as $popup) {
            $presented = $this->popups->present($popup);
            $form = $presented['form'];

            if (is_array($form) && $popup->form !== null) {
                $forms[$popup->form->public_id] = $form;
                $presented['form'] = $popup->form->public_id;
            }

            $manifestPopups[] = $presented;
        }

        $assetIds = array_map('strval', array_keys($assets));
        sort($assetIds);
        ksort($forms);

        return [
            'schema' => self::SCHEMA,
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'branding' => ! $this->entitlements->allows($site->workspace, Entitlement::RemoveBranding),
            'design' => SiteDesignTokens::resolve($site->design_tokens),
            'captcha_required' => SiteSecurityPolicy::forSite($site)->captchaRequired,
            'pages' => $manifestPages,
            'assets' => $assetIds,
            'vehicles' => array_map($this->stripMediaUrls(...), $vehicles),
            'offer_prices' => $money,
            'popups' => $manifestPopups,
            'forms' => array_values($forms),
        ];
    }

    /**
     * Draft URLs are authenticated routes; published URLs are version-scoped and derived later.
     *
     * @param  array<string, mixed>  $vehicle
     * @return array<string, mixed>
     */
    private function stripMediaUrls(array $vehicle): array
    {
        foreach ($vehicle['media']['sets'] as $s => $set) {
            foreach ($set['images'] as $i => $image) {
                unset($vehicle['media']['sets'][$s]['images'][$i]['url']);
            }
        }

        return $vehicle;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array{kind: PublishedAssetKind, public_id: string}>
     */
    private function assetReferences(array $manifest): array
    {
        $references = [];

        foreach ($manifest['assets'] as $assetId) {
            $references[] = ['kind' => PublishedAssetKind::SiteAsset, 'public_id' => $assetId];
        }

        $images = [];

        foreach ($manifest['vehicles'] as $vehicle) {
            foreach ($vehicle['media']['sets'] as $set) {
                foreach ($set['images'] as $image) {
                    $images[$image['public_id']] = true;
                }
            }
        }

        foreach (array_keys($images) as $imageId) {
            $references[] = ['kind' => PublishedAssetKind::SeriesMediaImage, 'public_id' => (string) $imageId];
        }

        return $references;
    }

    /**
     * Editable Site state for restore-to-Draft (P5-010). Relationships use public IDs; live
     * operational state (form security, blacklists, Submissions) is deliberately excluded.
     *
     * @param  list<Page>  $pages
     * @return array<string, mixed>
     */
    private function draftSnapshot(Site $site, array $pages): array
    {
        $vehicles = $site->vehicles()->ordered()
            ->with(['offers' => fn ($query) => $query->ordered()->with('benefits'), 'mediaSets'])
            ->get();
        $forms = $site->forms()->with('fields')->orderBy('id')->get();
        $popups = $site->popups()->with('form')->orderBy('id')->get();

        return [
            'schema' => self::SCHEMA,
            'site' => ['name' => $site->name, 'design_tokens' => $site->design_tokens],
            'pages' => array_map(fn (Page $page): array => [
                'public_id' => $page->public_id,
                'title' => $page->title,
                'slug' => $page->slug,
                'sort_order' => $page->sort_order,
                'is_home' => $page->is_home,
                'seo' => ['title' => $page->seo_title, 'description' => $page->seo_description, 'noindex' => $page->seo_noindex],
                'blocks' => array_values($page->blocks->map(fn (BlockInstance $block): array => [
                    'public_id' => $block->public_id,
                    'definition' => $block->version->definition->slug,
                    'version' => $block->version->version,
                    'sort_order' => $block->sort_order,
                    'is_hidden' => $block->is_hidden,
                    'state' => $block->state_json,
                ])->all()),
            ], $pages),
            'vehicles' => array_values($vehicles->map(fn (SiteVehicle $vehicle): array => [
                'public_id' => $vehicle->public_id,
                'catalog_series_public_id' => $vehicle->catalog_series_public_id,
                'status' => $vehicle->status,
                'sort_order' => $vehicle->sort_order,
                'media_sets' => array_values($vehicle->mediaSets->map(fn (SeriesMediaSet $set): string => $set->public_id)->all()),
                'offers' => array_values($vehicle->offers->map(fn (SiteOffer $offer): array => [
                    'public_id' => $offer->public_id,
                    'catalog_equipment_public_id' => $offer->catalog_equipment_public_id,
                    'price_minor' => $offer->price_minor,
                    'rrp_minor' => $offer->rrp_minor,
                    'currency' => $offer->currency,
                    'availability' => $offer->availability?->value,
                    'badge' => $offer->badge,
                    'status' => $offer->status,
                    'sort_order' => $offer->sort_order,
                    'benefits' => array_values($offer->benefits->map(fn (SiteOfferBenefit $benefit): array => [
                        'type' => $benefit->type->value,
                        'amount_minor' => $benefit->amount_minor,
                        'label' => $benefit->label,
                    ])->all()),
                ])->all()),
            ])->all()),
            'forms' => array_values($forms->map(fn (Form $form): array => [
                'public_id' => $form->public_id,
                'name' => $form->name,
                'status' => $form->status,
                'submit_label' => $form->submit_label,
                'success_message' => $form->success_message,
                'fields' => array_values($form->fields->map(fn (FormField $field): array => [
                    'key' => $field->key,
                    'type' => $field->type->value,
                    'label' => $field->label,
                    'placeholder' => $field->placeholder,
                    'default_value' => $field->default_value,
                    'required' => $field->required,
                    'validation' => $field->validation,
                    'options' => $field->options,
                    'sort_order' => $field->sort_order,
                ])->all()),
            ])->all()),
            'popups' => array_values($popups->map(fn (Popup $popup): array => [
                'public_id' => $popup->public_id,
                'form' => $popup->form?->public_id,
                'name' => $popup->name,
                'status' => $popup->status,
                'title' => $popup->title,
                'text' => $popup->text,
                'size' => $popup->size->value,
                'animation' => $popup->animation->value,
                'close_on_overlay' => $popup->close_on_overlay,
                'close_on_escape' => $popup->close_on_escape,
                'show_close_button' => $popup->show_close_button,
                'mobile_fullscreen' => $popup->mobile_fullscreen,
            ])->all()),
        ];
    }
}
