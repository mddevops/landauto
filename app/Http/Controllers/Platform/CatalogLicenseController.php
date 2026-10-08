<?php

namespace App\Http\Controllers\Platform;

use App\Blocks\BlockCatalogAccess;
use App\Enums\CatalogAccessMode;
use App\Enums\CatalogLicenseScope;
use App\Enums\CatalogLicenseSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\GrantCatalogLicenseRequest;
use App\Models\BlockDefinition;
use App\Models\CatalogLicense;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Лицензии каталога» (D-121): a Super Admin grants or revokes the right to use one catalog item —
 * a Block or a Template (which also covers the Blocks of the installed Template Version) — for one
 * Site or for one whole Workspace. Purchased licenses need the billing integration (P10-005) and
 * are not created here. Revoking never removes already installed Block Versions (D-122).
 */
class CatalogLicenseController extends Controller
{
    private const LIST_LIMIT = 200;

    public function index(): Response
    {
        return Inertia::render('platform/licenses/index', [
            'licenses' => CatalogLicense::query()
                ->with(['blockDefinition', 'template', 'site.workspace', 'workspace'])
                ->latest('id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(function (CatalogLicense $license): array {
                    $item = $license->blockDefinition ?? $license->template;
                    $site = $license->site;

                    return [
                        'public_id' => $license->public_id,
                        'item' => (string) $item?->name,
                        'kind' => $license->template_id !== null ? 'template' : 'block',
                        'access_label' => (string) $item?->access_mode->label(),
                        'scope' => $license->scope->value,
                        'scope_label' => $license->scope->label(),
                        'target' => $site !== null ? $site->name : (string) $license->workspace?->name,
                        'subdomain' => $site?->subdomain,
                        'workspace' => (string) ($site?->workspace->name ?? $license->workspace?->name),
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
            'scopes' => CatalogLicenseScope::options(),
        ]);
    }

    public function store(GrantCatalogLicenseRequest $request): RedirectResponse
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

        $scope = $request->scope();
        $address = mb_strtolower($request->string('target')->toString());
        $site = Site::query()->where('public_id', $address)->orWhere('subdomain', $address)->first();

        $license = new CatalogLicense;
        $license->scope = $scope;
        $license->source = CatalogLicenseSource::AdminGrant;

        if ($scope === CatalogLicenseScope::Site) {
            $target = $site ?? throw ValidationException::withMessages(['target' => 'Сайт не найден. Укажите поддомен или ID сайта.']);
            $license->site()->associate($target);
            $duplicate = "У этого сайта уже есть лицензия на этот {$noun}.";
        } else {
            $target = Workspace::query()->where('public_id', $address)->first()
                ?? $site->workspace
                ?? throw ValidationException::withMessages(['target' => 'Пространство не найдено. Укажите ID пространства или поддомен любого его сайта.']);
            $license->workspace()->associate($target);
            $duplicate = "У этого пространства уже есть лицензия на этот {$noun}.";
        }

        $item instanceof Template ? $license->template()->associate($item) : $license->blockDefinition()->associate($item);
        $license->grantedBy()->associate($this->actor($request));

        try {
            $license->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['target' => $duplicate]);
        }

        $this->log('platform.catalog_license_granted', $license, $item, $request);
        $recipient = $target instanceof Site ? "сайту «{$target->name}»" : "пространству «{$target->name}»";
        Inertia::flash('toast', ['type' => 'success', 'message' => "Лицензия на «{$item->name}» выдана {$recipient}."]);

        return to_route('platform.licenses.index');
    }

    public function destroy(Request $request, string $license): RedirectResponse
    {
        $found = CatalogLicense::query()->where('public_id', $license)->with(['blockDefinition', 'template', 'site', 'workspace'])->firstOrFail();
        $item = $found->blockDefinition ?? $found->template;
        $found->delete();

        if ($item !== null) {
            $this->log('platform.catalog_license_revoked', $found, $item, $request);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Лицензия отозвана.']);

        return to_route('platform.licenses.index');
    }

    private function log(string $event, CatalogLicense $license, BlockDefinition|Template $item, Request $request): void
    {
        Log::info($event, [
            'license' => $license->public_id,
            $item instanceof Template ? 'template' : 'block' => $item->public_id,
            'scope' => $license->scope->value,
            $license->scope === CatalogLicenseScope::Site ? 'site' : 'workspace' => $license->scope === CatalogLicenseScope::Site
                ? $license->site?->public_id
                : $license->workspace?->public_id,
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
