<?php

namespace App\Blocks;

use App\Exceptions\BlockDraftConflictException;
use App\Models\BlockDefinition;
use App\Models\BlockDraft;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Block Studio Draft (ADR-008): one editable set of sources per Block Definition. Saving (manual
 * or autosave) stores the Draft only, accepts an invalid schema so work is never lost, and never
 * creates or changes a Block Version.
 */
final class BlockStudio
{
    public const SOURCE_MAX_BYTES = 65536;

    /** Request / prop key => Draft column. */
    public const SOURCES = ['html' => 'html', 'css' => 'css', 'js' => 'js', 'schema' => 'schema_source'];

    private const STARTER_HTML = <<<'HTML'
        <section class="block">
          <h2>{{ title }}</h2>
        </section>
        HTML;

    private const STARTER_CSS = <<<'CSS'
        .block {
          padding: 48px 24px;
          text-align: center;
        }
        CSS;

    private const STARTER_SCHEMA = <<<'JSON'
        {
          "fields": [
            {
              "key": "title",
              "type": "text",
              "label": "Заголовок",
              "max_length": 120,
              "default": "Заголовок блока"
            }
          ]
        }
        JSON;

    public function __construct(private BlockAuthoringAuthorization $authorization) {}

    /**
     * The saved Draft, or an unsaved starter Draft (revision 0) until the first save.
     */
    public function draftFor(BlockDefinition $block): BlockDraft
    {
        $draft = $block->draft;

        if ($draft !== null) {
            return $draft;
        }

        $draft = new BlockDraft([
            'html' => self::STARTER_HTML,
            'css' => self::STARTER_CSS,
            'js' => '',
            'schema_source' => self::STARTER_SCHEMA,
        ]);
        $draft->revision = 0;

        return $draft;
    }

    /**
     * @param  array{html: string, css: string, js: string, schema: string}  $sources
     * @param  array<string, mixed>  $preview  synthetic Studio preview data (P9-005)
     *
     * @throws BlockDraftConflictException when `$revision` is not the latest saved revision
     */
    public function save(User $actor, BlockDefinition $block, array $sources, array $preview, int $revision): BlockDraft
    {
        if (! $this->authorization->canEdit($actor, $block)) {
            throw new AuthorizationException;
        }

        try {
            return DB::transaction(function () use ($actor, $block, $sources, $preview, $revision): BlockDraft {
                $draft = BlockDraft::query()
                    ->where('block_definition_id', $block->id)
                    ->lockForUpdate()
                    ->first();

                if (($draft->revision ?? 0) !== $revision) {
                    throw new BlockDraftConflictException;
                }

                $draft ??= $this->draftFor($block)->definition()->associate($block);

                foreach (self::SOURCES as $key => $column) {
                    $draft->setAttribute($column, $sources[$key]);
                }

                $draft->preview_data = $preview;

                $draft->revision = $revision + 1;
                $draft->lastEditor()->associate($actor);
                $draft->save();
                $block->setRelation('draft', $draft);

                return $draft;
            });
        } catch (UniqueConstraintViolationException) {
            // Two first saves raced; the other one won.
            throw new BlockDraftConflictException;
        }
    }

    /**
     * @return array{html: string, css: string, js: string, schema: string}
     */
    public static function sources(BlockDraft $draft): array
    {
        return [
            'html' => (string) $draft->html,
            'css' => (string) $draft->css,
            'js' => (string) $draft->js,
            'schema' => (string) $draft->schema_source,
        ];
    }
}
