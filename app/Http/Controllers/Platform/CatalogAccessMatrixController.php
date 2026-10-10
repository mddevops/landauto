<?php

namespace App\Http\Controllers\Platform;

use App\Enums\CatalogAccessMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateCatalogPlanAccessRequest;
use App\Models\BlockDefinition;
use App\Models\Plan;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/** Internal Plan ↔ catalog availability matrix (X-026). */
class CatalogAccessMatrixController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()->orderBy('name')->get(['key', 'name', 'is_active']);

        $blocks = BlockDefinition::query()
            ->inCatalog()
            ->whereHas('versions')
            ->with(['plans', 'developerProfile'])
            ->orderBy('name')
            ->get()
            ->map(fn (BlockDefinition $block): array => [
                'kind' => 'block',
                'public_id' => $block->public_id,
                'name' => $block->name,
                'author' => $block->isPlatformOwned() ? 'Landflow' : (string) $block->developerProfile?->display_name,
                'access_mode' => $block->access_mode->value,
                'access_label' => $block->access_mode->label(),
                'plan_keys' => $block->plans->pluck('key')->values()->all(),
                'update_url' => route('platform.catalog-access.blocks.update', $block->public_id),
            ]);

        $templates = Template::query()
            ->availableForSites()
            ->with(['plans', 'developerProfile'])
            ->orderBy('name')
            ->get()
            ->map(fn (Template $template): array => [
                'kind' => 'template',
                'public_id' => $template->public_id,
                'name' => $template->name,
                'author' => $template->isPlatformOwned() ? 'Landflow' : (string) $template->developerProfile?->display_name,
                'access_mode' => $template->access_mode->value,
                'access_label' => $template->access_mode->label(),
                'plan_keys' => $template->plans->pluck('key')->values()->all(),
                'update_url' => route('platform.catalog-access.templates.update', $template->public_id),
            ]);

        return Inertia::render('platform/catalog-access/index', [
            'plans' => $plans->map(fn (Plan $plan): array => [
                'key' => $plan->key,
                'name' => $plan->name,
                'active' => $plan->is_active,
            ])->values()->all(),
            'items' => $blocks->concat($templates)->values()->all(),
        ]);
    }

    public function block(UpdateCatalogPlanAccessRequest $request, string $block): RedirectResponse
    {
        $item = BlockDefinition::query()
            ->inCatalog()
            ->whereHas('versions')
            ->where('public_id', $block)
            ->firstOrFail();

        $this->sync($item, $request->planKeys());
        Log::info('platform.catalog_plan_access_updated', [
            'kind' => 'block', 'item' => $item->public_id, 'plan_keys' => $request->planKeys(),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Доступ блока по тарифам сохранён.']);

        return to_route('platform.catalog-access.index');
    }

    public function template(UpdateCatalogPlanAccessRequest $request, string $template): RedirectResponse
    {
        $item = Template::query()
            ->availableForSites()
            ->where('public_id', $template)
            ->firstOrFail();

        $this->sync($item, $request->planKeys());
        Log::info('platform.catalog_plan_access_updated', [
            'kind' => 'template', 'item' => $item->public_id, 'plan_keys' => $request->planKeys(),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Доступ шаблона по тарифам сохранён.']);

        return to_route('platform.catalog-access.index');
    }

    /** @param list<string> $planKeys */
    private function sync(BlockDefinition|Template $item, array $planKeys): void
    {
        abort_unless($item->access_mode === CatalogAccessMode::Entitlement, 422, 'Назначить тарифы можно только для режима «По тарифу».');

        $planIds = Plan::query()->whereIn('key', $planKeys)->pluck('id')->all();

        DB::transaction(function () use ($item, $planIds): void {
            $item->plans()->sync($planIds);
        });
    }
}
