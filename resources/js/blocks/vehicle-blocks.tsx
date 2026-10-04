import { Car } from 'lucide-react';
import { useState } from 'react';
import { Carousel, carouselSettings } from '@/blocks/carousel';
import { ButtonPreview, Container } from '@/blocks/official-blocks';
import { useBlockRenderContext } from '@/blocks/render-context';
import type { BlockRendererProps, BlockState } from '@/blocks/state';
import { flag, group, items, text } from '@/blocks/state';
import { TriggerScope } from '@/blocks/trigger-context';
import type { VehicleBinding, VehicleMediaSet } from '@/blocks/vehicles';
import { cn } from '@/lib/utils';

const CARD_ANGLES = ['front_3_4', 'front', 'side'];

export function useVehicle(
    state: BlockState,
    key = 'vehicle',
): VehicleBinding | null {
    const { vehicle } = useBlockRenderContext();
    const id = text(state, key);

    return id === null ? null : vehicle(id);
}

export function VehiclePlaceholder({ selected }: { selected: boolean }) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-(--lf-radius) border border-dashed border-neutral-300 bg-neutral-50 px-6 py-10 text-center text-sm text-neutral-500">
            <Car className="size-6" aria-hidden />
            {selected
                ? 'Автомобиль скрыт или недоступен в каталоге.'
                : 'Выберите автомобиль в свойствах блока.'}
        </div>
    );
}

export function cardImage(set: VehicleMediaSet | undefined) {
    if (!set) {
        return null;
    }

    for (const angle of CARD_ANGLES) {
        const image = set.images.find((candidate) => candidate.angle === angle);

        if (image) {
            return image;
        }
    }

    return set.images[0] ?? null;
}

export function ColorSwatches({
    sets,
    selected,
    onSelect,
}: {
    sets: VehicleMediaSet[];
    selected: string | undefined;
    onSelect: (setId: string) => void;
}) {
    if (sets.length < 2) {
        return null;
    }

    return (
        <div role="group" aria-label="Цвет" className="flex flex-wrap gap-2">
            {sets.map((set) => (
                <button
                    key={set.public_id}
                    type="button"
                    title={set.name}
                    aria-label={set.name}
                    aria-pressed={set.public_id === selected}
                    onClick={() => onSelect(set.public_id)}
                    className={cn(
                        'size-6 rounded-full border border-neutral-300 ring-offset-2 transition',
                        set.public_id === selected &&
                            'ring-2 ring-(--lf-primary)',
                    )}
                    style={{ backgroundColor: set.swatch_hex ?? '#d4d4d4' }}
                />
            ))}
        </div>
    );
}

export function VehicleCard({
    vehicle,
    showPrice,
    showBenefit,
    showColors,
    button,
}: {
    vehicle: VehicleBinding;
    showPrice: boolean;
    showBenefit: boolean;
    showColors: boolean;
    button: BlockState;
}) {
    const sets = vehicle.media.sets;
    const [setId, setSetId] = useState(sets[0]?.public_id);
    const set = sets.find((candidate) => candidate.public_id === setId);
    const image = cardImage(set ?? sets[0]);

    return (
        <article className="flex h-full flex-col overflow-hidden rounded-(--lf-radius) border border-neutral-200 bg-white text-neutral-900">
            <div className="flex aspect-[16/10] items-center justify-center bg-neutral-100">
                {image ? (
                    <img
                        src={image.url}
                        alt={`${vehicle.title} ${vehicle.series}${set ? `, ${set.name}` : ''}`}
                        width={image.width}
                        height={image.height}
                        className="size-full object-contain"
                    />
                ) : (
                    <Car className="size-10 text-neutral-400" aria-hidden />
                )}
            </div>
            <div className="flex flex-1 flex-col gap-3 p-5">
                <div>
                    <h3 className="text-lg font-semibold">{vehicle.title}</h3>
                    <p className="text-sm text-neutral-500">
                        {vehicle.generation} · {vehicle.series}
                    </p>
                </div>
                {showColors && (
                    <ColorSwatches
                        sets={sets}
                        selected={set?.public_id}
                        onSelect={setSetId}
                    />
                )}
                {showPrice && vehicle.price_from_label && (
                    <p className="text-xl font-bold">
                        от {vehicle.price_from_label}
                    </p>
                )}
                {showBenefit && vehicle.benefit_up_to_label && (
                    <p className="text-sm font-medium text-(--lf-secondary)">
                        Выгода до {vehicle.benefit_up_to_label}
                    </p>
                )}
                {vehicle.offers.length > 0 && (
                    <p className="text-sm text-neutral-500">
                        {offersCount(vehicle.offers.length)}
                    </p>
                )}
                <div className="mt-auto pt-2">
                    <TriggerScope
                        value={{
                            vehicle: vehicle.public_id,
                            media_set: set?.public_id,
                        }}
                    >
                        <ButtonPreview button={button} />
                    </TriggerScope>
                </div>
            </div>
        </article>
    );
}

