<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use App\Models\User;
use App\Templates\TemplateAuthoringAuthorization;
use Illuminate\Http\Request;

/**
 * Template Builder routes resolve only Templates the current User may author; any other Template,
 * Page or Block is a 404 so foreign content is not even confirmed to exist.
 */
trait ResolvesEditableTemplates
{
    protected function editableTemplate(Request $request, string $publicId): Template
    {
        $template = Template::query()->where('public_id', $publicId)->firstOrFail();
        abort_unless(app(TemplateAuthoringAuthorization::class)->canEdit($this->actor($request), $template), 404);

        return $template;
    }

    protected function templatePage(Template $template, string $publicId): TemplatePage
    {
        return TemplatePage::query()->where('template_id', $template->id)->where('public_id', $publicId)->firstOrFail();
    }

    protected function templateBlock(Template $template, string $publicId): TemplateBlock
    {
        return TemplateBlock::query()
            ->where('public_id', $publicId)
            ->whereHas('page', fn ($query) => $query->where('template_id', $template->id))
            ->with(['page', 'version.definition'])
            ->firstOrFail();
    }

    protected function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
