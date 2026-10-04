import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { TextField } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
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
import { dashboard } from '@/routes';
import { designer } from '@/routes/sites';
import { show, store } from '@/routes/sites/forms';

type FormSummary = {
    public_id: string;
    name: string;
    status: boolean;
    fields_count: number;
};

type FormsIndexProps = {
    site: { public_id: string; name: string };
    forms: FormSummary[];
    can: { editForms: boolean };
};

export default function FormsIndex({ site, forms, can }: FormsIndexProps) {
    return (
        <>
            <Head title={`Формы — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Формы
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Поля заявок. Форму можно показать в попапе и
                            использовать на разных страницах.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={designer(site.public_id)}>
                                Открыть дизайнер
                            </Link>
                        </Button>
                        {can.editForms && (
                            <CreateFormDialog sitePublicId={site.public_id} />
                        )}
                    </div>
                </header>

                {forms.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Форм пока нет
                            </h2>
                            <CardDescription>
                                Создайте форму, добавьте поля — например, имя,
                                телефон и согласие — и подключите её к попапу.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {forms.map((form) => (
                            <li key={form.public_id}>
                                <Link
                                    href={show({
                                        site: site.public_id,
                                        form: form.public_id,
                                    })}
                                    className="flex h-full min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <div className="flex items-start gap-3">
                                        <ClipboardList
                                            aria-hidden="true"
                                            className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                        />
                                        <h2 className="min-w-0 flex-1 font-semibold break-words">
                                            {form.name}
                                        </h2>
                                        {!form.status && (
                                            <Badge variant="secondary">
                                                Выключена
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {`Полей: ${form.fields_count}`}
                                    </p>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </>
    );
}

function CreateFormDialog({ sitePublicId }: { sitePublicId: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(store(sitePublicId), {
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus aria-hidden="true" />
                    Создать форму
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Новая форма</DialogTitle>
                    <DialogDescription>
                        Поля и тексты настроите на следующем шаге.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <TextField
                        id="new-form-name"
                        label="Название"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        maxLength={120}
                        required
                        error={form.errors.name}
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Создать
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

FormsIndex.layout = {
    breadcrumbs: [{ title: 'Панель управления', href: dashboard() }],
};
