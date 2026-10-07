<?php

namespace App\Publishing;

use App\Automotive\VehicleBindings;
use App\Blocks\BlockReferenceInspector;
use App\Blocks\BlockReferenceResolver;
use App\Blocks\BlockSourceChecker;
use App\Blocks\BlockStateValidator;
use App\Enums\SiteStatus;
use App\Http\Requests\SavePageRequest;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Popup;
use App\Models\SeriesMediaImage;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Centralized Publish validation (P5-004). Reads the current Draft and reports what would make
 * the published Site broken. The Draft itself may stay incomplete; only Publish is blocked.
 * All lookups are scoped to the Site, so foreign Workspace data can never satisfy a reference.
 */
final class PublishValidator
{
    /** Inspector issue kind => stable Publish code. */
    private const REFERENCE_CODES = [
        'page' => 'stale_page_action',
        'block' => 'stale_scroll_action',
        'hidden_block' => 'hidden_scroll_target',
        'popup' => 'stale_popup_action',
        'popup_form' => 'popup_form_disabled',
        'asset' => 'missing_asset',
        'vehicle' => 'missing_vehicle',
    ];

    /** @var list<PublishIssue> */
    private array $errors = [];

    /** @var list<PublishIssue> */
    private array $warnings = [];

    public function __construct(
        private BlockStateValidator $states,
        private BlockSourceChecker $sources,
        private BlockReferenceInspector $references,
        private VehicleBindings $vehicles,
    ) {}

    public function validate(Site $site, ?User $actor = null): PublishValidation
    {
        $this->errors = [];
        $this->warnings = [];

        if ($actor !== null && ! Gate::forUser($actor)->allows('publish', $site)) {
            $this->error('publish_forbidden', 'У вас нет права публиковать этот сайт.');
        }

        if ($site->status !== SiteStatus::Active) {
            $this->error('site_archived', 'Архивный сайт нельзя опубликовать.');
        }

        if ($site->subdomain === null) {
            $this->error('subdomain_missing', 'Укажите адрес сайта на Landflow.');
        }

        $pages = $site->pages()->orderBy('sort_order')->orderBy('id')->with('blocks.version.definition')->get();
        $this->validatePages($pages);
        $referenced = $this->validateBlocks($pages);
        $this->validateReferences($site);
        $this->validateAssetFiles($site, $referenced['assets']);
        $this->validateVehicles($site, $referenced['vehicles']);
        $this->validatePopups($site);

        return new PublishValidation($this->errors, $this->warnings);
    }

    /**
     * @param  Collection<int, Page>  $pages
     */
    private function validatePages(Collection $pages): void
    {
        if ($pages->where('is_home', true)->count() !== 1) {
            $this->error('home_page_missing', 'На сайте нет главной страницы.');
        }

        $seen = [];

        foreach ($pages as $page) {
            if (trim($page->title) === '') {
                $this->error('page_title_missing', 'У страницы нет названия.', page: $page->public_id);
            }

            if (preg_match(SavePageRequest::SLUG_PATTERN, $page->slug) !== 1 || mb_strlen($page->slug) > 100) {
                $this->error('page_slug_invalid', 'Адрес страницы может содержать только строчные латинские буквы, цифры и дефисы.', page: $page->public_id);
            } elseif (isset($seen[$page->slug])) {
                $this->error('page_slug_duplicate', 'Несколько страниц используют один и тот же адрес.', page: $page->public_id);
            }

            $seen[$page->slug] = true;
        }
    }

    /**
     * Pinned-schema state validation and completeness for every visible Block. Reference
     * existence is reported by the reference inspector, so it is not repeated here.
     *
     * @param  Collection<int, Page>  $pages
     * @return array{assets: array<string, true>, vehicles: array<string, true>}
     */
    private function validateBlocks(Collection $pages): array
    {
        $acceptAll = new class implements BlockReferenceResolver
        {
            public function existingAssets(array $ids): array
            {
                return $ids;
            }

            public function existingPages(array $ids): array
            {
                return $ids;
            }

            public function existingBlocks(array $ids): array
            {
                return $ids;
            }

            public function existingVehicles(array $ids): array
            {
                return $ids;
            }

            public function existingPopups(array $ids): array
            {
                return $ids;
            }
        };
        $referenced = ['assets' => [], 'vehicles' => []];

        foreach ($pages as $page) {
            foreach ($page->blocks->reject(fn (BlockInstance $block): bool => $block->is_hidden) as $block) {
                $version = $block->version;

                if (! $version->definition->isPlatformOwned()) {
                    $this->error('block_version_unavailable', 'Блок недоступен для публикации.', page: $page->public_id, block: $block->public_id);

                    continue;
                }

                // Studio code passed the checks when it was published; re-checked so a version that
                // fails today's rules never reaches a new Published Version.
                if ($this->sources->checkVersion($version) !== []) {
                    $this->error('block_source_rejected', 'Код блока не проходит автоматические проверки. Обратитесь к автору блока.', page: $page->public_id, block: $block->public_id);

                    continue;
                }

                foreach ($this->states->errors($version->schema_json, $block->state_json, $acceptAll) as $path => $message) {
                    $this->error('block_state_invalid', $message, page: $page->public_id, block: $block->public_id, path: $path);
                }

                foreach ($this->states->missing($version->schema_json, $block->state_json) as $path => $message) {
                    $this->error('block_required_missing', $message, page: $page->public_id, block: $block->public_id, path: $path);
                }

                $refs = $this->states->references($version->schema_json, $block->state_json);

                foreach ($refs['assets'] as $id) {
                    $referenced['assets'][$id] = true;
                }

                foreach ($refs['vehicles'] as $id) {
                    $referenced['vehicles'][$id] = true;
                }
            }
        }

        return $referenced;
    }

