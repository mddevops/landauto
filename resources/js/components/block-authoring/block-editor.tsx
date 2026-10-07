import { Form } from '@inertiajs/react';
import { Eye, FileCode2, History } from 'lucide-react';
import {
    formatBlockDate,
    versionsLabel,
} from '@/components/block-authoring/block-list';
import { Field, TextField } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { AuthoringBlockDetail } from '@/types/blocks';
import type { RouteFormDefinition } from '@/wayfinder';

const roadmap = [
    {
        title: 'Схема',
        icon: FileCode2,
        text: 'Редактор схемы будет доступен на следующем этапе.',
    },
    {
        title: 'Предпросмотр',
        icon: Eye,
        text: 'Предпросмотр будет добавлен после редактора схемы.',
    },
    {
        title: 'Версии',
        icon: History,
        text: 'Публикация версий пока недоступна.',
    },
];

type BlockEditorProps = {
    block: AuthoringBlockDetail;
    action: RouteFormDefinition<'post'>;
};

export function BlockEditor({ block, action }: BlockEditorProps) {
    return (
        <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
            <header className="space-y-2">
                <p className="text-sm text-muted-foreground">Редактор блока</p>
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="min-w-0 text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                        {block.name}
                    </h1>
                    <Badge variant="outline">
                        {versionsLabel(block.versions_count)}
                    </Badge>
                </div>
            </header>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
                <section
                    aria-labelledby="block-metadata-title"
                    className="space-y-4 rounded-xl border bg-card p-4 shadow-sm sm:p-6"
                >
                    <h2 id="block-metadata-title" className="font-semibold">
                        Основные данные
                    </h2>
                    <Form
                        {...action}
                        options={{ preserveScroll: true }}
                        disableWhileProcessing
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <TextField
                                    id="block-name"
                                    name="name"
                                    label="Название"
                                    defaultValue={block.name}
                                    required
                                    maxLength={100}
                                    autoComplete="off"
                                    error={errors.name}
                                />
                                <Field
                                    id="block-slug"
                                    label="Slug"
                                    hint="Технический идентификатор блока не меняется после создания."
                                >
                                    <Input
                                        id="block-slug"
                                        value={block.slug}
                                        readOnly
                                        aria-readonly="true"
                                        className="bg-muted/40 font-mono"
                                    />
                                </Field>
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Сохранить
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                <section
                    aria-labelledby="block-ownership-title"
                    className="space-y-3 rounded-xl border bg-card p-4 shadow-sm sm:p-6"
                >
                    <h2 id="block-ownership-title" className="font-semibold">
                        Владение
                    </h2>
                    <dl className="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[auto_1fr] lg:grid-cols-1">
                        <dt className="text-muted-foreground">Владелец</dt>
                        <dd className="break-words">{block.owner_name}</dd>
                        <dt className="text-muted-foreground">Тип владельца</dt>
                        <dd>{block.owner_scope_label}</dd>
                        <dt className="text-muted-foreground">
                            Количество версий
                        </dt>
                        <dd>{block.versions_count}</dd>
                        <dt className="text-muted-foreground">Обновлён</dt>
                        <dd>{formatBlockDate(block.updated_at)}</dd>
                    </dl>
                </section>
            </div>

            <section
                aria-labelledby="block-roadmap-title"
                className="space-y-3"
            >
                <h2 id="block-roadmap-title" className="font-semibold">
                    Следующие шаги
                </h2>
                <div className="grid gap-4 md:grid-cols-3">
                    {roadmap.map((item) => (
                        <section
                            key={item.title}
                            aria-label={item.title}
                            className="space-y-2 rounded-xl border border-dashed p-4 text-muted-foreground"
                        >
                            <h3 className="flex items-center gap-2 font-semibold text-foreground">
                                <item.icon
                                    aria-hidden="true"
                                    className="size-4"
                                />
                                {item.title}
                            </h3>
                            <p className="text-sm">{item.text}</p>
                        </section>
                    ))}
                </div>
            </section>
        </main>
    );
}
