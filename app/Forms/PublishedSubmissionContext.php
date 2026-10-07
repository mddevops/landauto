<?php

namespace App\Forms;

use App\Blocks\OfficialBlockCatalog;
use App\Models\BlockDefinition;
use App\Models\PublishedVersion;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Trusted Submission context for a published page (ADR-006 §7). Every public-ID hint must exist
 * in the manifest of the Published Version the page was loaded from; vehicle, offer and the exact
 * price come from that manifest, never from the Draft or the browser.
 *
 * @phpstan-import-type Context from SubmissionContextResolver
 */
final class PublishedSubmissionContext
{
    public function __construct(private SubmissionContextResolver $drafts) {}

    /**
     * @return Context|null Null when a hint is malformed or not part of this version.
     */
    public function resolve(PublishedVersion $version, string $formPublicId, mixed $context, mixed $tracking): ?array
    {
        if (($context !== null && ! is_array($context)) || ($tracking !== null && ! is_array($tracking))) {
            return null;
        }

        $ids = [];

        foreach (SubmissionContextResolver::CONTEXT_KEYS as $key) {
            $value = $context[$key] ?? null;

            if ($value === null) {
                continue;
            }

            if (! is_string($value) || ! Str::isUlid($value)) {
                return null;
            }

            $ids[$key] = strtolower($value);
        }

        $trusted = $this->trusted($version, $formPublicId, $ids, $context[SubmissionContextResolver::ANSWERS_KEY] ?? null);

        return $trusted === null ? null : ['trusted' => $trusted, 'visitor' => $this->drafts->visitor($tracking ?? [])];
    }

    /**
     * @param  array<string, string>  $ids
     * @return array<string, array<string, mixed>>|null
     */
    private function trusted(PublishedVersion $version, string $formPublicId, array $ids, mixed $answers): ?array
    {
        $manifest = $version->public_manifest_json;
        $trusted = ['published_version' => ['public_id' => $version->public_id, 'version_number' => $version->version_number]];
        $page = null;

        if (isset($ids['page'])) {
            $page = self::find($manifest['pages'], $ids['page']);

            if ($page === null) {
                return null;
            }

            $trusted['page'] = ['public_id' => $page['public_id'], 'title' => $page['title']];
        }

        if (isset($ids['block'])) {
            $block = null;

            foreach ($page === null ? $manifest['pages'] : [$page] as $candidate) {
                $block ??= self::find($candidate['blocks'], $ids['block']);
            }

            if ($block === null) {
                return null;
            }

            $trusted['block'] = [
                'public_id' => $block['public_id'],
                'name' => BlockDefinition::query()->where('slug', $block['definition'])->value('name') ?? $block['definition'],
            ];

            if ($answers !== null) {
                $resolved = in_array($block['definition'], OfficialBlockCatalog::ANSWER_SLUGS, true)
                    ? QuizAnswers::resolve($block['state'] ?? null, $answers)
                    : null;

                if ($resolved === null) {
                    return null;
                }

                $trusted['answers'] = ['items' => $resolved];
            }
        } elseif ($answers !== null) {
            return null;
        }

        if (isset($ids['popup'])) {
            $popup = self::find($manifest['popups'], $ids['popup']);

            if ($popup === null || $popup['form'] !== $formPublicId) {
                return null;
            }

            $trusted['popup'] = ['public_id' => $popup['public_id'], 'name' => $popup['name']];
        }

        $vehicle = isset($ids['vehicle']) ? self::find($manifest['vehicles'], $ids['vehicle']) : null;

        if (isset($ids['vehicle']) && $vehicle === null) {
            return null;
        }

        $offer = null;

        if (isset($ids['offer'])) {
            foreach ($vehicle === null ? $manifest['vehicles'] : [$vehicle] as $candidate) {
                if (($found = self::find($candidate['offers'], $ids['offer'])) !== null) {
                    $offer = $found;
                    $vehicle = $candidate;
                }
            }

            $money = $manifest['offer_prices'][$ids['offer']] ?? null;

            if ($offer === null || ! is_array($money)) {
                return null;
            }

            $offer = [
                'public_id' => $offer['public_id'],
                'modification' => $offer['modification']['name'],
                'equipment' => $offer['equipment']['name'],
                'price_minor' => $money['price_minor'],
                'currency' => $money['currency'],
                'price_label' => Money::format($money['price_minor'], $money['currency']),
            ];
        }

        if ($vehicle !== null) {
            $trusted['vehicle'] = [
                'public_id' => $vehicle['public_id'],
                'mark' => $vehicle['mark'],
                'model' => $vehicle['model'],
                'generation' => $vehicle['generation'],
                'series' => $vehicle['series'],
                'title' => $vehicle['title'],
            ];
        }

        if ($offer !== null) {
            $trusted['offer'] = $offer;
        }

        if (isset($ids['media_set'])) {
            $set = $vehicle === null ? null : self::find($vehicle['media']['sets'], $ids['media_set']);

            if ($set === null) {
                return null;
            }

            $trusted['media_set'] = ['public_id' => $set['public_id'], 'name' => $set['name']];
        }

        return $trusted;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private static function find(array $items, string $publicId): ?array
    {
        foreach ($items as $item) {
            if (strtolower((string) $item['public_id']) === $publicId) {
                return $item;
            }
        }

        return null;
    }
}