    private function validateReferences(Site $site): void
    {
        foreach ($this->references->inspectSite($site, visibleOnly: true) as $issue) {
            $code = self::REFERENCE_CODES[$issue['kind']];

            if ($issue['severity'] === 'error') {
                $this->error($code, $issue['message'], $issue['page'], $issue['block'], $issue['target'], $issue['path']);
            } else {
                $this->warning($code, $issue['message'], $issue['page'], $issue['block'], $issue['target'], $issue['path']);
            }
        }
    }

    /**
     * @param  array<string, true>  $ids
     */
    private function validateAssetFiles(Site $site, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $assets = SiteAsset::query()->where('site_id', $site->id)->whereIn('public_id', array_keys($ids))->get();

        foreach ($assets as $asset) {
            if (! Storage::disk(SiteAsset::DISK)->exists($asset->path)) {
                $this->error('asset_file_missing', 'Файл изображения не найден в хранилище.', target: $asset->public_id);
            }
        }
    }

    /**
     * Vehicles referenced by visible Blocks must be shown on the site; incomplete vehicles warn.
     *
     * @param  array<string, true>  $referenced
     */
    private function validateVehicles(Site $site, array $referenced): void
    {
        $bindings = [];

        foreach ($this->vehicles->forSite($site) as $binding) {
            $bindings[$binding['public_id']] = $binding;
        }

        $existing = $site->vehicles()->whereIn('public_id', array_keys($referenced))->pluck('public_id')->all();

        foreach ($existing as $publicId) {
            if (! isset($bindings[$publicId])) {
                $this->error('vehicle_unavailable', 'Автомобиль скрыт на сайте или недоступен в каталоге.', target: $publicId);
            }
        }

        $activeOffers = $site->vehicles()->where('status', true)
            ->withCount(['offers' => fn ($query) => $query->where('status', true)])
            ->pluck('offers_count', 'public_id');

        foreach ($bindings as $publicId => $binding) {
            if ($binding['offers'] === []) {
                $this->warning('vehicle_without_offers', 'У автомобиля нет доступных предложений с ценой.', target: $publicId);
            } elseif (count($binding['offers']) < (int) ($activeOffers[$publicId] ?? 0)) {
                $this->warning('offer_unavailable', 'Часть предложений автомобиля недоступна в каталоге и не будет показана.', target: $publicId);
            }

            if ($binding['media']['sets'] === []) {
                $this->warning('vehicle_without_media', 'У автомобиля нет фотографий.', target: $publicId);
            }
        }

        $this->validateMediaFiles($bindings);
    }

    /**
     * @param  array<string, array<string, mixed>>  $bindings
     */
    private function validateMediaFiles(array $bindings): void
    {
        $setIds = [];

        foreach ($bindings as $binding) {
            foreach ($binding['media']['sets'] as $set) {
                $setIds[$set['public_id']] = true;
            }
        }

        if ($setIds === []) {
            return;
        }

        $images = SeriesMediaImage::query()
            ->whereHas('set', fn ($query) => $query->whereIn('public_id', array_keys($setIds)))
            ->get();

        foreach ($images as $image) {
            if (! Storage::disk(SeriesMediaImage::DISK)->exists($image->path)) {
                $this->error('media_file_missing', 'Файл фотографии автомобиля не найден в хранилище.', target: $image->public_id);
            }
        }
    }

    /**
     * Every active Popup is published; its active Form must belong to the Site and have fields.
     */
    private function validatePopups(Site $site): void
    {
        $popups = $site->popups()->active()->with(['form' => fn ($query) => $query->withCount('fields')])->get();

        foreach ($popups as $popup) {
            /** @var Popup $popup */
            $form = $popup->form;

            if ($form === null) {
                continue;
            }

            if ($form->site_id !== $site->id) {
                $this->error('popup_form_foreign', 'Попап показывает форму другого сайта.', target: $popup->public_id);

                continue;
            }

            if ($form->status && (int) $form->getAttribute('fields_count') === 0) {
                $this->error('form_fields_missing', 'В форме попапа нет ни одного поля.', target: $form->public_id);
            }
        }
    }

    private function error(string $code, string $message, ?string $page = null, ?string $block = null, ?string $target = null, ?string $path = null): void
    {
        $this->errors[] = new PublishIssue($code, $message, $page, $block, $target, $path);
    }

    private function warning(string $code, string $message, ?string $page = null, ?string $block = null, ?string $target = null, ?string $path = null): void
    {
        $this->warnings[] = new PublishIssue($code, $message, $page, $block, $target, $path);
    }
}
