/** auto.ru catalog and customer vehicles (ADR-010): browser-safe props, no numeric IDs. */

export type AngleValue =
    | 'side_left'
    | 'front_3_4_left'
    | 'front'
    | 'front_3_4_right'
    | 'side_right';

export type AngleOption = { value: AngleValue; label: string };

export type CatalogMark = {
    url: string;
    name: string;
    name_ru: string | null;
    logo: string | null;
    logo_dark: string | null;
    popular: boolean;
};

export type CatalogModel = {
    url: string;
    name: string;
    image: string | null;
    years: string | null;
};

export type CatalogSeriesItem = {
    ref: string;
    name: string;
    image: string | null;
    colors_count?: number;
    gallery_count?: number;
};

export type CatalogGeneration = {
    key: string;
    name: string;
    years: string | null;
    series: CatalogSeriesItem[];
};

export type CatalogSeries = {
    ref: string;
    name: string;
    image: string | null;
    title: string;
    mark: { url: string; name: string; logo: string | null };
    model: { url: string; name: string };
    generation: { key: string; name: string; years: string | null };
};

export type Spec = { label: string; value: string };

export type TrimEquipment = {
    key: string;
    name: string;
    base: boolean;
    options_count: number;
};

export type Trim = {
    key: string;
    label: string;
    summary: string;
    specs: Spec[];
    equipments: TrimEquipment[];
};

export type CharacteristicSection = { group: string; items: Spec[] };

export type OptionSection = {
    group: string;
    items: { name: string; availability: 'standard' | 'optional' }[];
};

export type LibraryImage = {
    public_id: string;
    angle: AngleValue;
    url: string;
    width: number;
    height: number;
};

export type LibraryColor = {
    public_id: string;
    name: string;
    swatch_hex: string | null;
    status: boolean;
    sort_order: number;
    images: LibraryImage[];
};

export type LibraryGalleryImage = {
    public_id: string;
    url: string;
    width: number;
    height: number;
};

export type SeriesLibrary = {
    colors: LibraryColor[];
    gallery: LibraryGalleryImage[];
};

export type VehicleCard = {
    public_id: string;
    title: string;
    custom_name: string | null;
    mark: { url: string; name: string; logo: string | null } | null;
    model: { url: string; name: string } | null;
    generation: string | null;
    series: string | null;
    years: string | null;
    status: boolean;
    sort_order: number;
    cover: string | null;
    swatches: { name: string; hex: string | null }[];
    photos_count: number;
    offers_count: number;
    price_from: string | null;
    catalog_available: boolean;
    collections: string[];
};

export type VehicleColorPhoto = {
    public_id: string;
    angle: AngleValue;
    url: string;
};

export type VehicleColor = {
    public_id: string;
    name: string;
    swatch_hex: string | null;
    photos: VehicleColorPhoto[];
};

export type VehicleDetail = {
    public_id: string;
    title: string;
    custom_name: string | null;
    custom_description: string | null;
    status: boolean;
    sort_order: number;
    primary_angle: AngleValue | null;
    catalog: CatalogSeries | null;
    catalog_available: boolean;
    colors: VehicleColor[];
    gallery: { public_id: string; url: string }[];
};

export type EquipmentLabel = {
    equipment: string;
    name: string;
    modification: string;
    summary: string;
    available: boolean;
};

export type CatalogOffer = EquipmentLabel & {
    public_id: string;
    price: string;
    rrp: string;
    price_label: string | null;
};

export type VehicleCollection = {
    public_id: string;
    name: string;
    vehicles_count: number;
};

export type WizardProps = {
    marks: CatalogMark[];
    models: { mark: CatalogMark; items: CatalogModel[] } | null;
    generations: {
        model: { url: string; name: string };
        items: CatalogGeneration[];
    } | null;
    picked: {
        series: CatalogSeries;
        library: SeriesLibrary;
        trims: Trim[];
    } | null;
    options: OptionSection[];
    characteristics: CharacteristicSection[];
    angles: AngleOption[];
};
