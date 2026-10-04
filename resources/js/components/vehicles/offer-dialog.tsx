import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import type { Choice } from '@/components/platform/form-fields';
import {
    NativeSelect,
    SelectField,
    statusChoices,
    TextField,
} from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/sites/offers';

export type ModificationChoice = {
    public_id: string;
    name: string;
    summary: string;
    equipments: { public_id: string; name: string }[];
};

export type OfferBenefit = {
    type: string;
    amount: string;
    label: string | null;
};

export type Offer = {
    public_id: string;
    equipment: {
        public_id: string;
        name: string;
        modification: string;
        modification_name: string;
    } | null;
    price: string;
    price_label: string;
    rrp: string;
    availability: string | null;
    badge: string | null;
    status: boolean;
    sort_order: number;
    benefits: OfferBenefit[];
};

export type OfferChoices = { availability: Choice[]; benefitTypes: Choice[] };

type BenefitRow = { key: number; type: string; amount: string; label: string };

type OfferDialogProps = {
    sitePublicId: string;
    vehiclePublicId: string;
    modifications: ModificationChoice[];
    choices: OfferChoices;
    offer?: Offer;
    trigger: ReactNode;
};

const MAX_BENEFITS = 10;

let benefitKey = 0;

function benefitRows(benefits: OfferBenefit[]): BenefitRow[] {
    return benefits.map((benefit) => ({
        key: ++benefitKey,
        type: benefit.type,
        amount: benefit.amount,
        label: benefit.label ?? '',
    }));
}

