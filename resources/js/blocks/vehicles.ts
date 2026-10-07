/** Display-ready automotive view models from the backend binding registry (P3-012). */
export type VehicleSpec = { label: string; value: string };

export type VehicleMediaImage = {
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

export type VehicleOptionAvailability = 'standard' | 'optional';

export type VehicleOffer = {
    public_id: string;
    modification: { name: string; summary: string; specs: VehicleSpec[] };
    equipment: { name: string };
    price_label: string;
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
    series: string;
    title: string;
    description: string | null;
    price_from_label: string | null;
    benefit_up_to_label: string | null;
    media: { source: 'site' | 'global' | null; sets: VehicleMediaSet[] };
    offers: VehicleOffer[];
};

export function vehicleFullTitle(vehicle: VehicleBinding): string {
    return `${vehicle.title} ${vehicle.generation} ${vehicle.series}`;
}
