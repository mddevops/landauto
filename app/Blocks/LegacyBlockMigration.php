<?php

namespace App\Blocks;

use App\Enums\BlockRuntime;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Site;
use App\Models\TemplateBlock;
use App\Templates\TemplateBlockReferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Legacy sandboxed Block Versions (ADR-009 §10, X-028). A legacy version is never changed or
 * converted: its Block gets a new approved Native version in Block Studio, and a Site or Template
 * Draft moves one Block Instance to that version only through the explicit upgrade below.
 * Published Versions are never touched; the next Publish of the Draft carries the upgrade.
 */
final class LegacyBlockMigration
{
    public function __construct(
        private BlockCatalogAccess $access,
        private BlockVersionGrants $grants,
        private BlockStateValidator $states,
        private BlockStateDefaults $defaults,
    ) {}

    public static function isLegacy(BlockVersion $version): bool
    {
        return $version->runtime === BlockRuntime::Sandboxed;
    }

    /**
     * @return array{target_version: string|null, available: bool, reason: string|null}
     */
    public static function props(LegacyBlockUpgrade $upgrade): array
    {
        return [
            'target_version' => $upgrade->target?->version,
            'available' => $upgrade->possible(),
            'reason' => $upgrade->reason,
        ];
    }

    /**
     * The newest approved Native version of the same Block published after the legacy one.
     */
    public function successor(BlockVersion $version): ?BlockVersion
    {
        if (! self::isLegacy($version)) {
            return null;
        }

        $successor = BlockVersion::query()
            ->where('block_definition_id', $version->block_definition_id)
            ->where('runtime', BlockRuntime::Native->value)
            ->where('id', '>', $version->id)
            ->latest('id')
            ->first();

        return $successor?->setRelation('definition', $version->definition);
    }

    /**
     * Site Drafts follow D-124 (Quiz / Chat keep the Template's Blocks) and D-122: the Site needs a
     * grant for the target version or today's catalog access to the Block.
     *
     * @param  array<int, true>|null  $licensed  preloaded {@see BlockCatalogAccess::licensedBlockIds()}
     * @param  array<int, true>|null  $granted  preloaded {@see BlockVersionGrants::grantedVersionIds()}
     */
    public function forSite(Site $site, BlockInstance $block, ?array $licensed = null, ?array $granted = null): LegacyBlockUpgrade
    {
        $version = $block->version;

        if (! self::isLegacy($version)) {
            return LegacyBlockUpgrade::denied('Блок уже использует актуальную версию.');
        }

        $target = $this->successor($version);

        if ($target === null) {
            return LegacyBlockUpgrade::denied('Нативная версия этого блока ещё не опубликована.');
        }

        if ($site->site_type->hasLockedStructure()) {
            return LegacyBlockUpgrade::denied('На сайтах этого типа блоки обновляются только вместе с новой версией шаблона.', $target);
        }

        $isGranted = $granted !== null ? isset($granted[$target->id]) : $this->grants->has($site, $target);
        $denial = $isGranted ? null : $this->access->denial($site, $version->definition, $licensed);

        if ($denial !== null) {
            return LegacyBlockUpgrade::denied($denial, $target);
        }

        return $this->migrateState($target, $block->state_json, new PageBlockReferences($block->page));
    }

    public function forTemplate(TemplateBlock $block): LegacyBlockUpgrade
    {
        $version = $block->version;

        if (! self::isLegacy($version)) {
            return LegacyBlockUpgrade::denied('Блок уже использует актуальную версию.');
        }

        $target = $this->successor($version);

        if ($target === null) {
            return LegacyBlockUpgrade::denied('Нативная версия этого блока ещё не опубликована.');
        }

        return $this->migrateState($target, $block->state_json, new TemplateBlockReferences($block->page));
    }

    /**
     * Pins the Native successor to one Site Block Instance and records the D-122 grant in the same
     * transaction, after the current access check passed.
     *
     * @throws ValidationException with the Russian reason when the upgrade is not possible
     */
    public function upgradeSiteBlock(Site $site, BlockInstance $block): BlockVersion
    {
        return DB::transaction(function () use ($site, $block): BlockVersion {
            $current = BlockInstance::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $current->setRelation('page', $block->page);
            $upgrade = $this->forSite($site, $current);

            if (! $upgrade->possible()) {
                throw ValidationException::withMessages(['block' => $upgrade->reason ?? 'Блок нельзя обновить.']);
            }

            $this->grants->grant($site, [$upgrade->target->id]);
            $this->pin($current, $upgrade);

            return $upgrade->target;
        });
    }

    /**
     * @throws ValidationException with the Russian reason when the upgrade is not possible
     */
    public function upgradeTemplateBlock(TemplateBlock $block): BlockVersion
    {
        return DB::transaction(function () use ($block): BlockVersion {
            $current = TemplateBlock::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $current->setRelation('page', $block->page);
            $upgrade = $this->forTemplate($current);

            if (! $upgrade->possible()) {
                throw ValidationException::withMessages(['block' => $upgrade->reason ?? 'Блок нельзя обновить.']);
            }

            $this->pin($current, $upgrade);

            return $upgrade->target;
        });
    }

    /**
     * New fields of the target start from their schema defaults; the existing values must fit the
     * target schema unchanged, otherwise the Block has to be replaced manually.
     *
     * @param  array<string, mixed>  $state
     */
    private function migrateState(BlockVersion $target, array $state, BlockReferenceResolver $references): LegacyBlockUpgrade
    {
        $migrated = array_replace($this->defaults->fromSchema($target->schema_json), $state);
        $errors = $this->states->errors($target->schema_json, $migrated, $references);

        if ($errors !== []) {
            return LegacyBlockUpgrade::denied("Данные блока не подходят к нативной версии {$target->version}: ".reset($errors), $target);
        }

        return LegacyBlockUpgrade::to($target, $migrated);
    }

    /**
     * The pinned-version guard of the models is bypassed only here: the state was validated
     * against the target schema by {@see migrateState()}.
     */
    private function pin(BlockInstance|TemplateBlock $block, LegacyBlockUpgrade $upgrade): void
    {
        $block->block_version_id = (int) $upgrade->target?->id;
        $block->state_json = $upgrade->state;
        $block->saveQuietly();
    }
}
