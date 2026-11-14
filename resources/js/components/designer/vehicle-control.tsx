import type { SchemaField } from '@/blocks/schema';
import { useDesignerContext } from '@/components/designer/designer-context';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

export function VehicleControl({
    field,
    value,
    id,
    error,
    onChange,
    followsPage = false,
}: {
    field: SchemaField;
    value: unknown;
    id: string;
    error?: string;
    onChange: (value: string | null) => void;
    /** Top-level vehicle field, which follows the page on a catalog card template. */
    followsPage?: boolean;
}) {
    const { vehicles, pageVehicle } = useDesignerContext();

    if (followsPage && pageVehicle) {
        return (
            <div className="grid gap-1.5">
                <span className="text-sm font-medium">{field.label}</span>
                <p className="rounded-md border bg-muted/50 px-3 py-2 text-sm">
                    Автомобиль этой страницы
                </p>
                <p className="text-xs text-muted-foreground">
                    На карточке каждого автомобиля блок покажет его данные.
                </p>
            </div>
        );
    }

    const selected = typeof value === 'string' ? value : '';
    const missing =
        selected !== '' &&
        !vehicles.some((vehicle) => vehicle.public_id === selected);

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{field.label}</Label>
            <select
                id={id}
                value={selected}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                onChange={(event) => onChange(event.target.value || null)}
                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="">Не выбран</option>
                {missing && (
                    <option value={selected}>Скрытый автомобиль</option>
                )}
                {vehicles.map((vehicle) => (
                    <option key={vehicle.public_id} value={vehicle.public_id}>
                        {vehicle.title}
                    </option>
                ))}
            </select>
            {vehicles.length === 0 && (
                <p className="text-xs text-muted-foreground">
                    Добавьте автомобиль в разделе «Автомобили» сайта.
                </p>
            )}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}
