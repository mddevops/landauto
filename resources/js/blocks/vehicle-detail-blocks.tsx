import { Car, Check, ChevronDown, Expand, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useId, useRef, useState } from 'react';
import { Lightbox } from '@/blocks/lightbox';
import { ButtonPreview, Container } from '@/blocks/official-blocks';
import type { BlockRendererProps } from '@/blocks/state';
import { flag, group, text } from '@/blocks/state';
import { TriggerScope } from '@/blocks/trigger-context';
import {
    ColorSwatches,
    VehiclePlaceholder,
    cardImage,
    useVehicle,
} from '@/blocks/vehicle-blocks';
import type { VehicleBinding, VehicleOffer } from '@/blocks/vehicles';
import { vehicleFullTitle } from '@/blocks/vehicles';
import { cn } from '@/lib/utils';

function VehicleSection({
    state,
    title,
    children,
}: {
    state: BlockRendererProps['state'];
    title?: string | null;
    children: (vehicle: VehicleBinding) => ReactNode;
}) {
    const vehicle = useVehicle(state);

    return (
        <section className="bg-white py-12 text-neutral-900">
            <Container className="flex flex-col gap-6">
                {title && <h2 className="text-3xl font-bold">{title}</h2>}
                {vehicle ? (
                    children(vehicle)
                ) : (
                    <VehiclePlaceholder
                        selected={text(state, 'vehicle') !== null}
                    />
                )}
            </Container>
        </section>
    );
}

function offerLabel(offer: VehicleOffer): string {
    return `${offer.equipment.name} · ${offer.modification.name}`;
}

function OfferPicker({
    offers,
    selected,
    onSelect,
}: {
    offers: VehicleOffer[];
    selected: string | undefined;
    onSelect: (offerId: string) => void;
}) {
    if (offers.length < 2) {
        return null;
    }

    return (
        <div
            role="group"
            aria-label="Комплектация"
            className="flex flex-wrap gap-2"
        >
            {offers.map((offer) => (
                <button
                    key={offer.public_id}
                    type="button"
                    aria-pressed={offer.public_id === selected}
                    onClick={() => onSelect(offer.public_id)}
                    className={cn(
                        'rounded-(--lf-radius) border px-3 py-1.5 text-sm',
                        offer.public_id === selected
                            ? 'border-(--lf-primary) bg-(--lf-primary) text-(--lf-on-primary)'
                            : 'border-neutral-300 bg-white hover:border-neutral-500',
                    )}
                >
                    {offerLabel(offer)}
                </button>
            ))}
        </div>
    );
}

function useSelectedOffer(vehicle: VehicleBinding) {
    const [offerId, setOfferId] = useState(vehicle.offers[0]?.public_id);
    const offer =
        vehicle.offers.find((candidate) => candidate.public_id === offerId) ??
        vehicle.offers[0];

    return { offer, select: setOfferId };
}

function NoOffers() {
    return (
        <p className="text-sm text-neutral-500">
            Для этого автомобиля пока нет предложений.
        </p>
    );
}

function SpecList({ items }: { items: { label: string; value: string }[] }) {
    return (
        <dl className="divide-y divide-neutral-200">
            {items.map((item) => (
                <div
                    key={item.label}
                    className="flex justify-between gap-4 py-2 text-sm"
                >
                    <dt className="text-neutral-500">{item.label}</dt>
                    <dd className="text-right font-medium">{item.value}</dd>
                </div>
            ))}
        </dl>
    );
}

function CharacteristicGroups({ offer }: { offer: VehicleOffer }) {
    if (offer.characteristics.length === 0) {
        return (
            <p className="text-sm text-neutral-500">
                Характеристики не указаны.
            </p>
        );
    }

    return (
        <div className="grid gap-6 md:grid-cols-2">
            {offer.characteristics.map((section) => (
                <div key={section.group}>
                    <h3 className="mb-1 font-semibold">{section.group}</h3>
                    <SpecList items={section.items} />
                </div>
            ))}
        </div>
    );
}

