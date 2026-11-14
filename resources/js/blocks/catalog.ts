/** Catalog section of a Site (ADR-011, D-131): template Pages render every mark or vehicle. */
export type SectionPageRole = 'index' | 'mark' | 'item';

export type CatalogVehicle = {
    public_id: string;
    mark: { name: string; segment: string };
    model: { name: string; segment: string };
    slug: string;
    title: string;
};

export type CatalogProps = {
    public_id: string;
    title: string;
    prefix: string;
    /** Landing vehicle card: addresses at the Site root, no catalog or mark pages. */
    root: boolean;
    enabled: boolean;
    pages: Partial<Record<SectionPageRole, string>>;
    vehicles: CatalogVehicle[];
};

/** What a template Page is currently showing: segment of its mark and the vehicle. */
export type PageContext = {
    role: SectionPageRole;
    mark: string | null;
    vehicle: string | null;
};

export type CatalogTarget =
    | { role: 'index' }
    | { role: 'mark'; mark: string }
    | { role: 'item'; vehicle: string };

export type CatalogRuntime = {
    title: string;
    enabled: boolean;
    vehicles: CatalogVehicle[];
    href: (target: CatalogTarget) => string | null;
};

/**
 * Address of a section page relative to the Site root: `catalog/kia/rio-iv-restyling`, or
 * `rio-iv-restyling` for the Landing vehicle card.
 */
export function catalogPath(
    catalog: CatalogProps,
    target: CatalogTarget,
): string | null {
    switch (target.role) {
        case 'index':
            return catalog.root ? null : catalog.prefix;
        case 'mark':
            return catalog.root ? null : `${catalog.prefix}/${target.mark}`;
        case 'item': {
            const vehicle = catalog.vehicles.find(
                (candidate) => candidate.public_id === target.vehicle,
            );

            if (!vehicle) {
                return null;
            }

            return catalog.root
                ? vehicle.slug
                : `${catalog.prefix}/${vehicle.mark.segment}/${vehicle.slug}`;
        }
    }
}

/** Address of what a template Page currently shows; null while it has no vehicle to show. */
export function contextCatalogPath(
    catalog: CatalogProps,
    context: PageContext,
): string | null {
    const { role, mark, vehicle } = context;

    if (role === 'index') {
        return catalogPath(catalog, { role });
    }

    if (role === 'mark' && mark) {
        return catalogPath(catalog, { role, mark });
    }

    return role === 'item' && vehicle
        ? catalogPath(catalog, { role, vehicle })
        : null;
}

/** First vehicle the target stands for; the Designer shows template Pages on its example. */
export function sampleVehicle(
    catalog: CatalogProps,
    target: CatalogTarget,
): CatalogVehicle | null {
    return (
        catalog.vehicles.find((vehicle) => {
            switch (target.role) {
                case 'index':
                    return true;
                case 'mark':
                    return vehicle.mark.segment === target.mark;
                case 'item':
                    return vehicle.public_id === target.vehicle;
            }
        }) ?? null
    );
}

export type CatalogGroup = {
    segment: string;
    name: string;
    vehicles: CatalogVehicle[];
};

/** Marks in Site order with their vehicles. */
export function groupVehicles(vehicles: CatalogVehicle[]): CatalogGroup[] {
    const groups = new Map<string, CatalogGroup>();

    for (const vehicle of vehicles) {
        const group = groups.get(vehicle.mark.segment) ?? {
            segment: vehicle.mark.segment,
            name: vehicle.mark.name,
            vehicles: [],
        };
        group.vehicles.push(vehicle);
        groups.set(vehicle.mark.segment, group);
    }

    return [...groups.values()];
}

export function runtimeCatalog(
    catalog: CatalogProps | null,
    href: (catalog: CatalogProps, target: CatalogTarget) => string | null,
): CatalogRuntime | null {
    return catalog
        ? {
              title: catalog.title,
              enabled: catalog.enabled,
              vehicles: catalog.vehicles,
              href: (target) => href(catalog, target),
          }
        : null;
}
