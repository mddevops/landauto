/** Catalog section of a Site (ADR-011): template Pages render every mark, model or vehicle. */
export type SectionPageRole = 'index' | 'mark' | 'model' | 'item';

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
    enabled: boolean;
    pages: Partial<Record<SectionPageRole, string>>;
    vehicles: CatalogVehicle[];
};

/** What a template Page is currently showing: segments of its mark / model and the vehicle. */
export type PageContext = {
    role: SectionPageRole;
    mark: string | null;
    model: string | null;
    vehicle: string | null;
};

export type CatalogTarget =
    | { role: 'index' }
    | { role: 'mark'; mark: string }
    | { role: 'model'; mark: string; model: string }
    | { role: 'item'; vehicle: string };

export type CatalogRuntime = {
    title: string;
    enabled: boolean;
    vehicles: CatalogVehicle[];
    href: (target: CatalogTarget) => string | null;
};

/** Address of a section page relative to the Site root: `catalog/kia/rio/iv-restyling-sedan`. */
export function catalogPath(
    catalog: CatalogProps,
    target: CatalogTarget,
): string | null {
    switch (target.role) {
        case 'index':
            return catalog.prefix;
        case 'mark':
            return `${catalog.prefix}/${target.mark}`;
        case 'model':
            return `${catalog.prefix}/${target.mark}/${target.model}`;
        case 'item': {
            const vehicle = catalog.vehicles.find(
                (candidate) => candidate.public_id === target.vehicle,
            );

            return vehicle
                ? `${catalog.prefix}/${vehicle.mark.segment}/${vehicle.model.segment}/${vehicle.slug}`
                : null;
        }
    }
}

/** Address of what a template Page currently shows; null while it has no vehicle to show. */
export function contextCatalogPath(
    catalog: CatalogProps,
    context: PageContext,
): string | null {
    const { role, mark, model, vehicle } = context;

    if (role === 'index') {
        return catalogPath(catalog, { role });
    }

    if (role === 'mark' && mark) {
        return catalogPath(catalog, { role, mark });
    }

    if (role === 'model' && mark && model) {
        return catalogPath(catalog, { role, mark, model });
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
                case 'model':
                    return (
                        vehicle.mark.segment === target.mark &&
                        vehicle.model.segment === target.model
                    );
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

/** Marks (or models of one mark) in Site order with their vehicles. */
export function groupVehicles(
    vehicles: CatalogVehicle[],
    by: 'mark' | 'model',
): CatalogGroup[] {
    const groups = new Map<string, CatalogGroup>();

    for (const vehicle of vehicles) {
        const key = vehicle[by];
        const group = groups.get(key.segment) ?? {
            segment: key.segment,
            name: key.name,
            vehicles: [],
        };
        group.vehicles.push(vehicle);
        groups.set(key.segment, group);
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