function OptionGroups({
    offer,
    showOptional,
}: {
    offer: VehicleOffer;
    showOptional: boolean;
}) {
    const sections = offer.options
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) => showOptional || item.availability === 'standard',
            ),
        }))
        .filter((section) => section.items.length > 0);

    if (sections.length === 0) {
        return (
            <p className="text-sm text-neutral-500">Оснащение не указано.</p>
        );
    }

    return (
        <div className="grid gap-6 md:grid-cols-2">
            {sections.map((section) => (
                <div key={section.group}>
                    <h3 className="mb-2 font-semibold">{section.group}</h3>
                    <ul className="flex flex-col gap-1.5 text-sm">
                        {section.items.map((item) => (
                            <li
                                key={item.name}
                                className="flex items-start gap-2"
                            >
                                {item.availability === 'standard' ? (
                                    <Check
                                        className="mt-0.5 size-4 shrink-0 text-(--lf-primary)"
                                        aria-hidden
                                    />
                                ) : (
                                    <Plus
                                        className="mt-0.5 size-4 shrink-0 text-neutral-400"
                                        aria-hidden
                                    />
                                )}
                                <span>
                                    {item.name}
                                    {item.availability === 'optional' && (
                                        <span className="text-neutral-500">
                                            {' '}
                                            — опция
                                        </span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );
}

function Gallery({
    vehicle,
    showTitle,
    showColors,
}: {
    vehicle: VehicleBinding;
    showTitle: boolean;
    showColors: boolean;
}) {
    const sets = vehicle.media.sets;
    const [setId, setSetId] = useState(sets[0]?.public_id);
    const set =
        sets.find((candidate) => candidate.public_id === setId) ?? sets[0];
    const [angle, setAngle] = useState(cardImage(set)?.angle);
    const image =
        set?.images.find((candidate) => candidate.angle === angle) ??
        cardImage(set);
    const [lightboxOpen, setLightboxOpen] = useState(false);
    const photoButton = useRef<HTMLButtonElement>(null);
    const imageAlt = (label: string) =>
        `${vehicleFullTitle(vehicle)}, ${set?.name ?? ''}, ${label}`;
    const lightboxImages = (set?.images ?? []).map((candidate) => ({
        url: candidate.url,
        alt: imageAlt(candidate.label),
        width: candidate.width,
        height: candidate.height,
    }));
    const imageIndex = Math.max(
        0,
        set?.images.findIndex(
            (candidate) => candidate.angle === image?.angle,
        ) ?? 0,
    );

    return (
        <div className="grid gap-6 lg:grid-cols-[2fr_1fr] lg:items-start">
            <div className="flex flex-col gap-3">
                <div className="flex aspect-[16/10] items-center justify-center overflow-hidden rounded-(--lf-radius) bg-neutral-100">
                    {image ? (
                        <button
                            ref={photoButton}
                            type="button"
                            aria-label={`Открыть фото на весь экран: ${imageAlt(image.label)}`}
                            aria-haspopup="dialog"
                            onClick={() => setLightboxOpen(true)}
                            className="group relative size-full cursor-zoom-in focus-visible:ring-2 focus-visible:ring-(--lf-primary) focus-visible:outline-none focus-visible:ring-inset"
                        >
                            <img
                                src={image.url}
                                alt={imageAlt(image.label)}
                                width={image.width}
                                height={image.height}
                                className="size-full object-contain"
                            />
                            <span className="absolute right-3 bottom-3 inline-flex size-9 items-center justify-center rounded-full bg-white/90 text-neutral-800 opacity-0 shadow transition group-hover:opacity-100 group-focus-visible:opacity-100">
                                <Expand aria-hidden="true" className="size-4" />
                            </span>
                        </button>
                    ) : (
                        <Car className="size-12 text-neutral-400" aria-hidden />
                    )}
                </div>
                <Lightbox
                    label={`Фото: ${vehicleFullTitle(vehicle)}`}
                    images={lightboxImages}
                    index={imageIndex}
                    onIndexChange={(next) => setAngle(set?.images[next]?.angle)}
                    open={lightboxOpen}
                    onOpenChange={setLightboxOpen}
                    returnFocusTo={photoButton}
                />
                {set && set.images.length > 1 && (
                    <div
                        role="group"
                        aria-label="Ракурс"
                        className="flex flex-wrap gap-2"
                    >
                        {set.images.map((candidate) => (
                            <button
                                key={candidate.angle}
                                type="button"
                                aria-label={candidate.label}
                                aria-pressed={candidate.angle === image?.angle}
                                onClick={() => setAngle(candidate.angle)}
                                className={cn(
                                    'h-14 w-20 overflow-hidden rounded-(--lf-radius) border-2 bg-neutral-100',
                                    candidate.angle === image?.angle
                                        ? 'border-(--lf-primary)'
                                        : 'border-transparent',
                                )}
                            >
                                <img
                                    src={candidate.url}
                                    alt=""
                                    className="size-full object-contain"
                                />
                            </button>
                        ))}
                    </div>
                )}
            </div>
            <div className="flex flex-col gap-4">
                {showTitle && (
                    <div>
                        <h2 className="text-3xl font-bold">{vehicle.title}</h2>
                        <p className="text-neutral-500">
                            {vehicle.generation} · {vehicle.series}
                        </p>
                        {vehicle.price_from_label && (
                            <p className="mt-3 text-2xl font-bold">
                                от {vehicle.price_from_label}
                            </p>
                        )}
                        {vehicle.benefit_up_to_label && (
                            <p className="text-sm font-medium text-(--lf-secondary)">
                                Выгода до {vehicle.benefit_up_to_label}
                            </p>
                        )}
                    </div>
                )}
                {showColors && set && (
                    <div className="flex flex-col gap-2">
                        <p className="text-sm text-neutral-500">
                            Цвет:{' '}
                            <span className="text-neutral-900">{set.name}</span>
                        </p>
                        <ColorSwatches
                            sets={sets}
                            selected={set.public_id}
                            onSelect={setSetId}
                        />
                    </div>
                )}
            </div>
        </div>
    );
}

export function VehicleGalleryBlock({ state }: BlockRendererProps) {
    return (
        <VehicleSection state={state}>
            {(vehicle) => (
                <Gallery
                    key={vehicle.public_id}
                    vehicle={vehicle}
                    showTitle={flag(state, 'show_title', true)}
                    showColors={flag(state, 'show_colors', true)}
                />
            )}
        </VehicleSection>
    );
}

function OfferRow({
    vehicleId,
    offer,
    button,
}: {
    vehicleId: string;
    offer: VehicleOffer;
    button: BlockRendererProps['state'];
}) {
    const [open, setOpen] = useState(false);
    const panelId = useId();

    return (
        <li className="rounded-(--lf-radius) border border-neutral-200">
            <div className="flex flex-wrap items-center gap-4 p-5">
                <div className="min-w-48 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-lg font-semibold">
                            {offer.equipment.name}
                        </h3>
                        {offer.badge && (
                            <span className="rounded-full bg-(--lf-secondary) px-2 py-0.5 text-xs font-medium text-white">
                                {offer.badge}
                            </span>
                        )}
                    </div>
                    <p className="text-sm text-neutral-500">
                        {offer.modification.summary || offer.modification.name}
                    </p>
                    {offer.availability_label && (
                        <p className="mt-1 text-sm">
                            {offer.availability_label}
                        </p>
                    )}
                </div>
                <div className="text-right">
                    <p className="text-xl font-bold">{offer.price_label}</p>
                    {offer.rrp_label && (
                        <p className="text-sm text-neutral-500 line-through">
                            {offer.rrp_label}
                        </p>
                    )}
                </div>
                <div className="flex w-full flex-wrap items-center gap-3 sm:w-auto">
                    <button
                        type="button"
                        aria-expanded={open}
                        aria-controls={panelId}
                        onClick={() => setOpen(!open)}
                        className="inline-flex items-center gap-1 text-sm font-medium text-(--lf-primary) hover:underline"
                    >
                        {open ? 'Скрыть подробности' : 'Подробнее'}
                        <ChevronDown
                            className={cn(
                                'size-4 transition',
                                open && 'rotate-180',
                            )}
                            aria-hidden
                        />
                    </button>
                    <TriggerScope
                        value={{ vehicle: vehicleId, offer: offer.public_id }}
                    >
                        <ButtonPreview button={button} />
                    </TriggerScope>
                </div>
            </div>
            {offer.benefits.length > 0 && (
                <ul className="flex flex-wrap gap-2 px-5 pb-4 text-sm">
                    {offer.benefits.map((benefit, index) => (
                        <li
                            key={index}
                            className="rounded-full bg-neutral-100 px-3 py-1"
                        >
                            {benefit.label}: {benefit.amount_label}
                        </li>
                    ))}
                </ul>
            )}
            <div
                id={panelId}
                hidden={!open}
                className="flex flex-col gap-6 border-t border-neutral-200 p-5"
            >
                <div>
                    <h4 className="mb-1 font-semibold">
                        Модификация {offer.modification.name}
                    </h4>
                    <SpecList items={offer.modification.specs} />
                </div>
                <CharacteristicGroups offer={offer} />
                <OptionGroups offer={offer} showOptional />
            </div>
        </li>
    );
}

export function VehicleOffersBlock({ state }: BlockRendererProps) {
    return (
        <VehicleSection state={state} title={text(state, 'title')}>
            {(vehicle) =>
                vehicle.offers.length > 0 ? (
                    <ul className="flex flex-col gap-4">
                        {vehicle.offers.map((offer) => (
                            <OfferRow
                                key={offer.public_id}
                                vehicleId={vehicle.public_id}
                                offer={offer}
                                button={group(state, 'button')}
                            />
                        ))}
                    </ul>
                ) : (
                    <NoOffers />
                )
            }
        </VehicleSection>
    );
}

function OfferDetails({
    vehicle,
    children,
}: {
    vehicle: VehicleBinding;
    children: (offer: VehicleOffer) => ReactNode;
}) {
    const { offer, select } = useSelectedOffer(vehicle);

    if (!offer) {
        return <NoOffers />;
    }

    return (
        <div className="flex flex-col gap-6">
            <OfferPicker
                offers={vehicle.offers}
                selected={offer.public_id}
                onSelect={select}
            />
            {children(offer)}
        </div>
    );
}

export function VehicleCharacteristicsBlock({ state }: BlockRendererProps) {
    return (
        <VehicleSection state={state} title={text(state, 'title')}>
            {(vehicle) => (
                <OfferDetails key={vehicle.public_id} vehicle={vehicle}>
                    {(offer) => (
                        <>
                            <div className="max-w-xl">
                                <h3 className="mb-1 font-semibold">
                                    Двигатель и трансмиссия
                                </h3>
                                <SpecList items={offer.modification.specs} />
                            </div>
                            <CharacteristicGroups offer={offer} />
                        </>
                    )}
                </OfferDetails>
            )}
        </VehicleSection>
    );
}

export function VehicleEquipmentBlock({ state }: BlockRendererProps) {
    return (
        <VehicleSection state={state} title={text(state, 'title')}>
            {(vehicle) => (
                <OfferDetails key={vehicle.public_id} vehicle={vehicle}>
                    {(offer) => (
                        <OptionGroups
                            offer={offer}
                            showOptional={flag(state, 'show_optional', true)}
                        />
                    )}
                </OfferDetails>
            )}
        </VehicleSection>
    );
}
