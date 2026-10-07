<?php

namespace App\Blocks;

use App\Enums\BlockRuntime;
use App\Exceptions\BlockPublishException;
use App\Models\BlockDefinition;
use App\Models\BlockDraft;
use App\Models\BlockVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Publishes the saved Block Draft as the next immutable `sandboxed` Block Version (D-120): the
 * automated checks run first and are the only gate — there is no review queue. Earlier versions
 * and the Block Instances pinned to them never change.
 */
final class BlockPublisher
{
    /** A sandboxed version can never take over an official renderer (ADR-008 §8). */
    public const OFFICIAL_RUNTIME = 'Этот блок отображается встроенным компонентом Landflow и не публикуется из студии.';

    public function __construct(
        private BlockAuthoringAuthorization $authorization,
        private BlockSourceChecker $checker,
    ) {}

    /**
     * @param  int  $revision  the Draft revision the author saw; a newer save must be reviewed first
     *
     * @throws BlockPublishException when the Draft cannot be published (Russian reason)
     */
    public function publish(User $actor, BlockDefinition $block, int $revision): BlockVersion
    {
        if (! $this->authorization->canEdit($actor, $block)) {
            throw new AuthorizationException;
        }

        $version = DB::transaction(function () use ($actor, $block, $revision): BlockVersion {
            // Serializes publishing per Block so version numbers never collide.
            BlockDefinition::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();

            $draft = BlockDraft::query()->where('block_definition_id', $block->id)->first();

            if ($draft === null) {
                throw new BlockPublishException('Сначала сохраните черновик блока.');
            }

            if ($draft->revision !== $revision) {
                throw new BlockPublishException('Черновик изменился после последней проверки. Обновите страницу и опубликуйте снова.');
            }

            if ($block->versions()->officialRuntime()->exists()) {
                throw new BlockPublishException(self::OFFICIAL_RUNTIME);
            }

            $sources = BlockStudio::sources($draft);
            $issues = $this->checker->check($sources);

            if ($issues !== []) {
                throw new BlockPublishException('Публикация остановлена: исправьте проблемы из панели «Проверки перед публикацией» ('.count($issues).').');
            }

            /** @var array{fields: list<array<string, mixed>>} $schema */
            $schema = json_decode($sources['schema'], true, 64, JSON_THROW_ON_ERROR);
            $latest = $block->versions()->latest('id')->first();

            if ($latest !== null && $this->sameContent($latest, $sources, $schema)) {
                throw new BlockPublishException("Изменений с версии {$latest->version} нет.");
            }

            $version = new BlockVersion(['version' => $this->nextVersion($latest, $schema), 'schema_json' => $schema]);
            $version->runtime = BlockRuntime::Sandboxed;
            $version->html = $sources['html'];
            $version->css = $sources['css'];
            $version->js = $sources['js'];
            $version->definition()->associate($block);
            $version->publisher()->associate($actor);
            $version->save();

            return $version;
        });

        $context = ['block' => $block->public_id, 'version' => $version->version];

        if ($block->isDeveloperOwned()) {
            $context['developer_profile'] = $block->developerProfile?->public_id;
        }

        $prefix = $block->isPlatformOwned() ? 'platform' : 'developer';
        Log::info("{$prefix}.block_published", [...$context, 'actor_user_id' => $actor->id]);

        return $version;
    }

    /**
     * Semantic version from the schema change: removing a field or changing its type is major,
     * adding fields is minor, anything else (sources, labels, limits) is a patch.
     *
     * @param  array{fields: list<array<string, mixed>>}  $schema
     */
    private function nextVersion(?BlockVersion $latest, array $schema): string
    {
        if ($latest === null) {
            return '1.0.0';
        }

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $latest->version, $parts) !== 1) {
            throw new LogicException("Block Version {$latest->version} is not a semantic version.");
        }

        [$major, $minor, $patch] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        /** @var list<array<string, mixed>> $previousFields */
        $previousFields = is_array($latest->schema_json['fields'] ?? null) ? $latest->schema_json['fields'] : [];
        $before = $this->fieldTypes($previousFields);
        $after = $this->fieldTypes($schema['fields']);

        foreach ($before as $path => $type) {
            if (($after[$path] ?? null) !== $type) {
                return ($major + 1).'.0.0';
            }
        }

        if (array_diff_key($after, $before) !== []) {
            return $major.'.'.($minor + 1).'.0';
        }

        return $major.'.'.$minor.'.'.($patch + 1);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, string> field path => type
     */
    private function fieldTypes(array $fields, string $prefix = ''): array
    {
        $types = [];

        foreach ($fields as $field) {
            $path = $prefix.$field['key'];
            $types[$path] = (string) $field['type'];

            if (is_array($field['fields'] ?? null)) {
                /** @var list<array<string, mixed>> $nested */
                $nested = $field['fields'];
                $types = [...$types, ...$this->fieldTypes($nested, $path.'.')];
            }
        }

        return $types;
    }

    /**
     * @param  array{html: string, css: string, js: string, schema: string}  $sources
     * @param  array<string, mixed>  $schema
     */
    private function sameContent(BlockVersion $latest, array $sources, array $schema): bool
    {
        return $latest->html === $sources['html']
            && $latest->css === $sources['css']
            && $latest->js === $sources['js']
            && self::canonical($latest->schema_json) === self::canonical($schema);
    }

    /** JSON columns may reorder object keys; compare schemas independent of key order. */
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
}