export function OfferDialog({
    sitePublicId,
    vehiclePublicId,
    modifications,
    choices,
    offer,
    trigger,
}: OfferDialogProps) {
    const [open, setOpen] = useState(false);
    const prefix = `offer-${offer?.public_id ?? 'new'}`;
    const form = useForm({
        modification: offer?.equipment?.modification ?? '',
        equipment: offer?.equipment?.public_id ?? '',
        price: offer?.price ?? '',
        rrp: offer?.rrp ?? '',
        availability: offer?.availability ?? '',
        badge: offer?.badge ?? '',
        status: offer && !offer.status ? '0' : '1',
        sort_order: String(offer?.sort_order ?? 0),
        benefits: benefitRows(offer?.benefits ?? []),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const equipments =
        modifications.find(
            (modification) => modification.public_id === form.data.modification,
        )?.equipments ?? [];

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            equipment: data.equipment,
            price: data.price,
            rrp: data.rrp,
            availability: data.availability,
            badge: data.badge,
            status: data.status === '1',
            sort_order: data.sort_order,
            benefits: data.benefits.map((benefit) => ({
                type: benefit.type,
                amount: benefit.amount,
                label: benefit.label,
            })),
        }));
        form.submit(
            offer
                ? update({ site: sitePublicId, offer: offer.public_id })
                : store({ site: sitePublicId, vehicle: vehiclePublicId }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);

                    if (!offer) {
                        form.reset();
                    }
                },
            },
        );
    }

    function updateBenefit(key: number, patch: Partial<BenefitRow>) {
        form.setData(
            'benefits',
            form.data.benefits.map((benefit) =>
                benefit.key === key ? { ...benefit, ...patch } : benefit,
            ),
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {offer ? 'Изменить предложение' : 'Новое предложение'}
                    </DialogTitle>
                    <DialogDescription>
                        Модификация и комплектация берутся из каталога, цена и
                        выгоды — ваши.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <SelectField
                        id={`${prefix}-modification`}
                        label="Модификация"
                        choices={modifications.map((modification) => ({
                            value: modification.public_id,
                            label: modification.summary
                                ? `${modification.name} — ${modification.summary}`
                                : modification.name,
                        }))}
                        emptyLabel="Выберите модификацию"
                        value={form.data.modification}
                        onChange={(event) => {
                            form.setData((data) => ({
                                ...data,
                                modification: event.target.value,
                                equipment: '',
                            }));
                        }}
                        required
                    />
                    <SelectField
                        id={`${prefix}-equipment`}
                        label="Комплектация"
                        choices={equipments.map((equipment) => ({
                            value: equipment.public_id,
                            label: equipment.name,
                        }))}
                        emptyLabel={
                            form.data.modification
                                ? 'Выберите комплектацию'
                                : 'Сначала выберите модификацию'
                        }
                        value={form.data.equipment}
                        onChange={(event) =>
                            form.setData('equipment', event.target.value)
                        }
                        disabled={!form.data.modification}
                        required
                        error={errors.equipment}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id={`${prefix}-price`}
                            label="Цена, ₽"
                            inputMode="decimal"
                            autoComplete="off"
                            placeholder="1 850 000"
                            value={form.data.price}
                            onChange={(event) =>
                                form.setData('price', event.target.value)
                            }
                            required
                            error={errors.price}
                        />
                        <TextField
                            id={`${prefix}-rrp`}
                            label="Цена без скидки, ₽"
                            inputMode="decimal"
                            autoComplete="off"
                            hint="Необязательно"
                            value={form.data.rrp}
                            onChange={(event) =>
                                form.setData('rrp', event.target.value)
                            }
                            error={errors.rrp}
                        />
                        <SelectField
                            id={`${prefix}-availability`}
                            label="Наличие"
                            choices={choices.availability}
                            emptyLabel="Не указано"
                            value={form.data.availability}
                            onChange={(event) =>
                                form.setData('availability', event.target.value)
                            }
                            error={errors.availability}
                        />
                        <TextField
                            id={`${prefix}-badge`}
                            label="Метка"
                            maxLength={40}
                            autoComplete="off"
                            placeholder="Например, Хит продаж"
                            value={form.data.badge}
                            onChange={(event) =>
                                form.setData('badge', event.target.value)
                            }
                            error={errors.badge}
                        />
                        <SelectField
                            id={`${prefix}-status`}
                            label="Показ на сайте"
                            choices={statusChoices}
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
                            }
                            error={errors.status}
                        />
                        <TextField
                            id={`${prefix}-sort_order`}
                            label="Сортировка"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData('sort_order', event.target.value)
                            }
                            required
                            error={errors.sort_order}
                        />
                    </div>

                    <fieldset className="grid gap-3">
                        <legend className="mb-2 text-sm font-medium">
                            Выгоды
                        </legend>
                        {form.data.benefits.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Выгоды не добавлены.
                            </p>
                        )}
                        {form.data.benefits.map((benefit, index) => (
                            <div
                                key={benefit.key}
                                className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_8rem_auto] sm:items-end"
                            >
                                <div className="grid gap-1.5">
                                    <Label
                                        htmlFor={`${prefix}-benefit-${benefit.key}-type`}
                                    >
                                        Тип выгоды
                                    </Label>
                                    <NativeSelect
                                        id={`${prefix}-benefit-${benefit.key}-type`}
                                        value={benefit.type}
                                        onChange={(event) =>
                                            updateBenefit(benefit.key, {
                                                type: event.target.value,
                                            })
                                        }
                                    >
                                        {choices.benefitTypes.map((choice) => (
                                            <option
                                                key={choice.value}
                                                value={choice.value}
                                            >
                                                {choice.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                                <TextField
                                    id={`${prefix}-benefit-${benefit.key}-amount`}
                                    label="Сумма, ₽"
                                    inputMode="decimal"
                                    autoComplete="off"
                                    value={benefit.amount}
                                    onChange={(event) =>
                                        updateBenefit(benefit.key, {
                                            amount: event.target.value,
                                        })
                                    }
                                    required
                                    error={errors[`benefits.${index}.amount`]}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Удалить выгоду ${index + 1}`}
                                    onClick={() =>
                                        form.setData(
                                            'benefits',
                                            form.data.benefits.filter(
                                                (row) =>
                                                    row.key !== benefit.key,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                                <div className="sm:col-span-3">
                                    <TextField
                                        id={`${prefix}-benefit-${benefit.key}-label`}
                                        label="Подпись"
                                        maxLength={100}
                                        autoComplete="off"
                                        placeholder="Необязательно"
                                        value={benefit.label}
                                        onChange={(event) =>
                                            updateBenefit(benefit.key, {
                                                label: event.target.value,
                                            })
                                        }
                                        error={
                                            errors[`benefits.${index}.label`]
                                        }
                                    />
                                </div>
                            </div>
                        ))}
                        <InputError message={errors.benefits} />
                        {form.data.benefits.length < MAX_BENEFITS && (
                            <div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        form.setData('benefits', [
                                            ...form.data.benefits,
                                            {
                                                key: ++benefitKey,
                                                type:
                                                    choices.benefitTypes[0]
                                                        ?.value ?? '',
                                                amount: '',
                                                label: '',
                                            },
                                        ])
                                    }
                                >
                                    <Plus aria-hidden="true" />
                                    Добавить выгоду
                                </Button>
                            </div>
                        )}
                    </fieldset>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Сохранить
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
