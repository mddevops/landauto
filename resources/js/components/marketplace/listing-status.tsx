import { Form } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { MarketplaceListingStatus } from '@/types/marketplace';
import type { RouteFormDefinition } from '@/wayfinder';

const dateFormat = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatListingDate(value: string | null): string {
    return value ? dateFormat.format(new Date(value)) : '—';
}

export function ListingStatusBadge({
    status,
    label,
}: {
    status: MarketplaceListingStatus;
    label: string;
}) {
    return (
        <Badge
            data-testid="listing-status"
            variant={status === 'published' ? 'default' : 'secondary'}
        >
            {label}
        </Badge>
    );
}

/** Publish when Draft, unpublish when published; the server re-checks everything. */
export function ListingPublicationAction({
    status,
    title,
    publishAction,
    unpublishAction,
    size = 'sm',
}: {
    status: MarketplaceListingStatus;
    title: string;
    publishAction: RouteFormDefinition<'post'>;
    unpublishAction: RouteFormDefinition<'post'>;
    size?: 'sm' | 'default';
}) {
    const published = status === 'published';
    const label = published ? 'Снять с публикации' : 'Опубликовать';

    return (
        <Form
            {...(published ? unpublishAction : publishAction)}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <Button
                    type="submit"
                    size={size}
                    variant={published ? 'outline' : 'default'}
                    disabled={processing}
                    aria-label={size === 'sm' ? `${label}: ${title}` : label}
                >
                    {processing && <Spinner />}
                    {label}
                </Button>
            )}
        </Form>
    );
}
