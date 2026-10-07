<?php

namespace App\Http\Controllers\Platform;

use App\Blocks\BlockAuthoring;
use App\Blocks\BlockAuthoringPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blocks\StoreBlockDefinitionRequest;
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
 * «Блоки Landflow»: official platform-owned Block Definitions (`manage_platform_content`, D-117).
 * No Developer Profile is involved; Developer and Workspace-private Blocks are not reachable here.
 */
class PlatformBlockController extends Controller
{
    public function __construct(
        private BlockAuthoring $authoring,
        private BlockAuthoringPresenter $presenter,
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
        return Inertia::render('platform/blocks/create');
    }

    public function store(StoreBlockDefinitionRequest $request): RedirectResponse
    {
        try {
            $block = $this->authoring->createPlatform(
                $this->actor($request),
                $request->string('name')->toString(),
                $request->string('slug')->toString(),
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Этот slug уже используется другим блоком.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Официальный блок «{$block->name}» создан."]);

        return to_route('platform.blocks.show', $block);
    }

    public function show(string $block): Response
    {
        return Inertia::render('platform/blocks/show', [
            'block' => $this->presenter->detail($this->find($block)),
        ]);
    }

    public function update(UpdateBlockDefinitionRequest $request, string $block): RedirectResponse
    {
        $definition = $this->find($block);
        $this->authoring->updateMetadata($this->actor($request), $definition, $request->string('name')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Изменения сохранены.']);

        return to_route('platform.blocks.show', $definition);
    }

    private function find(string $publicId): BlockDefinition
    {
        return BlockDefinition::query()
            ->platformOwned()
            ->where('public_id', $publicId)
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
