<?php

namespace App\Integrations\Mapping;

use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use App\Models\Submission;
use App\Publishing\Runtime\PublicSiteResolver;
use LogicException;

/**
 * Everything a mapping may read for one Submission: its own field snapshot and trusted context,
 * its Site and the route's binding of that same Site. Nothing else is reachable.
 */
final readonly class MappingContext
{
    /**
     * @param  array<string, string|bool|null>  $fields
     * @param  array<string, array<string, mixed>>  $trusted
     * @param  array<string, string>  $visitor
     * @param  array<string, string>  $overrides
     */
    public function __construct(
        public array $fields,
        public array $trusted,
        public array $visitor,
        public array $overrides,
        public string $submissionPublicId,
        public string $submittedAt,
        public string $formPublicId,
        public string $formName,
        public string $sitePublicId,
        public string $siteName,
        public string $siteUrl,
    ) {}

    public static function for(Submission $submission, ?SiteIntegrationBinding $binding = null): self
    {
        $site = $submission->site;
        $form = $submission->form;

        if ($form->site_id !== $site->id || ($binding !== null && $binding->site_id !== $site->id)) {
            throw new LogicException('Mapping context must stay inside the Submission Site.');
        }

        $fields = [];

        foreach ($submission->payload as $field) {
            $fields[$field['key']] = $field['value'];
        }

        return new self(
            fields: $fields,
            trusted: $submission->context['trusted'] ?? [],
            visitor: $submission->context['visitor'] ?? [],
            overrides: $binding === null ? [] : array_map('strval', $binding->overrides_json),
            submissionPublicId: $submission->public_id,
            submittedAt: $submission->submitted_at->toIso8601String(),
            formPublicId: $form->public_id,
            formName: $form->name,
            sitePublicId: $site->public_id,
            siteName: $site->name,
            siteUrl: self::siteUrl($site),
        );
    }

    private static function siteUrl(Site $site): string
    {
        return (string) PublicSiteResolver::url($site, '/');
    }
}
