import { Head } from '@inertiajs/react';
import { ListingIndex } from '@/components/marketplace/listing-index';
import {
    index,
    publish,
    show,
    store,
    unpublish,
} from '@/routes/platform/marketplace';
import type {
    MarketplaceListingItem,
    MarketplaceProductOption,
    MarketplaceProductTypeOption,
} from '@/types/marketplace';

export default function PlatformMarketplace({
    listings,
    products,
    productTypes,
}: {
    listings: MarketplaceListingItem[];
    products: MarketplaceProductOption[];
    productTypes: MarketplaceProductTypeOption[];
}) {
    return (
        <>
            <Head title="Marketplace Landflow" />
            <ListingIndex
                title="Marketplace Landflow"
                description="Карточки официальных блоков и шаблонов Landflow. Автор — Landflow; доступ и цены задаются в самом блоке или шаблоне."
                listings={listings}
                products={products}
                productTypes={productTypes}
                emptyProductsText={{
                    block: 'Нет официальных блоков без карточки.',
                    template: 'Нет официальных шаблонов без карточки.',
                }}
                storeAction={store.form()}
                showHref={(publicId) => show(publicId)}
                publishAction={(publicId) => publish.form(publicId)}
                unpublishAction={(publicId) => unpublish.form(publicId)}
            />
        </>
    );
}

PlatformMarketplace.layout = {
    breadcrumbs: [{ title: 'Marketplace Landflow', href: index() }],
};
