import { Head } from '@inertiajs/react';
import { ListingEditor } from '@/components/marketplace/listing-editor';
import {
    index,
    publish,
    unpublish,
    update,
} from '@/routes/platform/marketplace';
import type { MarketplaceListingDetail } from '@/types/marketplace';

export default function PlatformMarketplaceListing({
    listing,
}: {
    listing: MarketplaceListingDetail;
}) {
    const id = listing.public_id;

    return (
        <>
            <Head title={`${listing.title} — Marketplace Landflow`} />
            <ListingEditor
                listing={listing}
                updateAction={update.form(id)}
                publishAction={publish.form(id)}
                unpublishAction={unpublish.form(id)}
                accessHint="Режим доступа и цены меняются в разделах «Блоки» и «Шаблоны», а не в карточке."
            />
        </>
    );
}

PlatformMarketplaceListing.layout = {
    breadcrumbs: [{ title: 'Marketplace Landflow', href: index() }],
};
