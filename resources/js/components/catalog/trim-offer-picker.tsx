import { useId } from 'react';
import { TrimsExplorer } from '@/components/catalog/trims-explorer';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    CharacteristicSection,
    OptionSection,
    Trim,
} from '@/types/catalog';

export type OfferPrices = Record<string, { price: string; rrp: string }>;

/**
 * Pick trims of a Series with optional prices (with and without discount). Errors use the
 * server keys `offers.{position}.price|rrp`, where position follows `selected`.
 */
export function TrimOfferPicker({
    trims,
    options,
    characteristics,
    selected,
    onSelectedChange,
    prices,
    onPricesChange,
    withPrices,
    errors,
}: {
    trims: Trim[];
    options: OptionSection[];
    characteristics?: CharacteristicSection[];
    selected: string[];
    onSelectedChange: (value: string[]) => void;
    prices: OfferPrices;
    onPricesChange: (value: OfferPrices) => void;
    withPrices: boolean;
    errors: Record<string, string | undefined>;
}) {
    const all = trims.flatMap((trim) =>
        trim.equipments.map((equipment) => equipment.key),
    );
    const allSelected = all.length > 0 && selected.length === all.length;

    return (
        <div className="flex flex-col gap-3">
            {all.length > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-muted-foreground">
                        Выбрано {selected.length} из {all.length}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onSelectedChange(allSelected ? [] : all)}
                    >
                        {allSelected ? 'Снять все' : 'Выбрать все'}
                    </Button>
                </div>
            )}
            <InputError message={errors.offers} />
            <TrimsExplorer
                trims={trims}
                options={options}
                characteristics={characteristics}
                renderEquipment={(equipment) => (
                    <OfferInputs
                        equipmentKey={equipment.key}
                        name={equipment.name}
                        selected={selected}
                        onSelectedChange={onSelectedChange}
                        prices={prices}
                        onPricesChange={onPricesChange}
                        withPrices={withPrices}
                        errors={errors}
                    />
                )}
            />
        </div>
    );
}

export function offerPayload(
    selected: string[],
    prices: OfferPrices,
    withPrices: boolean,
): { equipment: string; price: string; rrp: string }[] {
    return selected.map((equipment) => ({
        equipment,
        price: withPrices ? (prices[equipment]?.price ?? '') : '',
        rrp: withPrices ? (prices[equipment]?.rrp ?? '') : '',
    }));
}

function OfferInputs({
    equipmentKey,
    name,
    selected,
    onSelectedChange,
    prices,
    onPricesChange,
    withPrices,
    errors,
}: {
    equipmentKey: string;
    name: string;
    selected: string[];
    onSelectedChange: (value: string[]) => void;
    prices: OfferPrices;
    onPricesChange: (value: OfferPrices) => void;
    withPrices: boolean;
    errors: Record<string, string | undefined>;
}) {
    const id = useId();
    const index = selected.indexOf(equipmentKey);
    const checked = index !== -1;
    const value = prices[equipmentKey] ?? { price: '', rrp: '' };
    const priceError = checked ? errors[`offers.${index}.price`] : undefined;
    const rrpError = checked ? errors[`offers.${index}.rrp`] : undefined;
    const setPrice = (field: 'price' | 'rrp', next: string) =>
        onPricesChange({
            ...prices,
            [equipmentKey]: { ...value, [field]: next },
        });

    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
            {checked && withPrices && (
                <>
                    <div className="grid gap-1">
                        <Input
                            inputMode="numeric"
                            autoComplete="off"
                            placeholder="Цена со скидкой, ₽"
                            aria-label={`Цена со скидкой: ${name}`}
                            aria-invalid={Boolean(priceError)}
                            value={value.price}
                            onChange={(event) =>
                                setPrice('price', event.target.value)
                            }
                            className="h-8 sm:w-40"
                        />
                        <InputError message={priceError} />
                    </div>
                    <div className="grid gap-1">
                        <Input
                            inputMode="numeric"
                            autoComplete="off"
                            placeholder="Без скидки, ₽"
                            aria-label={`Цена без скидки: ${name}`}
                            aria-invalid={Boolean(rrpError)}
                            value={value.rrp}
                            onChange={(event) =>
                                setPrice('rrp', event.target.value)
                            }
                            className="h-8 sm:w-36"
                        />
                        <InputError message={rrpError} />
                    </div>
                </>
            )}
            <div className="flex h-8 items-center gap-2">
                <Checkbox
                    id={`${id}-pick`}
                    checked={checked}
                    aria-label={`Выбрать комплектацию ${name}`}
                    onCheckedChange={(on) =>
                        onSelectedChange(
                            on === true
                                ? [...selected, equipmentKey]
                                : selected.filter(
                                      (key) => key !== equipmentKey,
                                  ),
                        )
                    }
                />
                <Label htmlFor={`${id}-pick`} className="font-normal">
                    {checked ? 'Выбрана' : 'Выбрать'}
                </Label>
            </div>
        </div>
    );
}
