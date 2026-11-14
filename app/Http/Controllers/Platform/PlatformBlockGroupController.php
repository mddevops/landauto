<?php

namespace App\Http\Controllers\Platform;

use App\Blocks\BlockAuthoringPresenter;
use App\Blocks\BlockGroups;
use App\Enums\BlockCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blocks\SaveBlockGroupRequest;
use App\Models\BlockDefinition;
use App\Models\BlockGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Блоки Landflow» groups (D-130): super admins create, rename, order and remove empty groups;
 * a group page lists its official Blocks and creates new ones inside it.
 */
class PlatformBlockGroupController extends Controller
{
    public function __construct(private BlockGroups $groups) {}

    public function store(SaveBlockGroupRequest $request): RedirectResponse
    {
        $group = $this->groups->create($request->string('name')->toString(), $request->kind());
        Log::info('platform.block_group_created', ['group' => $group->public_id, 'actor_user_id' => $request->user()?->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Группа «{$group->name}» создана."]);

        return to_route('platform.blocks.groups.show', $group);
    }

    public function show(string $group, BlockAuthoringPresenter $presenter): Response
    {
        $model = $this->find($group);

        return Inertia::render('platform/blocks/group', [
            'group' => [
                'public_id' => $model->public_id,
                'name' => $model->name,
                'kind' => $model->kind->value,
                'kind_label' => $model->kind->label(),
            ],
            'blocks' => $model->blocks()
                ->platformOwned()
                ->with('group')
                ->withCount('versions')
                ->withLatestRuntime()
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (BlockDefinition $block): array => $presenter->listItem($block))
                ->values()
                ->all(),
            'canDelete' => ! $model->blocks()->exists(),
            'kinds' => BlockCategory::options(),
        ]);
    }

    public function update(SaveBlockGroupRequest $request, string $group): RedirectResponse
    {
        $model = $this->find($group);
        $this->groups->update($model, $request->string('name')->toString(), $request->kind());
        Log::info('platform.block_group_updated', ['group' => $model->public_id, 'actor_user_id' => $request->user()?->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Группа сохранена.']);

        return to_route('platform.blocks.groups.show', $model);
    }

    public function move(Request $request, string $group): RedirectResponse
    {
        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $this->groups->move($this->find($group), $validated['direction'] === 'up' ? -1 : 1);

        return to_route('platform.blocks.index');
    }

    public function destroy(Request $request, string $group): RedirectResponse
    {
        $model = $this->find($group);

        if ($model->blocks()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Удалить можно только пустую группу.']);

            return to_route('platform.blocks.groups.show', $model);
        }

        $model->delete();
        Log::info('platform.block_group_deleted', ['group' => $model->public_id, 'actor_user_id' => $request->user()?->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Группа «{$model->name}» удалена."]);

        return to_route('platform.blocks.index');
    }

    private function find(string $publicId): BlockGroup
    {
        return BlockGroup::query()->where('public_id', $publicId)->firstOrFail();
    }
}
