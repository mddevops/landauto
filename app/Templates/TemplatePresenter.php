<?php

namespace App\Templates;

use App\Enums\SiteType;
use App\Models\Template;
use App\Models\TemplateVersion;

/**
 * Explicit Inertia props for Template Builder pages: public IDs and labels only.
 */
final class TemplatePresenter
{
    /**
     * @return array{public_id: string, name: string, slug: string, site_types: list<string>, site_type_labels: list<string>, versions_count: int, latest_version: string|null, updated_at: string|null}
     */
    public function listItem(Template $template): array
    {
        return [
            'public_id' => $template->public_id,
            'name' => $template->name,
            'slug' => $template->slug,
            'site_types' => array_map(fn (SiteType $type): string => $type->value, $template->siteTypes()),
            'site_type_labels' => array_map(fn (SiteType $type): string => $type->label(), $template->siteTypes()),
            'versions_count' => (int) $template->getAttribute('versions_count'),
            'latest_version' => $template->latestVersion?->version,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function siteTypeOptions(): array
    {
        return array_map(fn (SiteType $type): array => ['value' => $type->value, 'label' => $type->label()], SiteType::cases());
    }

    /**
     * @return list<array{version: string, published_at: string|null}>
     */
    public function versions(Template $template): array
    {
        return array_values($template->versions()
            ->latest('id')
            ->get(['id', 'version', 'created_at'])
            ->map(fn (TemplateVersion $version): array => [
                'version' => $version->version,
                'published_at' => $version->created_at?->toIso8601String(),
            ])
            ->all());
    }
}
