import { Head } from '@inertiajs/react';
import { ListingIndex } from '@/components/marketplace/listing-index';
import { dashboard } from '@/routes/developer';
import {
    index,
    publish,
    show,
    store,
    unpublish,
} from '@/routes/developer/marketplace';
import type {
    MarketplaceListingItem,
    MarketplaceProductOption,
    MarketplaceProductTypeOption,
} from '@/types/marketplace';

export default function DeveloperMarketplace({
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
            <Head title="Marketplace — Студия" />
            <ListingIndex
                title="Marketplace"
                description="Карточки ваших блоков и шаблонов. Публикуете вы сами: после автоматических проверок, без ручной проверки. Доступ и цены задаются в самом блоке или шаблоне."
                listings={listings}
                products={products}
                productTypes={productTypes}
                emptyProductsText={{
                    block: 'Нет ваших блоков без карточки.',
                    template: 'Нет ваших шаблонов без карточки.',
                }}
                storeAction={store.form()}
                showHref={(publicId) => show(publicId)}
                publishAction={(publicId) => publish.form(publicId)}
                unpublishAction={(publicId) => unpublish.form(publicId)}
            />
        </>
    );
}

DeveloperMarketplace.layout = {
    breadcrumbs: [
        { title: 'Студия', href: dashboard() },
        { title: 'Marketplace', href: index() },
    ],
};
