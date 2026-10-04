import { Form, Head, Link } from '@inertiajs/react';
import { ChevronRight, Plus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create, index, store } from '@/routes/sites/vehicles';

type PickerItem = { public_id: string; name: string; added: boolean };

type PickerLevel = {
    key: 'marks' | 'models' | 'generations' | 'series';
    label: string;
    selectionKey: string;
    selected: string | null;
    items: PickerItem[];
};

type CreateVehicleProps = {
    site: { public_id: string; name: string };
    levels: PickerLevel[];
};

function selectionQuery(
    levels: PickerLevel[],
    position: number,
    item: PickerItem,
): Record<string, string> {
    const query: Record<string, string> = {};

    levels.slice(0, position).forEach((level) => {
        if (level.selected) {
            query[level.selectionKey] = level.selected;
        }
    });
    query[levels[position].selectionKey] = item.public_id;

    return query;
}

const emptyHints: Record<PickerLevel['key'], string> = {
    marks: 'В каталоге пока нет доступных марок.',
    models: 'У этой марки нет доступных моделей.',
    generations: 'У этой модели нет доступных поколений.',
    series: 'У этого поколения нет доступных серий.',
};

export default function CreateVehicle({ site, levels }: CreateVehicleProps) {
    return (
        <>
            <Head title={`Добавить автомобиль — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <p className="truncate text-sm text-muted-foreground">
                        {site.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Добавить автомобиль
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Выберите марку, модель, поколение и серию. Предложения с
                        комплектациями и ценами добавляются на следующем шаге.
                    </p>
                </header>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {levels.map((level, position) => (
                        <section
                            key={level.key}
                            aria-labelledby={`picker-${level.key}`}
                            className="flex min-w-0 flex-col rounded-xl border bg-card shadow-sm"
                        >
                            <h2
                                id={`picker-${level.key}`}
                                className="border-b px-4 py-3 font-semibold"
                            >
                                {level.label}
                            </h2>
                            {level.items.length === 0 ? (
                                <p className="px-4 py-6 text-sm text-muted-foreground">
                                    {emptyHints[level.key]}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {level.items.map((item) =>
                                        level.key === 'series' ? (
                                            <li
                                                key={item.public_id}
                                                className="flex items-center gap-2 px-4 py-2"
                                            >
                                                <span className="min-w-0 flex-1 text-sm break-words">
                                                    {item.name}
                                                </span>
                                                {item.added ? (
                                                    <Badge variant="secondary">
                                                        Уже на сайте
                                                    </Badge>
                                                ) : (
                                                    <Form
                                                        {...store.form(
                                                            site.public_id,
                                                        )}
                                                        disableWhileProcessing
                                                    >
                                                        {({ errors }) => (
                                                            <>
                                                                <input
                                                                    type="hidden"
                                                                    name="series"
                                                                    value={
                                                                        item.public_id
                                                                    }
                                                                />
                                                                <Button
                                                                    type="submit"
                                                                    size="sm"
                                                                    aria-label={`Добавить серию ${item.name}`}
                                                                >
                                                                    <Plus aria-hidden="true" />
                                                                    Добавить
                                                                </Button>
                                                                <InputError
                                                                    message={
                                                                        errors.series
                                                                    }
                                                                />
                                                            </>
                                                        )}
                                                    </Form>
                                                )}
                                            </li>
                                        ) : (
                                            <li key={item.public_id}>
                                                <Link
                                                    href={create(
                                                        site.public_id,
                                                        {
                                                            query: selectionQuery(
                                                                levels,
                                                                position,
                                                                item,
                                                            ),
                                                        },
                                                    )}
                                                    preserveScroll
                                                    aria-current={
                                                        level.selected ===
                                                        item.public_id
                                                            ? 'true'
                                                            : undefined
                                                    }
                                                    className={cn(
                                                        'flex items-center gap-2 px-4 py-2 text-sm hover:bg-accent',
                                                        level.selected ===
                                                            item.public_id &&
                                                            'bg-accent font-medium',
                                                    )}
                                                >
                                                    <span className="min-w-0 flex-1 break-words">
                                                        {item.name}
                                                    </span>
                                                    <ChevronRight
                                                        aria-hidden="true"
                                                        className="size-4 shrink-0 text-muted-foreground"
                                                    />
                                                </Link>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>

                <div>
                    <Button variant="outline" asChild>
                        <Link href={index(site.public_id)}>
                            Вернуться к автомобилям
                        </Link>
                    </Button>
                </div>
            </main>
        </>
    );
}

CreateVehicle.layout = {
    breadcrumbs: [{ title: 'Панель управления', href: dashboard() }],
};
