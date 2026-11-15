import { Car } from 'lucide-react';
import type { CatalogGroup, CatalogTarget } from '@/blocks/catalog';
import { groupModels, groupVehicles } from '@/blocks/catalog';
import { Container } from '@/blocks/official-blocks';
import { useBlockRenderContext } from '@/blocks/render-context';
import type { BlockRendererProps } from '@/blocks/state';
import { flag, text } from '@/blocks/state';
import { cardImage, VehicleCard } from '@/blocks/vehicle-blocks';
import { cn } from '@/lib/utils';

const columnsClass: Record<string, string> = {
    two: 'sm:grid-cols-2',
    three: 'sm:grid-cols-2 lg:grid-cols-3',
    four: 'sm:grid-cols-2 lg:grid-cols-4',
};

function vehiclesCount(count: number): string {
    const mod10 = count % 10;
    const mod100 = count % 100;
    const word =
        mod10 === 1 && mod100 !== 11
            ? 'автомобиль'
            : mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)
              ? 'автомобиля'
              : 'автомобилей';

    return `${count} ${word}`;
}

function GroupCard({
    group,
    href,
    label,
}: {
    group: CatalogGroup;
    href: string | null;
    label: string;
}) {
    const { vehicle } = useBlockRenderContext();
    const binding = group.vehicles
        .map((candidate) => vehicle(candidate.public_id))
        .find((candidate) => candidate !== null);
    const image = binding ? cardImage(binding, binding.media.sets[0]) : null;
    const content = (
        <>
            <div className="flex aspect-[16/10] items-center justify-center bg-neutral-100">
                {image ? (
                    <img
                        src={image.url}
                        alt={group.name}
                        width={image.width}
                        height={image.height}
                        className="size-full object-contain"
                    />
                ) : (
                    <Car className="size-10 text-neutral-400" aria-hidden />
                )}
            </div>
            <div className="flex flex-1 flex-col gap-1 p-5">
                <h3 className="text-lg font-semibold">{group.name}</h3>
                <p className="text-sm text-neutral-500">
                    {vehiclesCount(group.vehicles.length)}
                </p>
                {href && (
                    <span className="mt-2 text-sm font-medium text-(--lf-primary)">
                        {label}
                    </span>
                )}
            </div>
        </>
    );
    const className =
        'flex h-full flex-col overflow-hidden rounded-(--lf-radius) border border-neutral-200 bg-white text-neutral-900';

    return href ? (
        <a
            href={href}
            className={cn(
                className,
                'transition-colors hover:border-(--lf-primary)',
            )}
        >
            {content}
        </a>
    ) : (
        <article className={className}>{content}</article>
    );
}

/**
 * Listing block for Catalog (D-131) and Stock Used/Specials (X-038).
 * Catalog: index → marks, mark → vehicles.
 * Stock: index → marks, mark → models, model → vehicles.
 */
