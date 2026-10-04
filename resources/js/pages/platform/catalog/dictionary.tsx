import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { SelectField, TextField } from '@/components/platform/form-fields';
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
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/platform/catalog';
import { store, update } from '@/routes/platform/catalog/dictionaries';

type DictionaryKey = 'characteristics' | 'options';

type DictionaryEntry = {
    public_id: string;
    code: string;
    name: string;
    unit: string | null;
    sort_order: number;
};

type DictionaryGroup = DictionaryEntry & { items: DictionaryEntry[] };

type DictionaryProps = {
    dictionary: { key: DictionaryKey; title: string; hasUnit: boolean };
    groups: DictionaryGroup[];
    can: { edit: boolean };
};

type EntryDialogProps = {
    dictionary: DictionaryProps['dictionary'];
    groups: DictionaryGroup[];
    entry?: DictionaryEntry;
    isGroup?: boolean;
    defaultGroup?: string;
    trigger: ReactNode;
};

function EntryDialog({
    dictionary,
    groups,
    entry,
    isGroup = false,
    defaultGroup = '',
    trigger,
}: EntryDialogProps) {
    const [open, setOpen] = useState(false);
    const formAction = entry
        ? update.form({ dictionary: dictionary.key, entry: entry.public_id })
        : store.form(dictionary.key);
    const prefix = `dictionary-${entry?.public_id ?? 'new'}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {entry ? `Изменить: ${entry.name}` : 'Новая запись'}
                    </DialogTitle>
                    <DialogDescription>{dictionary.title}</DialogDescription>
                </DialogHeader>
                <Form
                    {...formAction}
                    options={{ preserveScroll: true }}
                    disableWhileProcessing
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {entry ? (
                                <p className="text-sm text-muted-foreground">
                                    {`Код: ${entry.code}`}
                                </p>
                            ) : (
                                <>
                                    <TextField
                                        id={`${prefix}-code`}
                                        name="code"
                                        label="Код"
                                        required
                                        maxLength={100}
                                        autoComplete="off"
                                        hint="Латиница в нижнем регистре, цифры и подчёркивание. После создания не меняется."
                                        error={errors.code}
                                    />
                                    <SelectField
                                        id={`${prefix}-group`}
                                        name="group"
                                        label="Группа"
                                        emptyLabel="Без группы (новая группа)"
                                        choices={groups.map((group) => ({
                                            value: group.public_id,
                                            label: group.name,
                                        }))}
                                        defaultValue={defaultGroup}
                                        error={errors.group}
                                    />
                                </>
                            )}
                            {entry && <InputError message={errors.code} />}
                            <TextField
                                id={`${prefix}-name`}
                                name="name"
                                label="Название"
                                required
                                maxLength={255}
                                autoComplete="off"
                                defaultValue={entry?.name ?? ''}
                                error={errors.name}
                            />
                            {dictionary.hasUnit && !isGroup && (
                                <TextField
                                    id={`${prefix}-unit`}
                                    name="unit"
                                    label="Единица измерения"
                                    maxLength={32}
                                    autoComplete="off"
                                    defaultValue={entry?.unit ?? ''}
                                    hint="У групп единица не указывается."
                                    error={errors.unit}
                                />
                            )}
                            <TextField
                                id={`${prefix}-sort_order`}
                                name="sort_order"
                                label="Сортировка"
                                type="number"
                                min={0}
                                required
                                defaultValue={String(entry?.sort_order ?? 0)}
                                error={errors.sort_order}
                            />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Отмена
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Сохранить
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function CatalogDictionary({
    dictionary,
    groups,
    can,
}: DictionaryProps) {
    return (
        <>
            <Head title={dictionary.title} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {dictionary.title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Двухуровневый словарь: группы и элементы внутри
                            групп. Значения задаются на странице комплектации.
                        </p>
                    </div>
                    {can.edit && (
                        <EntryDialog
                            dictionary={dictionary}
                            groups={groups}
                            trigger={
                                <Button>
                                    <Plus aria-hidden="true" />
                                    Добавить
                                </Button>
                            }
                        />
                    )}
                </header>

                {groups.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Записей пока нет.
                    </p>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {groups.map((group) => (
                            <section
                                key={group.public_id}
                                aria-labelledby={`group-${group.public_id}`}
                                className="rounded-xl border bg-card shadow-sm"
                            >
                                <div className="flex items-center gap-2 border-b px-4 py-3">
                                    <h2
                                        id={`group-${group.public_id}`}
                                        className="min-w-0 flex-1 truncate font-semibold"
                                    >
                                        {group.name}
                                    </h2>
                                    <code className="text-xs text-muted-foreground">
                                        {group.code}
                                    </code>
                                    {can.edit && (
                                        <>
                                            <EntryDialog
                                                dictionary={dictionary}
                                                groups={groups}
                                                defaultGroup={group.public_id}
                                                trigger={
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Добавить в группу ${group.name}`}
                                                    >
                                                        <Plus aria-hidden="true" />
                                                    </Button>
                                                }
                                            />
                                            <EntryDialog
                                                dictionary={dictionary}
                                                groups={groups}
                                                entry={group}
                                                isGroup
                                                trigger={
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Изменить ${group.name}`}
                                                    >
                                                        <Pencil aria-hidden="true" />
                                                    </Button>
                                                }
                                            />
                                        </>
                                    )}
                                </div>
                                {group.items.length === 0 ? (
                                    <p className="px-4 py-4 text-sm text-muted-foreground">
                                        В группе пока нет элементов.
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {group.items.map((item) => (
                                            <li
                                                key={item.public_id}
                                                className="flex items-center gap-2 px-4 py-2 text-sm"
                                            >
                                                <span className="min-w-0 flex-1 truncate">
                                                    {item.unit
                                                        ? `${item.name}, ${item.unit}`
                                                        : item.name}
                                                </span>
                                                <code className="text-xs text-muted-foreground">
                                                    {item.code}
                                                </code>
                                                {can.edit && (
                                                    <EntryDialog
                                                        dictionary={dictionary}
                                                        groups={groups}
                                                        entry={item}
                                                        trigger={
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                aria-label={`Изменить ${item.name}`}
                                                            >
                                                                <Pencil aria-hidden="true" />
                                                            </Button>
                                                        }
                                                    />
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>
                        ))}
                    </div>
                )}
            </main>
        </>
    );
}

CatalogDictionary.layout = {
    breadcrumbs: [{ title: 'Каталог автомобилей', href: index() }],
};
