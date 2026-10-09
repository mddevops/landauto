<?php

namespace App\Http\Controllers\Platform;

use App\Blocks\BlockAuthoring;
use App\Blocks\BlockAuthoringPresenter;
use App\Blocks\BlockPublisher;
use App\Blocks\BlockStudio;
use App\Enums\BlockCategory;
use App\Http\Controllers\Concerns\PublishesBlockVersions;
use App\Http\Controllers\Concerns\SavesBlockDrafts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blocks\PublishBlockRequest;
use App\Http\Requests\Blocks\SaveBlockDraftRequest;
use App\Http\Requests\Blocks\StoreBlockDefinitionRequest;
use App\Http\Requests\Blocks\UpdateBlockAccessRequest;
use App\Http\Requests\Blocks\UpdateBlockDefinitionRequest;
use App\Models\BlockDefinition;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Студия» → «Блоки Landflow»: official platform-owned Block Definitions (`manage_platform_content`,
 * D-117). No Developer Profile is involved; Developer and Workspace-private Blocks are not reachable here.
 */
class PlatformBlockController extends Controller
{
    use PublishesBlockVersions, SavesBlockDrafts;

    public function __construct(
        private BlockAuthoring $authoring,
        private BlockAuthoringPresenter $presenter,
        private BlockStudio $studio,
    ) {}

    public function index(): Response
    {
        return Inertia::render('platform/blocks/index', [
            'blocks' => BlockDefinition::query()
                ->platformOwned()
                ->withCount('versions')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (BlockDefinition $block): array => $this->presenter->listItem($block))
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/blocks/create', ['categories' => BlockCategory::options()]);
    }

    public function store(StoreBlockDefinitionRequest $request): RedirectResponse
    {
        try {
            $block = $this->authoring->createPlatform(
                $this->actor($request),
                $request->string('name')->toString(),
                $request->string('slug')->toString(),
                $request->category(),
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Этот slug уже используется другим блоком.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Официальный блок «{$block->name}» создан."]);

        return to_route('platform.blocks.show', $block);
    }

    public function show(Request $request, string $block): Response
    {
        return Inertia::render('platform/blocks/show', $this->presenter->studio($this->find($block), $this->actor($request)));
    }

    public function update(UpdateBlockDefinitionRequest $request, string $block): RedirectResponse
    {
        $definition = $this->find($block);
        $this->authoring->updateMetadata(
            $this->actor($request),
            $definition,
            $request->string('name')->toString(),
            $request->category(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Изменения сохранены.']);

        return to_route('platform.blocks.show', $definition);
    }

    public function access(UpdateBlockAccessRequest $request, string $block): RedirectResponse
    {
        $definition = $this->find($block);
        $this->authoring->updateAccess($this->actor($request), $definition, $request->mode(), $request->entitlement(), $request->sitePriceMinor(), $request->workspacePriceMinor());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Доступ в каталоге сохранён.']);

        return to_route('platform.blocks.show', $definition);
    }

    public function draft(SaveBlockDraftRequest $request, string $block): RedirectResponse
    {
        $definition = $this->find($block);
        $this->saveDraft($this->studio, $request, $this->actor($request), $definition);

        return to_route('platform.blocks.show', $definition);
    }

    public function publish(PublishBlockRequest $request, string $block, BlockPublisher $publisher): RedirectResponse
    {
        $definition = $this->find($block);
        $this->publishVersion($publisher, $request, $this->actor($request), $definition);

        return to_route('platform.blocks.show', $definition);
    }

    private function find(string $publicId): BlockDefinition
    {
        return BlockDefinition::query()
            ->platformOwned()
            ->where('public_id', $publicId)
            ->with('draft')
            ->withCount('versions')
            ->firstOrFail();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