export function VehicleSectionBlock({ state }: BlockRendererProps) {
    const { catalog, pageContext } = useBlockRenderContext();
    const columns =
        typeof state.columns === 'string' && state.columns in columnsClass
            ? columnsClass[state.columns]
            : columnsClass.three;
    const buttonLabel = text(state, 'button_label') ?? 'Подробнее';
    const mark = pageContext?.mark ?? null;
    const model = pageContext?.model ?? null;
    const stock = catalog?.source === 'stock';
    const vehicles = (catalog?.vehicles ?? []).filter((vehicle) => {
        if (mark !== null && vehicle.mark.segment !== mark) {
            return false;
        }
        if (model !== null && vehicle.model.segment !== model) {
            return false;
        }

        return true;
    });
    const level = model !== null ? 'model' : mark !== null ? 'mark' : 'index';
    const first = vehicles[0];
    const autoTitle =
        level === 'model' && first
            ? `${first.mark.name} ${first.model.name}`
            : level === 'mark' && first
              ? first.mark.name
              : (catalog?.title ?? null);
    const title = text(state, 'title') ?? autoTitle;
    const subtitle = text(state, 'subtitle');
    const href = (target: CatalogTarget) =>
        catalog?.enabled ? catalog.href(target) : null;
    const indexHref = href({ role: 'index' });
    const markHref = mark !== null ? href({ role: 'mark', mark }) : null;

    return (
        <section className="bg-white py-16 text-neutral-900">
            <Container className="flex flex-col gap-8">
                {catalog && first && level !== 'index' && (
                    <nav aria-label="Навигация по разделу">
                        <ol className="flex flex-wrap items-center gap-2 text-sm text-neutral-500">
                            <li className="flex items-center gap-2">
                                {indexHref ? (
                                    <a
                                        href={indexHref}
                                        className="transition-colors hover:text-neutral-900"
                                    >
                                        {catalog.title}
                                    </a>
                                ) : (
                                    <span>{catalog.title}</span>
                                )}
                                <span aria-hidden="true">/</span>
                            </li>
                            {level === 'model' ? (
                                <>
                                    <li className="flex items-center gap-2">
                                        {markHref ? (
                                            <a
                                                href={markHref}
                                                className="transition-colors hover:text-neutral-900"
                                            >
                                                {first.mark.name}
                                            </a>
                                        ) : (
                                            <span>{first.mark.name}</span>
                                        )}
                                        <span aria-hidden="true">/</span>
                                    </li>
                                    <li
                                        aria-current="page"
                                        className="text-neutral-900"
                                    >
                                        {first.model.name}
                                    </li>
                                </>
                            ) : (
                                <li
                                    aria-current="page"
                                    className="text-neutral-900"
                                >
                                    {first.mark.name}
                                </li>
                            )}
                        </ol>
                    </nav>
                )}
                {(title || subtitle) && (
                    <div className="flex flex-col gap-3">
                        {title && (
                            <h2 className="text-3xl font-bold">{title}</h2>
                        )}
                        {subtitle && (
                            <p className="max-w-2xl whitespace-pre-line text-neutral-600">
                                {subtitle}
                            </p>
                        )}
                    </div>
                )}
                {catalog === null ? (
                    <Placeholder>
                        Добавьте раздел автомобилей в «Разделах» сайта.
                    </Placeholder>
                ) : vehicles.length === 0 ? (
                    <Placeholder>
                        {stock
                            ? 'Добавьте готовые автомобили на склад.'
                            : 'Добавьте автомобили в разделе «Автомобили» сайта.'}
                    </Placeholder>
                ) : level === 'model' || (level === 'mark' && !stock) ? (
                    <VehicleCards
                        ids={vehicles.map((vehicle) => vehicle.public_id)}
                        columns={columns}
                        showPrice={flag(state, 'show_price', true)}
                        buttonLabel={buttonLabel}
                    />
                ) : level === 'mark' && stock && mark ? (
                    <ul className={cn('grid gap-6', columns)}>
                        {groupModels(vehicles).map((group) => (
                            <li key={group.segment}>
                                <GroupCard
                                    group={group}
                                    label={buttonLabel}
                                    href={href({
                                        role: 'model',
                                        mark,
                                        model: group.segment,
                                    })}
                                />
                            </li>
                        ))}
                    </ul>
                ) : (
                    <ul className={cn('grid gap-6', columns)}>
                        {groupVehicles(vehicles).map((group) => (
                            <li key={group.segment}>
                                <GroupCard
                                    group={group}
                                    label={buttonLabel}
                                    href={href({
                                        role: 'mark',
                                        mark: group.segment,
                                    })}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </Container>
        </section>
    );
}

function VehicleCards({
    ids,
    columns,
    showPrice,
    buttonLabel,
}: {
    ids: string[];
    columns: string;
    showPrice: boolean;
    buttonLabel: string;
}) {
    const { vehicle } = useBlockRenderContext();

    return (
        <ul className={cn('grid gap-6', columns)}>
            {ids.flatMap((id) => {
                const binding = vehicle(id);

                return binding
                    ? [
                          <li key={id}>
                              <VehicleCard
                                  vehicle={binding}
                                  showPrice={showPrice}
                                  showBenefit={showPrice}
                                  showColors
                                  button={{ label: buttonLabel }}
                              />
                          </li>,
                      ]
                    : [];
            })}
        </ul>
    );
}

function Placeholder({ children }: { children: string }) {
    return (
        <div className="rounded-(--lf-radius) border border-dashed border-neutral-300 bg-neutral-50 px-6 py-10 text-center text-sm text-neutral-500">
            {children}
        </div>
    );
}
