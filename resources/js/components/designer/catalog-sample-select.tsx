import { router } from '@inertiajs/react';
import { useId } from 'react';
import type {
    CatalogProps,
    CatalogVehicle,
    PageContext,
} from '@/blocks/catalog';

/**
 * «Показывать на примере»: a catalog template Page renders every mark, model or vehicle, so the
 * Designer shows it on one real vehicle of the Site.
 */
export function CatalogSampleSelect({
    catalog,
    context,
    href,
}: {
    catalog: CatalogProps;
    context: PageContext;
    href: (sample: string) => string;
}) {
    const id = useId();

    if (context.role === 'index') {
        return null;
    }

    const options = sampleOptions(catalog.vehicles, context.role);
    const current =
        catalog.vehicles.find((vehicle) =>
            context.role === 'item'
                ? vehicle.public_id === context.vehicle
                : vehicle.mark.segment === context.mark &&
                  (context.role === 'mark' ||
                      vehicle.model.segment === context.model),
        ) ?? null;
    const value =
        options.find((option) =>
            current ? sameGroup(option.vehicle, current, context.role) : false,
        )?.vehicle.public_id ?? '';

    if (options.length === 0) {
        return (
            <p className="hidden truncate text-xs text-muted-foreground md:block">
                Добавьте автомобили на сайт, чтобы увидеть пример
            </p>
        );
    }

    return (
        <div className="hidden min-w-0 items-center gap-2 md:flex">
            <label
                htmlFor={id}
                className="shrink-0 text-xs text-muted-foreground"
            >
                Показывать на примере:
            </label>
            <select
                id={id}
                value={value}
                onChange={(event) => router.get(href(event.target.value))}
                className="h-8 max-w-56 min-w-0 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                {options.map((option) => (
                    <option
                        key={option.vehicle.public_id}
                        value={option.vehicle.public_id}
                    >
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function sameGroup(
    a: CatalogVehicle,
    b: CatalogVehicle,
    role: PageContext['role'],
): boolean {
    switch (role) {
        case 'mark':
            return a.mark.segment === b.mark.segment;
        case 'model':
            return (
                a.mark.segment === b.mark.segment &&
                a.model.segment === b.model.segment
            );
        default:
            return a.public_id === b.public_id;
    }
}

/** One option per mark, per model or per vehicle, depending on the template Page. */
function sampleOptions(
    vehicles: CatalogVehicle[],
    role: PageContext['role'],
): { vehicle: CatalogVehicle; label: string }[] {
    const options: { vehicle: CatalogVehicle; label: string }[] = [];

    for (const vehicle of vehicles) {
        if (
            options.some((option) => sameGroup(option.vehicle, vehicle, role))
        ) {
            continue;
        }

        options.push({
            vehicle,
            label:
                role === 'mark'
                    ? vehicle.mark.name
                    : role === 'model'
                      ? `${vehicle.mark.name} ${vehicle.model.name}`
                      : vehicle.title,
        });
    }

    return options;
}