export function offersCount(count: number): string {
    const mod10 = count % 10;
    const mod100 = count % 100;
    const word =
        mod10 === 1 && mod100 !== 11
            ? 'предложение'
            : mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)
              ? 'предложения'
              : 'предложений';

    return `${count} ${word}`;
}

const gridColumns: Record<string, string> = {
    two: 'sm:grid-cols-2',
    three: 'sm:grid-cols-2 lg:grid-cols-3',
    four: 'sm:grid-cols-2 lg:grid-cols-4',
};

export function VehicleGridBlock({ state }: BlockRendererProps) {
    const { vehicle, vehicles } = useBlockRenderContext();
    const selectedOnly = state.source === 'selected';
    const columns =
        typeof state.columns === 'string' && state.columns in gridColumns
            ? gridColumns[state.columns]
            : gridColumns.three;
    const buttonLabel = text(state, 'button_label');
    const cards = selectedOnly
        ? items(state, 'items').flatMap((item) => {
              const id = text(item, 'vehicle');
              const binding = id === null ? null : vehicle(id);

              return binding
                  ? [
                        {
                            key: item.id,
                            vehicle: binding,
                            button: item.action
                                ? { label: buttonLabel, action: item.action }
                                : {},
                        },
                    ]
                  : [];
          })
        : vehicles.map((binding) => ({
              key: binding.public_id,
              vehicle: binding,
              button: {},
          }));
    const carousel = carouselSettings(state);
    const renderCard = (card: (typeof cards)[number]) => (
        <VehicleCard
            vehicle={card.vehicle}
            showPrice={flag(state, 'show_price', true)}
            showBenefit={flag(state, 'show_benefit', true)}
            showColors={flag(state, 'show_colors', true)}
            button={card.button}
        />
    );

    return (
        <section className="bg-white py-16 text-neutral-900">
            <Container className="flex flex-col gap-8">
                {(text(state, 'title') || text(state, 'subtitle')) && (
                    <div className="flex flex-col gap-3">
                        {text(state, 'title') && (
                            <h2 className="text-3xl font-bold">
                                {text(state, 'title')}
                            </h2>
                        )}
                        {text(state, 'subtitle') && (
                            <p className="max-w-2xl whitespace-pre-line text-neutral-600">
                                {text(state, 'subtitle')}
                            </p>
                        )}
                    </div>
                )}
                {cards.length > 0 && carousel.enabled ? (
                    <Carousel
                        label={text(state, 'title') ?? 'Автомобили'}
                        settings={carousel}
                        slides={cards.map((card) => ({
                            key: card.key,
                            content: renderCard(card),
                        }))}
                    />
                ) : cards.length > 0 ? (
                    <ul className={cn('grid gap-6', columns)}>
                        {cards.map((card) => (
                            <li key={card.key}>{renderCard(card)}</li>
                        ))}
                    </ul>
                ) : (
                    <div className="rounded-(--lf-radius) border border-dashed border-neutral-300 bg-neutral-50 px-6 py-10 text-center text-sm text-neutral-500">
                        {selectedOnly
                            ? 'Выберите автомобили в свойствах блока.'
                            : 'Добавьте автомобили в разделе «Автомобили» сайта.'}
                    </div>
                )}
            </Container>
        </section>
    );
}

export function VehicleCardBlock({ state }: BlockRendererProps) {
    const vehicle = useVehicle(state);

    return (
        <section className="bg-white py-12">
            <Container className="max-w-md">
                {vehicle ? (
                    <VehicleCard
                        key={vehicle.public_id}
                        vehicle={vehicle}
                        showPrice={flag(state, 'show_price', true)}
                        showBenefit={flag(state, 'show_benefit', true)}
                        showColors={flag(state, 'show_colors', true)}
                        button={group(state, 'button')}
                    />
                ) : (
                    <VehiclePlaceholder
                        selected={text(state, 'vehicle') !== null}
                    />
                )}
            </Container>
        </section>
    );
}
