<?php

namespace App\Templates;

use App\Blocks\BlockSourceChecker;
use App\Blocks\BlockStateValidator;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Publishes a Template Draft as an immutable Template Version (P9-007). Automated checks are the
 * only gate, as for Blocks (D-120): no review queue. The snapshot pins every Block Version.
 */
final class TemplatePublisher
{
    public function __construct(
        private TemplateAuthoringAuthorization $authorization,
        private TemplateAuthoring $authoring,
        private BlockStateValidator $states,
        private BlockSourceChecker $sources,
    ) {}

    /**
     * Russian reasons why the Draft cannot be published; empty when it passes.
     *
     * @return list<string>
     */
    public function checks(Template $template): array
    {
        $issues = [];

        if ($template->siteTypes() === []) {
            $issues[] = 'Выберите хотя бы один тип сайта в настройках шаблона.';
        }

        $pages = $template->pages()->with('blocks.version.definition')->get();

        if ($pages->every(fn (TemplatePage $page): bool => $page->blocks->isEmpty())) {
            $issues[] = 'Добавьте на страницы шаблона хотя бы один блок.';
        }

        foreach ($pages as $page) {
            foreach ($page->blocks as $block) {
                $definition = $block->version->definition;

                if ($definition->isWorkspacePrivate()) {
                    $issues[] = "Блок «{$definition->name}» на странице «{$page->title}» недоступен в каталоге.";

                    continue;
                }

                if ($this->states->errors($block->version->schema_json, $block->state_json, new TemplateBlockReferences($page)) !== []) {
                    $issues[] = "Блок «{$definition->name}» на странице «{$page->title}» содержит недопустимые данные или ссылки.";
                }

                if ($this->sources->checkVersion($block->version) !== []) {
                    $issues[] = "Код блока «{$definition->name}» не проходит автоматические проверки.";
                }
            }
        }

        return array_values(array_unique($issues));
    }

    public function publish(User $actor, Template $template): TemplateVersion
    {
        if (! $this->authorization->canEdit($actor, $template)) {
            throw new AuthorizationException;
        }

        $version = DB::transaction(function () use ($actor, $template): TemplateVersion {
            // Serializes publishing per Template so version numbers never collide.
            Template::query()->whereKey($template->id)->lockForUpdate()->first();

            $issues = $this->checks($template);

            if ($issues !== []) {
                throw ValidationException::withMessages(['template' => $issues]);
            }

            $content = $this->snapshot($template);
            $latest = $template->versions()->latest('id')->first();

            if ($latest !== null && self::canonical($latest->content_json) === self::canonical($content)) {
                throw ValidationException::withMessages(['template' => "Изменений с версии {$latest->version} нет."]);
            }

            $version = new TemplateVersion(['version' => $this->nextVersion($latest)]);
            $version->content_json = $content;
            $version->template()->associate($template);
            $version->publisher()->associate($actor);
            $version->save();

            return $version;
        });

        $this->authoring->log('template_published', $actor, $template, ['version' => $version->version]);

        return $version;
    }

    /**
     * @return array{pages: list<array{key: string, title: string, slug: string, is_home: bool, blocks: list<array{key: string, block_version_id: int, is_hidden: bool, state: array<string, mixed>}>}>}
     */
    private function snapshot(Template $template): array
    {
        return [
            'pages' => array_values($template->pages()->with('blocks')->get()
                ->map(fn (TemplatePage $page): array => [
                    'key' => $page->public_id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'is_home' => $page->is_home,
                    'blocks' => array_values($page->blocks
                        ->map(fn (TemplateBlock $block): array => [
                            'key' => $block->public_id,
                            'block_version_id' => $block->block_version_id,
                            'is_hidden' => $block->is_hidden,
                            'state' => $block->state_json,
                        ])
                        ->all()),
                ])
                ->all()),
        ];
    }

    /**
     * JSON columns may reorder object keys (MySQL), so snapshots compare in canonical key order.
     */
    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function nextVersion(?TemplateVersion $latest): string
    {
        if ($latest === null) {
            return '1.0.0';
        }

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $latest->version, $parts) !== 1) {
            throw new LogicException("Template Version {$latest->version} is not a semantic version.");
        }

        return $parts[1].'.'.((int) $parts[2] + 1).'.0';
    }
}
