<?php

namespace App\Http\Controllers\Developer;

use App\Blocks\BlockAuthoring;
use App\Blocks\BlockAuthoringPresenter;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveDeveloperProfile;
use App\Http\Requests\Blocks\StoreBlockDefinitionRequest;
use App\Http\Requests\Blocks\UpdateBlockDefinitionRequest;
use App\Models\BlockDefinition;
use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Мои блоки»: Block Definitions owned by the current User's own active Developer Profile
 * (`create_blocks`, D-117, D-118). Another profile's Block is indistinguishable from a missing one.
 */
class DeveloperBlockController extends Controller
{
    public function __construct(
        private BlockAuthoring $authoring,
        private BlockAuthoringPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('developer/blocks/index', [
            'blocks' => BlockDefinition::query()
                ->ownedByDeveloper($this->profile($request))
                ->withCount('versions')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (BlockDefinition $block): array => $this->presenter->listItem($block))
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('developer/blocks/create');
    }

    public function store(StoreBlockDefinitionRequest $request): RedirectResponse
    {
        try {
            $block = $this->authoring->createDeveloper(
                $this->actor($request),
                $request->string('name')->toString(),
                $request->string('slug')->toString(),
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Этот slug уже используется другим блоком.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Блок «{$block->name}» создан."]);

        return to_route('developer.blocks.show', $block);
    }

    public function show(Request $request, string $block): Response
    {
        return Inertia::render('developer/blocks/show', [
            'block' => $this->presenter->detail($this->find($request, $block)),
        ]);
    }

    public function update(UpdateBlockDefinitionRequest $request, string $block): RedirectResponse
    {
        $definition = $this->find($request, $block);
        $this->authoring->updateMetadata($this->actor($request), $definition, $request->string('name')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Изменения сохранены.']);

        return to_route('developer.blocks.show', $definition);
    }

    private function find(Request $request, string $publicId): BlockDefinition
    {
        return BlockDefinition::query()
            ->ownedByDeveloper($this->profile($request))
            ->where('public_id', $publicId)
            ->with('developerProfile')
            ->withCount('versions')
            ->firstOrFail();
    }

    private function profile(Request $request): DeveloperProfile
    {
        $profile = $request->attributes->get(EnsureActiveDeveloperProfile::ATTRIBUTE);
        abort_unless($profile instanceof DeveloperProfile, 403);

        return $profile;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
