import { Head } from '@inertiajs/react';
import { ListingEditor } from '@/components/marketplace/listing-editor';
import { dashboard } from '@/routes/developer';
import {
    index,
    publish,
    unpublish,
    update,
} from '@/routes/developer/marketplace';
import type { MarketplaceListingDetail } from '@/types/marketplace';

export default function DeveloperMarketplaceListing({
    listing,
}: {
    listing: MarketplaceListingDetail;
}) {
    const id = listing.public_id;

    return (
        <>
            <Head title={`${listing.title} — Marketplace`} />
            <ListingEditor
                listing={listing}
                updateAction={update.form(id)}
                publishAction={publish.form(id)}
                unpublishAction={unpublish.form(id)}
                accessHint="Режим доступа и цены меняются в студии блока или шаблона, а не в карточке."
            />
        </>
    );
}

DeveloperMarketplaceListing.layout = {
    breadcrumbs: [
        { title: 'Студия', href: dashboard() },
        { title: 'Marketplace', href: index() },
    ],
};
