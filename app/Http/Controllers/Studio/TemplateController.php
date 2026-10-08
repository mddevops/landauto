<?php

namespace App\Http\Controllers\Studio;

use App\Enums\CatalogAccessMode;
use App\Enums\Entitlement;
use App\Http\Controllers\Concerns\ResolvesEditableTemplates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blocks\UpdateBlockAccessRequest;
use App\Http\Requests\Templates\UpdateTemplateRequest;
use App\Support\Money;
use App\Templates\TemplateAuthoring;
use App\Templates\TemplatePresenter;
use App\Templates\TemplatePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Template settings, catalog access, automated checks and publishing (P9-007 / P9-015) for its
 * platform or Developer author.
 */
class TemplateController extends Controller
{
    use ResolvesEditableTemplates;

    public function show(Request $request, string $template, TemplatePresenter $presenter, TemplatePublisher $publisher): Response
    {
        $found = $this->editableTemplate($request, $template);

        return Inertia::render('studio/templates/show', [
            'template' => [
                'public_id' => $found->public_id,
                'name' => $found->name,
                'slug' => $found->slug,
                'owner_scope' => $found->owner_scope->value,
                'owner_label' => $found->owner_scope->label(),
                'site_types' => array_map(fn ($type): string => $type->value, $found->siteTypes()),
            ],
            'siteTypes' => $presenter->siteTypeOptions(),
            'checks' => $publisher->checks($found),
            'versions' => $presenter->versions($found),
            'access' => [
                'mode' => $found->access_mode->value,
                'entitlement' => $found->access_entitlement?->value,
                'site_price' => $found->site_price_minor !== null ? Money::toInput($found->site_price_minor, $found->price_currency ?? Money::DEFAULT_CURRENCY) : '',
                'workspace_price' => $found->workspace_price_minor !== null ? Money::toInput($found->workspace_price_minor, $found->price_currency ?? Money::DEFAULT_CURRENCY) : '',
            ],
            'accessModes' => CatalogAccessMode::options(),
            'accessEntitlements' => array_map(
                fn (Entitlement $entitlement): array => ['value' => $entitlement->value, 'label' => $entitlement->label()],
                Entitlement::catalogGates(),
            ),
        ]);
    }

    public function update(UpdateTemplateRequest $request, string $template, TemplateAuthoring $authoring): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $authoring->updateSettings($this->actor($request), $found, $request->string('name')->toString(), $request->siteTypes());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Настройки шаблона сохранены.']);

        return to_route('studio.templates.show', $found->public_id);
    }

    public function access(UpdateBlockAccessRequest $request, string $template, TemplateAuthoring $authoring): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $authoring->updateAccess($this->actor($request), $found, $request->mode(), $request->entitlement(), $request->sitePriceMinor(), $request->workspacePriceMinor());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Доступ в каталоге сохранён.']);

        return to_route('studio.templates.show', $found->public_id);
    }

    public function publish(Request $request, string $template, TemplatePublisher $publisher): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $version = $publisher->publish($this->actor($request), $found);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Опубликована версия {$version->version}."]);

        return to_route('studio.templates.show', $found->public_id);
    }
}
