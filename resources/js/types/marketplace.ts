export type MarketplaceProductType = 'block' | 'template';

export type MarketplaceListingStatus = 'draft' | 'published';

export type MarketplaceAccessCard = {
    mode: string;
    restricted: boolean;
    label: string;
    detail: string | null;
};

export type MarketplaceListingItem = {
    public_id: string;
    title: string;
    slug: string;
    status: MarketplaceListingStatus;
    status_label: string;
    product_type: MarketplaceProductType;
    product_type_label: string;
    product_name: string;
    author: string;
    access: MarketplaceAccessCard | null;
    published_at: string | null;
};

export type MarketplaceListingDetail = MarketplaceListingItem & {
    description: string | null;
    product_slug: string;
    has_published_version: boolean;
    pricing: {
        site_price_minor: number | null;
        workspace_price_minor: number | null;
        currency: string | null;
    };
    publication_denial: string | null;
    publicly_visible: boolean;
};

export type MarketplaceProductOption = {
    product_type: MarketplaceProductType;
    public_id: string;
    name: string;
    has_published_version: boolean;
    access_label: string;
};

export type MarketplaceProductTypeOption = {
    value: MarketplaceProductType;
    label: string;
};
