<?php

namespace App\Http\Controllers\Platform;

use App\Blocks\BlockCatalogAccess;
use App\Enums\CatalogAccessMode;
use App\Enums\SiteLicenseSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\GrantSiteLicenseRequest;
use App\Models\BlockDefinition;
use App\Models\Site;
use App\Models\SiteLicense;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Лицензии сайтов» (D-079): a Super Admin grants or revokes the right of one Site to use one
 * catalog item — a Block or a Template (which also covers its Blocks for that Site). Purchased
 * licenses need the billing integration (P10-005) and are not created here.
 */
class SiteLicenseController extends Controller
{
    private const LIST_LIMIT = 200;

    public function index(): Response
    {
        return Inertia::render('platform/licenses/index', [
            'licenses' => SiteLicense::query()
                ->with(['blockDefinition', 'template', 'site.workspace'])
                ->latest('id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(function (SiteLicense $license): array {
                    $item = $license->blockDefinition ?? $license->template;

                    return [
                        'public_id' => $license->public_id,
                        'item' => (string) $item?->name,
                        'kind' => $license->template_id !== null ? 'template' : 'block',
                        'access_label' => (string) $item?->access_mode->label(),
                        'site' => $license->site->name,
                        'subdomain' => $license->site->subdomain,
                        'workspace' => $license->site->workspace->name,
                        'source_label' => $license->source->label(),
                        'granted_at' => $license->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
            'items' => [
                ...BlockDefinition::query()
                    ->inCatalog()
                    ->whereHas('versions')
                    ->where('access_mode', '!=', CatalogAccessMode::Free->value)
                    ->with('developerProfile')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (BlockDefinition $block): array => [
                        'kind' => 'block',
                        'public_id' => $block->public_id,
                        'name' => $block->name,
                        'author' => $block->isPlatformOwned() ? 'Landflow' : (string) $block->developerProfile?->display_name,
                        'access' => BlockCatalogAccess::card($block),
                    ]),
                ...Template::query()
                    ->availableForSites()
                    ->where('access_mode', '!=', CatalogAccessMode::Free->value)
                    ->with('developerProfile')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Template $template): array => [
                        'kind' => 'template',
                        'public_id' => $template->public_id,
                        'name' => $template->name,
                        'author' => $template->isPlatformOwned() ? 'Landflow' : (string) $template->developerProfile?->display_name,
                        'access' => BlockCatalogAccess::card($template),
                    ]),
            ],
        ]);
    }

    public function store(GrantSiteLicenseRequest $request): RedirectResponse
    {
        [$field, $item] = $request->filled('template')
            ? ['template', Template::query()->availableForSites()->where('public_id', $request->string('template')->toString())->first()
                ?? throw ValidationException::withMessages(['template' => 'Выберите шаблон из каталога.'])]
            : ['block', BlockDefinition::query()->inCatalog()->whereHas('versions')->where('public_id', $request->string('block')->toString())->first()
                ?? throw ValidationException::withMessages(['block' => 'Выберите блок из каталога.'])];
        $noun = $item instanceof Template ? 'шаблон' : 'блок';

        if ($item->access_mode === CatalogAccessMode::Free) {
            throw ValidationException::withMessages([$field => $item instanceof Template ? 'Этот шаблон бесплатный — лицензия не нужна.' : 'Этот блок бесплатный — лицензия не нужна.']);
        }

        $address = mb_strtolower($request->string('site')->toString());
        $site = Site::query()->where('public_id', $address)->orWhere('subdomain', $address)->first()
            ?? throw ValidationException::withMessages(['site' => 'Сайт не найден. Укажите поддомен или ID сайта.']);

        $license = new SiteLicense;
        $license->source = SiteLicenseSource::AdminGrant;
        $license->site()->associate($site);
        $item instanceof Template ? $license->template()->associate($item) : $license->blockDefinition()->associate($item);
        $license->grantedBy()->associate($this->actor($request));

        try {
            $license->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['site' => "У этого сайта уже есть лицензия на этот {$noun}."]);
        }

        $this->log('platform.site_license_granted', $license, $item, $site, $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Лицензия на «{$item->name}» выдана сайту «{$site->name}»."]);

        return to_route('platform.licenses.index');
    }

    public function destroy(Request $request, string $license): RedirectResponse
    {
        $found = SiteLicense::query()->where('public_id', $license)->with(['blockDefinition', 'template', 'site'])->firstOrFail();
        $item = $found->blockDefinition ?? $found->template;
        $found->delete();

        if ($item !== null) {
            $this->log('platform.site_license_revoked', $found, $item, $found->site, $request);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Лицензия отозвана.']);

        return to_route('platform.licenses.index');
    }

    private function log(string $event, SiteLicense $license, BlockDefinition|Template $item, Site $site, Request $request): void
    {
        Log::info($event, [
            'license' => $license->public_id,
            $item instanceof Template ? 'template' : 'block' => $item->public_id,
            'site' => $site->public_id,
            'actor_user_id' => $this->actor($request)->id,
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
