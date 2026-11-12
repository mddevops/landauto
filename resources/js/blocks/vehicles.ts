/** Display-ready automotive view models from the backend binding registry (P3-012, ADR-010). */
export type VehicleSpec = { label: string; value: string };

export type VehicleMediaImage = {
    public_id: string;
    angle: string;
    label: string;
    url: string;
    width: number;
    height: number;
};

export type VehicleMediaSet = {
    public_id: string;
    name: string;
    swatch_hex: string | null;
    images: VehicleMediaImage[];
};

export type VehicleGalleryImage = {
    public_id: string;
    url: string;
    width: number;
    height: number;
};

export type VehicleOptionAvailability = 'standard' | 'optional';

export type VehicleOffer = {
    public_id: string;
    modification: { name: string; summary: string; specs: VehicleSpec[] };
    equipment: { name: string };
    /** `null` means «Цена по запросу». */
    price_label: string | null;
    rrp_label: string | null;
    availability_label: string | null;
    badge: string | null;
    benefits: { label: string; amount_label: string }[];
    characteristics: { group: string; items: VehicleSpec[] }[];
    options: {
        group: string;
        items: { name: string; availability: VehicleOptionAvailability }[];
    }[];
};

export type VehicleBinding = {
    public_id: string;
    mark: string;
    model: string;
    generation: string;
    years?: string | null;
    series: string;
    title: string;
    description?: string;
    price_from_label: string | null;
    benefit_up_to_label: string | null;
    media: {
        source: 'site' | null;
        primary_angle?: string | null;
        sets: VehicleMediaSet[];
        gallery?: VehicleGalleryImage[];
    };
    offers: VehicleOffer[];
};

export const PRICE_ON_REQUEST = 'Цена по запросу';

export function vehicleFullTitle(vehicle: VehicleBinding): string {
    return `${vehicle.title} ${vehicle.generation} ${vehicle.series}`;
}

/** Image of the primary angle in a colour, else its first image. */
export function primaryImage(
    vehicle: VehicleBinding,
    set: VehicleMediaSet | undefined,
): VehicleMediaImage | undefined {
    if (!set) {
        return undefined;
    }

    return (
        set.images.find(
            (image) => image.angle === vehicle.media.primary_angle,
        ) ?? set.images[0]
    );
}
